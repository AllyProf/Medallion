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

        $ledger = DailyCashLedger::where('user_id', $ownerId)
            ->where('ledger_date', $date)
            ->first();

        if (!$ledger) {
            return redirect()->route('accountant.daily-master-sheet.history')->with('error', 'No ledger found for this date.');
        }

        $transfers = \App\Models\StockTransfer::where('user_id', $ownerId)
            ->whereDate('created_at', $date)
            ->whereIn('status', ['prepared', 'completed'])
            ->with(['productVariant.product', 'transferSales' => function($q) use ($date) {
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
        
        $oldSales = \App\Models\TransferSale::whereHas('stockTransfer', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            })
            ->whereDate('created_at', $date)
            ->whereHas('stockTransfer', function($q) use ($date) {
                $q->whereDate('created_at', '<', $date);
            })
            ->with('stockTransfer.productVariant.product')
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
        $waiterReconciliations = \App\Models\WaiterDailyReconciliation::with('waiter')
            ->whereDate('reconciliation_date', $date)
            ->where('user_id', $ownerId)
            ->get();

        $handovers = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->where('department', 'bar')
            ->whereDate('handover_date', $date)
            ->get();
            
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
            
        $pettyCashList = \App\Models\PettyCashIssue::with('recipient')->where('user_id', $ownerId)
            ->whereDate('issue_date', $date)
            ->where('status', 'issued')
            ->where('purpose', 'NOT LIKE', '[FOOD]%')
            ->get();
            
        // Calculate Bar-specific collections for the summary
        $ledger->bar_cash_received = collect($handovers)->where('status', 'verified')->sum(function($h) {
             $b = $h->payment_breakdown ?? [];
             if (is_string($b)) $b = json_decode($b, true);
             return floatval($b['cash'] ?? 0);
        });
        $ledger->bar_digital_received = collect($handovers)->where('status', 'verified')->sum(function($h) {
            $b = $h->payment_breakdown ?? [];
            if (is_string($b)) $b = json_decode($b, true);
            $total = 0;
            foreach($b as $k => $v) if($k !== 'cash' && $k !== 'total' && !str_contains($k, 'attributed')) $total += floatval($v);
            return $total;
        });

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
        $query = DailyCashLedger::where('user_id', $ownerId);

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
                            if ($key === 'cash' || $key === 'shortage_payment' || str_contains($key, 'cash_')) {
                                $handoverCash += $amt;
                            } elseif ($key !== 'total') {
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

            $ledger->expenseList = $ledger->expenses()
                ->where('category', '!=', 'Kitchen/Food')
                ->orderBy('created_at', 'desc')->get();
                
            $ledger->pettyCashList = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->whereDate('issue_date', $ledger->ledger_date)
                ->where('status', 'issued')
                ->where('purpose', 'NOT LIKE', '[FOOD]%')
                ->get();
            
            // Expense Categorization
            $circulationExpenses = $ledger->total_expenses_from_circulation + $ledger->pettyCashList->where('fund_source', 'circulation')->sum('amount');
            $profitExpenses = $ledger->total_expenses_from_profit + $ledger->pettyCashList->where('fund_source', 'profit')->sum('amount');
            
            // Total Outflow is all cash leaving the box
            $ledger->combined_expenses = $circulationExpenses + $profitExpenses;
            
            // Expose grouped expenses to the blade for correct Rollover calculation
            $ledger->total_circulation_outflow = $circulationExpenses;
            $ledger->total_profit_outflow = $profitExpenses;

            // Manager Confirmation Status for the Profit
            $managerHandover = FinancialHandover::where('user_id', $ownerId)
                ->whereDate('handover_date', $ledger->ledger_date)
                ->where('handover_type', 'accountant_to_owner')
                ->where('department', 'Master Sheet')
                ->first();
            
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
        $prevLedger = DailyCashLedger::where('user_id', $ownerId)
            ->where('ledger_date', '<', $date)
            ->where('status', 'closed')
            ->orderBy('ledger_date', 'desc')
            ->first();

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
        
        if ($ledger->status !== 'open') {
            return response()->json(['success' => false, 'error' => 'Ledger is already closed/verified.']);
        }

        // Limit Checks
        if ($request->fund_source === 'profit') {
            $availableProfit = max(0, $ledger->profit_generated - $ledger->total_expenses_from_profit);
            // Relaxing constraint for active shifts: if it's the current date, allow slight overrides
            if ($request->amount > $availableProfit && $ledger->ledger_date != date('Y-m-d')) {
                return response()->json(['success' => false, 'error' => "Insufficient Profit! (Available: TSh " . number_format($availableProfit) . ")"]);
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

        // Recalculate ledger totals
        $ledger->total_expenses = $ledger->expenses()->sum('amount');
        $ledger->total_expenses_from_circulation = $ledger->expenses()->where('fund_source', 'circulation')->sum('amount');
        $ledger->total_expenses_from_profit = $ledger->expenses()->where('fund_source', 'profit')->sum('amount');
        
        // Re-calculate closing cash
        $ledger->expected_closing_cash = $ledger->opening_cash + $ledger->total_cash_received + $ledger->total_digital_received - $ledger->total_expenses;
        
        $ledger->save();

        return response()->json(['success' => true, 'message' => 'Expense logged successfully.']);
    }

    public function deleteExpense($id)
    {
        $expense = \App\Models\DailyExpense::findOrFail($id);
        $ledger = $expense->ledger;

        if ($ledger->status !== 'open') {
            return response()->json(['success' => false, 'error' => 'Cannot delete from a closed ledger.'], 403);
        }

        $expense->delete();

        // Recalculate
        $ledger->total_expenses = $ledger->expenses()->sum('amount');
        $ledger->total_expenses_from_circulation = $ledger->expenses()->where('fund_source', 'circulation')->sum('amount');
        $ledger->total_expenses_from_profit = $ledger->expenses()->where('fund_source', 'profit')->sum('amount');
        
        $ledger->expected_closing_cash = $ledger->opening_cash + $ledger->total_cash_received - $ledger->total_expenses;
        $ledger->save();

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
            'profit_submitted_to_boss' => $request->profit_submitted_to_boss,
            'money_in_circulation' => $request->money_in_circulation ?? $request->carried_forward,
            'carried_forward' => $request->carried_forward,
            'status' => 'closed',
            'closed_at' => now(),
            'accountant_id' => Auth::user()->staff->id ?? null
        ]);

        // ── AUTO-GENERATE FINANCIAL HANDOVER (Accountant to Manager/Boss)
        if ($request->profit_submitted_to_boss > 0) {
            $ownerId = session('is_staff') ? \App\Models\Staff::find(session('staff_id'))->user_id : Auth::id();
            \App\Models\FinancialHandover::updateOrCreate(
                [
                    'user_id' => $ownerId,
                    'handover_date' => $ledger->ledger_date,
                    'handover_type' => 'accountant_to_owner',
                    'department' => 'Master Sheet'
                ],
                [
                    'accountant_id' => Auth::user()->staff->id ?? null,
                    'amount' => $request->profit_submitted_to_boss,
                    'status' => 'pending',
                    'payment_method' => 'cash',
                ]
            );
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

        // Check if already exist
        $existing = FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $ledger->ledger_date)
            ->where('handover_type', 'accountant_to_owner')
            ->where('department', 'Master Sheet')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'error' => 'Profit handover already exists for this day.']);
        }

        FinancialHandover::create([
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

        return response()->json(['success' => true, 'message' => 'Profit handover submitted to Boss safely.']);
    }
}
