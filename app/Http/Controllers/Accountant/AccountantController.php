<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\BarOrder;
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;
use App\Models\OrderPayment;
use App\Models\PettyCashIssue;
use App\Models\FinancialHandover;
use App\Models\DailyCashLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AccountantController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Accountant Dashboard - Financial Overview
     */
    public function dashboard(Request $request)
    {
        // Check permission
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to access accountant dashboard.');
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $location = session('active_location');

        // Ensure today's ledger exists for chart accuracy
        $today = date('Y-m-d');
        \App\Models\DailyCashLedger::firstOrCreate(
            ['user_id' => $ownerId, 'ledger_date' => $today],
            [
                'opening_cash' => \App\Models\DailyCashLedger::where('user_id', $ownerId)
                    ->where('ledger_date', '<', $today)
                    ->where('status', 'closed')
                    ->orderBy('ledger_date', 'desc')
                    ->value('carried_forward') ?? 0,
                'status' => 'open',
            ]
        );

        // Helper to apply common filters (owner + location)
        $applyFilters = function($query) use ($ownerId, $location) {
            $isAdmin = auth()->check() && auth()->user()->role === 'admin';
            
            if (!$isAdmin) {
                $query->where('user_id', $ownerId);
            }
            if ($location && !$isAdmin) {
                $query->where(function($q) use ($location) {
                    $q->whereHas('table', function($sq) use ($location) {
                        $sq->where('location', $location);
                    })->orWhereHas('waiter', function($sq) use ($location) {
                        $sq->where('location_branch', $location);
                    });
                });
            }
            return $query;
        };


        // Today's Financial Summary - Include ALL valid orders (Sales Performance)
        $todayOrders = $applyFilters(BarOrder::query())
            ->whereDate('created_at', $date)
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'kitchenOrderItems', 'orderPayments'])
            ->get();

        // Total Potential Revenue (what was successfully mapped via handovers)
        $isAdmin = auth()->check() && auth()->user()->isAdmin();

        $todayLedgerQuery = \App\Models\DailyCashLedger::where('ledger_date', $date);
        if (!$isAdmin) {
            $todayLedgerQuery->where('user_id', $ownerId);
        }
        $todayLedger = $todayLedgerQuery->first();

        // Bar Handovers (Verified Money)
        $barHandoversQuery = \App\Models\FinancialHandover::where('department', 'bar')
            ->whereDate('handover_date', $date)
            ->where('status', 'verified');
        if (!$isAdmin) {
            $barHandoversQuery->where('user_id', $ownerId);
        }
        $barHandovers = $barHandoversQuery->get();

        $todayBarVerified = 0;
        $todayBarCash = 0;
        $todayBarMobile = 0;
        foreach ($barHandovers as $h) {
            $amt = (float)$h->amount;
            $todayBarVerified += $amt;
            
            $breakdown = $h->payment_breakdown ?? [];
            if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
            if (is_array($breakdown) && !empty($breakdown)) {
                foreach ($breakdown as $key => $val) {
                    $itemAmt = floatval($val);
                    if ($key === 'shortage_payment' || $key === 'total') continue;
                    if ($key === 'cash' || str_contains($key, 'cash_')) {
                        $todayBarCash += $itemAmt;
                    } else {
                        $todayBarMobile += $itemAmt;
                    }
                }
            } else {
                $todayBarCash += $amt;
            }
        }

        // Food Handovers (Verified Money)
        $foodHandoversQuery = \App\Models\FinancialHandover::where('department', 'food')
            ->whereDate('handover_date', $date)
            ->where('status', 'verified');
        if (!$isAdmin) {
            $foodHandoversQuery->where('user_id', $ownerId);
        }
        $foodHandovers = $foodHandoversQuery->get();

        $todayFoodVerified = 0;
        $todayFoodCash = 0;
        $todayFoodMobile = 0;
        foreach ($foodHandovers as $h) {
            $amt = (float)$h->amount;
            $todayFoodVerified += $amt;
            
            $breakdown = $h->payment_breakdown ?? [];
            if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
            if (is_array($breakdown) && !empty($breakdown)) {
                foreach ($breakdown as $key => $val) {
                    $itemAmt = floatval($val);
                    if ($key === 'shortage_payment' || $key === 'total') continue;
                    if ($key === 'cash' || str_contains($key, 'cash_')) {
                        $todayFoodCash += $itemAmt;
                    } else {
                        $todayFoodMobile += $itemAmt;
                    }
                }
            } else {
                $todayFoodCash += $amt;
            }
        }

        $todayRevenue = $todayBarVerified + $todayFoodVerified;
        $todayCash = $todayBarCash + $todayFoodCash;
        $todayMobileMoney = $todayBarMobile + $todayFoodMobile;

        $todayOrdersCount = $todayOrders->count();
        $todayPaidOrders = $todayOrders->where('payment_status', 'paid')->count();
        $todayPendingAmount = $todayOrders->where('payment_status', '!=', 'paid')->sum('total_amount');

        // Today's Expenses (from Master Sheet)
        if ($todayLedger) {
            $todayLedger->syncTotals();
            $todayExpenses = $todayLedger->total_expenses;
        } else {
            $todayExpenses = 0;
        }

        // Period Financial Summary (default: current month)
        $periodOrders = $applyFilters(BarOrder::query())
            ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'kitchenOrderItems', 'orderPayments'])
            ->get();

        // Calculate revenue from items (bar + food), not total_amount
        $periodRevenue = $periodOrders->where('payment_status', 'paid')->sum(function($order) {
            $barAmount = $order->items && $order->items->isNotEmpty() 
                ? $order->items->sum('total_price') 
                : 0;
            $foodAmount = $order->kitchenOrderItems && $order->kitchenOrderItems->isNotEmpty()
                ? $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price')
                : 0;
            return $barAmount + $foodAmount;
        });
        // Calculate cash and mobile money from OrderPayments only (source of truth)
        $periodCash = $periodOrders->sum(function($order) {
            return $order->orderPayments && $order->orderPayments->isNotEmpty()
                ? $order->orderPayments->where('payment_method', 'cash')->sum('amount')
                : 0;
        });
        $periodMobileMoney = $periodOrders->sum(function($order) {
            return $order->orderPayments && $order->orderPayments->isNotEmpty()
                ? $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount')
                : 0;
        });
        $periodOrdersCount = $periodOrders->count();
        $periodPaidOrders = $periodOrders->where('payment_status', 'paid')->count();
        $periodPendingAmount = $periodOrders->where('payment_status', '!=', 'paid')->sum('total_amount');

        // Add Food Profits to Period Summary
        $periodFoodHandovers = 0; // Removed from monthly charts
        
        $periodRevenue += $periodFoodHandovers;
        $periodCash += $periodFoodHandovers;

        // Separate bar and food sales (Today) - Strictly realized sales (Served or Paid)
        $realizedStatuses = ['served', 'paid', 'completed', 'delivered'];

        $todayBarSales = $todayOrders->whereIn('status', $realizedStatuses)->sum(function($order) {
            return $order->items->sum('total_price');
        });

        $todayFoodSales = $todayOrders->whereIn('status', $realizedStatuses)->sum(function($order) {
            return $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price');
        });

        // Period sales - Strictly realized sales
        $periodBarSales = $periodOrders->whereIn('status', $realizedStatuses)->sum(function($order) {
            return $order->items->sum('total_price');
        });

        $periodFoodSales = $periodOrders->whereIn('status', $realizedStatuses)->sum(function($order) {
            return $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price');
        });

        // Waiter Reconciliations Summary (filtered by branch)
        $reconciliations = WaiterDailyReconciliation::query()
            ->where('user_id', $ownerId)
            ->whereBetween('reconciliation_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->when($location, function($q) use ($location) {
                $q->whereHas('waiter', function($sq) use ($location) {
                    $sq->where('location_branch', $location);
                });
            })
            ->with('waiter')
            ->get();

        $totalExpected = $reconciliations->sum('expected_amount');
        $totalSubmitted = $reconciliations->sum('submitted_amount');
        $totalDifference = $reconciliations->sum('difference');
        $verifiedReconciliations = $reconciliations->where('status', 'verified')->count();
        $pendingReconciliations = $reconciliations->where('status', 'pending')->count();
        $submittedReconciliations = $reconciliations->where('status', 'submitted')->count();

        // Revenue by Day (Last 30 days) – read from DailyCashLedger for accuracy
        $revenueByDay = [];
        $last7Start = Carbon::now()->subDays(6)->startOfDay();
        $last7End   = Carbon::now()->endOfDay();

        $isAdmin = auth()->check() && auth()->user()->isAdmin();

        $last7LedgersQuery = \App\Models\DailyCashLedger::whereBetween('ledger_date', [$last7Start, $last7End]);
        if (!$isAdmin) {
            $last7LedgersQuery->where('user_id', $ownerId);
        }
        $last7Ledgers = $last7LedgersQuery->get()
            ->keyBy(fn($l) => Carbon::parse($l->ledger_date)->format('Y-m-d'));

        // Fetch Food Profit Handovers for same period to add to charts
        $foodProfitsQuery = \App\Models\FinancialHandover::where('department', 'food')
            ->where('handover_type', 'accountant_to_owner')
            ->where('status', 'confirmed')
            ->whereBetween('handover_date', [$last7Start, $last7End]);
        if (!$isAdmin) {
            $foodProfitsQuery->where('user_id', $ownerId);
        }
        $foodProfits = $foodProfitsQuery->get()
            ->keyBy(fn($h) => Carbon::parse($h->handover_date)->format('Y-m-d'));

        for ($i = 6; $i >= 0; $i--) {
            $day    = Carbon::now()->subDays($i);
            $dayStr = $day->format('Y-m-d');
            $ledger = $last7Ledgers->get($dayStr);
            $food   = $foodProfits->get($dayStr);

            $dayRevenue  = $ledger ? (float)($ledger->total_cash_received + $ledger->total_digital_received) : 0;
            $dayExpenses = $ledger ? (float)($ledger->total_expenses ?? 0) : 0;
            $dayProfit   = $ledger ? (float)($ledger->profit_generated ?? max(0, $dayRevenue - $dayExpenses)) : 0;
            $dayCash     = $ledger ? (float)($ledger->total_cash_received ?? 0) : 0;
            $dayDigital  = $ledger ? (float)($ledger->total_digital_received ?? 0) : 0;
            
            // Add Food Profit to Daily Profit if exists
            if ($food) {
                $dayProfit += (float)$food->amount;
                // Also add food amount to total revenue if you want it combined in charts
                $dayRevenue += (float)$food->amount;
                $dayCash += (float)$food->amount;
            }

            // Calculate distinct profits for the chart (Subtract Bar Expenses from Bar Profit for accuracy)
            $barProfit = $ledger ? (float)($ledger->profit_generated ?? 0) : 0;
            $barProfit = max(0, $barProfit - $dayExpenses); // Deduct expenses from bar profit
            
            $foodProfit = $food ? (float)$food->amount : 0;

            $revenueByDay[] = [
                'date' => $day->format('M d'),
                'bar_profit' => $barProfit,
                'food_profit' => $foodProfit,
                'total_profit' => $barProfit + $foodProfit,
                'revenue' => $dayRevenue + (float)($food->amount ?? 0),
                'expenses' => $dayExpenses,
            ];
        }

        // Top Waiters by Revenue (filtered by owner and branch)
        $topWaiters = Staff::query()
            ->where('user_id', $ownerId)
            ->whereHas('role', function($q) {
                $q->where('name', 'Waiter');
            })
            ->when($location, function($q) use ($location) {
                $q->where('location_branch', $location);
            })
            ->with(['dailyReconciliations' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('reconciliation_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            }])
            ->get()
            ->map(function($waiter) use ($startDate, $endDate, $ownerId, $location) {
                $query = BarOrder::query()
                    ->where('user_id', $ownerId)
                    ->where('waiter_id', $waiter->id)
                    ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
                    ->where('status', '!=', 'cancelled');
                
                if ($location) {
                    $query->where(function($q) use ($location) {
                        $q->whereHas('table', function($sq) use ($location) {
                            $sq->where('location', $location);
                        })->orWhere('waiter_id', $waiter->id); 
                    });
                }

                $orders = $query->with(['items', 'kitchenOrderItems', 'orderPayments'])->get();
                
                $barRev = $orders->sum(function($order) {
                    return $order->items->sum('total_price');
                });
                $foodRev = $orders->sum(function($order) {
                    return $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price');
                });

                return [
                    'waiter' => $waiter,
                    'total_revenue' => $barRev + $foodRev,
                    'orders_count' => $orders->count(),
                    'bar_revenue' => $barRev,
                    'food_revenue' => $foodRev,
                    'cash_collected' => $orders->sum(function($order) {
                        return $order->orderPayments->where('payment_method', 'cash')->sum('amount');
                    }),
                    'mobile_money_collected' => $orders->sum(function($order) {
                        return $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount');
                    }),
                ];
            })
            ->filter(fn($item) => $item['total_revenue'] > 0)
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        // Pending Stock Transfer Verifications (filtered by owner and branch)
        $pendingTransferVerifications = \App\Models\StockTransfer::query()
            ->where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereNull('verified_at')
            ->when($location, function($q) use ($location) {
                $q->whereExists(function($sq) use ($location) {
                    $sq->select(DB::raw(1))
                       ->from('staff')
                       ->whereRaw('staff.user_id = stock_transfers.user_id')
                       ->whereRaw('staff.email = (select email from users where id = stock_transfers.requested_by limit 1)')
                       ->where('staff.location_branch', $location);
                });
            })
            ->with(['productVariant.product', 'productVariant.counterStock', 'productVariant.warehouseStock'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($transfer) use ($ownerId, $location) {
                // Calculate expected and real-time profit/revenue
                if ($transfer->productVariant) {
                    $counterStock = \App\Models\StockLocation::where('user_id', $ownerId)
                        ->where('product_variant_id', $transfer->product_variant_id)
                        ->where('location', 'counter')
                        ->first();
                    
                    $warehouseStock = \App\Models\StockLocation::where('user_id', $ownerId)
                        ->where('product_variant_id', $transfer->product_variant_id)
                        ->where('location', 'warehouse')
                        ->first();
                    
                    $sellingPrice = $counterStock->selling_price ?? $warehouseStock->selling_price ?? $transfer->productVariant->selling_price_per_unit ?? 0;
                    $buyingPrice = $warehouseStock->average_buying_price ?? $transfer->productVariant->buying_price_per_unit ?? 0;
                    
                    $transfer->expected_revenue = $transfer->total_units * $sellingPrice;
                    $transfer->expected_profit = ($sellingPrice - $buyingPrice) * $transfer->total_units;
                    
                    // Calculate real-time profit and revenue (branch-aware)
                    $transfer->real_time_profit = $this->calculateRealTimeProfitForTransfer($transfer, $ownerId, $sellingPrice, $buyingPrice, $location);
                    $revenueData = $this->calculateRealTimeRevenueForTransfer($transfer, $ownerId, $location);
                    $transfer->real_time_revenue = $revenueData['total'];
                }
                return $transfer;
            });

        // Recent Reconciliations (filtered by branch)
        $recentReconciliations = WaiterDailyReconciliation::query()
            ->where('user_id', $ownerId)
            ->when($location, function($q) use ($location) {
                $q->whereHas('waiter', function($sq) use ($location) {
                    $sq->where('location_branch', $location);
                });
            })
            ->with('waiter', 'verifiedBy')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Outstanding Payments (filtered by branch)
        $outstandingOrders = $applyFilters(BarOrder::query())
            ->where('status', 'served')
            ->where('payment_status', '!=', 'paid')
            ->with(['waiter', 'table', 'items', 'kitchenOrderItems'])
            ->orderBy('created_at', 'desc')
            ->get();

        $outstandingAmount = $outstandingOrders->sum('total_amount');

        // ── Top selling products for selected period (Bar + Kitchen)
        $barTopProducts = \App\Models\OrderItem::with('productVariant.product')
            ->whereHas('order', function($q) use ($applyFilters, $startDate, $endDate) {
                $applyFilters($q);
                $q->where('status', '!=', 'cancelled')
                  ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            })
            ->whereNotNull('product_variant_id')
            ->selectRaw('product_variant_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
            ->groupBy('product_variant_id')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->productVariant ? $item->productVariant->display_name : 'Unknown Drink',
                    'total_sold' => (int)$item->total_sold,
                    'total_revenue' => (float)$item->total_revenue
                ];
            });

        $foodTopProducts = \App\Models\KitchenOrderItem::where('status', '!=', 'cancelled')
            ->whereHas('order', function($q) use ($applyFilters, $startDate, $endDate) {
                $applyFilters($q);
                $q->where('status', '!=', 'cancelled')
                  ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            })
            ->selectRaw('COALESCE(food_item_id, 0) as food_id, COALESCE(food_item_name, "Kitchen Item") as name, variant_name, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
            ->groupBy('food_id', 'name', 'variant_name')
            ->get()
            ->map(function($item) {
                $displayName = $item->name;
                if ($item->variant_name) {
                    $displayName .= ' (' . $item->variant_name . ')';
                }
                return [
                    'name' => $displayName,
                    'total_sold' => (int)$item->total_sold,
                    'total_revenue' => (float)$item->total_revenue
                ];
            });

        $topProducts = $barTopProducts->concat($foodTopProducts)
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        // ── Category Distribution (selected period) - Bar + Food
        $barCategories = \App\Models\OrderItem::whereHas('order', function($q) use ($applyFilters, $startDate, $endDate) {
                $applyFilters($q);
                $q->where('status', '!=', 'cancelled')
                  ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            })
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->selectRaw('products.category, SUM(order_items.total_price) as total_revenue')
            ->groupBy('products.category')
            ->get()
            ->map(function($c) {
                return ['category' => $c->category, 'total_revenue' => (float)$c->total_revenue];
            });

        $foodCategories = \App\Models\KitchenOrderItem::where('status', '!=', 'cancelled')
            ->whereHas('order', function($q) use ($applyFilters, $startDate, $endDate) {
                $applyFilters($q);
                $q->where('status', '!=', 'cancelled')
                  ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            })
            ->leftJoin('food_items', 'kitchen_order_items.food_item_id', '=', 'food_items.id')
            ->selectRaw('COALESCE(food_items.category, "Kitchen") as category, SUM(kitchen_order_items.total_price) as total_revenue')
            ->groupBy('category')
            ->get()
            ->map(function($c) {
                return ['category' => $c->category, 'total_revenue' => (float)$c->total_revenue];
            });

        $categoryDistribution = $barCategories->concat($foodCategories)
            ->groupBy('category')
            ->map(function($group, $name) {
                return ['category' => $name, 'total_revenue' => $group->sum('total_revenue')];
            });

        // Manual Food Handovers removed from Category Distribution

        $categoryDistribution = $categoryDistribution->sortByDesc('total_revenue')->values();

        return view('accountant.dashboard', compact(
            'date',
            'startDate',
            'endDate',
            'todayRevenue',
            'todayBarVerified',
            'todayFoodVerified',
            'todayCash',
            'todayMobileMoney',
            'todayExpenses',
            'todayOrdersCount',
            'todayPaidOrders',
            'todayPendingAmount',
            'todayBarSales',
            'todayFoodSales',
            'periodRevenue',
            'periodCash',
            'periodMobileMoney',
            'periodOrdersCount',
            'periodPaidOrders',
            'periodPendingAmount',
            'periodBarSales',
            'periodFoodSales',
            'totalExpected',
            'totalSubmitted',
            'totalDifference',
            'verifiedReconciliations',
            'pendingReconciliations',
            'submittedReconciliations',
            'revenueByDay',
            'topWaiters',
            'pendingTransferVerifications',
            'recentReconciliations',
            'outstandingOrders',
            'outstandingAmount',
            'topProducts',
            'categoryDistribution'
        ));
    }

    /**
     * View All Stock Transfer Reconciliations (Accountant reconciles stock transfers)
     */
    public function reconciliations(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view') && !$this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to view reconciliations.');
        }

        $ownerId = $this->getOwnerId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $location = session('active_location');
        $tab = $request->get('tab', 'financial'); // 'financial' or 'waiters' or 'payments'
        $canReconcile = $this->hasPermission('finance', 'edit');
        
        // Is user a manager/accountant who can see performance charts?
        $isManagerView = $this->hasPermission('finance', 'view') || $this->hasPermission('reports', 'view');
        
        // Determine if we should filter by department (Counter = bar, Chef = food)
        $currentStaff = $this->getCurrentStaff();
        $roleSlug = strtolower(trim($currentStaff->role->slug ?? ''));
        $deptFilter = null;
        if (in_array($roleSlug, ['counter', 'waiter'])) {
            $deptFilter = 'bar';
        } elseif ($roleSlug === 'chef') {
            $deptFilter = 'food';
        }

        // ── Financial Reconciliations Aggregate (By Date & Type)
        // 0. Get submitted handovers for visibility filter
        $handoversQuery = \App\Models\FinancialHandover::whereBetween('handover_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
        
        if (auth()->user()?->role !== 'admin') {
            $handoversQuery->where('user_id', $ownerId);
        }
        $handovers = $handoversQuery->get();
        $handoverMap = $handovers->mapWithKeys(function($h) {
            $dateStr = $h->handover_date instanceof Carbon ? $h->handover_date->format('Y-m-d') : date('Y-m-d', strtotime($h->handover_date));
            return [$dateStr . '_' . $h->department => $h];
        });

        // 1. Get already submitted/verified reconciliations
        $submittedQuery = WaiterDailyReconciliation::query()
            ->when($location && $location !== 'all' && auth()->user()?->role !== 'admin', function($q) use ($location) {
                $q->whereHas('waiter', function($sq) use ($location) {
                    $sq->where('location_branch', $location);
                });
            })
            ->when($deptFilter, function($q) use ($deptFilter) {
                // In DB, reconciliation_type for food might be 'kitchen' or 'food', but controller uses 'food'
                $dbType = $deptFilter === 'food' ? ['food', 'kitchen'] : [$deptFilter];
                $q->whereIn('reconciliation_type', $dbType);
            })
            ->whereBetween('reconciliation_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw('reconciliation_date, reconciliation_type, SUM(expected_amount) as total_expected, SUM(submitted_amount) as total_submitted, SUM(cash_collected) as total_cash, SUM(mobile_money_collected) as total_mobile, SUM(bank_collected) as total_bank, SUM(card_collected) as total_card, COUNT(waiter_id) as waiter_count, MIN(status) as status_indicator, MAX(notes) as notes');

        if (auth()->user()?->role !== 'admin') {
            $submittedQuery->where('user_id', $ownerId);
        }

        $submittedReconciliations = $submittedQuery->groupBy('reconciliation_date', 'reconciliation_type')
            ->get()
            ->filter(function($r) use ($handoverMap) {
                // Only show to accountant if handover exists
                $dateVal = $r->reconciliation_date;
                $dateStr = ($dateVal instanceof \Carbon\Carbon) ? $dateVal->format('Y-m-d') : date('Y-m-d', strtotime($dateVal));
                $key = $dateStr . '_' . $r->reconciliation_type;
                return isset($handoverMap[$key]);
            })
            ->map(function($r) use ($handoverMap) {
                $dateVal = $r->reconciliation_date;
                $dateStr = ($dateVal instanceof \Carbon\Carbon) ? $dateVal->format('Y-m-d') : date('Y-m-d', strtotime($dateVal));
                $key = $dateStr . '_' . $r->reconciliation_type;
                
                $h = $handoverMap[$key] ?? null;
                $r->payment_breakdown = $h ? $h->payment_breakdown : [];
                $r->handover_id = $h ? $h->id : null;
                $r->handover_status = $h ? $h->status : 'pending';

                // AGGREGATE RECORDED PLATFORM BREAKDOWNS FROM ALL WAITERS
                $dbDate = ($dateVal instanceof \Illuminate\Support\Carbon) ? $dateVal->toDateString() : date('Y-m-d', strtotime($dateVal));
                $dbTypeArr = ($r->reconciliation_type === 'food') ? ['food', 'kitchen'] : [$r->reconciliation_type];
                
                $underlyingRecs = \App\Models\WaiterDailyReconciliation::where('reconciliation_date', $dbDate)
                    ->whereIn('reconciliation_type', $dbTypeArr)
                    ->get();
                
                $recordedPlatformBreakdown = [];
                $submittedPlatformBreakdown = [];
                foreach ($underlyingRecs as $wr) {
                    if ($wr->notes) {
                        try {
                            $notesData = json_decode($wr->notes, true);
                            if (is_array($notesData)) {
                                // 1. Map RECORDED (Expected)
                                $wRec = $notesData['recorded_breakdown'] ?? [];
                                foreach ($wRec as $channel => $amt) {
                                    $cLower = strtolower(trim(str_replace(' ', '_', $channel)));
                                    $recordedPlatformBreakdown[$cLower] = ($recordedPlatformBreakdown[$cLower] ?? 0) + (float)$amt;
                                }
                                
                                // 2. Map SUBMITTED (Actual)
                                $wSub = $notesData['submitted_breakdown'] ?? [];
                                foreach ($wSub as $channel => $amt) {
                                    $cLower = strtolower(trim(str_replace(' ', '_', $channel)));
                                    $submittedPlatformBreakdown[$cLower] = ($submittedPlatformBreakdown[$cLower] ?? 0) + (float)$amt;
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                }
                $r->recorded_platform_breakdown = $recordedPlatformBreakdown;
                $r->submitted_platform_breakdown = $submittedPlatformBreakdown;

                // Track shortage payments for summary and JS
                $paid = 0;
                $noteSource = ($h && $h->notes) ? $h->notes : ($r->notes ?? '');
                $r->notes = $noteSource; // CRITICAL: Updates the object so Blade regex works
                if(preg_match('/\[ShortagePaidTotal:(\d+)\]/', $noteSource, $m)) $paid = (int)$m[1];
                $r->shortage_paid = (float)$paid;
                
                $breakdown = "";
                if(preg_match('/\[ShortagePaidBreakdown:([^\]]+)\]/', $noteSource, $bm)) $breakdown = $bm[1];
                $r->shortage_breakdown = $breakdown;

                // CRITICAL: We NO LONGER override the record's 'total_submitted' because
                // we need to see the original shortage relative to Expected sales.
                // We just attach the handover data for the folders.
                // CRITICAL: Aligned truth must come from the Handover physical bag.
                // This resolves the mismatch where manual staff records (16k) 
                // differ from the bag (12k). The Accountant audits the BAG vs SALES.
                if ($h) {
                    $r->handover_amount = $h->amount;
                    // We keep total_cash/mobile/etc as the RECORDED values (from Sales Audit)
                    // and store the Handover values in 'submitted_' keys.
                    $r->submitted_cash = 0;
                    $r->submitted_mobile = 0;
                    $r->submitted_bank = 0;
                    $r->submitted_card = 0;
                    
                    foreach ($h->payment_breakdown as $channel => $amt) {
                        $channel = strtolower($channel);
                        $amt = (float)$amt;
                        if (str_contains($channel, 'cash')) {
                            $r->submitted_cash += $amt;
                        } elseif (in_array($channel, ['mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'mixx'])) {
                            $r->submitted_mobile += $amt;
                        } elseif (in_array($channel, ['crdb', 'nmb', 'kcb', 'bank_transfer', 'bank'])) {
                            $r->submitted_bank += $amt;
                        } elseif (str_contains($channel, 'card') || str_contains($channel, 'pos')) {
                            $r->submitted_card += $amt;
                        }
                    }
                    // For the total 'Submitted (Actual)' column in the table, we use the bag total.
                    $r->total_submitted_bag = $h->amount;
                } else {
                    $r->handover_amount = $r->total_submitted;
                    $r->total_submitted_bag = $r->total_submitted;
                    $r->submitted_cash = $r->total_cash;
                    $r->submitted_mobile = $r->total_mobile;
                }
                
                return $r;
            });

        // 2. Get Real-time Expected Sales (Pending Reconciliations)
        // We look for orders that haven't been reconciled yet for the date range
        $realTimeSales = BarOrder::query()
            ->where('user_id', $ownerId)
            ->when($location && $location !== 'all', function($q) use ($location) {
                // Determine branch from the table location
                $q->whereHas('table', function($sq) use ($location) {
                    $sq->where('location', $location);
                });
            })
            ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->where('status', 'served')
            ->whereDoesntHave('reconciliation', function($q) {
                $q->where('status', 'verified');
            })
            ->with(['items', 'kitchenOrderItems', 'orderPayments'])
            ->get();

        // Group the real-time sales by date and type for display
        $pendingAggr = [];
        foreach ($realTimeSales as $order) {
            $dateStr = $order->created_at->format('Y-m-d');
            
            // Bar Sales (Drinks)
            $barAmount = $order->items->sum('total_price');
            $foodAmount = $order->kitchenOrderItems->sum('total_price');
            $totalOrderAmount = $barAmount + $foodAmount;

            if ($barAmount > 0 && (!$deptFilter || $deptFilter === 'bar')) {
                $key = $dateStr . '_bar';
                if (!isset($pendingAggr[$key])) {
                    $pendingAggr[$key] = [
                        'reconciliation_date' => $dateStr,
                        'reconciliation_type' => 'bar',
                        'total_expected' => 0,
                        'total_submitted' => 0,
                        'total_cash' => 0,
                        'total_mobile' => 0,
                        'total_bank' => 0,
                        'total_card' => 0,
                        'waiter_count' => 0,
                        'status_indicator' => 'pending',
                        'notes' => ''
                    ];
                }
                $pendingAggr[$key]['total_expected'] += $barAmount;
                
                // Proportion of payment for bar
                $proportion = $totalOrderAmount > 0 ? ($barAmount / $totalOrderAmount) : 0;
                
                foreach ($order->orderPayments as $payment) {
                    $amount = $payment->amount * $proportion;
                    $pendingAggr[$key]['total_submitted'] += $amount;
                    $pendingAggr[$key]['status_indicator'] = 'submitted';
                    if ($payment->payment_method === 'cash') {
                        $pendingAggr[$key]['total_cash'] += $amount;
                    } else if ($payment->payment_method === 'mobile_money') {
                        $pendingAggr[$key]['total_mobile'] += $amount;
                    } else if ($payment->payment_method === 'bank_transfer') {
                        $pendingAggr[$key]['total_bank'] += $amount;
                    } else if (in_array($payment->payment_method, ['pos_card', 'card'])) {
                        $pendingAggr[$key]['total_card'] += $amount;
                    }
                }
            }

            // Chef Sales (Food)
            if ($foodAmount > 0 && (!$deptFilter || $deptFilter === 'food')) {
                $key = $dateStr . '_food';
                if (!isset($pendingAggr[$key])) {
                    $pendingAggr[$key] = [
                        'reconciliation_date' => $dateStr,
                        'reconciliation_type' => 'food',
                        'total_expected' => 0,
                        'total_submitted' => 0,
                        'total_cash' => 0,
                        'total_mobile' => 0,
                        'total_bank' => 0,
                        'total_card' => 0,
                        'waiter_count' => 0,
                        'status_indicator' => 'pending',
                        'notes' => ''
                    ];
                }
                $pendingAggr[$key]['total_expected'] += $foodAmount;
                
                // Proportion of payment for food
                $proportion = $totalOrderAmount > 0 ? ($foodAmount / $totalOrderAmount) : 0;

                foreach ($order->orderPayments as $payment) {
                    $amount = $payment->amount * $proportion;
                    $pendingAggr[$key]['total_submitted'] += $amount;
                    $pendingAggr[$key]['status_indicator'] = 'submitted';
                    if ($payment->payment_method === 'cash') {
                        $pendingAggr[$key]['total_cash'] += $amount;
                    } else if ($payment->payment_method === 'mobile_money') {
                        $pendingAggr[$key]['total_mobile'] += $amount;
                    } else if ($payment->payment_method === 'bank_transfer') {
                        $pendingAggr[$key]['total_bank'] += $amount;
                    } else if (in_array($payment->payment_method, ['pos_card', 'card'])) {
                        $pendingAggr[$key]['total_card'] += $amount;
                    }
                }
            }
        }

        // Merge submitted and pending
        // If a date/type has both, we might want to prioritize the submitted one or combine?
        // Usually, once 'Marked Paid', it vanishes from pending.
        $financialReconciliations = collect($submittedReconciliations);
        foreach ($pendingAggr as $item) {
            $key = $item['reconciliation_date'] . '_' . $item['reconciliation_type'];
            // Only add if handover exists AND it's not already in submitted
            if (isset($handoverMap[$key])) {
                $exists = $financialReconciliations->contains(function($r) use ($item) {
                    return $r->reconciliation_date->format('Y-m-d') == $item['reconciliation_date'] && $r->reconciliation_type == $item['reconciliation_type'];
                });
                if (!$exists) {
                    $item['reconciliation_date'] = Carbon::parse($item['reconciliation_date']);
                    $financialReconciliations->push((object)$item);
                }
            }
        }

        $financialReconciliations = $financialReconciliations->sortByDesc('reconciliation_date')->values();

        // Separate Waiter-level reconciliations for the details view or drill down
        $waiterReconciliations = WaiterDailyReconciliation::query()
            ->where('user_id', $ownerId)
            ->when($location && $location !== 'all', function($q) use ($location) {
                $q->whereHas('waiter', function($sq) use ($location) {
                    $sq->where('location_branch', $location);
                });
            })
            ->whereBetween('reconciliation_date', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->with(['waiter', 'verifiedBy'])
            ->orderBy('reconciliation_date', 'desc')
            ->get();
        $waiterReconciliations = $waiterReconciliations->values();

        // ── Payments Log (Detailed breakdown for the accountant)
        $paymentSearch = $request->get('payment_search');
        $paymentMethod = $request->get('payment_method');
        $paymentStaff = $request->get('payment_staff');

        $paymentsQuery = OrderPayment::query()
            ->whereHas('order', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            })
            ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->with(['order.waiter', 'order.table']);

        if ($paymentSearch) {
            $paymentsQuery->where(function($q) use ($paymentSearch) {
                $q->where('transaction_reference', 'like', "%{$paymentSearch}%")
                  ->orWhere('mobile_money_number', 'like', "%{$paymentSearch}%")
                  ->orWhereHas('order', function($sq) use ($paymentSearch) {
                      $sq->where('order_number', 'like', "%{$paymentSearch}%");
                  });
            });
        }
        if ($paymentMethod) {
            $paymentsQuery->where('payment_method', $paymentMethod);
        }
        if ($paymentStaff) {
            $paymentsQuery->whereHas('order', function($q) use ($paymentStaff) {
                $q->where('waiter_id', $paymentStaff);
            });
        }

        $payments = $paymentsQuery->orderBy('created_at', 'desc')
            ->paginate(30, ['*'], 'payments_page');

        $staffMembers = Staff::where('user_id', $ownerId)->get();

        // ── Financial Dashboard Charts (High Fidelity) ──
        $chartData = [
            'dates' => [],
            'expected' => [],
            'collected' => [],
            'methods' => [
                'Cash' => 0,
                'Mobile' => 0,
                'Bank' => 0,
                'Card' => 0
            ]
        ];

        // Group data by date for the trend graph
        $dailyResults = [];
        foreach ($financialReconciliations as $fr) {
            $dKey = \Carbon\Carbon::parse($fr->reconciliation_date)->format('M d');
            if(!isset($dailyResults[$dKey])) {
                $dailyResults[$dKey] = ['expected' => 0, 'collected' => 0];
            }
            
            // Tracking payments
            $paid = 0;
            if(preg_match('/\[ShortagePaidTotal:(\d+)\]/', $fr->notes ?? '', $m)) $paid = (int)$m[1];
            
            $dailyResults[$dKey]['expected'] += (float)$fr->total_expected;
            $dailyResults[$dKey]['collected'] += ((float)$fr->total_submitted + $paid);

            // Channel aggregation for Pie chart
            $breakdown = [];
            if(preg_match('/\[ShortagePaidBreakdown:([^\]]+)\]/', $fr->notes ?? '', $bm)) {
                foreach(explode(',', $bm[1]) as $p) {
                    $kv = explode('=', $p);
                    if(count($kv) == 2) $breakdown[$kv[0]] = (int)$kv[1];
                }
            }
            $chartData['methods']['Cash'] += ((float)$fr->total_cash + ($breakdown['cash'] ?? 0));
            $chartData['methods']['Mobile'] += ((float)$fr->total_mobile + ($breakdown['mobile_money'] ?? 0));
            $chartData['methods']['Bank'] += ((float)($fr->total_bank ?? 0) + ($breakdown['bank_transfer'] ?? 0));
            $chartData['methods']['Card'] += ((float)($fr->total_card ?? 0) + ($breakdown['pos_card'] ?? 0));
        }

        // Fill final chart arrays in chronological order
        ksort($dailyResults);
        foreach($dailyResults as $date => $vals) {
            $chartData['dates'][] = $date;
            $chartData['expected'][] = $vals['expected'];
            $chartData['collected'][] = $vals['collected'];
        }

        // ── Estimated Profit Calculation (Bar + Kitchen) ──
        $barProfit = \App\Models\OrderItem::whereHas('order', function($q) use ($ownerId, $startDate, $endDate, $location) {
            $q->where('user_id', $ownerId)
              ->where('status', 'served')
              ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            if($location && $location !== 'all') {
                $q->whereHas('table', function($sq) use ($location) { $sq->where('location', $location); });
            }
        })
        ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
        ->selectRaw('SUM((order_items.unit_price - COALESCE(product_variants.buying_price_per_unit, 0)) * order_items.quantity) as profit')
        ->value('profit') ?? 0;

        $foodProfit = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId, $startDate, $endDate, $location) {
            $q->where('user_id', $ownerId)
              ->where('status', 'served')
              ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);
            if($location && $location !== 'all') {
                $q->whereHas('table', function($sq) use ($location) { $sq->where('location', $location); });
            }
        })
        ->join('food_items', 'kitchen_order_items.food_item_id', '=', 'food_items.id')
        ->selectRaw('SUM((kitchen_order_items.unit_price - 0) * kitchen_order_items.quantity) as profit')
        ->value('profit') ?? 0;

        $summaryProfit = (float)$barProfit + (float)$foodProfit;

        return view('accountant.reconciliations', compact(
            'financialReconciliations',
            'waiterReconciliations',
            'payments',
            'startDate',
            'endDate',
            'tab',
            'staffMembers',
            'canReconcile',
            'chartData',
            'summaryProfit',
            'isManagerView',
            'handoverMap'
        ));
    }

    /**
     * Finalize Department Reconciliation (Accountant Action)
     */
    public function finalizeDepartmentReconciliation(Request $request)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:bar,food',
            'cash_received' => 'required|numeric|min:0',
            'mobile_received' => 'required|numeric|min:0',
            'bank_received' => 'required|numeric|min:0',
            'card_received' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $ownerId = $this->getOwnerId();
        $date = $validated['date'];
        $type = $validated['type'];

        // 1. Get all pending orders for this department and date
        $orders = BarOrder::where('user_id', $ownerId)
            ->whereDate('created_at', $date)
            ->where('status', 'served')
            ->whereDoesntHave('reconciliation', function($q) {
                $q->where('status', 'verified');
            })
            ->with(['items', 'kitchenOrderItems', 'orderPayments'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'No pending orders found for this period.']);
        }

        // 2. We group orders by waiter to create reconciliation records for each
        $waiterGroups = $orders->groupBy('waiter_id');
        $processedWaiters = 0;

        foreach ($waiterGroups as $waiterId => $waiterOrders) {
            if (!$waiterId) continue;
            
            $expected = 0;
            if ($type === 'bar') {
                $expected = $waiterOrders->sum(function($o) { return $o->items->sum('total_price'); });
            } else {
                $expected = $waiterOrders->sum(function($o) { return $o->kitchenOrderItems->sum('total_price'); });
            }

            if ($expected <= 0) continue;

            // For the sake of this aggregate action, we distribute the 'actual' amounts proportionally 
            // or just attribute the 'recorded' amounts as a baseline.
            // But since the accountant is doing a WHOLE department, we might just mark them all 'submitted' 
            // and the 'Actual' goes into a master record or distributed.
            
            // 3. We use the ACTUAL inputs from the accountant (re-distributed proportionally if needed)
            // For now, since they reconcile the whole department, we set the actuals on each waiter record
            // OR we could create a master record. But the table in the UI sums them up.
            
            // CRITICAL FIX: Use the accountant's input!
            // Wait, proportional distribution is safer for multiple waiters:
            $totalCashInput = $validated['cash_received'];
            $totalMobileInput = $validated['mobile_received'];
            $totalBankInput = $validated['bank_received'];
            $totalCardInput = $validated['card_received'];
            $totalExpectedAll = $orders->sum(function($o) use ($type) { 
                return ($type === 'bar') ? $o->items->sum('total_price') : $o->kitchenOrderItems->sum('total_price');
            });

            $proportion = ($totalExpectedAll > 0) ? ($expected / $totalExpectedAll) : 0;
            $waiterCashActual = $totalCashInput * $proportion;
            $waiterMobileActual = $totalMobileInput * $proportion;
            $waiterBankActual = $totalBankInput * $proportion;
            $waiterCardActual = $totalCardInput * $proportion;
            $waiterSubmittedActual = $waiterCashActual + $waiterMobileActual + $waiterBankActual + $waiterCardActual;

            $reconciliation = WaiterDailyReconciliation::updateOrCreate(
                [
                    'user_id' => $ownerId,
                    'waiter_id' => $waiterId,
                    'reconciliation_date' => $date,
                    'reconciliation_type' => $type,
                ],
                [
                    'expected_amount' => $expected,
                    'submitted_amount' => $waiterSubmittedActual,
                    'cash_collected' => $waiterCashActual,
                    'mobile_money_collected' => $waiterMobileActual,
                    'bank_collected' => $waiterBankActual,
                    'card_collected' => $waiterCardActual,
                    'difference' => $waiterSubmittedActual - $expected,
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => auth()->id(),
                    'notes' => $validated['notes'] . " (Bulk reconciled by Accountant)"
                ]
            );

            // Mark orders as reconciled
            foreach ($waiterOrders as $order) {
                $order->update(['reconciliation_id' => $reconciliation->id, 'payment_status' => 'paid']);
            }
            
            $processedWaiters++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully reconciled {$processedWaiters} staff records for this department."
        ]);
    }

    /**
     * Verify a financial reconciliation (Counter/Chef)
     */
    public function verifyFinancialReconciliation(Request $request, $id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $reconciliation = WaiterDailyReconciliation::findOrFail($id);
        
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'status' => 'required|in:verified,flagged'
        ]);

        $reconciliation->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'],
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation updated successfully.'
        ]);
    }

    /**
     * Verify a departmental financial handover (consolidates money into Cash Ledger)
     */
    public function verifyHandover(Request $request, $id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $handover = FinancialHandover::findOrFail($id);
        if ($handover->status !== 'pending') {
            return response()->json(['success' => false, 'error' => 'Handover is already processed.'], 400);
        }

        DB::beginTransaction();
        try {
            $handover->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            // Consolidate into the Daily Cash Ledger for that date
            $ledger = DailyCashLedger::firstOrCreate(
                [
                    'user_id' => $handover->user_id,
                    'ledger_date' => $handover->handover_date,
                ],
                [
                    'accountant_id' => Auth::user()->staff->id ?? null,
                    'status' => 'open',
                    'opening_cash' => 0,
                    'total_cash_received' => 0,
                    'expected_closing_cash' => 0
                ]
            );

            // Re-calculate opening float if it's the first time 
            if ($ledger->wasRecentlyCreated || $ledger->opening_cash == 0) {
                $prevLedger = DailyCashLedger::where('user_id', $handover->user_id)
                    ->where('ledger_date', '<', $handover->handover_date)
                    ->where('status', 'closed')
                    ->orderBy('ledger_date', 'desc')
                    ->first();
                
                $ledger->opening_cash = $prevLedger ? $prevLedger->carried_forward : 0;
            }

            // Accurately split physical cash and digital revenue
            $breakdown = $handover->payment_breakdown ?? [];
            if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
            
            $cashPart = 0; $digitalPart = 0;
            if (is_array($breakdown) && !empty($breakdown)) {
                foreach ($breakdown as $key => $val) {
                    $keyUpper = strtoupper($key);
                    if ($keyUpper === 'CASH' || str_contains($keyUpper, 'SHORTAGE')) {
                        $cashPart += floatval($val);
                    } elseif (!in_array($keyUpper, ['TOTAL', 'CASH TOTAL', 'DIGITAL TOTAL'])) {
                        $digitalPart += floatval($val);
                    }
                }
            } else {
                $cashPart = floatval($handover->amount);
            }

            $ledger->total_cash_received += $cashPart;
            $ledger->total_digital_received += $digitalPart;
            
            // Re-calculate expected closing
            $totalExpenses = $ledger->expenses()->sum('amount');
                $ledger->expected_closing_cash = $ledger->opening_cash + $ledger->total_cash_received + $ledger->total_digital_received - $totalExpenses;
            
            $ledger->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Handover verified and funds consolidated into your cash vault.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Undo a handover verification
     */
    public function undoVerifyHandover(Request $request, $id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $handover = FinancialHandover::findOrFail($id);
        if ($handover->status !== 'verified') {
            return response()->json(['success' => false, 'error' => 'Only verified handovers can be undone.'], 400);
        }

        DB::beginTransaction();
        try {
            $ledger = DailyCashLedger::where('user_id', $handover->user_id)
                ->where('ledger_date', $handover->handover_date)
                ->first();

            if ($ledger) {
                // Accurately subtract based on original breakdown
                $breakdown = $handover->payment_breakdown ?? [];
                if (is_string($breakdown)) $breakdown = json_decode($breakdown, true);
                
                $cashPart = 0; $digitalPart = 0;
                if (is_array($breakdown) && !empty($breakdown)) {
                    foreach ($breakdown as $key => $val) {
                        $keyUpper = strtoupper($key);
                        if ($keyUpper === 'CASH' || str_contains($keyUpper, 'SHORTAGE')) {
                            $cashPart += floatval($val);
                        } elseif (!in_array($keyUpper, ['TOTAL', 'CASH TOTAL', 'DIGITAL TOTAL'])) {
                            $digitalPart += floatval($val);
                        }
                    }
                } else {
                    $cashPart = floatval($handover->amount);
                }

                $ledger->total_cash_received = max(0, $ledger->total_cash_received - $cashPart);
                $ledger->total_digital_received = max(0, $ledger->total_digital_received - $digitalPart);
                
                // Re-calculate expected closing
                $totalExpenses = $ledger->expenses()->sum('amount');
                $ledger->expected_closing_cash = $ledger->opening_cash + $ledger->total_cash_received - $totalExpenses;
                
                $ledger->save();
            }

            $handover->update([
                'status' => 'pending',
                'verified_at' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Verification undone. Handover is back to pending.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Counter Reconciliation (Accountant View - Proxy)
     */
    public function counterReconciliation(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to view counter reconciliation.');
        }

        // Use the same controller method but with accountant permissions
        $counterController = new \App\Http\Controllers\Bar\CounterReconciliationController();
        return $counterController->reconciliation($request);
    }

    public function staffShortages()
    {
        $ownerId = $this->getOwnerId();
        
        // 1. Outstanding Shortages (Current Debt)
        $shortages = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
            ->where('difference', '<', 0)
            ->with(['waiter'])
            ->orderBy('reconciliation_date', 'desc')
            ->get();
            
        $groupedShortages = $shortages->groupBy('waiter_id');
        $staffShortageSummaries = [];
        $totalOutstandingShortages = 0;

        foreach ($groupedShortages as $waiterId => $records) {
            $waiter = $records->first()->waiter;
            $totalOwed = abs($records->sum('difference'));
            $totalOutstandingShortages += $totalOwed;
            
            $staffShortageSummaries[] = [
                'waiter' => $waiter,
                'total_owed' => $totalOwed,
                'records' => $records
            ];
        }

        // Sort by the highest owed
        usort($staffShortageSummaries, function($a, $b) {
            return $b['total_owed'] <=> $a['total_owed'];
        });

        // 2. Recent Settlement History (Last 30 Days)
        $thirtyDaysAgo = now()->subDays(30);
        $historyRecords = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
            ->where('notes', 'like', '%"settlements"%')
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->with(['waiter'])
            ->get();
        
        $settlementHistory = [];
        foreach ($historyRecords as $rec) {
            $notes = json_decode($rec->notes, true);
            if (is_array($notes) && isset($notes['settlements'])) {
                foreach ($notes['settlements'] as $s) {
                    $sDate = isset($s['date']) ? \Carbon\Carbon::parse($s['date']) : null;
                    if ($sDate && $sDate->greaterThanOrEqualTo($thirtyDaysAgo)) {
                        $s['waiter_name'] = $rec->waiter->full_name ?? 'N/A';
                        $s['reconciliation_id'] = $rec->id;
                        $s['dept'] = $rec->reconciliation_type === 'food' ? 'KITCHEN' : 'DRINKS';
                        $s['shift_date'] = $rec->reconciliation_date->format('d M Y');
                        $settlementHistory[] = $s;
                    }
                }
            }
        }
        
        // Sort history by settlement date descending
        usort($settlementHistory, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return view('accountant.staff_shortages', compact(
            'staffShortageSummaries', 
            'totalOutstandingShortages',
            'settlementHistory'
        ));
    }

    /**
     * View Reconciliation Details
     */
    public function reconciliationDetails($id, Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to view reconciliation details.');
        }

        $ownerId = $this->getOwnerId();

        // Accountant can view any reconciliation
        $reconciliation = WaiterDailyReconciliation::query()
            ->with(['waiter', 'verifiedBy', 'orders.items.productVariant.product', 'orders.kitchenOrderItems'])
            ->findOrFail($id);

        // Only get orders that are specifically linked to this reconciliation
        $orders = $reconciliation->orders;

        // If AJAX request, return JSON
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'date' => $reconciliation->reconciliation_date->format('F d, Y'),
                    'waiter' => [
                        'name' => $reconciliation->waiter->full_name,
                        'email' => $reconciliation->waiter->email,
                    ],
                    'status' => $reconciliation->status,
                    'expected_amount' => $reconciliation->expected_amount,
                    'submitted_amount' => $reconciliation->submitted_amount,
                    'difference' => $reconciliation->difference,
                    'cash_collected' => $reconciliation->cash_collected,
                    'mobile_money_collected' => $reconciliation->mobile_money_collected,
                    'notes' => $reconciliation->notes,
                    'verified_by' => $reconciliation->verifiedBy ? [
                        'name' => $reconciliation->verifiedBy->full_name,
                        'date' => $reconciliation->verified_at->format('M d, Y H:i'),
                    ] : null,
                    'orders' => $orders->map(function($order) {
                        return [
                            'order_number' => $order->order_number,
                            'date' => $order->created_at->format('M d, Y H:i'),
                            'bar_items' => $order->items ? $order->items->map(function($item) {
                                return [
                                    'quantity' => $item->quantity,
                                    'product_name' => ($item->productVariant && $item->productVariant->product) 
                                        ? $item->productVariant->product->name 
                                        : 'N/A',
                                ];
                            })->toArray() : [],
                            'food_items' => $order->kitchenOrderItems ? $order->kitchenOrderItems->map(function($item) {
                                return [
                                    'quantity' => $item->quantity,
                                    'name' => $item->food_item_name ?? 'N/A',
                                ];
                            })->toArray() : [],
                            'bar_amount' => $order->items ? $order->items->sum('total_price') : 0,
                            'food_amount' => $order->kitchenOrderItems ? $order->kitchenOrderItems->sum('total_price') : 0,
                            'total_amount' => $order->total_amount,
                            'payment_method' => $order->payment_method,
                            'payment_status' => $order->payment_status,
                        ];
                    })->toArray(),
                ]
            ]);
        }

        return view('accountant.reconciliation-details', compact('reconciliation'));
    }

    /**
     * Verify a stock transfer (Final approval by accountant based on expected profit/revenue)
     */
    public function verifyStockTransfer(Request $request, \App\Models\StockTransfer $stockTransfer)
    {
        if (!$this->hasPermission('finance', 'edit') && !$this->hasPermission('reports', 'edit')) {
            return response()->json(['error' => 'You do not have permission to verify stock transfers.'], 403);
        }

        // Only completed transfers can be verified
        if ($stockTransfer->status !== 'completed') {
            return response()->json(['error' => 'Only completed stock transfers can be verified.'], 400);
        }

        // Check if already verified
        if ($stockTransfer->verified_at) {
            return response()->json(['error' => 'This stock transfer has already been verified.'], 400);
        }

        $stockTransfer->update([
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock transfer verified successfully.',
            'transfer' => $stockTransfer->load('verifiedBy', 'productVariant.product')
        ]);
    }

    /**
     * Calculate real-time profit for a completed stock transfer.
     */
    private function calculateRealTimeProfitForTransfer($transfer, $ownerId, $sellingPrice, $buyingPrice, $location = null)
    {
        if ($transfer->status !== 'completed' || !$transfer->productVariant) {
            return 0;
        }

        // Real-time profit is based on items physically sold through the POS.
        // It should match the revenue generated by the quantity sold.
        $transferSales = \App\Models\TransferSale::where('stock_transfer_id', $transfer->id)->get();
        
        $totalProfit = 0;
        foreach ($transferSales as $ts) {
            $itemRevenue = $ts->total_price;
            $itemCogs = $buyingPrice * $ts->quantity;
            $totalProfit += ($itemRevenue - $itemCogs);
        }

        return $totalProfit;
    }

    /**
     * Calculate real-time revenue for a completed stock transfer.
     * Returns array with 'recorded', 'submitted', 'pending', and 'total' amounts.
     */
    private function calculateRealTimeRevenueForTransfer($transfer, $ownerId, $location = null)
    {
        if ($transfer->status !== 'completed' || !$transfer->productVariant) {
            return [
                'recorded' => 0,
                'submitted' => 0,
                'pending' => 0,
                'total' => 0
            ];
        }

        // Real-time revenue tracks exactly what was recorded as sold via the POS.
        // Waiter payment breakdowns might not be perfectly attached to individual items.
        // The business counts sold goods as generated revenue.
        $totalRevenue = \App\Models\TransferSale::where('stock_transfer_id', $transfer->id)->sum('total_price');

        return [
            'recorded' => $totalRevenue,
            'submitted' => $totalRevenue,
            'pending' => 0,
            'total' => $totalRevenue
        ];
    }

    /**
     * Financial Reports
     */
    public function reports(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to view financial reports.');
        }

        $ownerId = $this->getOwnerId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $location = session('active_location');

        // Helper to apply common filters (owner + location)
        $applyFilters = function($query) use ($ownerId, $location) {
            $query->where('user_id', $ownerId);
            if ($location) {
                $query->whereHas('table', function($q) use ($location) {
                    $q->where('location', $location);
                });
            }
            return $query;
        };

        // Revenue by Day (all orders)
        $revenueByDay = [];
        $currentDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($currentDate->lte($end)) {
            $dayOrders = $applyFilters(BarOrder::query())
                ->whereDate('created_at', $currentDate->format('Y-m-d'))
                ->where('payment_status', 'paid')
                ->with(['items', 'kitchenOrderItems', 'orderPayments'])
                ->get();
            
            // Calculate revenue from items (bar + food), not total_amount
            $dayRevenue = $dayOrders->sum(function($order) {
                $barAmount = $order->items && $order->items->isNotEmpty() 
                    ? $order->items->sum('total_price') 
                    : 0;
                $foodAmount = $order->kitchenOrderItems && $order->kitchenOrderItems->isNotEmpty()
                    ? $order->kitchenOrderItems->sum('total_price')
                    : 0;
                return $barAmount + $foodAmount;
            });
            
            $revenueByDay[] = [
                'date' => $currentDate->format('Y-m-d'),
                'date_formatted' => $currentDate->format('M d, Y'),
                'revenue' => $dayRevenue,
                'orders_count' => $dayOrders->count(),
                'cash' => $dayOrders->sum(function($order) {
                    return $order->orderPayments && $order->orderPayments->isNotEmpty()
                        ? $order->orderPayments->where('payment_method', 'cash')->sum('amount')
                        : 0;
                }),
                'mobile_money' => $dayOrders->sum(function($order) {
                    return $order->orderPayments && $order->orderPayments->isNotEmpty()
                        ? $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount')
                        : 0;
                }),
            ];
            
            $currentDate->addDay();
        }

        // Revenue by Waiter (branch-filtered if applicable)
        $revenueByWaiter = Staff::query()
            ->where('user_id', $ownerId)
            ->whereHas('role', function($q) {
                $q->where('name', 'Waiter');
            })
            ->when($location, function($q) use ($location) {
                $q->where('location_branch', $location);
            })
            ->get()
            ->map(function($waiter) use ($startDate, $endDate, $ownerId, $location) {
                $query = BarOrder::query()
                    ->where('user_id', $ownerId)
                    ->where('waiter_id', $waiter->id)
                    ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
                    ->where('payment_status', 'paid');
                
                if ($location) {
                    $query->whereHas('table', function($q) use ($location) {
                        $q->where('location', $location);
                    });
                }

                $orders = $query->with(['items', 'kitchenOrderItems'])->get();

                // Calculate revenue from items (bar + food), not total_amount
                $barSales = $orders->filter(function($order) {
                    return $order->items && $order->items->isNotEmpty();
                })->sum(function($order) {
                    return $order->items->sum('total_price');
                });
                
                $foodSales = $orders->sum(function($order) {
                    return $order->kitchenOrderItems && $order->kitchenOrderItems->isNotEmpty()
                        ? $order->kitchenOrderItems->sum('total_price')
                        : 0;
                });
                
                return [
                    'waiter' => $waiter,
                    'total_revenue' => $barSales + $foodSales,
                    'orders_count' => $orders->count(),
                    'bar_sales' => $barSales,
                    'food_sales' => $foodSales,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        return view('accountant.reports', compact('revenueByDay', 'revenueByWaiter', 'startDate', 'endDate'));
    }

    /**
     * Stock Receipt Reports
     */
    public function stockReceiptsReport(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to view stock receipt reports.');
        }

        $ownerId = $this->getOwnerId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $location = session('active_location');

        $receipts = \App\Models\StockReceipt::where('user_id', $ownerId)
            ->whereBetween('received_date', [$startDate, $endDate])
            ->when($location, function($q) use ($location) {
                // If location is provided, filter by requested_by branch (assuming staff belong to branches)
                $q->whereExists(function($sq) use ($location) {
                    $sq->select(DB::raw(1))
                       ->from('staff')
                       ->whereRaw('staff.user_id = stock_receipts.user_id')
                       ->whereRaw('staff.email = (select email from users where id = stock_receipts.received_by limit 1)')
                       ->where('staff.location_branch', $location);
                });
            })
            ->with(['supplier', 'productVariant.product', 'receivedBy'])
            ->orderBy('received_date', 'desc')
            ->paginate(50);

        $groupSummary = \App\Models\StockReceipt::where('user_id', $ownerId)
            ->whereBetween('received_date', [$startDate, $endDate])
            ->when($location, function($q) use ($location) {
                $q->whereExists(function($sq) use ($location) {
                    $sq->select(DB::raw(1))
                       ->from('staff')
                       ->whereRaw('staff.user_id = stock_receipts.user_id')
                       ->whereRaw('staff.email = (select email from users where id = stock_receipts.received_by limit 1)')
                       ->where('staff.location_branch', $location);
                });
            })
            ->selectRaw('sum(final_buying_cost) as total_buying_cost')
            ->selectRaw('count(distinct receipt_number) as unique_batches')
            ->selectRaw('sum(total_units) as total_items')
            ->first();

        return view('accountant.stock-receipts-report', compact('receipts', 'groupSummary', 'startDate', 'endDate'));
    }

    /**
     * Stock Transfer Reports (with Real-time tracking)
     */
    public function stockTransfersReport(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to view stock transfer reports.');
        }

        $ownerId = $this->getOwnerId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $location = session('active_location');

        $transfers = \App\Models\StockTransfer::where('user_id', $ownerId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
            ->when($location, function($q) use ($location) {
                $q->whereExists(function($sq) use ($location) {
                    $sq->select(DB::raw(1))
                       ->from('staff')
                       ->whereRaw('staff.user_id = stock_transfers.user_id')
                       ->whereRaw('staff.email = (select email from users where id = stock_transfers.requested_by limit 1)')
                       ->where('staff.location_branch', $location);
                });
            })
            ->with(['productVariant.product', 'requestedBy', 'verifiedBy'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($transfer) use ($ownerId, $location) {
                $financials = $transfer->calculateFinancials();
                $transfer->expected_revenue = $financials['revenue'];
                $transfer->expected_profit = $financials['profit'];
                
                $transfer->expected_bottle_revenue = $financials['bottle_revenue'] ?? 0;
                $transfer->expected_glass_revenue = $financials['glass_revenue'] ?? 0;
                $transfer->expected_bottle_profit = $financials['bottle_profit'] ?? 0;
                $transfer->expected_glass_profit = $financials['glass_profit'] ?? 0;
                $transfer->can_sell_tots = $financials['can_sell_tots'] ?? false;
                
                // Track physical inventory levels
                // Use actual counter stock as source of truth; fall back to TransferSale records
                $counterStock = \App\Models\StockLocation::where('user_id', $ownerId)
                    ->where('product_variant_id', $transfer->product_variant_id)
                    ->where('location', 'counter')
                    ->first();

                $transferSaleQty = \App\Models\TransferSale::where('stock_transfer_id', $transfer->id)->sum('quantity');

                if ($counterStock) {
                    // Physically remaining in counter is authoritative, including open portions
                    $openBottle = \App\Models\OpenBottle::where('user_id', $ownerId)
                        ->where('product_variant_id', $transfer->product_variant_id)
                        ->first();
                    
                    $totalTots = $transfer->productVariant->total_tots ?? 0;
                    $portionQty = ($openBottle && $totalTots > 0) ? ($openBottle->tots_remaining / $totalTots) : 0;
                    
                    $physicalRemaining = ($counterStock->quantity ?? 0) + $portionQty;
                    
                    // Sold = Transferred In - Physically Remaining (capped to transfer total)
                    $transfer->sold_quantity = max($transferSaleQty, max(0, $transfer->total_units - $physicalRemaining));
                    $transfer->remaining_quantity = max(0, $transfer->total_units - $transfer->sold_quantity);
                } else {
                    $transfer->sold_quantity = $transferSaleQty;
                    $transfer->remaining_quantity = max(0, $transfer->total_units - $transferSaleQty);
                }
                
                // Real-time data (branch-aware)
                $revenueData = $this->calculateRealTimeRevenueForTransfer($transfer, $ownerId, $location);
                $transfer->real_time_submitted = $revenueData['submitted'];

                // If physical sold_qty matches TransferSale records, trust POS revenue strictly
                // Otherwise fallback to pro-rata estimation
                $transferSaleRevenue = $revenueData['total'];
                $unitRevenue = $transfer->total_units > 0 ? ($transfer->expected_revenue / $transfer->total_units) : $financials['selling_price'];
                $physicalSoldRevenue = $transfer->sold_quantity * $unitRevenue;
                
                if ($transferSaleQty >= ($transfer->sold_quantity - 0.01)) {
                    $transfer->real_time_revenue = $transferSaleRevenue;
                } else {
                    $transfer->real_time_revenue = max($transferSaleRevenue, $physicalSoldRevenue);
                }

                $transferSaleProfit = $this->calculateRealTimeProfitForTransfer(
                    $transfer,
                    $ownerId,
                    $financials['selling_price'],
                    $financials['buying_price'],
                    $location
                );
                $physicalSoldProfit = $transfer->sold_quantity * ($financials['selling_price'] - $financials['buying_price']);
                
                if ($transferSaleQty >= ($transfer->sold_quantity - 0.01)) {
                    $transfer->real_time_profit = $transferSaleProfit;
                } else {
                    $transfer->real_time_profit = max($transferSaleProfit, $physicalSoldProfit);
                }
                // Final metrics for UI
                $totsPerBottle = $transfer->productVariant->total_tots ?? 0;
                $formatQty = function($qty, $tots) {
                    if ($tots <= 0) return number_format($qty, 0) . ' btl';
                    $bottles = (int) floor(round($qty, 4));
                    $remaining = round($qty - $bottles, 4);
                    $glasses = (int) round($remaining * $tots);
                    
                    if ($bottles > 0 && $glasses > 0) return "{$bottles} btl, {$glasses} gls";
                    if ($bottles > 0) return "{$bottles} btl";
                    return "{$glasses} gls";
                };

                $transfer->formatted_stock_in = $formatQty($transfer->total_units, $totsPerBottle);
                $transfer->formatted_sold = $formatQty($transfer->sold_quantity, $totsPerBottle);
                $transfer->formatted_remaining = $formatQty($transfer->remaining_quantity, $totsPerBottle);
                
                $transfer->stock_progress = $transfer->total_units > 0 ? min(100, ($transfer->sold_quantity / $transfer->total_units) * 100) : 0;
                $transfer->revenue_yield = $transfer->expected_revenue > 0 ? ($transfer->real_time_revenue / $transfer->expected_revenue) * 100 : 0;
                
                return $transfer;
            });

        $totals = [
            'expected_revenue' => $transfers->sum('expected_revenue'),
            'expected_profit' => $transfers->sum('expected_profit'),
            'real_time_revenue' => $transfers->sum('real_time_revenue'),
            'real_time_profit' => $transfers->sum('real_time_profit')
        ];

        return view('accountant.stock-transfers-report', compact('transfers', 'totals', 'startDate', 'endDate'));
    }

    /**
     * Export Financial Reports as PDF
     */
    public function exportReportsPdf(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('reports', 'view')) {
            abort(403, 'You do not have permission to export financial reports.');
        }

        $ownerId = $this->getOwnerId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $location = session('active_location');

        // Helper to apply common filters (owner + location)
        $applyFilters = function($query) use ($ownerId, $location) {
            $query->where('user_id', $ownerId);
            if ($location) {
                $query->whereHas('table', function($q) use ($location) {
                    $q->where('location', $location);
                });
            }
            return $query;
        };

        // Revenue by Day (all orders)
        $revenueByDay = [];
        $currentDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($currentDate->lte($end)) {
            $dayOrders = $applyFilters(BarOrder::query())
                ->whereDate('created_at', $currentDate->format('Y-m-d'))
                ->where('payment_status', 'paid')
                ->with(['items', 'kitchenOrderItems', 'orderPayments'])
                ->get();
            
            // Calculate revenue from items (bar + food), not total_amount
            $dayRevenue = $dayOrders->sum(function($order) {
                $barAmount = $order->items && $order->items->isNotEmpty() 
                    ? $order->items->sum('total_price') 
                    : 0;
                $foodAmount = $order->kitchenOrderItems && $order->kitchenOrderItems->isNotEmpty()
                    ? $order->kitchenOrderItems->sum('total_price')
                    : 0;
                return $barAmount + $foodAmount;
            });
            
            $revenueByDay[] = [
                'date' => $currentDate->format('Y-m-d'),
                'date_formatted' => $currentDate->format('M d, Y'),
                'revenue' => $dayRevenue,
                'orders_count' => $dayOrders->count(),
                'cash' => $dayOrders->sum(function($order) {
                    return $order->orderPayments && $order->orderPayments->isNotEmpty()
                        ? $order->orderPayments->where('payment_method', 'cash')->sum('amount')
                        : 0;
                }),
                'mobile_money' => $dayOrders->sum(function($order) {
                    return $order->orderPayments && $order->orderPayments->isNotEmpty()
                        ? $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount')
                        : 0;
                }),
            ];
            
            $currentDate->addDay();
        }

        $revenueByWaiter = Staff::query()
            ->where('user_id', $ownerId)
            ->whereHas('role', function($q) {
                $q->where('name', 'Waiter');
            })
            ->when($location, function($q) use ($location) {
                $q->where('location_branch', $location);
            })
            ->get()
            ->map(function($waiter) use ($startDate, $endDate, $ownerId, $location, $applyFilters) {
                $orders = $applyFilters(BarOrder::query())
                    ->where('waiter_id', $waiter->id)
                    ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()])
                    ->where('payment_status', 'paid')
                    ->with(['items', 'kitchenOrderItems'])
                    ->get();
                
                // Calculate revenue from items (bar + food), not total_amount
                $barSales = $orders->filter(function($order) {
                    return $order->items && $order->items->isNotEmpty();
                })->sum(function($order) {
                    return $order->items->sum('total_price');
                });
                
                $foodSales = $orders->sum(function($order) {
                    return $order->kitchenOrderItems && $order->kitchenOrderItems->isNotEmpty()
                        ? $order->kitchenOrderItems->sum('total_price')
                        : 0;
                });
                
                return [
                    'waiter' => $waiter,
                    'total_revenue' => $barSales + $foodSales,
                    'orders_count' => $orders->count(),
                    'bar_sales' => $barSales,
                    'food_sales' => $foodSales,
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        // Calculate totals
        $totalRevenue = collect($revenueByDay)->sum('revenue');
        $totalCash = collect($revenueByDay)->sum('cash');
        $totalMobileMoney = collect($revenueByDay)->sum('mobile_money');
        $totalOrders = collect($revenueByDay)->sum('orders_count');

        // Generate PDF
        $pdf = Pdf::loadView('accountant.reports-pdf', compact(
            'revenueByDay',
            'revenueByWaiter',
            'startDate',
            'endDate',
            'totalRevenue',
            'totalCash',
            'totalMobileMoney',
            'totalOrders'
        ));

        $filename = 'Financial_Report_' . $startDate . '_to_' . $endDate . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Petty Cash / Fund Issuance List
     */
    public function fundIssuance(Request $request)
    {
        if (!$this->hasPermission('finance', 'view')) {
            abort(403);
        }

        $ownerId = $this->getOwnerId();
        
        $query = PettyCashIssue::where('user_id', $ownerId)
            ->with(['recipient', 'issuer'])
            ->orderBy('issue_date', 'desc');

        if ($request->has('start_date') && $request->has('end_date') && $request->start_date && $request->end_date) {
            $query->whereBetween('issue_date', [$request->start_date, $request->end_date]);
        }

        $issues = $query->paginate(20);
        $staffMembers = Staff::where('user_id', $ownerId)->get();
        
        // Fetch recent ledgers to check for bar profit submission status
        $recentLedgers = \App\Models\DailyCashLedger::where('user_id', $ownerId)
            ->where('ledger_date', '>=', now()->subDays(7))
            ->get()
            ->keyBy(fn($l) => $l->ledger_date->format('Y-m-d'));

        // Fetch recent food handovers to check for kitchen profit submission status
        $recentFoodHandovers = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->where('department', 'food')
            ->where('handover_type', 'accountant_to_owner')
            ->where('handover_date', '>=', now()->subDays(7))
            ->get()
            ->keyBy(fn($h) => \Carbon\Carbon::parse($h->handover_date)->format('Y-m-d'));

        return view('accountant.fund_issuance', compact('issues', 'staffMembers', 'recentLedgers', 'recentFoodHandovers'));
    }

    /**
     * Print Fund Issuance Voucher
     */
    public function printFundIssuance($id)
    {
        $ownerId = $this->getOwnerId();
        $issue = PettyCashIssue::where('user_id', $ownerId)->with(['recipient', 'issuer'])->findOrFail($id);
        
        return view('accountant.fund_issuance_print', compact('issue'));
    }

    /**
     * Store New Fund Issuance
     */
    public function storeFundIssuance(Request $request)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return back()->with('error', 'Unauthorized');
        }

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'amount' => 'required|numeric|min:0',
            'fund_source' => 'required|string|in:circulation,profit',
            'purpose' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        $ownerId = $this->getOwnerId();
        
        // Resolve the actual User ID of the person performing the action
        $issuedByUserId = auth()->id() ?? (session('is_staff') ? \App\Models\Staff::where('id', session('staff_id'))->value('user_id') : null);

        if (!$issuedByUserId) {
             return back()->with('error', 'Your session has expired. Please log in again.');
        }

        $dept = $request->input('department', 'bar');
        $purpose = $request->purpose;
        if ($dept === 'food') {
            $purpose = "[FOOD] " . $purpose;
        }

        // --- FINANCIAL AVAILABILITY CHECK ---
        if ($dept === 'food') {
            // Check Kitchen Handover for the date
            $foodHandover = \App\Models\FinancialHandover::where('user_id', $ownerId)
                ->whereDate('handover_date', $request->issue_date)
                ->where('department', 'food')
                ->where('handover_type', 'staff_to_accountant')
                ->first();

            if (!$foodHandover) {
                return back()->with('error', 'No kitchen handover found for this date. You cannot issue food petty cash until the chef handovers the money.');
            }

            // Calculate already issued food petty cash
            $issuedFood = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->whereDate('issue_date', $request->issue_date)
                ->where('status', 'issued')
                ->where('purpose', 'LIKE', '[FOOD]%')
                ->sum('amount');

            $availableFood = max(0, floatval($foodHandover->amount) - $issuedFood);
            
            if ($request->amount > $availableFood) {
                return back()->with('error', "Insufficient Kitchen Funds! Available food cash is TSh " . number_format($availableFood) . ". You cannot issue TSh " . number_format($request->amount) . ".");
            }
        } else {
            // Standard Bar Logic
            $ledger = \App\Models\DailyCashLedger::where('user_id', $ownerId)
                ->whereDate('ledger_date', $request->issue_date)
                ->first();

            if (!$ledger) {
                return back()->with('error', 'No cash ledger found for the selected date.');
            }

            // Filter out food issues when checking bar availability
            $issuedProfit = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->whereDate('issue_date', $request->issue_date)
                ->where('status', 'issued')
                ->where('fund_source', 'profit')
                ->where('purpose', 'NOT LIKE', '[FOOD]%')
                ->sum('amount');
                
            $issuedCirculation = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->whereDate('issue_date', $request->issue_date)
                ->where('status', 'issued')
                ->where('fund_source', 'circulation')
                ->where('purpose', 'NOT LIKE', '[FOOD]%')
                ->sum('amount');

            if ($request->fund_source === 'profit') {
                $available = max(0, $ledger->profit_generated - $ledger->total_expenses_from_profit - $issuedProfit);
                if ($request->amount > $available) {
                    return back()->with('error', "Insufficient Profit! Available profit remaining is TSh " . number_format($available) . ".");
                }
            } else {
                // For bar circulation, we check against the bar's handover total if possible, or ledger as fallback
                $available = max(0, $ledger->expected_closing_cash - $issuedCirculation);
                if ($request->amount > $available) {
                    return back()->with('error', "Insufficient Funds! Available vault cash is TSh " . number_format($available) . ".");
                }
            }
        }

        $issue = PettyCashIssue::create([
            'user_id' => $ownerId,
            'issued_by' => $issuedByUserId,
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
            'fund_source' => $request->fund_source,
            'purpose' => $purpose,
            'issue_date' => $request->issue_date,
            'notes' => $request->notes,
            'status' => 'issued'
        ]);

        // Synchronize ledger if it exists for this date
        $ledger = \App\Models\DailyCashLedger::where('user_id', $ownerId)
            ->whereDate('ledger_date', $request->issue_date)
            ->first();
        if ($ledger) {
            $ledger->syncTotals()->save();
        }

        return back()->with('success', 'Funds issued successfully and SMS sent to staff.');
    }

    /**
     * Update Existing Fund Issuance
     */
    public function updateFundIssuance(Request $request, $id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return back()->with('error', 'Unauthorized');
        }

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'amount' => 'required|numeric|min:0',
            'fund_source' => 'required|string|in:circulation,profit',
            'purpose' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        $ownerId = $this->getOwnerId();
        $issue = PettyCashIssue::where('user_id', $ownerId)->findOrFail($id);

        // check financial availability
        $ledger = \App\Models\DailyCashLedger::where('user_id', $ownerId)
            ->whereDate('ledger_date', $request->issue_date)
            ->first();

        if (!$ledger) {
            return back()->with('error', 'No cash ledger found for the selected date. Please open the shift first.');
        }

        if ($request->fund_source === 'profit') {
            $available = $ledger->profit_generated;
            if ($request->amount > $available) {
                return back()->with('error', "Insufficient Profit! (Available: TSh " . number_format($available) . ")");
            }
        }

        $issue->update([
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
            'fund_source' => $request->fund_source,
            'purpose' => $request->purpose,
            'issue_date' => $request->issue_date,
            'notes' => $request->notes
        ]);

        // Recalculate ledger for both old and new dates (in case date changed)
        if ($ledger) {
            $ledger->syncTotals()->save();
        }

        return back()->with('success', 'Fund issuance updated successfully.');
    }

    /**
     * Delete Fund Issuance
     */
    public function deleteFundIssuance($id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return back()->with('error', 'Unauthorized');
        }

        $ownerId = $this->getOwnerId();
        $issue = PettyCashIssue::where('user_id', $ownerId)->findOrFail($id);
        $issue->delete();

        // Sync ledger for that date
        $ledger = \App\Models\DailyCashLedger::where('user_id', $ownerId)
            ->whereDate('ledger_date', $issue->issue_date)
            ->first();
        if ($ledger) {
            $ledger->syncTotals()->save();
        }

        return back()->with('success', 'Fund issuance deleted successfully.');
    }
    /**
     * Re-open a previously finalized department shift (undo reconciliation)
     */
    public function reopenDepartmentShift(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'required|string|in:bar,food',
        ]);

        $ownerId = $this->getOwnerId();
        $date = $validated['date'];
        $type = $validated['type'];

        DB::beginTransaction();
        try {
            // Find the reconciliations for this department/date
            $reconciliations = WaiterDailyReconciliation::where('user_id', $ownerId)
                ->where('reconciliation_date', $date)
                ->where('reconciliation_type', $type)
                ->get();

            foreach ($reconciliations as $recon) {
                // We don't necessarily need to touch the orders if they are linked via a relationship
                // but if we want them to show up as 'Served' but 'Un-reconciled', deleting the record is enough.
                $recon->delete();
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Shift re-opened successfully. You can now re-reconcile it.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to re-open shift: ' . $e->getMessage()], 500);
        }
    }



    public function updateFundStatus(Request $request, $id)
    {
        if (!$this->hasPermission('finance', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $issue = PettyCashIssue::findOrFail($id);
        $request->validate(['status' => 'required|in:completed,cancelled']);

        $issue->update(['status' => $request->status]);

        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    public function cashLedger(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $date = $request->get('date', date('Y-m-d'));

        // Cash In: Verified Reconciliations (ONLY Physical Cash)
        $cashIn = WaiterDailyReconciliation::where('user_id', $ownerId)
            ->whereDate('reconciliation_date', $date)
            ->where('status', 'verified')
            ->with('waiter')
            ->get();

        // Independent Cash Injections (Starting Float, Bank Withdrawals, etc.)
        $topups = \App\Models\CashTopup::where('user_id', $ownerId)
            ->whereDate('topup_date', $date)
            ->get();

        // Cash Out: Issued Petty Cash
        $cashOut = PettyCashIssue::where('user_id', $ownerId)
            ->whereDate('issue_date', $date)
            ->where('status', 'issued')
            ->with('recipient')
            ->get();

        $totalIn = $cashIn->sum('cash_collected') + $topups->sum('amount');
        $totalOut = $cashOut->sum('amount');
        $netCash = $totalIn - $totalOut;

        $handover = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $date)
            ->where('handover_type', 'accountant_to_owner')
            ->first();

        // Staff handovers to accountant (Chef, Counter → Accountant)
        $staffHandovers = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $date)
            ->where('handover_type', 'staff_to_accountant')
            ->with('staff')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalStaffHandovers = $staffHandovers->sum('amount');

        return view('accountant.cash_ledger', compact(
            'cashIn', 'topups', 'cashOut', 'totalIn', 'totalOut', 'netCash',
            'date', 'handover', 'staffHandovers', 'totalStaffHandovers'
        ));
    }

    /**
     * Confirm a staff handover (Chef, Counter → Accountant)
     */
    public function confirmStaffHandover($id)
    {
        $ownerId = $this->getOwnerId();
        $handover = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->where('id', $id)
            ->where('handover_type', 'staff_to_accountant')
            ->firstOrFail();

        $handover->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Trigger SMS if this is a 'food' department handover
        if ($handover->department === 'food') {
            try {
                $smsService = new \App\Services\HandoverSmsService();
                $smsService->sendChefHandoverVerifiedSms($handover);

                // Also notify Waiters who had food sales on this date
                $date = $handover->handover_date;
                $waitersWithFoodSales = \App\Models\BarOrder::where('user_id', $ownerId)
                    ->whereDate('created_at', $date)
                    ->whereHas('kitchenOrderItems')
                    ->with('waiter')
                    ->get()
                    ->pluck('waiter')
                    ->unique('id');

                foreach ($waitersWithFoodSales as $waiter) {
                    // Fetch their reconciliation to get the final amount
                    $recon = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                        ->where('waiter_id', $waiter->id)
                        ->whereDate('reconciliation_date', $date)
                        ->where('reconciliation_type', 'food')
                        ->first();
                    
                    $amount = $recon ? $recon->submitted_amount : 0;
                    // Fallback to expected if submitted is 0 (auto-shortage records)
                    if ($amount <= 0 && $recon) {
                        $amount = $recon->expected_amount + $recon->difference;
                    }

                    $smsService->sendWaiterFoodReconciliationVerifiedSms($waiter, $date, $amount);
                }
            } catch (\Exception $e) {
                \Log::error("SMS sending failed for Food Handover Verification: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Staff handover confirmed successfully.');
    }

    public function storeTopup(Request $request)
    {
        $ownerId = $this->getOwnerId();
        
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'source' => 'required|string',
            'topup_date' => 'required|date',
        ]);

        \App\Models\CashTopup::create([
            'user_id' => $ownerId,
            'accountant_id' => session('staff_id'),
            'amount' => $request->amount,
            'topup_date' => $request->topup_date,
            'source' => $request->source,
            'notes' => $request->notes
        ]);

        return back()->with('success', 'Cash addition recorded successfully.');
    }

    public function storeHandover(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $date = $request->input('date', date('Y-m-d'));
        
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        // Check if already exists
        $existing = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $date)
            ->first();

        if ($existing) {
            return back()->with('error', 'Handover for this date already exists.');
        }

        \App\Models\FinancialHandover::create([
            'user_id' => $ownerId,
            'accountant_id' => session('staff_id'),
            'amount' => $request->amount,
            'handover_date' => $date,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        return back()->with('success', 'Handover to Boss successful! Awaiting confirmation.');
    }

    public function confirmHandover($id)
    {
        $ownerId = $this->getOwnerId();
        $handover = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->where('id', $id)
            ->firstOrFail();

        $handover->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return back()->with('success', 'Cash handover confirmed successfully.');
    }

    /**
     * Get orders for a specific department and date (AJAX)
     */
    public function getDepartmentOrders(Request $request)
    {
        if (!$this->hasPermission('finance', 'view') && !$this->hasPermission('bar_orders', 'view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $date = $request->get('date');
        $type = $request->get('type');
        $ownerId = $this->getOwnerId();

        try {
            \Log::info('getDepartmentOrders called', ['date' => $date, 'type' => $type, 'owner' => $ownerId]);
            
            $orders = BarOrder::where('user_id', $ownerId)
                ->whereDate('created_at', $date)
                ->where('status', 'served')
                ->with(['waiter', 'table', 'items', 'kitchenOrderItems', 'orderPayments'])
                ->get();

            \Log::info('Fetched orders count', ['count' => $orders->count()]);

            $filteredOrders = [];
            foreach ($orders as $order) {
                $amount = 0;
                if ($type === 'bar') {
                    $amount = $order->items ? $order->items->sum('total_price') : 0;
                } else {
                    $amount = $order->kitchenOrderItems ? $order->kitchenOrderItems->sum('total_price') : 0;
                }

                if ($amount > 0) {
                    $filteredOrders[] = [
                        'order_number' => $order->order_number,
                        'waiter_name' => $order->waiter->full_name ?? 'N/A',
                        'table_name' => $order->table->name ?? 'Direct',
                        'total_amount' => $amount,
                        'payment_status' => $order->payment_status,
                        'created_at' => $order->created_at->format('H:i'),
                    ];
                }
            }

            $breakdown = $this->getDetailedPaymentBreakdown($orders, $type);

            $reconciliations = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                ->whereDate('reconciliation_date', $date)
                ->where('reconciliation_type', $type)
                ->get();
            
            $settlements = [];
            foreach ($reconciliations as $r) {
                $notes = json_decode($r->notes, true);
                if (is_array($notes) && isset($notes['settlements'])) {
                    foreach ($notes['settlements'] as $s) {
                        $s['waiter_name'] = $r->waiter->full_name ?? 'N/A';
                        $s['reconciliation_id'] = $r->id;
                        $settlements[] = $s;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'orders' => $filteredOrders,
                'payment_breakdown' => $breakdown,
                'settlements' => $settlements
            ]);
        } catch (\Exception $e) {
            \Log::error('getDepartmentOrders failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Group payments by method and reference
     */
    private function getDetailedPaymentBreakdown($orders, $type)
    {
        $breakdownByMethod = [];
        foreach ($orders as $order) {
            $barAmount = $order->items ? $order->items->sum('total_price') : 0;
            $foodAmount = $order->kitchenOrderItems ? $order->kitchenOrderItems->sum('total_price') : 0;
            $totalAmount = $barAmount + $foodAmount;
            $proportion = $totalAmount > 0 ? (($type === 'bar' ? $barAmount : $foodAmount) / $totalAmount) : 0;

            if ($proportion <= 0) continue;

            foreach ($order->orderPayments as $payment) {
                $method = $payment->payment_method;
                $amount = $payment->amount * $proportion;
                
                if (!isset($breakdownByMethod[$method])) {
                    $breakdownByMethod[$method] = [
                        'total' => 0,
                        'transactions' => []
                    ];
                }
                $breakdownByMethod[$method]['total'] += $amount;
                $breakdownByMethod[$method]['transactions'][] = [
                    'order' => $order->order_number,
                    'waiter' => $order->waiter->full_name ?? 'N/A',
                    'amount' => $amount,
                    'reference' => $payment->transaction_reference,
                    'time' => $payment->created_at->format('H:i')
                ];
            }
        }
        return $breakdownByMethod;
    }

    /**
     * Clear/Pay a shortage for a department (Accountant Action)
     */
    public function payShortage(Request $request)
    {
        $request->validate([
            'date'      => 'required|date',
            'type'      => 'required|string',
            'amount'    => 'required|numeric|min:0',
            'channel'   => 'required|string|in:cash,mobile_money,bank_transfer,pos_card,salary_deduction',
            'reference' => 'nullable|string|max:500',
        ]);

        $ownerId = $this->getOwnerId();
        
        $handover = \App\Models\FinancialHandover::where('user_id', $ownerId)
            ->whereDate('handover_date', $request->date)
            ->where('department', $request->type)
            ->first();

        if (!$handover) {
            return response()->json(['success' => false, 'error' => 'Handover records not found for this department.'], 404);
        }

        $existingNotes = $handover->notes ?? '';
        $channel = $request->channel;
        $amount = (float)$request->amount;

        // 1. Accumulate total paid
        $shortagePaidTotal = 0;
        if (preg_match('/\[ShortagePaidTotal:(\d+)\]/', $existingNotes, $m)) {
            $shortagePaidTotal = (int)$m[1];
        }
        $newTotal = $shortagePaidTotal + $amount;
        
        // 2. Accumulate channel breakdown
        $breakdown = [];
        if (preg_match('/\[ShortagePaidBreakdown:([^\]]+)\]/', $existingNotes, $bm)) {
            foreach (explode(',', $bm[1]) as $pair) {
                $kv = explode('=', $pair);
                if (count($kv) == 2) $breakdown[$kv[0]] = (float)$kv[1];
            }
        }
        $breakdown[$channel] = ($breakdown[$channel] ?? 0) + $amount;
        $breakdownStr = "";
        foreach ($breakdown as $k => $v) {
            $breakdownStr .= ($breakdownStr ? "," : "") . "{$k}={$v}";
        }

        // Clean up old tags
        $newNotes = preg_replace('/\[ShortagePaidTotal:\d+\]/', '', $existingNotes);
        $newNotes = preg_replace('/\[ShortagePaidBreakdown:[^\]]+\]/', '', $newNotes);
        $newNotes = trim($newNotes);

        // Append updated tags
        $newNotes .= "\n[ShortagePaidTotal:{$newTotal}]";
        $newNotes .= "\n[ShortagePaidBreakdown:{$breakdownStr}]";

        // Append timestamped note entry
        $timestamp = now()->format('d M Y H:i');
        $noteEntry = "Shortage payment of TSh " . number_format($amount) . " (" . strtoupper(str_replace('_', ' ', $channel)) . ") recorded on {$timestamp}";
        if ($request->reference) {
            $noteEntry .= " — " . $request->reference;
        }
        $newNotes .= "\n[ShortageNote: {$noteEntry}]";
        
        $handover->notes = $newNotes;
        $handover->save();

        return response()->json(['success' => true, 'message' => 'Shortage payment recorded successfully.']);
    }
    /**
     * Display the business trends and profitability report
     */
    public function businessTrends(Request $request)
    {
        $ownerId = $this->getOwnerId();

        $period = $request->get('period', '7days');
        $endDate = now()->endOfDay();
        if ($period == '30days') $startDate = now()->subDays(29)->startOfDay();
        elseif ($period == 'month') $startDate = now()->startOfMonth()->startOfDay();
        else $startDate = now()->subDays(6)->startOfDay();

        // Pull directly from the Daily Cash Ledger — already perfectly consolidated
        $ledgersQuery = \App\Models\DailyCashLedger::whereBetween('ledger_date', [$startDate, $endDate])
            ->orderBy('ledger_date');
            
        if (!auth()->user()?->isAdmin()) {
            $ledgersQuery->where('user_id', $ownerId);
        }
        $ledgers = $ledgersQuery->get();

        $foodProfitsQuery = \App\Models\FinancialHandover::where('department', 'food')
            ->where('handover_type', 'accountant_to_owner')
            ->where('status', 'confirmed')
            ->whereBetween('handover_date', [$startDate, $endDate]);
            
        if (!auth()->user()?->isAdmin()) {
            $foodProfitsQuery->where('user_id', $ownerId);
        }
        $foodProfits = $foodProfitsQuery->get();
        $dailyPerformance = [];
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalExpenses = 0;

        // Walk through every day in range to fill gaps with zeros
        for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
            $dayStr = $d->format('Y-m-d');
            $ledger = $ledgers->first(fn($l) => \Carbon\Carbon::parse($l->ledger_date)->format('Y-m-d') === $dayStr);
            $food   = $foodProfits->first(fn($f) => \Carbon\Carbon::parse($f->handover_date)->format('Y-m-d') === $dayStr);

            $dayRevenue   = $ledger ? (float)($ledger->total_cash_received + $ledger->total_digital_received) : 0;
            $dayExpenses  = $ledger ? (float)($ledger->total_expenses ?? 0) : 0;
            $barProfitStock = $ledger ? (float)($ledger->profit_generated ?? max(0, $dayRevenue - $dayExpenses)) : 0;
            $barProfit = max(0, $barProfitStock - $dayExpenses);
            
            $foodProfit = $food ? (float)$food->amount : 0;
            
            // Integrate food profit as additional revenue/profit
            $integratedRevenue = $dayRevenue + $foodProfit;
            $integratedProfit = $barProfit + $foodProfit;

            $totalRevenue  += $integratedRevenue;
            $totalExpenses += $dayExpenses;

            $dailyPerformance[] = [
                'label'      => $d->format('M d'),
                'revenue'    => $integratedRevenue,
                'bar_profit' => $barProfit,
                'food_profit' => $foodProfit,
                'total_profit' => $integratedProfit,
                'expenses'   => $dayExpenses,
            ];
        }

        // Approximate COGS = Revenue - Profit - Expenses
        $totalNetProfit = collect($dailyPerformance)->sum('total_profit');
        $totalCogs = max(0, $totalRevenue - $totalNetProfit - $totalExpenses);

        // Expense Distribution by Category (from PettyCashIssue purposes)
        $expenseItems = \App\Models\PettyCashIssue::where('user_id', $ownerId)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->get();

        $expenseByCategory = $expenseItems
            ->groupBy(function($item) {
                return $item->purpose ? ucfirst(trim($item->purpose)) : 'General';
            })
            ->map(fn($group) => $group->sum('amount'))
            ->sortByDesc(fn($amount) => $amount)
            ->toArray();

        // Historical Comparison (Everyday for the last 7 days)
        $historical = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $dayStr = $d->format('Y-m-d');

            $dayLedger = \App\Models\DailyCashLedger::where('user_id', $ownerId)
                ->where('ledger_date', clone $d->startOfDay())
                ->first();
                
            $dayFood = \App\Models\FinancialHandover::where('user_id', $ownerId)
                ->where('department', 'food')
                ->where('handover_type', 'accountant_to_owner')
                ->where('status', 'confirmed')
                ->whereDate('handover_date', clone $d->startOfDay())
                ->get();
                
            $dayPettyCash = \App\Models\PettyCashIssue::where('user_id', $ownerId)
                ->where('status', 'issued')
                ->whereDate('issue_date', clone $d->startOfDay())
                ->sum('amount');

            $dRev = $dayLedger ? (float)($dayLedger->total_cash_received + $dayLedger->total_digital_received) : 0;
            $dExp = $dayLedger ? (float)($dayLedger->total_expenses ?? 0) : (float)$dayPettyCash;
            $dBarProfitStock = $dayLedger ? (float)($dayLedger->profit_generated ?? 0) : 0;
            $dBarProfit = max(0, $dBarProfitStock - $dExp);
            
            $dFoodProfit = $dayFood->sum('amount');

            $historical[] = [
                'label'        => $d->format('D, M d'),
                'revenue'      => $dRev + $dFoodProfit,
                'bar_profit'   => $dBarProfit,
                'food_profit'  => $dFoodProfit,
                'total_profit' => $dBarProfit + $dFoodProfit,
                'expenses'     => $dExp,
            ];
        }

        return view('accountant.reports.business-trends', compact(
            'period', 'dailyPerformance', 'totalRevenue', 'totalCogs', 'totalExpenses', 'totalNetProfit', 'historical', 'expenseByCategory'
        ));
    }

    /**
     * Display the waiter trends and performance audit
     */
    public function waiterTrends(Request $request)
    {
        $ownerId = $this->getOwnerId();

        $period = $request->get('period', '7days');
        $endDate = now()->endOfDay();
        if ($period == '30days') $startDate = now()->subDays(29)->startOfDay();
        elseif ($period == 'month') $startDate = now()->startOfMonth()->startOfDay();
        else $startDate = now()->subDays(6)->startOfDay();

        // Build date labels for X axis
        $labels = [];
        $dateRange = [];
        for ($d = $startDate->copy(); $d <= $endDate; $d->addDay()) {
            $labels[]    = $d->format('M d');
            $dateRange[] = $d->format('Y-m-d');
        }

        $ordersQuery = \App\Models\BarOrder::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled') // Capture all active valid orders (fixes missing food orders stuck in 'pending' bar states)
            ->whereNotNull('waiter_id');
            
        if (!auth()->user()?->isAdmin()) {
            $ordersQuery->where('user_id', $ownerId);
        }
        
        $orders = $ordersQuery->with(['waiter', 'items', 'kitchenOrderItems'])->get();

        $grouped = $orders->groupBy('waiter_id');

        $waiterData      = collect();
        $totalWaitersRevenue = 0;

        foreach ($grouped as $waiterId => $waiterOrders) {
            $waiter = $waiterOrders->first()->waiter;
            if (!$waiter) continue;
            
            // Strictly exclude non-waiter staff from Waiter tracking
            if (stripos($waiter->full_name, 'counter') !== false || stripos($waiter->phone, 'counter') !== false) {
                continue;
            }
            if ($waiter->role && strtolower($waiter->role->name) !== 'waiter') {
                continue;
            }

            $orderCount = $waiterOrders->count(); // Undeniable distinct order counts

            // Calculate unified absolute total revenue generated by this waiter
            $barRevenueTotal = $waiterOrders->sum(function($order) {
                return $order->items ? $order->items->where('status', '!=', 'cancelled')->sum('total_price') : 0;
            });
            $foodRevenueTotal = $waiterOrders->sum(function($order) {
                return $order->kitchenOrderItems ? $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0;
            });
            $revenue = $barRevenueTotal + $foodRevenueTotal;

            // Build daily flow array aligned to date labels
            $dailyFlow = array_map(function($dayStr) use ($waiterOrders) {
                $dayOrders = $waiterOrders->filter(fn($o) => \Carbon\Carbon::parse($o->created_at)->format('Y-m-d') === $dayStr);
                return $dayOrders->sum(function($order) {
                    $barAmount = $order->items ? $order->items->where('status', '!=', 'cancelled')->sum('total_price') : 0;
                    $foodAmount = $order->kitchenOrderItems ? $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0;
                    return $barAmount + $foodAmount;
                });
            }, $dateRange);

            $totalWaitersRevenue += $revenue;
            $waiterData->push([
                'id'         => $waiterId,
                'name'       => strtoupper(explode(' ', trim($waiter->full_name))[0]),
                'orders'       => $orderCount,
                'revenue'      => $revenue,
                'bar_revenue'  => $barRevenueTotal,
                'food_revenue' => $foodRevenueTotal,
                'daily_flow'   => $dailyFlow,
            ]);
        }

        // Sort by revenue
        $waiterData = $waiterData->sortByDesc('revenue')->values();

        // Calculate market share
        $waiterData = $waiterData->map(function($data) use ($totalWaitersRevenue) {
            $data['share'] = $totalWaitersRevenue > 0
                ? round(($data['revenue'] / $totalWaitersRevenue) * 100, 1)
                : 0;
            return $data;
        });

        return view('accountant.reports.waiter-trends', compact('waiterData', 'labels', 'totalWaitersRevenue', 'period'));
    }
}
