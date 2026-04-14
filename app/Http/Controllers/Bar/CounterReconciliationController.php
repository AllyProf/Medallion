<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\BarOrder;
use App\Models\DailyCashLedger;
use App\Models\FinancialHandover;
use App\Models\OrderPayment;
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;
use App\Models\WaiterNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounterReconciliationController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Display reconciliation page with all waiters
     */
    public function reconciliation(Request $request)
    {
        $currentStaff = $this->getCurrentStaff();
        $isSuperAdmin = $this->isSuperAdminRole();
        $roleSlug = $currentStaff ? strtolower(trim($currentStaff->role->slug ?? '')) : '';
        $isCounterOrAccountant = in_array($roleSlug, ['counter', 'accountant']);

        if (!$isSuperAdmin && !$isCounterOrAccountant && !$this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to view reconciliations.');
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));

        // Management role detection
        $isAccountant = $currentStaff && (
            strtolower($currentStaff->role->slug ?? '') === 'accountant' ||
            strtolower($currentStaff->role->name ?? '') === 'accountant'
        );
        $isManager = $currentStaff && in_array(strtolower($currentStaff->role->slug ?? ''), ['manager', 'admin', 'general-manager']);
        $isManagementRole = $isAccountant || $isManager || $isSuperAdmin;

        // Get location from session (branch switcher)
        $location = session('active_location');

        // Get waiters, or anyone who placed an order today, or has a reconciliation today
        $waitersQuery = Staff::where('is_active', true)
            ->where(function ($query) use ($date, $location) {
                // Role check
                $query->whereHas('role', function ($q) {
                    $q->where('slug', 'waiter');
                })
                    // OR orders today check
                    ->orWhereHas('orders', function ($q) use ($date, $location) {
                        $q->whereDate('created_at', $date);
                        if ($location && $location !== 'all') {
                            $q->whereHas('table', function ($sq) use ($location) {
                                $sq->where('location', $location);
                            });
                        }
                    })
                    // OR daily reconciliations check
                    ->orWhereHas('dailyReconciliations', function ($q) use ($date) {
                        $q->where('reconciliation_date', $date)
                            ->where('reconciliation_type', 'bar');
                    });
            })
            ->when($location && $location !== 'all', function ($q) use ($location) {
                $q->where('location_branch', $location);
            });

        // If not accountant and not super admin, filter by owner
        if (!$isAccountant && !$isSuperAdmin) {
            $waitersQuery->where('user_id', $ownerId);
        }

        $bar_shift = \App\Models\BarShift::when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->where('status', 'open')
            ->when($currentStaff && !$isAccountant && !$isSuperAdmin, function ($q) use ($currentStaff) {
                $q->where('staff_id', $currentStaff->id);
            })
            ->first();

        $waiters = $waitersQuery
            ->with([
                'dailyReconciliations' => function ($q) use ($date, $bar_shift) {
                    $q->where('reconciliation_date', $date)
                        ->where('reconciliation_type', 'bar') // Only get bar reconciliations
                        ->when($bar_shift, function ($sq) use ($bar_shift) {
                            $sq->where('bar_shift_id', $bar_shift->id);
                        });
                },
            ])
            ->get()
            ->map(function ($waiter) use ($ownerId, $date, $isAccountant, $isSuperAdmin, $location, $bar_shift) {
                $ordersQuery = BarOrder::query()
                    ->where('waiter_id', $waiter->id)
                    ->when($location && $location !== 'all', function ($q) use ($location) {
                        $q->whereHas('table', function ($sq) use ($location) {
                            $sq->where('location', $location);
                        });
                    });

                // If not accountant, filter by owner
                if (!$isAccountant && !$isSuperAdmin) {
                    $ordersQuery->where('user_id', $ownerId);
                }

                $bar_reconciliation = $waiter->dailyReconciliations->first();
                $targetShiftId = $bar_reconciliation ? $bar_reconciliation->bar_shift_id : ($bar_shift ? $bar_shift->id : null);

                $allOrders = $ordersQuery
                    ->when($targetShiftId, function ($q) use ($targetShiftId) {
                        return $q->where('bar_shift_id', $targetShiftId);
                    }, function ($q) use ($date) {
                        return $q->whereDate('created_at', $date);
                    })
                    ->where('status', '!=', 'cancelled')
                    ->with(['items.transferSales.stockTransfer', 'items.productVariant.product', 'kitchenOrderItems', 'table', 'orderPayments'])
                    ->get();

                // Separate bar orders (drinks) from food orders
                // Bar orders: orders that have items (drinks) - may also have food
                // Food-only orders: orders that only have kitchenOrderItems, no items
                $barOrders = $allOrders->filter(function ($order) {
                    return $order->items && $order->items->count() > 0;
                });

                $foodOnlyOrders = $allOrders->filter(function ($order) {
                    return ($order->items->count() === 0) && ($order->kitchenOrderItems && $order->kitchenOrderItems->count() > 0);
                });

                // Check for unpaid bar orders (both served and pending)
                $unpaidBarOrders = $barOrders->filter(function ($order) {
                    return $order->payment_status !== 'paid' && $order->status !== 'cancelled';
                });
                $hasUnpaidOrders = $unpaidBarOrders->count() > 0;

                // Counter reconciliation summary should show value of all current bar orders
                // (pending + served), while cancelled are already excluded above.
                $barSales = $barOrders->sum(function ($order) {
                    return $order->items->sum('total_price');
                });

                // Total sales for counter summary = bar sales (drinks only)
                $totalSales = $barSales;

                // Calculate food sales from kitchenOrderItems
                $foodSales = $allOrders->sum(function ($order) {
                    return $order->kitchenOrderItems ? $order->kitchenOrderItems->sum('total_price') : 0;
                });

                // Keep both counts:
                // - all bar orders (pending + served) for visibility in the table
                // - served bar orders for actual sales figures
                $barOrdersCount = $barOrders->count();
                $servedBarOrdersCount = $barOrders->where('status', 'served')->count();
                $foodOrdersCount = $foodOnlyOrders->count();

                // Already calculated unpaidBarOrders above

                // Calculate total paid amount (only orders that have been reconciled/submitted)
                $totalPaidAmount = $barOrders->where('payment_status', 'paid')
                    ->sum('paid_amount');

                // Payment collection from bar orders only (Avoiding double counting)
                $cashCollected = 0;
                $mobileMoneyCollected = 0;

                foreach ($barOrders as $order) {
                    $orderBar = (float) $order->items->sum('total_price');
                    $orderFood = (float) ($order->kitchenOrderItems ? $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0);
                    $orderTotal = $orderBar + $orderFood;
                    $barShare = $orderTotal > 0 ? ($orderBar / $orderTotal) : 1;

                    if ($order->orderPayments->count() > 0) {
                        $pSum = (float) $order->orderPayments->sum('amount');
                        $cappedSum = min($pSum, (float) $order->total_amount);

                        // Ratio-based distribution if overpaid
                        $ratio = $pSum > 0 ? ($cappedSum / $pSum) : 1;

                        $cashCollected += $order->orderPayments->where('payment_method', 'cash')->sum('amount') * $ratio * $barShare;
                        $mobileMoneyCollected += $order->orderPayments->where('payment_method', '!=', 'cash')->sum('amount') * $ratio * $barShare;
                    } else {
                        // Fallback to order fields
                        if ($order->payment_method === 'cash') {
                            $cashCollected += (float) $order->paid_amount * $barShare;
                        } else {
                            $mobileMoneyCollected += (float) $order->paid_amount * $barShare;
                        }
                    }
                }

                // Detailed platform breakdown for the waiter
                $waiterPlatformTotals = [];
                foreach ($barOrders as $order) {
                    $orderBar = (float) $order->items->sum('total_price');
                    $orderFood = (float) ($order->kitchenOrderItems ? $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0);
                    $orderTotal = $orderBar + $orderFood;
                    $barShare = $orderTotal > 0 ? ($orderBar / $orderTotal) : 1;

                    $pSum = (float) $order->orderPayments->sum('amount');
                    $cappedSum = min($pSum, (float) $order->total_amount);
                    $ratio = $pSum > 0 ? ($cappedSum / $pSum) : 1;

                    foreach ($order->orderPayments as $payment) {
                        if ($payment->payment_method === 'cash') {
                            continue;
                        }

                        $provider = strtolower(trim($payment->mobile_money_number ?? 'mobile'));
                        $label = 'MOBILE MONEY';
                        if (str_contains($provider, 'm-pesa') || str_contains($provider, 'mpesa')) {
                            $label = 'M-PESA';
                        } elseif (str_contains($provider, 'mixx')) {
                            $label = 'MIXX BY YAS';
                        } elseif (str_contains($provider, 'halo')) {
                            $label = 'HALOPESA';
                        } elseif (str_contains($provider, 'tigo')) {
                            $label = 'TIGO PESA';
                        } elseif (str_contains($provider, 'airtel')) {
                            $label = 'AIRTEL MONEY';
                        } elseif (str_contains($provider, 'nmb')) {
                            $label = 'NMB BANK';
                        } elseif (str_contains($provider, 'crdb')) {
                            $label = 'CRDB BANK';
                        } elseif (str_contains($provider, 'kcb')) {
                            $label = 'KCB BANK';
                        }

                        $waiterPlatformTotals[$label] = ($waiterPlatformTotals[$label] ?? 0) + ((float) $payment->amount * $ratio * $barShare);
                    }
                }

                // Re-calculate Total Recorded to match the above logic
                $totalRecordedAmount = $cashCollected + $mobileMoneyCollected;

                $reconciliation = $waiter->dailyReconciliations->first();

                // Submitted amount: use reconciliation if exists, otherwise 0 (not yet submitted)
                // Don't use totalPaidAmount here - that would show as submitted before reconciliation
                $submittedAmount = $reconciliation ? $reconciliation->submitted_amount : 0;

                // Calculate difference:
                // If submitted, use submitted - total. Else use recorded - total.
                $difference = ($submittedAmount > 0 || $reconciliation)
                    ? ($submittedAmount - $totalSales)
                    : ($totalRecordedAmount - $totalSales);

                // Determine status intelligently
                $status = 'pending';
                if ($reconciliation) {
                    // If reconciliation exists, use its status
                    $status = $reconciliation->status;
                } else {
                    // No reconciliation record - determine status based on payment
                    if ($hasUnpaidOrders) {
                        $status = 'pending'; // Still has unpaid orders
                    } elseif ($totalPaidAmount > 0 && abs($difference) < 0.01) {
                        $status = 'paid'; // All orders paid and amounts match
                    } elseif ($totalPaidAmount > 0) {
                        $status = 'partial'; // Some orders paid but amounts don't match
                    }
                }

                // Final amounts for the UI: Use reconciliation record if it exists
                $finalCash = $reconciliation ? $reconciliation->cash_collected : $cashCollected;
                $finalDigital = $reconciliation ? $reconciliation->mobile_money_collected : $mobileMoneyCollected;

                $waiterProfit = 0;
                foreach ($barOrders->where('status', 'served') as $order) {
                    foreach ($order->items as $item) {
                        $itemProfit = 0;
                        if ($item->transferSales->count() > 0) {
                            foreach ($item->transferSales as $ts) {
                                $variant = $ts->stockTransfer->productVariant;
                                $whStock = \App\Models\StockLocation::where('user_id', $ownerId)
                                    ->where('product_variant_id', $ts->stockTransfer->product_variant_id)
                                    ->where('location', 'warehouse')
                                    ->first();
                                $buyingPrice = $whStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0;
                                $itemProfit += ($ts->total_price - ($ts->quantity * $buyingPrice));
                            }
                        } elseif ($item->productVariant) {
                            $variant = $item->productVariant;
                            $qty = $item->quantity;
                            if (($item->sell_type ?? 'unit') === 'tot') {
                                $totsPerBtl = $variant->total_tots ?: 1;
                                $qty = $item->quantity / $totsPerBtl;
                            }
                            $buyingPrice = $variant->buying_price_per_unit ?? 0;
                            $itemProfit = ($item->total_price - ($qty * $buyingPrice));
                        }
                        $waiterProfit += $itemProfit;
                    }
                }

                return [
                    'waiter' => $waiter,
                    'total_sales' => $totalSales, // Bar sales only
                    'bar_sales' => $barSales,
                    'food_sales' => $foodSales,
                    'total_orders' => $barOrdersCount, // Show waiter if they have any bar order
                    'bar_orders_count' => $barOrdersCount,
                    'served_bar_orders_count' => $servedBarOrdersCount,
                    'food_orders_count' => $foodOrdersCount,
                    'has_unpaid_orders' => $hasUnpaidOrders,
                    'cash_collected' => $finalCash,
                    'mobile_money_collected' => $finalDigital,
                    'recorded_cash' => $cashCollected,
                    'recorded_digital' => $mobileMoneyCollected,
                    'expected_amount' => $totalSales, // Expected = bar sales only
                    'recorded_amount' => $totalRecordedAmount, // Amount recorded by waiter (from OrderPayments)
                    'submitted_amount' => $submittedAmount, // Amount submitted/reconciled by counter
                    'difference' => $difference, // Always calculate difference
                    'status' => $status,
                    'orders' => $barOrders, // Only bar orders
                    'reconciliation' => $reconciliation,
                    'platform_totals' => $waiterPlatformTotals,
                    'profit' => $waiterProfit,
                ];
            })
            ->filter(function ($data) {
                // Show active rows with any bar order OR already reconciled records
                return $data['total_orders'] > 0 || ! empty($data['reconciliation']);
            })
            ->sortByDesc('total_sales')
            ->values();

        // Get an active accountant to handover to
        $accountant = Staff::where('user_id', $ownerId)
            ->whereHas('role', function ($q) {
                $q->where('slug', 'accountant');
            })
            ->where('is_active', true)
            ->first();

        // Check if there is already a handover for this shift/date
        $todayHandover = null;
        if ($currentStaff) {
            // Find the shift context for the handover
            $handoverShiftId = $bar_shift ? $bar_shift->id : null;

            $handoverQuery = FinancialHandover::where('user_id', $ownerId)
                ->where('handover_type', 'staff_to_accountant');

            if ($isAccountant || $isSuperAdmin) {
                // Accountant sees handovers where they are recipients for this date
                $handoverQuery->whereDate('handover_date', $date);
            } else {
                // Staff sees their own handovers
                if ($currentStaff) {
                    $handoverQuery->where('accountant_id', $currentStaff->id);
                }

                if ($handoverShiftId) {
                    // If looking at an active shift, prioritize that shift's handover
                    $handoverQuery->where('bar_shift_id', $handoverShiftId);
                } else {
                    // Otherwise show the latest for this date
                    $handoverQuery->whereDate('handover_date', $date);
                }
            }

            $todayHandover = $handoverQuery->orderBy('created_at', 'desc')->first();
        }

        // Management roles (Accountant/Manager/Admin) should not see any waiter data until they receive a handover
        if ($isManagementRole && ! $todayHandover) {
            $waiters = collect([]);
        }

        $expectedBreakdowns = [
            'cash_amount' => 0,
            'mpesa_amount' => 0,
            'mixx_amount' => 0,
            'halopesa_amount' => 0,
            'tigo_pesa_amount' => 0,
            'airtel_money_amount' => 0,
            'nmb_amount' => 0,
            'crdb_amount' => 0,
            'kcb_amount' => 0,
        ];

        foreach ($waiters as $data) {
            $orders = is_array($data) ? $data['orders'] : $data->orders;
            foreach ($orders as $order) {
                // Determine payments to iterate over
                if ($order->orderPayments && $order->orderPayments->count() > 0) {
                    $payments = $order->orderPayments;
                } else {
                    // mock orderPayment interface using order itself
                    if ($order->payment_status === 'paid' && $order->paid_amount > 0) {
                        $payments = [
                            (object) [
                                'payment_method' => $order->payment_method,
                                'mobile_money_number' => $order->mobile_money_number,
                                'amount' => $order->paid_amount,
                            ],
                        ];
                    } else {
                        $payments = [];
                    }
                }

                foreach ($payments as $payment) {
                    $amount = $payment->amount;
                    if ($payment->payment_method === 'cash') {
                        $expectedBreakdowns['cash_amount'] += $amount;
                    } else {
                        $provider = strtolower(trim($payment->mobile_money_number ?? ''));
                        if (str_contains($provider, 'm-pesa') || str_contains($provider, 'mpesa')) {
                            $expectedBreakdowns['mpesa_amount'] += $amount;
                        } elseif (str_contains($provider, 'mixx')) {
                            $expectedBreakdowns['mixx_amount'] += $amount;
                        } elseif (str_contains($provider, 'halo')) {
                            $expectedBreakdowns['halopesa_amount'] += $amount;
                        } elseif (str_contains($provider, 'tigo')) {
                            $expectedBreakdowns['tigo_pesa_amount'] += $amount;
                        } elseif (str_contains($provider, 'airtel')) {
                            $expectedBreakdowns['airtel_money_amount'] += $amount;
                        } elseif (str_contains($provider, 'nmb')) {
                            $expectedBreakdowns['nmb_amount'] += $amount;
                        } elseif (str_contains($provider, 'crdb')) {
                            $expectedBreakdowns['crdb_amount'] += $amount;
                        } elseif (str_contains($provider, 'kcb')) {
                            $expectedBreakdowns['kcb_amount'] += $amount;
                        } else {
                            // If somehow generic mobile money or bank without explicit provider
                            if (str_contains($payment->payment_method, 'bank') || $payment->payment_method === 'card') {
                                // Defaulting unspecified banks to NMB to prevent loss (could adjust as needed)
                                $expectedBreakdowns['nmb_amount'] += $amount;
                            } else {
                                // default generic M-PESA
                                $expectedBreakdowns['mpesa_amount'] += $amount;
                            }
                        }
                    }
                }
            }
        }

        // FETCH MASTER SHEET LOGIC DATA
        $ledger = DailyCashLedger::firstOrCreate(
            ['user_id' => $ownerId, 'ledger_date' => $date],
            [
                'accountant_id' => $currentStaff->id ?? null,
                'opening_cash' => $this->getPreviousClosingCash($ownerId, $date),
                'status' => 'open',
            ]
        );

        // SYNC: If ledger is still open, always re-fetch opening cash from the latest previous close
        if ($ledger->status === 'open') {
            $latestOpening = $this->getPreviousClosingCash($ownerId, $date);
            if ($ledger->opening_cash != $latestOpening) {
                $ledger->opening_cash = $latestOpening;
                $ledger->save();
            }
        }

        // Real-time sales profit for today (Aggregate from the waiters loop to ensure consistency)
        $stockProfit = collect($waiters)->sum('profit');

        // Fetch manually logged daily expenses
        $expenses = $ledger->expenses()->orderBy('created_at', 'desc')->get();

        // Fetch Petty Cash Issues (which contains Purchase Requests issued money)
        $pettyCashIssues = \App\Models\PettyCashIssue::where('user_id', $ownerId)
            ->whereDate('issue_date', $date)
            ->where('status', 'issued')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPettyCash = $pettyCashIssues->sum('amount');
        $totalExpensesCombined = $ledger->expenses()->sum('amount') + $totalPettyCash;

        // Financial calculations for the drawer closure
        $totalRevenueToday = $ledger->total_cash_received + $ledger->total_digital_received; // Consolidated collections (Cash + Withdrawn Digital)
        $totalBusinessValue = $ledger->opening_cash + $totalRevenueToday - $totalExpensesCombined;

        $expFromProfit = floatval($ledger->total_expenses_from_profit) + floatval($pettyCashIssues->where('fund_source', 'profit')->sum('amount'));
        $expFromCirculation = floatval($ledger->total_expenses_from_circulation) + floatval($pettyCashIssues->where('fund_source', 'circulation')->sum('amount'));

        // 1. Net Earnings (before pullout)
        $netEarnings = $stockProfit - $expFromProfit;

        // 2. Pragmatic Distribution Logic (for the boss):
        // Boss only pulls out positive profit.
        $finalProfit = max(0, $netEarnings);

        // 3. Practical Rollover (for the accountant bank):
        // Whatever is actually left in the vault after pullout.
        $rolloverFloat = max(0, $totalBusinessValue - $finalProfit);

        // 4. Money in Circulation (Isolated Today's Sales Portion):
        // This is the revenue portion used for restocking (COGS), isolated from opening float and profit.
        $moneyInCirculation = max(0, $totalRevenueToday - $stockProfit - $expFromCirculation);

        if ($ledger->status === 'open') {
            $ledger->update([
                'profit_generated' => $stockProfit,
                'expected_closing_cash' => $totalBusinessValue,
            ]);
        }

        $accountantLedger = $ledger;

        $staff = $this->getCurrentStaff();

        return view('bar.counter.reconciliation', compact(
            'waiters',
            'date',
            'accountant',
            'todayHandover',
            'expectedBreakdowns',
            'accountantLedger',
            'isAccountant',
            'isManager',
            'isManagementRole',
            'currentStaff',
            'ledger',
            'expenses',
            'pettyCashIssues',
            'totalPettyCash',
            'totalExpensesCombined',
            'stockProfit',
            'finalProfit',
            'moneyInCirculation',
            'totalBusinessValue',
            'rolloverFloat',
            'stockProfit',
            'staff',
            'bar_shift',
            'totalRevenueToday'
        ));
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

    /**
     * Verify a waiter's reconciliation
     */
    public function verifyReconciliation(Request $request, WaiterDailyReconciliation $reconciliation)
    {
        $currentStaff = $this->getCurrentStaff();
        $isAccountant = $currentStaff && strtolower($currentStaff->role->slug ?? '') === 'accountant';

        if (!$isAccountant && !$isSuperAdmin && !$this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission to verify reconciliations.'], 403);
        }

        $ownerId = $this->getOwnerId();

        // Verify reconciliation belongs to owner
        if ($reconciliation->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $reconciliation->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        // Send Waiter SMS notifying them their money was accepted and safely verified by Counter Staff
        try {
            $smsService = new \App\Services\HandoverSmsService;
            $smsService->sendWaiterVerificationSms($reconciliation);
        } catch (\Exception $e) {
            \Log::error('Failed to send Waiter Verification SMS: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation verified successfully.',
            'reconciliation' => $reconciliation,
        ]);
    }

    /**
     * Mark all orders as paid for a waiter after reconciliation verification
     */
    public function markAllOrdersPaid(Request $request)
    {
        $currentStaff = $this->getCurrentStaff();
        $isAccountant = $currentStaff && strtolower($currentStaff->role->slug ?? '') === 'accountant';

        if (!$isAccountant && !$isSuperAdmin && !$this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission to mark orders as paid.'], 403);
        }

        $ownerId = $this->getOwnerId();

        $validated = $request->validate([
            'waiter_id' => 'required|exists:staff,id',
            'date' => 'required|date',
            'submitted_amount' => 'nullable|numeric|min:0',
        ]);

        // Check if current user is accountant
        $currentStaff = $this->getCurrentStaff();
        $isAccountant = $currentStaff && strtolower($currentStaff->role->name ?? '') === 'accountant';

        // Verify waiter belongs to owner (unless accountant)
        $waiterQuery = Staff::where('id', $validated['waiter_id']);
        if (!$isAccountant && !$isSuperAdmin) {
            $waiterQuery->where('user_id', $ownerId);
        }
        $waiter = $waiterQuery->first();

        if (! $waiter) {
            return response()->json(['error' => 'Waiter not found'], 404);
        }

        $location = session('active_location');

        // Get all served bar orders (with drinks) for this waiter on this date that are not yet paid
        // Counter only marks bar orders as paid, not food orders
        $ordersQuery = BarOrder::query()
            ->where('waiter_id', $waiter->id)
            ->when($location && $location !== 'all', function ($q) use ($location) {
                $q->whereHas('table', function ($sq) use ($location) {
                    $sq->where('location', $location);
                });
            });

        // If not accountant, filter by owner
        if (!$isAccountant && !$isSuperAdmin) {
            $ordersQuery->where('user_id', $ownerId);
        }

        $orders = $ordersQuery
            ->whereDate('created_at', $validated['date'])
            ->where('status', 'served')
            ->where('payment_status', '!=', 'paid')
            ->whereHas('items') // Only orders with drinks (bar items)
            ->get();

        // Only error out if we have NO unpaid orders AND no submitted_amount provided
        if ($orders->isEmpty() && ! isset($validated['submitted_amount'])) {
            return response()->json([
                'success' => false,
                'error' => 'No unpaid served orders found for this waiter on this date.',
            ], 400);
        }

        // REQUIRE ACTIVE SHIFT
        $activeShift = $this->getCurrentShift();
        if (!$activeShift && !$isAccountant && !$isSuperAdmin) {
            return response()->json(['error' => 'Please open a shift before reconciling waiters.'], 403);
        }

        // Calculate expected amount (total bar sales for this waiter on this date)
        // This includes both paid and unpaid orders
        $expectedOrdersQuery = BarOrder::query()
            ->where('waiter_id', $waiter->id);

        // If not accountant, filter by owner
        if (!$isAccountant && !$isSuperAdmin) {
            $expectedOrdersQuery->where('user_id', $ownerId);
        }

        $expectedAmount = $expectedOrdersQuery
            ->when($activeShift, function ($q) use ($activeShift) {
                return $q->where('bar_shift_id', $activeShift->id);
            }, function ($q) use ($validated) {
                return $q->whereDate('created_at', $validated['date']);
            })
            ->where('status', 'served')
            ->whereHas('items') // Only bar orders
            ->with('items')
            ->get()
            ->sum(function ($order) {
                return $order->items->sum('total_price');
            });

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $updatedCount = 0;

            foreach ($orders as $order) {
                // Check if there are existing order payments (partial payments recorded by counter)
                $order->load('orderPayments');
                $alreadyRecorded = $order->orderPayments->sum('amount');

                if ($alreadyRecorded > 0 && $alreadyRecorded < $order->total_amount - 0.01) {
                    // Partial payment exists — mark as partial, preserve actual amount
                    $order->payment_status = 'partial';
                    $order->paid_amount = $alreadyRecorded;
                } else {
                    // Fully paid or no prior payment — mark as fully paid
                    $order->payment_status = 'paid';
                    $order->paid_amount = $order->total_amount;
                }

                $order->paid_by_waiter_id = $waiter->id; // Records which waiter row was reconciled
                // Default to cash if no method specified
                if (! $order->payment_method) {
                    $order->payment_method = 'cash';
                }
                if ($activeShift) {
                    $order->bar_shift_id = $activeShift->id;
                }
                $order->save();

                $totalAmount += $order->total_amount;
                $updatedCount++;
            }

            DB::commit();

            \Log::info('Bulk mark orders as paid', [
                'waiter_id' => $waiter->id,
                'date' => $validated['date'],
                'orders_count' => $updatedCount,
                'total_amount' => $totalAmount,
            ]);

            // Check if reconciliation already exists for this shift/date
            $existingReconciliation = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->where('waiter_id', $waiter->id)
                ->where('reconciliation_date', $validated['date'])
                ->when($activeShift, function ($q) use ($activeShift) {
                    return $q->where('bar_shift_id', $activeShift->id);
                })
                ->first();

            $previousSubmittedAmount = $existingReconciliation ? $existingReconciliation->submitted_amount : 0;

            // Use submitted_amount if provided, otherwise calculate from OrderPayments (recorded payments)
            if (isset($validated['submitted_amount'])) {
                // If there's already a submitted amount, add the new amount to it
                $newSubmittedAmount = $validated['submitted_amount'];
                $submittedAmount = $previousSubmittedAmount + $newSubmittedAmount;
            } else {
                // Calculate submitted amount from OrderPayments (what waiters have recorded)
                $allOrdersWithPaymentsQuery = BarOrder::query()
                    ->where('waiter_id', $waiter->id)
                    ->when($activeShift, function ($q) use ($activeShift) {
                        return $q->where('bar_shift_id', $activeShift->id);
                    }, function ($q) use ($validated) {
                        return $q->whereDate('created_at', $validated['date']);
                    })
                    ->where('status', 'served')
                    ->whereHas('items') // Only bar orders
                    ->whereHas('orderPayments') // Must have recorded payments
                    ->with(['items', 'orderPayments']);

                // If not accountant, filter by owner
                if (!$isAccountant && !$isSuperAdmin) {
                    $allOrdersWithPaymentsQuery->where('user_id', $ownerId);
                }

                $calculatedSubmittedAmount = $allOrdersWithPaymentsQuery
                    ->get()
                    ->sum(function ($order) {
                        // Sum recorded payments but cap at order total to avoid double counting
                        return min($order->orderPayments->sum('amount'), $order->total_amount);
                    });

                // Add to previous submitted amount if exists
                $submittedAmount = $previousSubmittedAmount + $calculatedSubmittedAmount;
            }

            // Calculate difference
            $difference = $submittedAmount - $expectedAmount;

            // Get bar orders for cash/mobile money calculation
            $barOrdersQuery = BarOrder::query()
                ->where('waiter_id', $waiter->id)
                ->when($activeShift, function ($q) use ($activeShift) {
                    return $q->where('bar_shift_id', $activeShift->id);
                }, function ($q) use ($validated) {
                    return $q->whereDate('created_at', $validated['date']);
                })
                ->where('status', 'served')
                ->whereHas('items') // Only bar orders
                ->with(['items', 'orderPayments']);

            if (!$isAccountant && !$isSuperAdmin) {
                $barOrdersQuery->where('user_id', $ownerId);
            }
            $barOrders = $barOrdersQuery->get();

            // Calculate recorded platform breakdown from orders
            $waiterPlatformTotals = [];
            foreach ($barOrders as $order) {
                if ($order->orderPayments->count() > 0) {
                    $orderPaymentsSum = $order->orderPayments->sum('amount');
                    // Cap the payments at the order total to avoid double counting during bulk reconciliation
                    $cappedTotal = min($orderPaymentsSum, $order->total_amount);

                    if ($order->orderPayments->count() === 1) {
                        $payment = $order->orderPayments->first();
                        $pKey = ($payment->payment_method === 'cash') ? 'cash' : strtolower(trim(str_replace(' ', '_', $payment->mobile_money_number ?? 'mobile')));
                        $waiterPlatformTotals[$pKey] = ($waiterPlatformTotals[$pKey] ?? 0) + $cappedTotal;
                    } else {
                        // If multiple payments (rare but possible), spread the capped total across methods
                        foreach ($order->orderPayments as $payment) {
                            $ratio = ($orderPaymentsSum > 0) ? ($payment->amount / $orderPaymentsSum) : 0;
                            $pKey = ($payment->payment_method === 'cash') ? 'cash' : strtolower(trim(str_replace(' ', '_', $payment->mobile_money_number ?? 'mobile')));
                            $waiterPlatformTotals[$pKey] = ($waiterPlatformTotals[$pKey] ?? 0) + ($cappedTotal * $ratio);
                        }
                    }
                } else {
                    $pKey = ($order->payment_method === 'cash') ? 'cash' : strtolower(trim(str_replace(' ', '_', $order->mobile_money_number ?? 'mobile')));
                    $waiterPlatformTotals[$pKey] = ($waiterPlatformTotals[$pKey] ?? 0) + $order->paid_amount;
                }
            }

            $breakdown = $request->input('breakdown', []);
            $submittedCash = $breakdown['cash'] ?? 0;
            $submittedDigital = 0;
            foreach ($breakdown as $platform => $amt) {
                if ($platform !== 'cash') {
                    $submittedDigital += $amt;
                }
            }

            $matchArray = [
                'user_id' => $ownerId,
                'waiter_id' => $waiter->id,
                'reconciliation_date' => $validated['date'],
                'reconciliation_type' => 'bar', // Bar-specific reconciliation
            ];
            if ($activeShift) {
                $matchArray['bar_shift_id'] = $activeShift->id;
            }

            // Create or update bar-specific reconciliation record
            $reconciliation = \App\Models\WaiterDailyReconciliation::updateOrCreate(
                $matchArray,
                [
                    'expected_amount' => $expectedAmount,
                    'submitted_amount' => $submittedAmount,
                    'cash_collected' => $submittedCash,
                    'mobile_money_collected' => $submittedDigital,
                    'difference' => $difference,
                    'status' => abs($difference) < 0.01 ? 'reconciled' : 'partial',
                    'submitted_at' => now(),
                    'notes' => json_encode([
                        'submitted_breakdown' => $breakdown,
                        'recorded_breakdown' => $waiterPlatformTotals,
                        'waiter_note' => $request->input('notes', ''),
                    ]),
                    'bar_shift_id' => $activeShift ? $activeShift->id : null,
                ]
            );

            // Create notification for waiter
            try {
                WaiterNotification::create([
                    'waiter_id' => $waiter->id,
                    'type' => 'payment_recorded',
                    'title' => 'Bar Orders Marked as Paid',
                    'message' => "Counter has marked {$updatedCount} bar order(s) as paid for ".\Carbon\Carbon::parse($validated['date'])->format('M d, Y').'. Total amount: TSh '.number_format($totalAmount, 0),
                    'data' => [
                        'date' => $validated['date'],
                        'orders_count' => $updatedCount,
                        'total_amount' => $totalAmount,
                        'order_type' => 'bar',
                        'marked_by' => 'counter',
                    ],
                ]);

                // Send SMS to Waiter informing them their shift is reconciled by Counter
                try {
                    $smsService = new \App\Services\HandoverSmsService;
                    $smsService->sendWaiterReconciliationSubmissionSms($reconciliation);
                } catch (\Exception $e) {
                    \Log::error('Failed to send Waiter Bar Reconciliation SMS: '.$e->getMessage());
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create notification', [
                    'waiter_id' => $waiter->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $message = "Successfully marked {$updatedCount} order(s) as paid.";
            if ($submittedAmount < $expectedAmount) {
                $message .= ' Submitted amount: TSh '.number_format($submittedAmount, 0).' (Expected: TSh '.number_format($expectedAmount, 0).')';
            } else {
                $message .= ' Total: TSh '.number_format($totalAmount, 0);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'orders_count' => $updatedCount,
                'total_amount' => $totalAmount,
                'submitted_amount' => $submittedAmount,
                'expected_amount' => $expectedAmount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to mark all orders as paid', [
                'waiter_id' => $waiter->id,
                'date' => $validated['date'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to mark orders as paid: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get waiter's orders for a specific date (AJAX)
     */
    public function getWaiterOrders(Request $request, Staff $waiter)
    {
        $currentStaff = $this->getCurrentStaff();
        $isAccountant = $currentStaff && strtolower($currentStaff->role->slug ?? '') === 'accountant';

        if (!$isAccountant && !$isSuperAdmin && !$this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));

        // Check if current user is accountant
        $currentStaff = $this->getCurrentStaff();
        $isAccountant = $currentStaff && strtolower($currentStaff->role->name ?? '') === 'accountant';

        // Verify waiter belongs to owner (unless accountant)
        if (!$isAccountant && !$isSuperAdmin && $waiter->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Find if there is an active shift or a specific reconciliation shift for this waiter/date
        // We look for a reconciliation first to "freeze" the view if it's already reconciled.
        $reconciliation = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
            ->where('waiter_id', $waiter->id)
            ->where('reconciliation_date', $date)
            ->where('reconciliation_type', 'bar')
            ->first();

        // Try to find if there is an active shift
        $bar_shift = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->first();

        $targetShiftId = $reconciliation ? $reconciliation->bar_shift_id : ($bar_shift ? $bar_shift->id : null);

        // Return all orders (both bar and food) for counter reconciliation view
        $ordersQuery = BarOrder::query()
            ->where('waiter_id', $waiter->id);

        // If not accountant, filter by owner
        if (!$isAccountant && !$isSuperAdmin) {
            $ordersQuery->where('user_id', $ownerId);
        }

        $orders = $ordersQuery
            ->when($targetShiftId, function ($q) use ($targetShiftId) {
                return $q->where('bar_shift_id', $targetShiftId);
            }, function ($q) use ($date) {
                return $q->whereDate('created_at', $date);
            })
            ->where('status', '!=', 'cancelled')
            ->with(['items.productVariant.product', 'kitchenOrderItems', 'table', 'orderPayments', 'paidByWaiter'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Store financial handover to accountant
     */
    public function storeHandover(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $staff = $this->getCurrentStaff();
        $date = $request->input('date', date('Y-m-d'));

        $request->validate([
            'cash_amount' => 'required|numeric|min:0',
            'mpesa_amount' => 'nullable|numeric|min:0',
            'nmb_amount' => 'nullable|numeric|min:0',
            'kcb_amount' => 'nullable|numeric|min:0',
            'crdb_amount' => 'nullable|numeric|min:0',
            'mixx_amount' => 'nullable|numeric|min:0',
            'tigo_pesa_amount' => 'nullable|numeric|min:0',
            'airtel_money_amount' => 'nullable|numeric|min:0',
            'halopesa_amount' => 'nullable|numeric|min:0',
        ]);

        // Calculate total amount
        $breakdown = [
            'cash' => $request->input('cash_amount', 0),
            'mpesa' => $request->input('mpesa_amount', 0),
            'nmb' => $request->input('nmb_amount', 0),
            'kcb' => $request->input('kcb_amount', 0),
            'crdb' => $request->input('crdb_amount', 0),
            'mixx' => $request->input('mixx_amount', 0),
            'tigo_pesa' => $request->input('tigo_pesa_amount', 0),
            'airtel_money' => $request->input('airtel_money_amount', 0),
            'halopesa' => $request->input('halopesa_amount', 0),
        ];

        $totalAmount = array_sum($breakdown);

        // Find an active shift before validating
        $activeShift = null;
        if ($staff) {
            $activeShift = \App\Models\BarShift::where('staff_id', $staff->id)
                ->where('status', 'open')
                ->first();
        }

        // Check if already exists for this specific shift (Preferred) or date
        $isSuperAdmin = $this->isSuperAdminRole();
        $existingQuery = FinancialHandover::where('user_id', $ownerId)
            ->when($staff, function($q) use ($staff) {
                return $q->where('accountant_id', $staff->id);
            })
            ->where('handover_type', 'staff_to_accountant');

        if ($activeShift) {
            $existingQuery->where('bar_shift_id', $activeShift->id);
        } else {
            $existingQuery->whereDate('handover_date', $date);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            return back()->with('error', $activeShift ? 'Handover for this active shift has already been submitted.' : 'Handover for this date already exists.');
        }

        // Find an active accountant for the owner to be the recipient
        $accountant = Staff::where('user_id', $ownerId)
            ->whereHas('role', function ($q) {
                $q->where('slug', 'accountant');
            })
            ->where('is_active', true)
            ->first();

        // Find an active shift before creating handover
        $activeShift = \App\Models\BarShift::where('staff_id', $staff->id)
            ->where('status', 'open')
            ->first();

        $handover = FinancialHandover::create([
            'user_id' => $ownerId,
            'accountant_id' => $staff->id,
            'handover_type' => 'staff_to_accountant',
            'recipient_id' => $accountant ? $accountant->id : null,
            'department' => 'bar',
            'amount' => $totalAmount,
            'payment_breakdown' => $breakdown,
            'handover_date' => $date,
            'status' => 'pending',
            'notes' => $request->notes,
            'bar_shift_id' => $activeShift ? $activeShift->id : null,
        ]);

        // AUTOMATICALLY CLOSE ACTIVE SHIFT UPON HANDOVER SUBMISSION
        if ($activeShift) {
            // Calculate sales for this shift to close it accurately
            $shiftOrders = \App\Models\BarOrder::where('bar_shift_id', $activeShift->id)
                ->where('payment_status', 'paid')
                ->get();

            $cashSales = 0;
            $digitalSales = 0;
            foreach ($shiftOrders as $order) {
                if ($order->payment_method === 'cash') {
                    $cashSales += $order->total_amount;
                } else {
                    $digitalSales += $order->total_amount;
                }
            }

            // Close the shift
            $activeShift->update([
                'closed_at' => now(),
                'status' => 'closed',
                'expected_cash' => $cashSales,
                'actual_cash' => $breakdown['cash'] ?? 0, // Follow lowercase key pattern
                'digital_revenue' => $digitalSales,
                'notes' => ($activeShift->notes ? $activeShift->notes.' | ' : '').'Shift closed via handover submission.',
            ]);
        }

        // No longer auto-reconciling here.
        // The Counter Staff MUST explicitly reconcile each waiter in the table
        // BEFORE submitting the final handover. This ensures all shortages,
        // surpluses, and paid/unpaid statuses are accurately recorded and
        // not overwritten by automatic order matching.

        // Send SMS notification to accountant
        try {
            $smsService = new \App\Services\HandoverSmsService;
            $smsService->sendHandoverSubmissionSms($handover, $ownerId);
        } catch (\Exception $e) {
            \Log::error('SMS notification failed for handover: '.$e->getMessage());
        }

        return back()->with('success', 'Handover mapped and sent to Accountant successful! Awaiting confirmation.');
    }

    /**
     * Reset a reconciliation record (Reopen the staff row)
     */
    public function resetReconciliation(WaiterDailyReconciliation $reconciliation)
    {
        if (! $this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Only allow resetting if not yet verified by accountant
        if ($reconciliation->status === 'verified') {
            return response()->json(['error' => 'Cannot reset a verified reconciliation.'], 400);
        }

        try {
            DB::beginTransaction();

            // Delete the reconciliation record
            $reconciliation->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation reset successfully. Row is now reopened.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Failed to reset reconciliation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset the entire handover (Cancel and Reopen the day)
     */
    public function resetHandover(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $date = $request->input('date');

        DB::beginTransaction();
        try {
            // 1. Delete the handover
            $deleted = FinancialHandover::where('user_id', $ownerId)
                ->whereDate('handover_date', $date)
                ->where('status', 'pending') // Only pending handovers can be reset
                ->delete();

            if ($deleted) {
                // 2. Revert staff records from 'verified' back to 'reconciled'
                // so they can be individually reset/adjusted.
                WaiterDailyReconciliation::where('user_id', $ownerId)
                    ->where('reconciliation_date', $date)
                    ->where('status', 'verified')
                    ->update(['status' => 'reconciled']);
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display food reconciliation page (Accountant View)
     */
    public function foodReconciliation(Request $request)
    {
        $currentStaff = $this->getCurrentStaff();
        $isSuperAdmin = $this->isSuperAdminRole();

        // Allow Super Admin, Accountant, or anyone with permissions
        $roleSlug = $currentStaff ? strtolower(trim($currentStaff->role->slug ?? '')) : '';
        if (!$isSuperAdmin && $roleSlug !== 'accountant' && !$this->hasPermission('bar_orders', 'view')) {
            abort(403, 'Permission denied.');
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));

        // Calculate Opening Cash (Bf) - Money left in drawer from previous days
        $previousHandover = \App\Models\FinancialHandover::where('department', 'food')
            ->where('handover_type', 'staff_to_accountant')
            ->whereDate('handover_date', '<', $date)
            ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->orderBy('handover_date', 'desc')
            ->first();

        $openingCash = 0;
        if ($previousHandover) {
            // Check if this profit was ever submitted to the boss
            $wasSubmittedToBoss = \App\Models\FinancialHandover::where('department', 'food')
                ->where('handover_type', 'accountant_to_owner')
                ->whereDate('handover_date', $previousHandover->handover_date)
                ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                    return $q->where('user_id', $ownerId);
                })
                ->exists();

            if (! $wasSubmittedToBoss) {
                // If not submitted to boss, the net physical cash from that day is our opening for today
                $prevBreakdown = $previousHandover->payment_breakdown ?? [];
                $prevExpensesTotal = collect($prevBreakdown['attributed_expenses'] ?? [])->sum('amount');
                $openingCash = $previousHandover->amount - $prevExpensesTotal;
            }
        }

        // Get location from session (branch switcher)
        $location = session('active_location');

        // Get Waiters (or anyone who has food orders today)
        $waitersQuery = Staff::where('is_active', true)
            ->where(function ($query) use ($date, $location) {
                // Role check
                $query->whereHas('role', function ($q) {
                    $q->where('slug', 'waiter');
                })
                    // OR orders today check
                    ->orWhereHas('orders', function ($q) use ($date, $location) {
                        $q->whereDate('created_at', $date)
                            ->whereHas('kitchenOrderItems', function ($sq) {
                                $sq->where('status', '!=', 'cancelled');
                            });
                        if ($location && $location !== 'all') {
                            $q->whereHas('table', function ($sq) use ($location) {
                                $sq->where('location', $location);
                            });
                        }
                    })
                    // OR daily reconciliations check
                    ->orWhereHas('dailyReconciliations', function ($q) use ($date) {
                        $q->where('reconciliation_date', $date)
                            ->where('reconciliation_type', 'food');
                    });
            })
            ->when($location && $location !== 'all', function ($q) use ($location) {
                $q->where('location_branch', $location);
            });

        // If not super admin, filter by owner
        if (!$isSuperAdmin) {
            $waitersQuery->where('user_id', $ownerId);
        }

        $waiters = $waitersQuery
            ->with(['role', 'dailyReconciliations' => function ($q) use ($date) {
                $q->where('reconciliation_date', $date)
                    ->where('reconciliation_type', 'food');
            }])
            ->get()
            ->map(function ($waiter) use ($date, $location) {
                // Get orders for this waiter and date
                $orders = BarOrder::where('waiter_id', $waiter->id)
                    ->whereDate('created_at', $date)
                    ->when($location && $location !== 'all', function ($q) use ($location) {
                        $q->whereHas('table', function ($sq) use ($location) {
                            $sq->where('location', $location);
                        });
                    })
                    ->whereHas('kitchenOrderItems', function ($sq) {
                        $sq->where('status', '!=', 'cancelled');
                    })
                    ->with(['items', 'kitchenOrderItems', 'orderPayments'])
                    ->where('status', '!=', 'cancelled')
                    ->get();

                $foodSales = 0;
                $cashCollected = 0;
                $digitalCollected = 0;
                $foodOrdersCount = 0;

                foreach ($orders as $order) {
                    $orderBar = $order->items->sum('total_price');
                    $orderFood = $order->kitchenOrderItems
                        ->where('status', '!=', 'cancelled')
                        ->sum(function ($kItem) {
                            return ((float) ($kItem->unit_price ?? 0)) * ((int) ($kItem->quantity ?? 0));
                        });
                    $orderTotal = $orderBar + $orderFood;

                    if ($orderFood > 0) {
                        $foodSales += $orderFood;
                        $foodOrdersCount++;

                        if ($orderTotal > 0) {
                            $share = $orderFood / $orderTotal;
                            $cashCollected += $order->orderPayments->where('payment_method', 'cash')->sum('amount') * $share;
                            $digitalCollected += $order->orderPayments->where('payment_method', '!=', 'cash')->sum('amount') * $share;
                        }
                    }
                }

                $reconciliation = $waiter->dailyReconciliations->first();
                $submittedAmount = $reconciliation ? $reconciliation->submitted_amount : 0;
                
                // Fix: If submitted_amount is 0 but there is a negative difference (shortage attributed by chef),
                // calculate the virtual submitted amount as Sales + Difference (e.g., 10k - 1k = 9k)
                if ($submittedAmount <= 0 && $reconciliation && $reconciliation->difference < 0) {
                    $submittedAmount = (float)$foodSales + $reconciliation->difference;
                }

                $recordedAmount = $cashCollected + $digitalCollected;

                // Difference
                $difference = ($submittedAmount > 0 || $reconciliation)
                    ? ($submittedAmount - (float) $foodSales)
                    : ($recordedAmount - (float) $foodSales);

                return [
                    'waiter' => $waiter,
                    'food_sales' => $foodSales,
                    'food_orders_count' => $foodOrdersCount,
                    'cash_collected' => $reconciliation ? $reconciliation->cash_collected : $cashCollected,
                    'mobile_money_collected' => $reconciliation ? $reconciliation->mobile_money_collected : $digitalCollected,
                    'recorded_amount' => $recordedAmount,
                    'submitted_amount' => $submittedAmount,
                    'difference' => $difference,
                    'status' => $reconciliation ? $reconciliation->status : 'pending',
                    'reconciliation' => $reconciliation,
                    'platform_totals' => ['cash' => $cashCollected, 'mobile' => $digitalCollected],
                ];
            });

        // Get Chef's Handover
        $chefHandover = FinancialHandover::where('department', 'food')
            ->whereDate('handover_date', $date)
            ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->first();

        // When today's food handover is already verified by accountant, lock the page
        // into "waiting for next shift day" mode to avoid showing already-closed data.
        $isToday = $date === now()->format('Y-m-d');
        $foodShiftClosedForToday = $isToday && $chefHandover && $chefHandover->status === 'verified';

        if ($foodShiftClosedForToday) {
            $waiters = collect([]);
        }

        // Ensure a Chef Role and Staff exists (auto-seeding per request)
        $chefRole = \App\Models\Role::firstOrCreate(
            ['name' => 'Chef', 'user_id' => $ownerId],
            ['slug' => 'chef', 'description' => 'Kitchen Chef']
        );
        $dummyChef = \App\Models\Staff::where('email', "chef-{$ownerId}@mauzolink.com")
            ->orWhere(function($q) use ($chefRole, $ownerId) {
                $q->where('role_id', $chefRole->id)->where('user_id', $ownerId);
            })
            ->first();

        if (!$dummyChef) {
            $dummyChef = \App\Models\Staff::create([
                'role_id' => $chefRole->id,
                'user_id' => $ownerId,
                'full_name' => 'John Chef',
                'staff_id' => \App\Models\Staff::generateStaffId($ownerId),
                'phone_number' => '0777777777',
                'email' => "chef-{$ownerId}@mauzolink.com",
                'is_active' => true,
                'salary_paid' => 0.00,
                'password' => bcrypt('password'),
            ]);
        }

        // Get ONLY Chef Staff as requested
        $chefs = Staff::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->where('slug', 'chef')->orWhere('name', 'LIKE', '%chef%');
            })
            ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->get();
        // Keep $chef for backward compatibility of selected value
        $chef = $chefs->first();

        $totalFoodSalesToday = $foodShiftClosedForToday ? 0 : $waiters->sum('food_sales');

        // Ledger check
        $ledger = DailyCashLedger::when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->whereDate('ledger_date', $date)
            ->first();

        return view('bar.counter.reconciliation-food', compact(
            'waiters',
            'date',
            'currentStaff',
            'chefHandover',
            'totalFoodSalesToday',
            'foodShiftClosedForToday',
            'chefs',
            'chef',
            'ledger'
        ));
    }

    /**
     * Get orders for a waiter during food reconciliation
     */
    public function getWaiterFoodOrders(Request $request, Staff $waiter)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $ownerId = $this->getOwnerId();

        $isSuperAdmin = $this->isSuperAdminRole();

        $orders = BarOrder::where('waiter_id', $waiter->id)
            ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                return $q->where('user_id', $ownerId);
            })
            ->whereDate('created_at', $date)
            ->whereHas('kitchenOrderItems', function ($sq) {
                $sq->where('status', '!=', 'cancelled');
            })
            ->with(['items.productVariant.product', 'kitchenOrderItems.extras', 'table', 'orderPayments', 'paidByWaiter'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                $activeKitchenItems = $order->kitchenOrderItems
                    ->where('status', '!=', 'cancelled')
                    ->values();

                $order->setRelation('kitchenOrderItems', $activeKitchenItems);

                return $order;
            })
            ->filter(function ($order) {
                return $order->kitchenOrderItems->isNotEmpty();
            })
            ->values();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Mark all food orders as paid for a waiter
     */
    public function markAllFoodPaid(Request $request)
    {
        $validated = $request->validate([
            'waiter_id' => 'required|exists:staff,id',
            'date' => 'required|date',
            'submitted_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $ownerId = $this->getOwnerId();
        $date = $validated['date'];
        $waiter = Staff::findOrFail($validated['waiter_id']);

        $isSuperAdmin = $this->isSuperAdminRole();

        DB::beginTransaction();
        try {
            // Logic to calculate food sales (repeated from dashboard for security)
            $orders = BarOrder::where('waiter_id', $waiter->id)
                ->when(!$isSuperAdmin, function($q) use ($ownerId) {
                    return $q->where('user_id', $ownerId);
                })
                ->whereDate('created_at', $date)
                ->whereHas('kitchenOrderItems', function ($sq) {
                    $sq->where('status', '!=', 'cancelled');
                })
                ->with(['items', 'kitchenOrderItems', 'orderPayments'])
                ->where('status', '!=', 'cancelled')
                ->get();

            $foodSales = 0;
            $cashCollected = 0;
            $digitalCollected = 0;

            foreach ($orders as $order) {
                $orderBar = $order->items->sum('total_price');
                $orderFood = $order->kitchenOrderItems
                    ->where('status', '!=', 'cancelled')
                    ->sum(function ($kItem) {
                        return ((float) ($kItem->unit_price ?? 0)) * ((int) ($kItem->quantity ?? 0));
                    });
                $orderTotal = $orderBar + $orderFood;

                if ($orderFood > 0) {
                    $foodSales += $orderFood;
                    if ($orderTotal > 0) {
                        $share = $orderFood / $orderTotal;
                        $cashCollected += $order->orderPayments->where('payment_method', 'cash')->sum('amount') * $share;
                        $digitalCollected += $order->orderPayments->where('payment_method', '!=', 'cash')->sum('amount') * $share;
                    }
                }
            }

            $reconciliation = WaiterDailyReconciliation::updateOrCreate(
                [
                    'user_id' => $ownerId,
                    'waiter_id' => $waiter->id,
                    'reconciliation_date' => $date,
                    'reconciliation_type' => 'food',
                ],
                [
                    'total_sales' => $foodSales,
                    'cash_collected' => $cashCollected,
                    'mobile_money_collected' => $digitalCollected,
                    'expected_amount' => $foodSales,
                    'submitted_amount' => $validated['submitted_amount'],
                    'difference' => $validated['submitted_amount'] - $foodSales,
                    'status' => 'reconciled',
                    'submitted_at' => now(),
                    'notes' => $validated['notes'],
                ]
            );

            // Send Waiter SMS notifying them their food money was reconciled
            try {
                $smsService = new \App\Services\HandoverSmsService;
                $smsService->sendWaiterReconciliationSubmissionSms($reconciliation);
            } catch (\Exception $e) {
                \Log::error('Failed to send Waiter Food Reconciliation SMS: '.$e->getMessage());
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Settle a staff shortage (debt)
     */
    public function settleShortage(Request $request)
    {
        $validated = $request->validate([
            'reconciliation_id' => 'required|exists:waiter_daily_reconciliations,id',
            'amount' => 'required|numeric|min:0',
            'channel' => 'nullable|string|in:cash,mobile_money,bank_transfer,pos_card,salary_deduction',
            'notes' => 'nullable|string',
        ]);

        $reconciliation = \App\Models\WaiterDailyReconciliation::findOrFail($validated['reconciliation_id']);
        $ownerId = $this->getOwnerId();
        $channel = $request->get('channel', 'cash');

        DB::beginTransaction();
        try {
            $amount = floatval($validated['amount']);

            // 1. Update the reconciliation record (Debt Settlement)
            $reconciliation->submitted_amount += $amount;

            // Track specific payment channel for digital vs cash vs salary deduction
            if ($channel === 'cash') {
                $reconciliation->cash_collected += $amount;
            } elseif ($channel === 'mobile_money') {
                $reconciliation->mobile_money_collected += $amount;
            } elseif ($channel === 'bank_transfer') {
                $reconciliation->bank_collected += $amount;
            } elseif ($channel === 'pos_card') {
                $reconciliation->card_collected += $amount;
            }
            // salary_deduction counts towards debt recovery but is NOT physical cash

            $reconciliation->difference = $reconciliation->submitted_amount - $reconciliation->expected_amount;

            if (abs($reconciliation->difference) < 0.1) {
                $reconciliation->status = 'reconciled';
            }

            // Track detailed settlement history in JSON
            $notesData = json_decode($reconciliation->notes, true) ?: [];
            if (!is_array($notesData)) $notesData = ['legacy_notes' => $reconciliation->notes];

            $notesData['settlements'][] = [
                'id' => uniqid('set_'),
                'amount' => $amount,
                'channel' => $channel,
                'date' => now()->toDateTimeString(),
                'recorded_by' => auth()->user()->name ?? 'Accountant',
                'staff_note' => $validated['notes'] ?? 'Shortage settled',
            ];
            $reconciliation->notes = json_encode($notesData);
            $reconciliation->save();

            // 2. Update Financial records (SKIP IF salary deduction)
            if ($channel !== 'salary_deduction') {
                $ledger = DailyCashLedger::firstOrCreate(
                    ['user_id' => $ownerId, 'ledger_date' => now()->toDateString()],
                    ['status' => 'open']
                );

                if ($channel === 'cash') {
                    $ledger->total_cash_received += $amount;
                } else {
                    $ledger->total_digital_received += $amount;
                }

                $totalExpenses = $ledger->expenses()->sum('amount');
                $ledger->expected_closing_cash = floatval($ledger->opening_cash) + floatval($ledger->total_cash_received) + floatval($ledger->total_digital_received) - $totalExpenses;
                $ledger->save();

                // Financial Handover update
                $handover = FinancialHandover::where('user_id', $ownerId)
                    ->whereDate('handover_date', now()->toDateString())
                    ->where('department', $reconciliation->reconciliation_type === 'food' ? 'food' : 'bar')
                    ->where('handover_type', 'staff_to_accountant')
                    ->first();

                if (!$handover) {
                    $handover = FinancialHandover::create([
                        'user_id' => $ownerId,
                        'handover_date' => now()->toDateString(),
                        'department' => $reconciliation->reconciliation_type === 'food' ? 'food' : 'bar',
                        'handover_type' => 'staff_to_accountant',
                        'amount' => $amount,
                        'status' => 'verified',
                        'payment_method' => 'mixed',
                        'payment_breakdown' => [
                            ($channel === 'cash' ? 'cash' : 'digital') => $amount,
                            'shortage_payment' => $amount
                        ],
                        'notes' => 'Generated by shortage settlement'
                    ]);
                } else {
                    $handover->amount += $amount;
                    $pBreakdown = $handover->payment_breakdown ?: [];
                    if (is_array($pBreakdown)) {
                        $pKey = ($channel === 'cash') ? 'cash' : 'digital';
                        $pBreakdown[$pKey] = ($pBreakdown[$pKey] ?? 0) + $amount;
                        $pBreakdown['shortage_payment'] = ($pBreakdown['shortage_payment'] ?? 0) + $amount;
                        $handover->payment_breakdown = $pBreakdown;
                        $handover->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shortage settled successfully! ' . ($channel === 'salary_deduction' ? 'Applied as salary deduction.' : 'Amount added to accounts.'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Shortage Settlement Error: '.$e->getMessage());
            return response()->json(['error' => 'An internal error occurred: '.$e->getMessage()], 500);
        }
    }

    /**
     * Reverse a specific shortage settlement entry
     */
    public function undoSettleShortage(Request $request)
    {
        $validated = $request->validate([
            'reconciliation_id' => 'required|exists:waiter_daily_reconciliations,id',
            'settlement_id' => 'required|string',
        ]);

        $reconciliation = \App\Models\WaiterDailyReconciliation::findOrFail($validated['reconciliation_id']);
        $ownerId = $this->getOwnerId();

        $notesData = json_decode($reconciliation->notes, true) ?: [];
        $settlements = $notesData['settlements'] ?? [];
        
        $targetIndex = -1;
        $targetSettlement = null;
        foreach ($settlements as $idx => $s) {
            if (isset($s['id']) && $s['id'] === $validated['settlement_id']) {
                $targetIndex = $idx;
                $targetSettlement = $s;
                break;
            }
        }

        if (!$targetSettlement) {
            return response()->json(['error' => 'Settlement record not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $amount = floatval($targetSettlement['amount']);
            $channel = $targetSettlement['channel'] ?? 'cash';

            // 1. Revert reconciliation debt tracking
            $reconciliation->submitted_amount -= $amount;
            
            if ($channel === 'cash') {
                $reconciliation->cash_collected -= $amount;
            } elseif ($channel === 'mobile_money') {
                $reconciliation->mobile_money_collected -= $amount;
            } elseif ($channel === 'bank_transfer') {
                $reconciliation->bank_collected -= $amount;
            } elseif ($channel === 'pos_card') {
                $reconciliation->card_collected -= $amount;
            }

            $reconciliation->difference = $reconciliation->submitted_amount - $reconciliation->expected_amount;
            
            if (abs($reconciliation->difference) > 0.1) {
                $reconciliation->status = 'partial';
            }

            // Remove from history
            array_splice($settlements, $targetIndex, 1);
            $notesData['settlements'] = $settlements;
            $reconciliation->notes = json_encode($notesData);
            $reconciliation->save();

            // 2. Revert Financial totals (IF NOT salary_deduction)
            if ($channel !== 'salary_deduction') {
                $settledDate = isset($targetSettlement['date']) ? \Carbon\Carbon::parse($targetSettlement['date'])->toDateString() : $reconciliation->reconciliation_date;
                
                $ledger = DailyCashLedger::where('user_id', $ownerId)
                    ->where('ledger_date', $settledDate)
                    ->first();

                if ($ledger) {
                    if ($channel === 'cash') {
                        $ledger->total_cash_received -= $amount;
                    } else {
                        $ledger->total_digital_received -= $amount;
                    }
                    $totalExpenses = $ledger->expenses()->sum('amount');
                    $ledger->expected_closing_cash = floatval($ledger->opening_cash) + floatval($ledger->total_cash_received) + floatval($ledger->total_digital_received) - $totalExpenses;
                    $ledger->save();
                }

                $handover = FinancialHandover::where('user_id', $ownerId)
                    ->whereDate('handover_date', $settledDate)
                    ->where('department', $reconciliation->reconciliation_type === 'food' ? 'food' : 'bar')
                    ->where('handover_type', 'staff_to_accountant')
                    ->first();

                if ($handover) {
                    $handover->amount -= $amount;
                    $pBreakdown = $handover->payment_breakdown;
                    if (is_array($pBreakdown)) {
                        $pKey = ($targetSettlement['channel'] === 'cash') ? 'cash' : 'digital';
                        $pBreakdown[$pKey] = max(0, ($pBreakdown[$pKey] ?? 0) - $targetSettlement['amount']);
                        $pBreakdown['shortage_payment'] = max(0, ($pBreakdown['shortage_payment'] ?? 0) - $targetSettlement['amount']);
                        $handover->payment_breakdown = $pBreakdown;
                        $handover->save();
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Settlement reversed successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Undo failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View a detailed summary report for a specific shift
     */
    public function shiftReport(\App\Models\BarShift $shift)
    {
        $ownerId = $this->getOwnerId();
        
        // Security: Ensure the shift belongs to this business
        if ($shift->user_id !== $ownerId) {
            abort(403, 'Unauthorized access to this shift report.');
        }

        // Fetch handover details
        $handover = FinancialHandover::where('bar_shift_id', $shift->id)
            ->where('handover_type', 'staff_to_accountant')
            ->first();

        // Fetch waiter reconciliations for this shift
        $reconciliations = WaiterDailyReconciliation::where('bar_shift_id', $shift->id)
            ->with(['waiter.role'])
            ->get();

        // Calculate totals from reconciliations
        $totalExpected = $reconciliations->sum('expected_amount');
        $totalSubmitted = $reconciliations->sum('submitted_amount');
        $totalDifference = $reconciliations->sum('difference');

        return view('bar.counter.shift_report', compact(
            'shift',
            'handover',
            'reconciliations',
            'totalExpected',
            'totalSubmitted',
            'totalDifference'
        ));
    }
}
