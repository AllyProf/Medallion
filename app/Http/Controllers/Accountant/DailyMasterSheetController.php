<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyCashLedger;
use App\Models\DailyExpense;
use App\Models\FinancialHandover;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Traits\HandlesStaffPermissions;

class DailyMasterSheetController extends Controller
{
    use HandlesStaffPermissions;

    public function report(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $date = $request->get('date', date('Y-m-d'));

        $today = date('Y-m-d');
        $ledgerQuery = DailyCashLedger::where('ledger_date', $date);
        
        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        if (!$isAdmin) {
            $ledgerQuery->where('user_id', $ownerId);
        }
        $ledger = $ledgerQuery->first();

        // If visiting "today" and it doesn't exist, create it
        if (!$ledger && $date === $today) {
            $currentStaff = $this->getCurrentStaff();
            $ledger = DailyCashLedger::create([
                'user_id' => $ownerId,
                'ledger_date' => $today,
                'accountant_id' => $currentStaff->id ?? null,
                'opening_cash' => $this->getPreviousClosingCash($ownerId, $today),
                'status' => 'open',
            ]);
        }

        if (!$ledger) {
            return redirect()->route('accountant.daily-master-sheet.history')->with('error', 'No ledger found for this date.');
        }

        $transfersQuery = \App\Models\StockTransfer::whereDate('created_at', $date)
            ->whereIn('status', ['prepared', 'completed']);
            
        if (!$isAdmin) {
            $transfersQuery->where('user_id', $ownerId);
        }
        
        $transfers = $transfersQuery->with(['productVariant.product', 'transferSales' => function($q) use ($date) {
                $q->whereDate('created_at', $date);
            }])
            ->get();
            
        $variantsData = [];
        $totalBottlesSold = 0;
        $totalRevenue = 0;
        
        foreach ($transfers as $transfer) {
            if (!$transfer->productVariant || !$transfer->productVariant->product) continue;
            $vid = $transfer->product_variant_id;
            if (!isset($variantsData[$vid])) {
                $variantsData[$vid] = [
                    'item' => $transfer->productVariant->product->name . ' (' . $transfer->productVariant->name . ')',
                    'opening' => 0.0, 
                    'new_in' => 0,
                    'sold' => 0,
                    'sales_tzs' => 0,
                    'closing' => 0
                ];
            }
            
            $variantsData[$vid]['new_in'] += $transfer->quantity_requested;
            $variantsData[$vid]['closing'] += $transfer->total_units;
            
            foreach ($transfer->transferSales as $sale) {
                $variantsData[$vid]['sold'] += $sale->quantity;
                $variantsData[$vid]['sales_tzs'] += $sale->total_price;
                $totalBottlesSold += $sale->quantity;
                $totalRevenue += $sale->total_price;
            }
        }
        
        $oldSalesQuery = \App\Models\TransferSale::whereDate('created_at', $date)
            ->whereHas('stockTransfer', function($q) use ($date) {
                $q->whereDate('created_at', '<', $date);
            });
            
        if (!$isAdmin) {
            $oldSalesQuery->whereHas('stockTransfer', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            });
        }

        $oldSales = $oldSalesQuery->with('stockTransfer.productVariant.product')
            ->get();
            
        foreach ($oldSales as $sale) {
            $transfer = $sale->stockTransfer;
            if (!$transfer->productVariant || !$transfer->productVariant->product) continue;
            $vid = $transfer->product_variant_id;
            if (!isset($variantsData[$vid])) {
                $variantsData[$vid] = [
                    'item' => $transfer->productVariant->product->name . ' (' . $transfer->productVariant->name . ')',
                    'opening' => $sale->quantity + $transfer->total_units,
                    'new_in' => 0,
                    'sold' => $sale->quantity,
                    'sales_tzs' => $sale->total_price,
                    'closing' => $transfer->total_units
                ];
            } else {
                $variantsData[$vid]['opening'] += $sale->quantity;
                $variantsData[$vid]['sold'] += $sale->quantity;
                $variantsData[$vid]['sales_tzs'] += $sale->total_price;
            }
            $totalBottlesSold += $sale->quantity;
            $totalRevenue += $sale->total_price;
        }

