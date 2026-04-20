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

        // Identify all shifts that started on this business day
        $dailyShiftIds = \App\Models\BarShift::where('user_id', $ownerId)
            ->whereDate('opened_at', $date)
            ->pluck('id');

        $handoversQuery = \App\Models\FinancialHandover::where('department', 'bar')
            ->where(function($q) use ($date, $dailyShiftIds) {
                $q->whereDate('handover_date', $date)
                  ->orWhereIn('bar_shift_id', $dailyShiftIds);
            });
            
        $paymentBreakdown = [];
        $handovers = $handoversQuery->get();
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
            
        // Identify all shifts that logically belong to this business day (started on this date)
        $dailyShifts = \App\Models\BarShift::where('user_id', $ownerId)
            ->whereDate('opened_at', $date)
            ->get();
        $dailyShiftIds = $dailyShifts->pluck('id')->toArray();
        
        $handovers = \App\Models\FinancialHandover::where('department', 'bar')
            ->where(function($q) use ($date, $dailyShiftIds) {
                $q->whereDate('handover_date', $date)
                  ->orWhereIn('bar_shift_id', $dailyShiftIds);
            })
            ->get();

        // 1. Identify all shortages for this day to calculate NET revenue
        $dailyRecIds = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
            ->whereNotNull('reconciliation_id')
            ->pluck('reconciliation_id')
            ->unique()
            ->toArray();

        $totalDayShortage = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
            ->where(function($q) use ($dailyShiftIds, $dailyRecIds) {
                $q->whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
                  ->orWhereIn('id', !empty($dailyRecIds) ? $dailyRecIds : [0]);
            })
            ->where('difference', '<', 0)
            ->sum('difference');
        $totalDayShortage = abs($totalDayShortage);
        $ledger->totalDayShortage = $totalDayShortage;

        $handoverCash = 0;
        $handoverDigital = 0;
        $shortageCollected = 0;
        
        // 2. Process Verified Handovers (Legacy & Current)
        $verifiedHandovers = $handovers->where('status', 'verified');
        foreach ($verifiedHandovers as $h) {
             $b = $h->payment_breakdown ?? [];
             if (is_string($b)) $b = json_decode($b, true);
             
             // 'shortage_payment' is just a tracker tag. The actual money is already binned inside 'cash' or 'digital' keys.
             $handoverCash += floatval($b['cash'] ?? 0);
             $shortageCollected += floatval($b['shortage_payment'] ?? 0);
             
             if (is_array($b)) {
                 foreach($b as $k => $v) {
                     if($k !== 'cash' && $k !== 'total' && $k !== 'shortage_payment' && !str_contains($k, 'attributed')) {
                         $handoverDigital += floatval($v);
                     }
                 }
             }
        }

        // 3. [REAL-TIME PROJECTION] Aggregate Expected Revenue ONLY if no verified handovers exist for these shifts
        if ($verifiedHandovers->count() == 0 && !empty($dailyShiftIds)) {
            $handoverTotal = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                ->whereIn('status', ['served', 'delivered'])
                ->sum('total_amount');
            
            $handoverDigital = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                ->whereIn('status', ['served', 'delivered'])
                ->where('payment_method', '!=', 'cash')
                ->sum('total_amount');
            
            // USE NET COLLECTIONS: Deduct shortage from the projected cash collections
            $projectedCash = $handoverTotal - $handoverDigital;
            $handoverCash = max(0, $projectedCash - $totalDayShortage);
        }

        // Define transition dates for the migration to Net Collections
        $lDateFormatted = $ledger->ledger_date->format('Y-m-d');
        $isTransitionDate = in_array($lDateFormatted, ['2026-04-15', '2026-04-16', '2026-04-17', '2026-04-18']);

        // SYNC LEDGER: Persist the net collections (Model handle Profit/Distribution)
        if ($ledger->status === 'open' || $isTransitionDate) {
             $ledger->total_cash_received = $handoverCash;
             $ledger->total_digital_received = $handoverDigital;
             $ledger->syncTotals()->save();
        } else {
             $ledger->syncTotals();
        }

        // Attach UI helpers for the view
        $ledger->handoverCash = $handoverCash;
        $ledger->handoverDigital = $handoverDigital;

        // Manager Confirmation Status for the Profit (View-only properties assigned after save)
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
            'pettyCashList',
            'shortageCollected'
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

        $ledgers = $query->orderBy('ledger_date', 'desc')->paginate(10);
            
        // SYNC CHAIN: We sort the current page collection ASC to ensure forward propagation (15 -> 16 -> 17)
        $ledgers->getCollection()->sortBy('ledger_date')->each(function($ledger) use ($ownerId) {
            // SYNC LEDGER ROLLOVERS: Ensure current opening cash matches previous closing cash
            // We sync even for 'closed' ledgers during this migration to ensure the chain is perfect.
            $latestPrev = $this->getPreviousClosingCash($ownerId, $ledger->ledger_date);
            if ($ledger->opening_cash != $latestPrev) {
                $ledger->opening_cash = $latestPrev;
                $ledger->save();
            }

            $handoverCash = 0;
            $handoverDigital = 0;
            $shortageCollected = 0;
            $hasOpenShift = false;
            $hasUnverifiedReconciliations = false;
            
            // 1. Identify all shifts that logically belong to this business day (started on this date)
            $dailyShifts = \App\Models\BarShift::where('user_id', $ownerId)
                ->whereDate('opened_at', $ledger->ledger_date)
                ->get();
            $dailyShiftIds = $dailyShifts->pluck('id')->toArray();
            
            $handovers = FinancialHandover::where('user_id', $ownerId)
                ->where('department', 'bar')
                ->where(function($q) use ($ledger, $dailyShiftIds) {
                    $q->whereDate('handover_date', $ledger->ledger_date)
                      ->orWhereIn('bar_shift_id', $dailyShiftIds);
                })
                ->get();

            if ($dailyShifts->where('status', 'open')->count() > 0) {
                $hasOpenShift = true;
            }

            // 2. [REVENUE PROJECTION] Aggregate all revenue from shifts on this day
            $totalProjectedRevenue = 0;
            if (!empty($dailyShiftIds)) {
                $totalProjectedRevenue = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                    ->whereIn('status', ['served', 'delivered'])
                    ->sum('total_amount');
            }

            // 1. Identify all shortages for this day to calculate NET revenue
            $dailyRecIds = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                ->whereNotNull('reconciliation_id')
                ->pluck('reconciliation_id')
                ->unique()
                ->toArray();

            $totalDayShortage = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->where(function($q) use ($dailyShiftIds, $dailyRecIds) {
                    $q->whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
                      ->orWhereIn('id', !empty($dailyRecIds) ? $dailyRecIds : [0]);
                })
                ->where('difference', '<', 0)
                ->sum('difference');
            $totalDayShortage = abs($totalDayShortage);

            $currentHandoversTotal = $handovers->whereIn('status', ['pending', 'verified'])->sum('amount');
            
            if ($currentHandoversTotal > 100) {
                // Process both verified money and any pending shortage settlements
                foreach ($handovers as $h) {
                    // Only process pending handovers if they are shortage settlements
                    $isShortagePay = false;
                    $breakdown = $h->payment_breakdown ?? [];
                    if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
                    if (isset($breakdown['shortage_payment'])) $isShortagePay = true;
                    
                    if ($h->status !== 'verified' && !$isShortagePay) continue;

                    if (is_array($breakdown)) {
                        foreach ($breakdown as $key => $val) {
                            $amt = floatval($val);
                            if ($key === 'shortage_payment') {
                                $shortageCollected += $amt;
                                continue;
                            }
                            if ($key === 'total') continue;
                            if ($key === 'cash' || str_contains($key, 'cash_')) {
                                $handoverCash += $amt;
                            } else {
                                $handoverDigital += $amt;
                            }
                        }
                    }
                }
            }

            // [NEW LOGIC] Combine handovers with live projections for "Uwazi" (Transparency)
            if ($hasOpenShift || $currentHandoversTotal <= 100) {
                // FALLBACK/ADDITION: Use Order-based projection for open shifts
                $projectedDigital = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                    ->whereIn('status', ['served', 'delivered'])
                    ->where('payment_method', '!=', 'cash')
                    ->sum('total_amount');
                
                $projectedTotal = $totalProjectedRevenue;
                $projectedCash = max(0, $projectedTotal - $projectedDigital - $totalDayShortage);

                // If we are in the middle of a shift, we ADD the handovers (debt recoveries) to the projection
                $handoverCash += $projectedCash;
                $handoverDigital += $projectedDigital;
            }

            // 3. Determine Business Status (Temporary properties, do not save to DB)
            $businessStatus = 'DONE';
            $statusColor = '#28a745';
            
            $hasPendingHandover = $handovers->where('status', 'pending')->count() > 0;

            if ($hasOpenShift) {
                $businessStatus = 'SHIFT IN PROGRESS';
                $statusColor = '#17a2b8'; // Teal
            } elseif ($hasPendingHandover) {
                $businessStatus = 'PENDING';
                $statusColor = '#ffc107'; // Gold
            } elseif (empty($dailyShiftIds)) {
                $businessStatus = 'NO ACTIVITY';
                $statusColor = '#6c757d'; // Gray
            }
            
            $lDateFormatted = $ledger->ledger_date->format('Y-m-d');
            $isTransitionDate = in_array($lDateFormatted, ['2026-04-15', '2026-04-16', '2026-04-17', '2026-04-18']);

            if ($ledger->status === 'open' || $isTransitionDate) {
                // Important: Persist the NET collections (Model's syncTotals will handle Profit/Deficit)
                $ledger->total_cash_received = $handoverCash;
                $ledger->total_digital_received = $handoverDigital;
                $ledger->save(); 
            }

            // 2. ATTACH VIEW-ONLY PROPERTIES
            // We call syncTotals() manually here to ensure that historical records 
            // have their virtual attributes (grossProfit, circulationDebt, etc.) populated for the view.
            $ledger->syncTotals();

            $ledger->businessStatus = $businessStatus;
            $ledger->statusColor = $statusColor;
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

            $ledger->combined_expenses = $ledger->total_expenses;
            $ledger->total_circulation_outflow = $ledger->total_expenses_from_circulation;
            $ledger->total_profit_outflow = $ledger->total_expenses_from_profit;

            // NEW: Fetch individual debt repayments for the "Uwazi" (Transparency) breakdown
            $ledger->shortageBreakdown = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->where(function($q) use ($ledger) {
                    $q->where('notes', 'LIKE', '%' . $ledger->ledger_date->format('Y-m-d') . '%')
                      ->where('status', 'reconciled');
                })
                ->with('waiter')
                ->get()
                ->map(function($rec) use ($ledger) {
                    $notes = json_decode($rec->notes, true);
                    $settlements = $notes['settlements'] ?? [];
                    $amountToday = 0;
                    foreach($settlements as $s) {
                        if (isset($s['date']) && str_contains($s['date'], $ledger->ledger_date->format('Y-m-d'))) {
                            $amountToday += $s['amount'];
                        }
                    }
                    return [
                        'name' => $rec->waiter->full_name ?? 'Staff',
                        'amount' => $amountToday
                    ];
                })->filter(fn($item) => $item['amount'] > 0);

            // If we have verified handovers but no linked recs (legacy), try to show the handover itself
            if ($ledger->shortageBreakdown->isEmpty() && $shortageCollected > 0) {
                 $ledger->shortageBreakdown = collect([[
                     'name' => 'Debt Recovery',
                     'amount' => $shortageCollected
                 ]]);
            }


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

            // Attach shortages for this specific date (Robust link via shifts + order-reconciliation links)
            $dailyRecIds = \App\Models\BarOrder::whereIn('bar_shift_id', $dailyShiftIds)
                ->whereNotNull('reconciliation_id')
                ->pluck('reconciliation_id')
                ->unique()
                ->toArray();

            $ledger->shortages = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->where(function($q) use ($dailyShiftIds, $dailyRecIds) {
                    $q->whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
                      ->orWhereIn('id', !empty($dailyRecIds) ? $dailyRecIds : [0]);
                })
                ->where('difference', '<', 0)
                ->with('waiter')
                ->get();

            // Attach metrics for Blade processing
            $ledger->handoverCash = $handoverCash;
            $ledger->handoverDigital = $handoverDigital;

            return $ledger;
        });

        return view('accountant.daily_master_sheet_history', compact('ledgers'));
    }



    private function getPreviousClosingCash($ownerId, $date)
    {
        // Find the immediately preceding ledger by date, regardless of status
        // This ensures the financial chain (15 -> 16 -> 17 -> 18) is never broken
        $prevLedgerQuery = DailyCashLedger::where('ledger_date', '<', $date)
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
            'accountant_id' => $this->getCurrentStaff()->id ?? null
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
                'accountant_id' => $this->getCurrentStaff()->id ?? null,
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
            'accountant_id' => $this->getCurrentStaff()->id ?? null,
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
