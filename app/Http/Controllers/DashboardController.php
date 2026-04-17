<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Subscription;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index($role = null)
    {
        // IMPORTANT: Check for session conflicts - ensure only one type of session exists
        $isStaff = session('is_staff');
        $isUser = auth()->check();

        // If both exist, there's a conflict - clear staff session and use user
        if ($isStaff && $isUser) {
            session()->forget(['is_staff', 'staff_id', 'staff_name', 'staff_email', 'staff_role_id', 'staff_user_id']);
            $isStaff = false;
        }

        // Check if this is a staff member or a Super Admin managing the business
        if ($isStaff || ($isUser && auth()->user()->isAdmin())) {
            if ($isStaff) {
                $staff = \App\Models\Staff::find(session('staff_id'));
                
                if (!$staff || !$staff->is_active) {
                    session()->forget(['is_staff', 'staff_id', 'staff_name', 'staff_email', 'staff_role_id', 'staff_user_id']);
                    return redirect()->route('login')->with('error', 'Your staff account is no longer active.');
                }

                // IMPORTANT: Verify staff email matches session
                if ($staff->email !== session('staff_email')) {
                    session()->forget(['is_staff', 'staff_id', 'staff_name', 'staff_email', 'staff_role_id', 'staff_user_id']);
                    return redirect()->route('login')->with('error', 'Session mismatch. Please login again.');
                }

                $owner = $staff->owner;
                $roleSlug = $staff->role ? \Illuminate\Support\Str::slug($staff->role->name) : 'staff';
                $roleName = strtolower($staff->role->name ?? '');
            } else {
                $staff = auth()->user();
                $owner = auth()->user();
                $roleSlug = 'admin';
                $roleName = 'manager'; // Treat admin as manager for business view
            }
            
            // Redirect specific staff roles to their dedicated dashboards (not for admin users)
            if ($isStaff) {
                if (strtolower($staff->role->name ?? '') === 'counter') {
                    return redirect()->route('bar.counter.dashboard');
                }
                if (strtolower($staff->role->name ?? '') === 'waiter') {
                    return redirect()->route('bar.waiter.dashboard');
                }
                if (strtolower($staff->role->name ?? '') === 'chef') {
                    return redirect()->route('bar.chef.dashboard');
                }
                if (strtolower($staff->role->name ?? '') === 'accountant') {
                    return redirect()->route('accountant.dashboard');
                }
                if (strtolower($staff->role->name ?? '') === 'marketing') {
                    return redirect()->route('marketing.dashboard');
                }

                // Super Admin staff see the manager dashboard (Manager View)
                if (in_array($roleName, ['super admin', 'super administrator', 'super_admin']) || in_array($roleSlug, ['super-admin', 'superadmin'])) {
                    $roleName = 'manager';
                }

                // If URL doesn't include the role, redirect to include it (only for staff)
                if (!$role || $role !== $roleSlug) {
                    return redirect()->route('dashboard.role', ['role' => $roleSlug]);
                }
            }

            // Route Manager/Admin to dedicated dashboard with rich data
            $statistics = [];
            $ownerId  = $owner->id;

            if ($roleName === 'manager' || $roleName === 'super admin' || $roleSlug === 'super-admin' || (auth()->check() && auth()->user()->role === 'admin')) {
                $location = session('active_location');

                // Ensure today's ledger exists for the manager's trend chart
                $todayDate = date('Y-m-d');
                \App\Models\DailyCashLedger::firstOrCreate(
                    ['user_id' => $ownerId, 'ledger_date' => $todayDate],
                    [
                        'opening_cash' => \App\Models\DailyCashLedger::where('user_id', $ownerId)
                            ->where('ledger_date', '<', $todayDate)
                            ->where('status', 'closed')
                            ->orderBy('ledger_date', 'desc')
                            ->value('carried_forward') ?? 0,
                        'status' => 'open',
                    ]
                );

                // Helper to apply branch filter to various models
                // It checks both the waiter/staff associated and the table location (if applicable)
                $applyLocation = function($query, $staffKey = 'waiter_id', $tableCheck = true) use ($location) {
                    $isAdmin = auth()->check() && auth()->user()->isAdmin();
                    if ($location && !$isAdmin) {
                        $query->where(function($q) use ($location, $staffKey, $tableCheck) {
                            // Filter by staff/waiter's branch
                            $q->whereExists(function ($sq) use ($location, $staffKey) {
                                $sq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', $staffKey)
                                   ->where('staff.location_branch', $location);
                            });

                            // OR Filter by table's location (if applicable)
                            if ($tableCheck) {
                                $q->orWhereHas('table', function($sq) use ($location) {
                                    $sq->where('location', $location);
                                });
                            }
                        });
                    }
                    return $query;
                };

                // ── Today's revenue (Live Real-Time Orders)
                $todayBarSales = \App\Models\OrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', '!=', 'cancelled')
                      ->whereDate('created_at', today());
                      
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->where(function($sq) use ($location) {
                            $sq->whereExists(function ($ssq) use ($location) {
                                $ssq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', 'orders.waiter_id')
                                   ->where('staff.location_branch', $location);
                            })->orWhereHas('table', function($ssq) use ($location) {
                                $ssq->where('location', $location);
                            });
                        });
                    }
                })->sum('total_price');

                $todayFoodSales = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', '!=', 'cancelled')
                      ->whereDate('created_at', today());
                      
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->where(function($sq) use ($location) {
                            $sq->whereExists(function ($ssq) use ($location) {
                                $ssq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', 'orders.waiter_id')
                                   ->where('staff.location_branch', $location);
                            })->orWhereHas('table', function($ssq) use ($location) {
                                $ssq->where('location', $location);
                            });
                        });
                    }
                })->where('status', '!=', 'cancelled')->sum('total_price');

                $todayRevenue = $todayBarSales + $todayFoodSales;

                // ── This month revenue (Live Real-Time Orders)
                $monthBarSalesRaw = \App\Models\OrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', '!=', 'cancelled')
                      ->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                      
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->where(function($sq) use ($location) {
                            $sq->whereExists(function ($ssq) use ($location) {
                                $ssq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', 'orders.waiter_id')
                                   ->where('staff.location_branch', $location);
                            })->orWhereHas('table', function($ssq) use ($location) {
                                $ssq->where('location', $location);
                            });
                        });
                    }
                })->sum('total_price');

                $monthFoodSalesRaw = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', '!=', 'cancelled')
                      ->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                      
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->where(function($sq) use ($location) {
                            $sq->whereExists(function ($ssq) use ($location) {
                                $ssq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', 'orders.waiter_id')
                                   ->where('staff.location_branch', $location);
                            })->orWhereHas('table', function($ssq) use ($location) {
                                $ssq->where('location', $location);
                            });
                        });
                    }
                })->where('status', '!=', 'cancelled')->sum('total_price');

                $monthRevenue = $monthBarSalesRaw + $monthFoodSalesRaw;

                // ── Today's orders
                $todayOrdersQuery = \App\Models\BarOrder::whereDate('created_at', today())
                    ->where('status', '!=', 'cancelled');
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $todayOrdersQuery->where('user_id', $ownerId);
                }
                $todayOrders = $applyLocation($todayOrdersQuery)->count();

                // ── Pending orders
                $pendingOrdersQuery = \App\Models\BarOrder::where('status', 'pending');
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $pendingOrdersQuery->where('user_id', $ownerId);
                }
                $pendingOrders = $applyLocation($pendingOrdersQuery)->count();

                // ── Stock transfers summary
                // Filter by requester branch
                $pTransfersQuery = \App\Models\StockTransfer::where('status', 'pending');
                $aTransfersQuery = \App\Models\StockTransfer::where('status', 'approved');
                $cTransfersQuery = \App\Models\StockTransfer::where('status', 'completed')->whereDate('updated_at', today());

                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $pTransfersQuery->where('user_id', $ownerId);
                    $aTransfersQuery->where('user_id', $ownerId);
                    $cTransfersQuery->where('user_id', $ownerId);
                }

                $pendingTransfers  = $applyLocation($pTransfersQuery, 'requested_by', false)->count();
                $approvedTransfers = $applyLocation($aTransfersQuery, 'requested_by', false)->count();
                $completedTransfersToday = $applyLocation($cTransfersQuery, 'requested_by', false)->count();

                // ── Total transfer sales value
                $transSalesQuery = \App\Models\TransferSale::whereHas('stockTransfer', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', 'completed')
                      ->whereMonth('created_at', now()->month);
                    
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->whereExists(function ($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('staff')
                               ->whereColumn('staff.id', 'stock_transfers.requested_by')
                               ->where('staff.location_branch', $location);
                        });
                    }
                });
                $totalTransferSalesValue = $transSalesQuery->sum('total_price');

                // ── Recent stock receipts (this month)
                // Filter by received_by branch
                $recentReceiptsQuery = \App\Models\StockReceipt::with(['productVariant.product', 'supplier'])
                    ->whereMonth('received_date', now()->month);
                
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $recentReceiptsQuery->where('user_id', $ownerId);
                    if ($location) {
                        $recentReceiptsQuery->whereExists(function ($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('staff')
                               ->whereColumn('staff.id', 'stock_receipts.received_by')
                               ->where('staff.location_branch', $location);
                        });
                    }
                }
                
                $recentReceipts = $recentReceiptsQuery->orderBy('received_date', 'desc')
                    ->limit(8)
                    ->get();

                // ── Monthly Purchase Cost
                $monthlyPurchaseCostQuery = \App\Models\StockReceipt::whereMonth('received_date', now()->month);
                
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $monthlyPurchaseCostQuery->where('user_id', $ownerId);
                    if ($location) {
                        $monthlyPurchaseCostQuery->whereExists(function ($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('staff')
                               ->whereColumn('staff.id', 'stock_receipts.received_by')
                               ->where('staff.location_branch', $location);
                        });
                    }
                }
                $monthlyPurchaseCost = $monthlyPurchaseCostQuery->sum('final_buying_cost');

                // ── Recent stock transfers (last 8)
                $recentTransfers = $applyLocation(\App\Models\StockTransfer::where('user_id', $ownerId), 'requested_by', false)
                    ->with(['productVariant.product', 'requestedBy', 'approvedBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit(8)
                    ->get();

                // ── Revenue last 7 days
                $dateRange = collect(range(0, 6))->map(fn($day) => now()->subDays($day)->format('Y-m-d'))->reverse()->values();
                
                $trendQuery = \App\Models\BarOrder::where('status', '!=', 'cancelled')
                    ->where('created_at', '>=', now()->subDays(6)->startOfDay());
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $trendQuery->where('user_id', $ownerId);
                }
                $trendOrdersRaw = $applyLocation($trendQuery)
                    ->with(['items', 'kitchenOrderItems'])
                    ->get();
                    
                $revenueTrend = $dateRange->map(function($dateStr) use ($trendOrdersRaw) {
                    $dayOrders = $trendOrdersRaw->filter(fn($o) => \Carbon\Carbon::parse($o->created_at)->format('Y-m-d') === $dateStr);
                    $barRev = $dayOrders->sum(fn($o) => $o->items ? $o->items->sum('total_price') : 0);
                    $foodRev = $dayOrders->sum(fn($o) => $o->kitchenOrderItems ? $o->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0);
                    
                    return (object)[
                        'date'        => $dateStr,
                        'revenue'     => $barRev + $foodRev,
                        'bar_revenue' => $barRev,
                        'food_revenue'=> $foodRev,
                        'orders'      => $dayOrders->count()
                    ];
                })->values();

                // ── Top selling DRINKS this month
                $topDrinks = \App\Models\OrderItem::with('productVariant.product')
                    ->whereHas('order', function($q) use ($ownerId, $location) {
                        if (!auth()->check() || auth()->user()->role !== 'admin') {
                            $q->where('user_id', $ownerId);
                        }
                        $q->where('status', '!=', 'cancelled')
                          ->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                        
                        if ($location && auth()->user()->role !== 'admin') {
                            $q->where(function($sq) use ($location) {
                                $sq->whereExists(function ($ssq) use ($location) {
                                    $ssq->select(\DB::raw(1))
                                       ->from('staff')
                                       ->whereColumn('staff.id', 'orders.waiter_id')
                                       ->where('staff.location_branch', $location);
                                })->orWhereHas('table', function($ssq) use ($location) {
                                    $ssq->where('location', $location);
                                });
                            });
                        }
                    })
                    ->whereNotNull('product_variant_id')
                    ->selectRaw('product_variant_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
                    ->groupBy('product_variant_id')
                    ->orderByDesc('total_sold')
                    ->limit(6)
                    ->get()
                    ->map(function($item) {
                        $item->product_full_name = $item->productVariant ? $item->productVariant->display_name : 'Unknown';
                        $item->type = 'drink';
                        return $item;
                    });

                // ── Top selling FOOD items this month
                $topFoodItems = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId) {
                        $q->where('user_id', $ownerId)
                          ->where('status', '!=', 'cancelled')
                          ->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    })
                    ->where('status', '!=', 'cancelled')
                    ->selectRaw('food_item_name, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
                    ->groupBy('food_item_name')
                    ->orderByDesc('total_sold')
                    ->limit(4)
                    ->get()
                    ->map(function($item) {
                        $item->product_full_name = $item->food_item_name ?? 'Food Item';
                        $item->type = 'food';
                        return $item;
                    });

                // Merge drinks + food, sorted by total_sold
                $topProducts = $topDrinks->concat($topFoodItems)
                    ->sortByDesc('total_sold')
                    ->values();
                
                // ── Warehouse stock statistics
                // Filter by variants that have been received or transferred into this branch context
                $warehouseStockItemsQuery = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->whereHas('stockLocations', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId)->where('location', 'warehouse')->where('quantity', '>', 0);
                });

                if ($location) {
                    // Filter variants that have some activity in this branch (receipts or orders)
                    $warehouseStockItemsQuery->where(function($q) use ($location) {
                        $q->whereExists(function($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('stock_receipts')
                               ->join('staff', 'stock_receipts.received_by', '=', 'staff.id')
                               ->whereColumn('stock_receipts.product_variant_id', 'product_variants.id')
                               ->where('staff.location_branch', $location);
                        })->orWhereExists(function($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('order_items')
                               ->join('orders', 'order_items.order_id', '=', 'orders.id')
                               ->join('staff', 'orders.waiter_id', '=', 'staff.id')
                               ->whereColumn('order_items.product_variant_id', 'product_variants.id')
                               ->where('staff.location_branch', $location);
                        });
                    });
                }
                $warehouseStockItems = $warehouseStockItemsQuery->count();

                $counterStockItemsQuery = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->whereHas('stockLocations', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId)->where('location', 'counter')->where('quantity', '>', 0);
                });

                if ($location) {
                    $counterStockItemsQuery->where(function($q) use ($location) {
                        $q->whereExists(function($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('stock_receipts')
                               ->join('staff', 'stock_receipts.received_by', '=', 'staff.id')
                               ->whereColumn('stock_receipts.product_variant_id', 'product_variants.id')
                               ->where('staff.location_branch', $location);
                        })->orWhereExists(function($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('order_items')
                               ->join('orders', 'order_items.order_id', '=', 'orders.id')
                               ->join('staff', 'orders.waiter_id', '=', 'staff.id')
                               ->whereColumn('order_items.product_variant_id', 'product_variants.id')
                               ->where('staff.location_branch', $location);
                        });
                    });
                }
                $counterStockItems = $counterStockItemsQuery->count();

                $lowStockThreshold = \App\Models\SystemSetting::get('low_stock_threshold_' . $ownerId, 10);
                $lowStockListQuery = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->with(['product', 'stockLocations' => function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                }]);

                if ($location) {
                    $lowStockListQuery->where(function($q) use ($location) {
                        $q->whereExists(function($sq) use ($location) {
                            $sq->select(\DB::raw(1))
                               ->from('stock_receipts')
                               ->join('staff', 'stock_receipts.received_by', '=', 'staff.id')
                               ->whereColumn('stock_receipts.product_variant_id', 'product_variants.id')
                               ->where('staff.location_branch', $location);
                        });
                    });
                }

                $lowStockList = $lowStockListQuery->get()
                ->filter(function($variant) use ($lowStockThreshold) {
                    $warehouseQty = optional($variant->stockLocations->where('location', 'warehouse')->first())->quantity ?? 0;
                    $counterQty   = optional($variant->stockLocations->where('location', 'counter')->first())->quantity ?? 0;
                    $totalQty = $warehouseQty + $counterQty;
                    return $totalQty > 0 && $totalQty < $lowStockThreshold;
                })
                ->take(10);

                // ── Category Distribution: Drinks categories + Food dishes (this month)
                $drinkCatsQuery = \App\Models\OrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                    if (!auth()->check() || auth()->user()->role !== 'admin') {
                        $q->where('user_id', $ownerId);
                    }
                    $q->where('status', '!=', 'cancelled')
                      ->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                    
                    if ($location && auth()->user()->role !== 'admin') {
                        $q->where(function($sq) use ($location) {
                            $sq->whereExists(function ($ssq) use ($location) {
                                $ssq->select(\DB::raw(1))
                                   ->from('staff')
                                   ->whereColumn('staff.id', 'orders.waiter_id')
                                   ->where('staff.location_branch', $location);
                            })->orWhereHas('table', function($ssq) use ($location) {
                                $ssq->where('location', $location);
                            });
                        });
                    }
                });
                
                $drinkCategories = $drinkCatsQuery
                    ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->selectRaw('products.category as category, SUM(order_items.quantity) as total_sold, SUM(order_items.total_price) as total_revenue')
                    ->groupBy('products.category')
                    ->orderByDesc('total_sold')
                    ->get()
                ->map(fn($item) => (object)[
                    'category'      => $item->category ?? 'Drinks',
                    'total_sold'    => (int) $item->total_sold,
                    'total_revenue' => (float) $item->total_revenue,
                    'type'          => 'drink',
                ]);

                // Food items — grouped by their actual food category
                $foodDishes = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId) {
                        $q->where('user_id', $ownerId)
                          ->where('status', '!=', 'cancelled')
                          ->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    })
                    ->leftJoin('food_items', 'kitchen_order_items.food_item_id', '=', 'food_items.id')
                    ->where('kitchen_order_items.status', '!=', 'cancelled')
                    ->selectRaw('IFNULL(food_items.category, "Meals") as category_name, SUM(kitchen_order_items.quantity) as total_sold, SUM(kitchen_order_items.total_price) as total_revenue')
                    ->groupByRaw('IFNULL(food_items.category, "Meals")')
                    ->get()
                    ->map(fn($item) => (object)[
                        'category'      => $item->category_name,
                        'total_sold'    => (int) $item->total_sold,
                        'total_revenue' => (float) $item->total_revenue,
                        'type'          => 'food',
                    ]);

                // Merge and sort
                $categoryDistribution = $drinkCategories->concat($foodDishes)->sortByDesc('total_sold')->values();

                // ── Monthly Targets Progress
                $monthlyTargets = \App\Models\SalesTarget::where('user_id', $ownerId)
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->get()
                    ->keyBy('target_type');
                
                $barMonthlyTarget = $monthlyTargets['monthly_bar']->target_amount ?? 0;
                $foodMonthlyTarget = $monthlyTargets['monthly_food']->target_amount ?? 0;
                
                // Calculate gross bar sales (OrderItem) for target progress instead of using verified revenue
                $monthBarSales = \App\Models\OrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                        $q->where('user_id', $ownerId)
                          ->where('status', '!=', 'cancelled')
                          ->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                        
                        // Apply location filters
                        if ($location) {
                            $q->where(function($sq) use ($location) {
                                $sq->whereExists(function ($ssq) use ($location) {
                                    $ssq->select(\DB::raw(1))
                                       ->from('staff')
                                       ->whereColumn('staff.id', 'orders.waiter_id')
                                       ->where('staff.location_branch', $location);
                                })->orWhereHas('table', function($ssq) use ($location) {
                                    $ssq->where('location', $location);
                                });
                            });
                        }
                    })->sum('total_price');

                $barTargetProgress = $barMonthlyTarget > 0 ? min(100, round(($monthBarSales / $barMonthlyTarget) * 100)) : 0;
                
                // Re-calculating food actual for dashboard with PROPER location filtering
                $foodMonthRevenue = \App\Models\KitchenOrderItem::whereHas('order', function($q) use ($ownerId, $location) {
                        $q->where('user_id', $ownerId)
                          ->where('status', '!=', 'cancelled')
                          ->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                        
                        // Apply location filters
                        if ($location) {
                            $q->where(function($sq) use ($location) {
                                $sq->whereExists(function ($ssq) use ($location) {
                                    $ssq->select(\DB::raw(1))
                                       ->from('staff')
                                       ->whereColumn('staff.id', 'orders.waiter_id')
                                       ->where('staff.location_branch', $location);
                                })->orWhereHas('table', function($ssq) use ($location) {
                                    $ssq->where('location', $location);
                                });
                            });
                        }
                    })
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_price');
                $foodTargetProgress = $foodMonthlyTarget > 0 ? min(100, round(($foodMonthRevenue / $foodMonthlyTarget) * 100)) : 0;

                // ── Master Sheet Financials (Manager Context)
                $monthProfit = \App\Models\DailyCashLedger::where('user_id', $ownerId)
                    ->whereMonth('ledger_date', now()->month)
                    ->whereYear('ledger_date', now()->year)
                    ->where('status', 'closed')
                    ->sum('profit_submitted_to_boss');

                $masterSheetTrend = \App\Models\DailyCashLedger::where('user_id', $ownerId)
                    ->where('ledger_date', '>=', now()->subDays(6)->startOfDay())
                    ->orderBy('ledger_date')
                    ->get();

                // ── Top Waiters this month
                $waitersQuery = \App\Models\BarOrder::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->where('status', '!=', 'cancelled')
                    ->whereNotNull('waiter_id');
                
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    $waitersQuery->where('user_id', $ownerId);
                }
                
                $waiterOrdersMonth = $applyLocation($waitersQuery)
                    ->with(['waiter', 'items', 'kitchenOrderItems'])
                    ->get();

                $topWaiters = $waiterOrdersMonth->groupBy('waiter_id')->map(function($orders) {
                    $waiter = $orders->first()->waiter;
                    if (!$waiter) return null;

                    // Skip non-waiters from waiter leaderboard
                    if (stripos($waiter->full_name, 'counter') !== false) return null;
                    if ($waiter->role && strtolower($waiter->role->name) !== 'waiter') return null;

                    $barRev = $orders->sum(fn($o) => $o->items ? $o->items->sum('total_price') : 0);
                    $foodRev = $orders->sum(fn($o) => $o->kitchenOrderItems ? $o->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price') : 0);
                    return [
                        'waiter' => $waiter,
                        'orders_count' => $orders->count(),
                        'bar_revenue' => $barRev,
                        'food_revenue' => $foodRev,
                        'total_revenue' => $barRev + $foodRev,
                    ];
                })->filter(fn($item) => $item !== null && $item['total_revenue'] > 0)->sortByDesc('total_revenue')->values();

                return view('dashboard.manager', compact(
                    'staff', 'owner',
                    'todayRevenue', 'monthRevenue', 'todayOrders', 'pendingOrders',
                    'pendingTransfers', 'approvedTransfers', 'completedTransfersToday',
                    'totalTransferSalesValue', 'monthlyPurchaseCost',
                    'recentReceipts', 'recentTransfers',
                    'revenueTrend', 'topProducts', 'topWaiters',
                    'warehouseStockItems', 'counterStockItems',
                    'lowStockList', 'categoryDistribution',
                    'barMonthlyTarget', 'foodMonthlyTarget', 'barTargetProgress', 'foodTargetProgress', 'foodMonthRevenue',
                    'monthProfit', 'masterSheetTrend'
                ));
            }

            // Stock Keeper and other roles
            if ($roleName === 'stock keeper' || $roleName === 'stockkeeper' || ($staff->role && $staff->role->hasPermission('inventory', 'view'))) {
                // Warehouse stock statistics
                $statistics['warehouseStockItems'] = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->whereHas('stockLocations', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId)->where('location', 'warehouse')->where('quantity', '>', 0);
                })
                ->count();

                $statistics['counterStockItems'] = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->whereHas('stockLocations', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId)->where('location', 'counter')->where('quantity', '>', 0);
                })
                ->count();

                $statistics['pendingTransfers'] = \App\Models\StockTransfer::where('user_id', $ownerId)
                    ->where('status', 'pending')->count();

                $lowStockThreshold    = \App\Models\SystemSetting::get('low_stock_threshold_' . $ownerId, 10);
                $criticalStockThreshold = \App\Models\SystemSetting::get('critical_stock_threshold_' . $ownerId, 5);

                $lowStockVariants = \App\Models\ProductVariant::whereHas('product', function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                })
                ->with(['product', 'stockLocations' => function($query) use ($ownerId) {
                    $query->where('user_id', $ownerId);
                }])
                ->get()
                ->filter(function($variant) use ($lowStockThreshold) {
                    $warehouseQty = optional($variant->stockLocations->where('location', 'warehouse')->first())->quantity ?? 0;
                    $counterQty   = optional($variant->stockLocations->where('location', 'counter')->first())->quantity ?? 0;
                    $totalQty = $warehouseQty + $counterQty;
                    return $totalQty > 0 && $totalQty < $lowStockThreshold;
                });

                $statistics['lowStockItems'] = $lowStockVariants->count();
                $statistics['lowStockItemsList'] = $lowStockVariants->take(10)->map(function($variant) use ($criticalStockThreshold) {
                    $warehouseQty = optional($variant->stockLocations->where('location', 'warehouse')->first())->quantity ?? 0;
                    $counterQty   = optional($variant->stockLocations->where('location', 'counter')->first())->quantity ?? 0;
                    return [
                        'id'           => $variant->id,
                        'product_name' => $variant->product->name,
                        'variant'      => $variant->measurement,
                        'warehouse_qty'=> $warehouseQty,
                        'counter_qty'  => $counterQty,
                        'total_qty'    => $warehouseQty + $counterQty,
                        'is_critical'  => ($warehouseQty + $counterQty) < $criticalStockThreshold,
                    ];
                });
            }

            return view('dashboard.staff', compact('staff', 'owner', 'statistics'));
        }

        
        // Regular users should not have role in URL - redirect to clean URL if role is present
        if ($role) {
            return redirect()->route('dashboard');
        }

        // Regular user authentication
        if (!$isUser) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        // IMPORTANT: Verify user email matches authenticated user
        if ($user->email !== request()->input('email') && !session()->has('verified_user')) {
            // This is just a safety check - in normal flow, auth()->user() is already verified
        }
        
        // Redirect admins to business dashboard (DEPRECATED - handled above)
        // if ($user->isAdmin()) {
        //     return redirect()->route('admin.dashboard.index');
        // }

        // Check if user needs to complete business configuration
        if (!$user->isConfigured()) {
            $plan = $user->currentPlan();
            $canConfigure = false;

            // If user has a plan
            if ($plan) {
                // Free plan - can configure immediately
                if ($plan->slug === 'free') {
                    $canConfigure = true;
                } 
                // Basic/Pro plans - need verified payment first
                elseif (in_array($plan->slug, ['basic', 'pro'])) {
                    $subscription = $user->activeSubscription;
                    $canConfigure = $subscription && $subscription->status === 'active';
                }
            } else {
                // No plan yet - check if they have a subscription (even if pending)
                $subscription = $user->activeSubscription;
                if ($subscription && $subscription->plan) {
                    // If they have a free plan subscription (trial), allow configuration
                    if ($subscription->plan->slug === 'free') {
                        $canConfigure = true;
                    }
                }
            }

            if ($canConfigure) {
                return redirect()->route('business-configuration.index')
                    ->with('info', 'Please complete your business configuration to get started.');
            }
        }
        
        // Get active subscription
        $subscription = $user->activeSubscription;
        $currentPlan = $subscription ? $subscription->plan : null;
        
        // Check if user has pending subscription (paid plan waiting for payment)
        $pendingSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('is_trial', false)
            ->latest()
            ->first();
        
        // Get pending invoices
        $pendingInvoices = Invoice::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'pending_verification', 'paid'])
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Check if trial is expiring soon (within 7 days)
        $trialExpiringSoon = false;
        $trialDaysRemaining = 0;
        if ($subscription && $subscription->is_trial) {
            $trialDaysRemaining = $subscription->getTrialDaysRemaining();
            $trialExpiringSoon = $trialDaysRemaining > 0 && $trialDaysRemaining <= 7;
        }
        
        // Check if trial has expired
        $trialExpired = false;
        if ($subscription && $subscription->is_trial && $subscription->trial_ends_at) {
            $trialExpired = \Carbon\Carbon::now()->greaterThan($subscription->trial_ends_at);
        }
        
        // Get upgrade plans (Basic and Pro)
        $upgradePlans = \App\Models\Plan::where('slug', '!=', 'free')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        
        // Get pending cash handovers
        $pendingHandovers = \App\Models\FinancialHandover::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('accountant')
            ->orderBy('handover_date', 'desc')
            ->get();
        
        return view('dashboard.index', compact(
            'subscription', 
            'currentPlan', 
            'pendingInvoices',
            'trialExpiringSoon',
            'trialDaysRemaining',
            'trialExpired',
            'upgradePlans',
            'pendingSubscription',
            'pendingHandovers'
        ));
    }

    /**
     * Switch active location/branch context
     */
    public function switchLocation(Request $request)
    {
        $location = $request->input('active_location');
        
        if ($location === 'all') {
            session()->forget('active_location');
        } else {
            session(['active_location' => $location]);
        }
        
        return back()->with('success', 'Location switched to: ' . ($location === 'all' ? 'All Locations' : $location));
    }
}