        $salesStatistics = [
            'bottles_sold' => $totalBottlesSold,
            'sales_variants' => count($variantsData),
            'total_revenue' => $totalRevenue,
            'inventory_items' => count($variantsData)
        ];

        // NEW DATA
        $waiterReconciliationsQuery = \App\Models\WaiterDailyReconciliation::with('waiter')
            ->whereDate('reconciliation_date', $date);
        if (!$isAdmin) {
            $waiterReconciliationsQuery->where('user_id', $ownerId);
        }
        $waiterReconciliations = $waiterReconciliationsQuery->get();

        $handoversQuery = \App\Models\FinancialHandover::where('department', 'bar')
            ->whereDate('handover_date', $date);
        if (!$isAdmin) {
            $handoversQuery->where('user_id', $ownerId);
        }
        $handovers = $handoversQuery->get();
            
        $paymentBreakdown = [];
        foreach ($handovers as $h) {
            if ($h->status === 'verified') {
                $breakdown = $h->payment_breakdown ?? [];
                if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
                if (is_array($breakdown)) {
                    foreach ($breakdown as $key => $val) {
                        $amt = floatval($val);
                        if ($amt > 0) {
                            $name = strtoupper(str_replace('_', ' ', $key));
                            if ($name === 'CASH TOTAL' || $name === 'DIGITAL TOTAL' || $name === 'TOTAL') continue;
                            
                            // Treat shortage payments as a distinct category but include them in collections
                            if ($name === 'SHORTAGE PAYMENT') {
                                $name = 'SHORTAGE RECOVERED';
                            }
                            
                            if (!isset($paymentBreakdown[$name])) $paymentBreakdown[$name] = 0;
                            $paymentBreakdown[$name] += $amt;
                        }
                    }
                }
            }
        }

        $expenses = $ledger->expenses()
            ->where('category', '!=', 'Kitchen/Food')
            ->orderBy('created_at', 'desc')->get();
            
        $pettyCashListQuery = \App\Models\PettyCashIssue::with('recipient')
            ->whereDate('issue_date', $date)
            ->where('status', 'issued')
            ->where('purpose', 'NOT LIKE', '[FOOD]%');
        if (!$isAdmin) {
            $pettyCashListQuery->where('user_id', $ownerId);
        }
        $pettyCashList = $pettyCashListQuery->get();
            
        // Calculate Bar-specific collections for the summary
        $ledger->bar_cash_received = collect($handovers)->where('status', 'verified')->sum(function($h) {
             $b = $h->payment_breakdown ?? [];
             if (is_string($b)) $b = json_decode($b, true);
             return floatval($b['cash'] ?? 0) + floatval($b['shortage_payment'] ?? 0);
        });
        $ledger->bar_digital_received = collect($handovers)->where('status', 'verified')->sum(function($h) {
            $b = $h->payment_breakdown ?? [];
            if (is_string($b)) $b = json_decode($b, true);
            $total = 0;
            foreach($b as $k => $v) if($k !== 'cash' && $k !== 'total' && !str_contains($k, 'attributed')) $total += floatval($v);
            return $total;
        });
        // Manager Confirmation Status for the Profit
        $managerHandover = FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->first();
        
        $ledger->managerReceiptStatus = $managerHandover ? $managerHandover->status : 'none';
        $ledger->isManagerReceived = ($managerHandover && $managerHandover->status === 'confirmed');
        $ledger->actualPayout = $managerHandover ? $managerHandover->amount : 0;

        return view('accountant.daily_master_sheet_report', compact(
            'ledger', 
            'date',
            'variantsData',
            'salesStatistics',
            'waiterReconciliations',
            'paymentBreakdown',
            'expenses',
            'pettyCashList'
        ));
    }

    public function history(Request $request)
    {
        $ownerId = $this->getOwnerId();
        
        // Ensure today's ledger exists so it shows in history
        $today = date('Y-m-d');
        $currentStaff = $this->getCurrentStaff();
        DailyCashLedger::firstOrCreate(
            ['user_id' => $ownerId, 'ledger_date' => $today],
            [
                'accountant_id' => $currentStaff->id ?? null,
                'opening_cash' => $this->getPreviousClosingCash($ownerId, $today),
                'status' => 'open',
            ]
        );

        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        $query = DailyCashLedger::query();
        if (!$isAdmin) {
            $query->where('user_id', $ownerId);
        }

        if ($request->filled('start_date')) {
            $query->where('ledger_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('ledger_date', '<=', $request->end_date);
        }

        $ledgers = $query->orderBy('ledger_date', 'desc')->paginate(5);
            
        $ledgers->getCollection()->transform(function($ledger) use ($ownerId) {
            // SYNC OPEN LEDGERS: If it's still active, pull the latest previous close balance
            if ($ledger->status === 'open') {
                $latestPrev = $this->getPreviousClosingCash($ownerId, $ledger->ledger_date);
                if ($ledger->opening_cash != $latestPrev) {
                    $ledger->opening_cash = $latestPrev;
                    $ledger->save();
                }
            }

            $handovers = FinancialHandover::where('user_id', $ownerId)
                ->where('department', 'bar')
                ->whereDate('handover_date', $ledger->ledger_date)
                ->get();
            
            $handoverCash = 0;
            $handoverDigital = 0;
            $shortageCollected = 0;
            foreach ($handovers as $h) {
                if ($h->status === 'verified') {
                    // Start by checking breakdown (Cash vs. Digital)
                    $breakdown = $h->payment_breakdown ?? [];
                    if (is_string($breakdown)) {
                        $breakdown = json_decode($breakdown, true);
                    }
                    
                    if (is_array($breakdown) && !empty($breakdown)) {
                        foreach ($breakdown as $key => $val) {
                            $amt = floatval($val);
                            if ($key === 'shortage_payment') {
                                $shortageCollected += $amt;
                                // IMPORTANT: Shoratges paid in CASH should contribute to the daily cash rollover
                                // If they were paid via digital channels, they go to digital collections
                                $handoverCash += $amt; 
                                continue;
                            }
                            if ($key === 'total') continue; // Handled within cash/digital totals
                            
                            if ($key === 'cash' || str_contains($key, 'cash_')) {
                                $handoverCash += $amt;
                            } else {
                                $handoverDigital += $amt;
                            }
                        }
                    } else {
                        // Fallback: If no breakdown, treat as all cash
                        $handoverCash += floatval($h->amount);
                    }
                }
            }
            $ledger->handoverCash = $handoverCash;
            $ledger->handoverDigital = $handoverDigital;
            $ledger->handoverTotal = $handoverCash + $handoverDigital;
            $ledger->shortageCollected = $shortageCollected;

            $ledger->expenseList = $ledger->expenses()
                ->where('category', '!=', 'Kitchen/Food')
                ->orderBy('created_at', 'desc')->get();
                
            $ledger->pettyCashList = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->whereDate('issue_date', $ledger->ledger_date)
                ->where('status', 'issued')
                ->where('purpose', 'NOT LIKE', '[FOOD]%')
                ->get();
            // Sync ledger totals to ensure petty cash is included in DB fields
            $ledger->syncTotals();
            
            // For backward compatibility with the dynamic properties used in the view
            $ledger->combined_expenses = $ledger->total_expenses;
            $ledger->total_circulation_outflow = $ledger->total_expenses_from_circulation;
            $ledger->total_profit_outflow = $ledger->total_expenses_from_profit;


            // Manager Confirmation Status for the Profit
            $managerHandoverQuery = FinancialHandover::whereDate('handover_date', $ledger->ledger_date)
                ->where('handover_type', 'accountant_to_owner')
                ->where('department', 'Master Sheet');
            if (!auth()->check() || !auth()->user()->isAdmin()) {
                $managerHandoverQuery->where('user_id', $ownerId);
            }
            $managerHandover = $managerHandoverQuery->first();
            
            $ledger->managerReceiptStatus = $managerHandover ? $managerHandover->status : 'none';
            $ledger->isManagerReceived = ($managerHandover && $managerHandover->status === 'confirmed');

            // Attach shortages for this specific date
            $ledger->shortages = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->whereDate('reconciliation_date', $ledger->ledger_date)
                ->where('difference', '<', 0)
                ->with('waiter')
                ->get();

            return $ledger;
        });

        return view('accountant.daily_master_sheet_history', compact('ledgers'));
    }



    private function getPreviousClosingCash($ownerId, $date)
    {
        $prevLedgerQuery = DailyCashLedger::where('ledger_date', '<', $date)
            ->where('status', 'closed')
            ->orderBy('ledger_date', 'desc');
            
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $prevLedgerQuery->where('user_id', $ownerId);
        }
        $prevLedger = $prevLedgerQuery->first();

        return $prevLedger ? $prevLedger->carried_forward : 0;
    }

    public function storeExpense(Request $request)
    {
        $request->validate([
            'ledger_id' => 'required|exists:daily_cash_ledgers,id',
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'fund_source' => 'required|in:circulation,profit'
        ]);

        $ledger = DailyCashLedger::findOrFail($request->ledger_id);
        
        // Find handover status
        $managerHandover = \App\Models\FinancialHandover::where('user_id', $ledger->user_id)
            ->whereDate('handover_date', $ledger->ledger_date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->first();
        $isHandoverConfirmed = ($managerHandover && $managerHandover->status === 'confirmed');

        if ($isHandoverConfirmed) {
            return response()->json(['success' => false, 'error' => 'Financial handover is already confirmed by the owner. Cannot add expenses.']);
        }

        // Limit Checks
        if ($request->fund_source === 'profit') {
            $submittedProfit = $ledger->profit_submitted_to_boss ?? 0;
            $availableProfit = max(0, $ledger->profit_generated - $ledger->total_expenses_from_profit - $submittedProfit);
            
            if ($request->amount > $availableProfit) {
                $errorMsg = "Insufficient Profit! ";
                if ($submittedProfit > 0) {
                    $errorMsg .= "(Profit of TSh " . number_format($submittedProfit) . " has already been submitted to the boss). ";
                }
                $errorMsg .= "Available: TSh " . number_format($availableProfit);
                return response()->json(['success' => false, 'error' => $errorMsg]);
            }
        } else {
            $availableCirculation = max(0, ($ledger->opening_cash + $ledger->total_cash_received) - $ledger->total_expenses_from_circulation);
            if ($request->amount > $availableCirculation && $ledger->ledger_date != date('Y-m-d')) {
                return response()->json(['success' => false, 'error' => "Insufficient funds in circulation! (Available: TSh " . number_format($availableCirculation) . ")"]);
            }
        }

        $user = \Auth::user();
        
        $expense = \App\Models\DailyExpense::create([
            'daily_cash_ledger_id' => $ledger->id,
            'user_id' => $ledger->user_id,
            'logged_by' => $user->staff->id ?? null,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'fund_source' => $request->fund_source
        ]);

        // Recalculate ledger totals via centralized model logic
        $ledger->syncTotals()->save();

        return response()->json(['success' => true, 'message' => 'Expense logged successfully.']);
    }

    public function deleteExpense($id)
    {
        $expense = \App\Models\DailyExpense::findOrFail($id);
        $ledger = $expense->ledger;

        // Find handover status
        $managerHandover = \App\Models\FinancialHandover::where('user_id', $ledger->user_id)
            ->whereDate('handover_date', $ledger->ledger_date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->first();
        $isHandoverConfirmed = ($managerHandover && $managerHandover->status === 'confirmed');

        if ($isHandoverConfirmed) {
            return response()->json(['success' => false, 'error' => 'Financial handover is already confirmed. Cannot remove expenses.'], 403);
        }

        $expense->delete();

        // Recalculate via centralized model logic
        $ledger->syncTotals()->save();

        return response()->json(['success' => true, 'message' => 'Expense removed.']);
    }

    public function closeDay(Request $request)
    {
        $request->validate([
            'ledger_id' => 'required|exists:daily_cash_ledgers,id',
            'actual_closing_cash' => 'required|numeric|min:0',
            'profit_submitted_to_boss' => 'required|numeric|min:0',
            'carried_forward' => 'required|numeric|min:0'
        ]);

        $ledger = DailyCashLedger::findOrFail($request->ledger_id);
        
        if ($ledger->status !== 'open') {
            return redirect()->back()->with('error', 'Ledger is already closed.');
        }

        $ledger->update([
            'actual_closing_cash' => $request->actual_closing_cash,
            'profit_submitted_to_boss' => 0, // Payout is now a separate step
            'money_in_circulation' => $request->money_in_circulation ?? $request->carried_forward,
            'carried_forward' => $request->carried_forward,
            'status' => 'closed',
            'closed_at' => now(),
            'accountant_id' => Auth::user()->staff->id ?? null
        ]);

        // Trigger SMS Notifications (Manager, Accountant, and Counter Staff)
        try {
            $handoverSmsService = new \App\Services\HandoverSmsService();
            $handoverSmsService->sendDailyMasterSheetClosedSms($ledger);
        } catch (\Exception $e) {
            \Log::error('SMS notification failed for Daily Master Sheet closure: ' . $e->getMessage());
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Day closed and verified successfully. Financial records are now locked.',
                'redirect' => route('accountant.daily-master-sheet', ['date' => $ledger->ledger_date])
            ]);
        }

        return redirect()->back()->with('success', 'Day closed successfully. Values locked.');
    }

    public function undoCloseDay(Request $request)
    {
        $request->validate([
            'ledger_id' => 'required|exists:daily_cash_ledgers,id',
        ]);

        $ledger = DailyCashLedger::findOrFail($request->ledger_id);
        
        if ($ledger->status !== 'closed') {
            return response()->json(['success' => false, 'error' => 'Ledger is not closed.']);
        }

        // Delete any pending profit handover if it exists
        \App\Models\FinancialHandover::where('user_id', $ledger->user_id)
            ->whereDate('handover_date', $ledger->ledger_date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->where('status', 'pending')
            ->delete();

        $ledger->update([
            'status' => 'open',
            'closed_at' => null,
            // We can leave the numbers there as they will be recalculated
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Day reopened successfully. You can now add expenses and recalculate.'
        ]);
    }

    public function submitProfitHandover(Request $request)
    {
        $request->validate([
            'ledger_id' => 'required|exists:daily_cash_ledgers,id',
            'amount' => 'required|numeric|min:0.01'
        ]);

        $ledger = DailyCashLedger::findOrFail($request->ledger_id);
        $ownerId = $this->getOwnerId();

        // Handle existing handover
        $existing = FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $ledger->ledger_date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->first();

        if ($existing) {
            if ($existing->status !== 'pending') {
                return response()->json(['success' => false, 'error' => 'Profit handover has already been confirmed/verified. Cannot update.']);
            }
            
            // Update existing pending handover
            $existing->update([
                'amount' => $request->amount,
                'accountant_id' => Auth::user()->staff->id ?? null,
                'notes' => 'Updated profit submission due to expense changes'
            ]);
            
            $ledger->update(['profit_submitted_to_boss' => $request->amount]);
            
            try {
                $smsService = new \App\Services\HandoverSmsService();
                $smsService->sendProfitSubmissionToBossSms($existing);
            } catch (\Exception $e) {
                \Log::error('Failed to send manager profit submission SMS: ' . $e->getMessage());
            }
            
            return response()->json(['success' => true, 'message' => 'Profit payout updated successfully.']);
        }

        $handover = FinancialHandover::create([
            'user_id' => $ownerId,
            'accountant_id' => Auth::user()->staff->id ?? null,
            'handover_date' => $ledger->ledger_date,
            'handover_type' => 'accountant_to_owner',
            'department' => 'Master Sheet',
            'amount' => $request->amount,
            'status' => 'pending',
            'payment_method' => 'cash',
            'notes' => 'Manual profit submission from history archive'
        ]);

        // Update ledger to show that a payout has been initiated
        $ledger->update(['profit_submitted_to_boss' => $request->amount]);

        try {
            $smsService = new \App\Services\HandoverSmsService();
            $smsService->sendProfitSubmissionToBossSms($handover);
        } catch (\Exception $e) {
            \Log::error('Failed to send manager profit submission SMS: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Profit payout successfully registered.']);
    }
}
