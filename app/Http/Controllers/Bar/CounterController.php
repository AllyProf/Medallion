<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\BarOrder;
use App\Models\FoodItem;
use App\Models\KitchenOrderItem;
use App\Models\OpenBottle;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Staff;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TransferSale;
use App\Services\TransferSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounterController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * View Waiter Orders
     */
    public function waiterOrders(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to view waiter orders.');
        }

        $ownerId = $this->getOwnerId();

        $search = $request->get('search');
        $waiter_id = $request->get('waiter_id');
        $status = $request->get('status');

        // Get all orders from waiters with filters
        $ordersQuery = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->where(function ($q) {
                $q->whereHas('items')
                    ->orWhere('status', 'cancelled');
            })
            ->with(['waiter', 'items.productVariant.product', 'table', 'paidByWaiter', 'orderPayments'])
            ->orderBy('created_at', 'desc');

        if ($search) {
            $ordersQuery->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhereHas('items', function ($sq) use ($search) {
                        $sq->where('product_name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('kitchenOrderItems', function ($sq) use ($search) {
                        $sq->where('food_item_name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($waiter_id && $waiter_id !== 'all') {
            $ordersQuery->where('waiter_id', $waiter_id);
        }

        if ($status && $status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        $orders = $ordersQuery->paginate(10)->appends($request->all());

        // Get order counts by status (total, ignoring search filters for overview)
        $pendingCount = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->whereHas('items')
            ->where('status', 'pending')
            ->count();

        $servedCount = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->whereHas('items')
            ->where('status', 'served')
            ->where('payment_status', 'pending')
            ->count();

        // Get all waiters for filter dropdown
        $waiters = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->where('name', 'Waiter');
            })
            ->get();

        $activeShift = $this->getCurrentShift();
        $allOpenShiftIds = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->pluck('id')
            ->toArray();

        if ($request->ajax()) {
            return view('bar.counter.partials._waiter_orders_table_body', compact('orders', 'pendingCount', 'servedCount', 'waiters', 'activeShift', 'allOpenShiftIds'))->render();
        }

        return view('bar.counter.waiter-orders', compact('orders', 'pendingCount', 'servedCount', 'waiters', 'activeShift', 'allOpenShiftIds'));
    }

    /**
     * Update Order Status
     */
    public function updateOrderStatus(Request $request, BarOrder $order)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission to update orders.'], 403);
        }

        $ownerId = $this->getOwnerId();
        if ($order->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,served,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['status'] === 'cancelled') {
                $message = $this->handleCounterCancellation($order, $ownerId, $validated['reason'] ?? null);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'order' => $order->fresh()->load(['waiter', 'items.productVariant.product', 'table']),
                ]);
            }

            $order->update(['status' => $validated['status']]);

            if ($validated['status'] === 'served') {
                $order->update([
                    'served_at' => now(),
                    'served_by' => $this->getCurrentUser() ? $this->getCurrentUser()->id : null,
                ]);

                $order->load('items.productVariant');
                $transferSaleService = new TransferSaleService;
                $currentUser = $this->getCurrentUser();

                foreach ($order->items as $orderItem) {
                    // Update is_served status for workflow tracking.
                    if ($orderItem->is_served) {
                        continue;
                    }

                    // --- SMART LEDGER CHECK ---
                    // Only deduct stock if it hasn't been deducted yet (common for older pending orders)
                    $alreadyDeducted = StockMovement::where([
                        'reference_type' => BarOrder::class,
                        'reference_id' => $order->id,
                        'product_variant_id' => $orderItem->product_variant_id,
                    ])->whereIn('movement_type', ['sale', 'usage'])->exists();

                    if (! $alreadyDeducted && $orderItem->productVariant && $orderItem->productVariant->product) {
                        // Get counter stock for this variant
                        $counterStock = StockLocation::where('user_id', $ownerId)
                            ->where('product_variant_id', $orderItem->product_variant_id)
                            ->where('location', 'counter')
                            ->first();

                        if ($counterStock) {
                            // Handle stock deduction based on sell type
                            if (($orderItem->sell_type ?? 'unit') === 'tot') {
                                $variant = $orderItem->productVariant;
                                $totsPerBottle = $variant->total_tots ?: 1;
                                $totsNeeded = $orderItem->quantity;

                                // 1. Check for open bottles
                                $openBottle = \App\Models\OpenBottle::where('user_id', $ownerId)
                                    ->where('product_variant_id', $orderItem->product_variant_id)
                                    ->first();

                                if ($openBottle) {
                                    if ($openBottle->tots_remaining >= $totsNeeded) {
                                        $openBottle->decrement('tots_remaining', $totsNeeded);
                                        if ($openBottle->tots_remaining <= 0) $openBottle->delete();
                                        $totsNeeded = 0;
                                    } else {
                                        $totsNeeded -= $openBottle->tots_remaining;
                                        $openBottle->delete();
                                    }
                                }

                                // 2. Open new bottles if needed
                                while ($totsNeeded > 0) {
                                    if ($counterStock->quantity < 1) break;

                                    $counterStock->decrement('quantity', 1);
                                    app(\App\Services\StockAlertService::class)->checkCounterStock($variant->id, $ownerId);

                                    if ($totsNeeded >= $totsPerBottle) {
                                        $totsNeeded -= $totsPerBottle;
                                    } else {
                                        \App\Models\OpenBottle::create([
                                            'user_id' => $ownerId,
                                            'product_variant_id' => $variant->id,
                                            'tots_remaining' => $totsPerBottle - $totsNeeded,
                                        ]);
                                        $totsNeeded = 0;
                                    }

                                    StockMovement::create([
                                        'user_id' => $ownerId,
                                        'product_variant_id' => $variant->id,
                                        'movement_type' => 'sale',
                                        'from_location' => 'counter',
                                        'to_location' => null,
                                        'quantity' => 1,
                                        'unit_price' => $orderItem->unit_price,
                                        'reference_type' => BarOrder::class,
                                        'reference_id' => $order->id,
                                        'created_by' => $currentUser ? $currentUser->id : $ownerId,
                                        'notes' => 'Bottle opened (Delayed Serve): '.$order->order_number,
                                    ]);
                                }
                            } else {
                                // Standard unit/bottle deduction
                                if ($counterStock->quantity >= $orderItem->quantity) {
                                    $counterStock->decrement('quantity', $orderItem->quantity);
                                    app(\App\Services\StockAlertService::class)->checkCounterStock($orderItem->product_variant_id, $ownerId);

                                    StockMovement::create([
                                        'user_id' => $ownerId,
                                        'product_variant_id' => $orderItem->product_variant_id,
                                        'movement_type' => 'sale',
                                        'from_location' => 'counter',
                                        'to_location' => null,
                                        'quantity' => $orderItem->quantity,
                                        'unit_price' => $orderItem->unit_price,
                                        'reference_type' => BarOrder::class,
                                        'reference_id' => $order->id,
                                        'created_by' => $currentUser ? $currentUser->id : $ownerId,
                                        'notes' => 'Order served (Delayed Serve): '.$order->order_number,
                                    ]);
                                }
                            }
                        }
                    }

                    // Mark item as served
                    $orderItem->update(['is_served' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $validated['status'] === 'served'
                    ? 'Order marked as served. Stock was already deducted at order creation for real-time tracking.'
                    : 'Order status updated successfully',
                'order' => $order->load(['waiter', 'items.productVariant.product', 'table']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update order status: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to update order status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark Order as Paid
     */
    public function markAsPaid(Request $request, BarOrder $order)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission to mark orders as paid.'], 403);
        }

        $ownerId = $this->getOwnerId();
        if ($order->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0|max:'.$order->total_amount,
            'waiter_id' => 'nullable|exists:staff,id', // Waiter who collected payment (optional for customer orders)
        ]);

        $paidAmount = $validated['paid_amount'];
        $remainingAmount = $order->total_amount - $paidAmount;

        $updateData = [
            'paid_amount' => $paidAmount,
            'payment_status' => $remainingAmount <= 0 ? 'paid' : 'partial',
        ];

        // Only set paid_by_waiter_id if provided (for waiter orders)
        if (isset($validated['waiter_id']) && $validated['waiter_id']) {
            $updateData['paid_by_waiter_id'] = $validated['waiter_id'];
        }

        $order->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'order' => $order->load(['waiter', 'paidByWaiter', 'items.productVariant.product', 'table']),
        ]);
    }

    /**
     * Get Orders by Status (for filtering)
     */
    public function getOrdersByStatus(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission to view orders.'], 403);
        }

        $ownerId = $this->getOwnerId();
        $status = $request->input('status', 'all');

        $query = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->whereHas('items')
            ->with(['waiter', 'items.productVariant.product', 'table', 'paidByWaiter']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * Get Latest Orders for Real-time Updates
     */
    public function getLatestOrders(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission to view orders.'], 403);
        }

        $ownerId = $this->getOwnerId();
        $lastOrderId = $request->input('last_order_id', 0);

        // Get new orders (pending status only for announcements)
        $newOrders = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->whereHas('items')
            ->where('status', 'pending')
            ->where('id', '>', $lastOrderId)
            ->with(['waiter', 'items.productVariant.product', 'table'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'waiter_name' => $order->waiter ? $order->waiter->full_name : 'N/A',
                    'table_number' => $order->table ? $order->table->table_number : null,
                    'items' => $order->items->map(function ($item) {
                        $productName = $item->productVariant->product->name ?? 'N/A';

                        return [
                            'name' => $productName,
                            'quantity' => $item->quantity,
                        ];
                    })->toArray(),
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at->toDateTimeString(),
                ];
            });

        // Get the latest order ID
        $latestOrderId = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->max('id') ?? 0;

        return response()->json([
            'success' => true,
            'new_orders' => $newOrders,
            'latest_order_id' => $latestOrderId,
        ]);
    }

    /**
     * Counter Dashboard
     */
    public function dashboard()
    {
        // Check permission - allow both bar_orders and inventory permissions
        if (! $this->hasPermission('bar_orders', 'view') && ! $this->hasPermission('inventory', 'view')) {
            abort(403, 'You do not have permission to access counter dashboard.');
        }

        $ownerId = $this->getOwnerId();

        // Get order statistics
        $todayOrders = BarOrder::where('user_id', $ownerId)
            ->whereDate('created_at', today())
            ->where('status', '!=', 'cancelled')
            ->count();

        $pendingOrders = BarOrder::where('user_id', $ownerId)
            ->whereNotNull('waiter_id')
            ->whereHas('items')
            ->where('status', 'pending')
            ->count();

        $todayRevenue = BarOrder::where('user_id', $ownerId)
            ->whereDate('created_at', today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Get counter stock statistics
        $counterStockItems = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->whereHas('stockLocations', function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)->where('location', 'counter')->where('quantity', '>', 0);
            })
            ->count();

        // Get low stock threshold from settings
        $lowStockThreshold = \App\Models\SystemSetting::get('low_stock_threshold_'.$ownerId, 10);

        $lowStockItems = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->whereHas('stockLocations', function ($query) use ($ownerId, $lowStockThreshold) {
                $query->where('user_id', $ownerId)
                    ->where('location', 'counter')
                    ->where('quantity', '>', 0)
                    ->where('quantity', '<', $lowStockThreshold);
            })
            ->count();

        // Get pending stock transfer requests (transfers requested by counter/owner)
        // Since transfers are always warehouse to counter, we count all pending transfers
        $pendingTransfers = StockTransfer::where('user_id', $ownerId)
            ->where('status', 'pending')
            ->count();

        // Get warehouse stock statistics
        $warehouseStockItems = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->whereHas('stockLocations', function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)->where('location', 'warehouse')->where('quantity', '>', 0);
            })
            ->count();

        // Get low stock threshold from settings
        $lowStockThreshold = \App\Models\SystemSetting::get('low_stock_threshold_'.$ownerId, 10);
        $criticalStockThreshold = \App\Models\SystemSetting::get('critical_stock_threshold_'.$ownerId, 5);

        // Get low stock items specifically for the COUNTER
        // We focus on items that are low in the counter to prompt transfers from warehouse
        $lowStockItemsList = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->with(['product', 'warehouseStock', 'counterStock'])
            ->get()
            ->filter(function ($variant) use ($lowStockThreshold) {
                $counterQty = $variant->counterStock ? $variant->counterStock->quantity : 0;
                $warehouseQty = $variant->warehouseStock ? $variant->warehouseStock->quantity : 0;
                $specificThreshold = $variant->counter_alert_threshold ?? $lowStockThreshold;

                // Alert if:
                // 1. Counter stock is below its specific alert threshold (or global threshold)
                // 2. ONLY show items that still have some stock (ignore 0.00 items as requested)
                return $counterQty > 0 && $counterQty < $specificThreshold;
            })
            ->sortBy(function($variant) {
                // Priority: Lower stock numbers first
                return $variant->counterStock ? $variant->counterStock->quantity : 0;
            })
            ->take(10)
            ->map(function ($variant) use ($criticalStockThreshold) {
                $warehouseQty = $variant->warehouseStock ? $variant->warehouseStock->quantity : 0;
                $counterQty = $variant->counterStock ? $variant->counterStock->quantity : 0;
                $totalQty = $warehouseQty + $counterQty;

                return [
                    'id' => $variant->id,
                    'product_name' => $variant->display_name ?: $variant->product->name,
                    'warehouse_qty' => $warehouseQty,
                    'counter_qty' => $counterQty,
                    'total_qty' => $totalQty,
                    'is_critical' => $totalQty < $criticalStockThreshold,
                    'unit' => $variant->inventory_unit,
                    'packaging' => $variant->packaging ?? 'pkg',
                ];
            });

        // Recent stock transfer requests
        $recentTransferRequests = StockTransfer::where('user_id', $ownerId)
            ->with(['productVariant.product', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent orders (Filtered to only show orders containing bar items)
        $recentOrders = BarOrder::where('user_id', $ownerId)
            ->whereHas('items')
            ->with(['waiter', 'items.productVariant.product', 'table'])
            ->orderBy('created_at', 'desc')
            ->paginate(3);

        $activeShift = $this->getCurrentShift();

        // --- NEW POS DATA FOR COUNTER ---
        // Get all products with counter stock
        $openBottles = \App\Models\OpenBottle::where('user_id', $ownerId)
            ->get()
            ->groupBy('product_variant_id');

        $variants = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)
                    ->where('location', 'counter');
            }])
            ->get()
            ->map(function ($variant) use ($openBottles) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                $openPortions = $openBottles->has($variant->id) ? $openBottles->get($variant->id)->sum('tots_remaining') : 0;
                $variant->open_portions_count = $openPortions;
                return $variant;
            })
            ->filter(function ($variant) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                $hasSealed = $counterStock && $counterStock->quantity > 0;
                $hasOpen = ($variant->open_portions_count ?? 0) > 0;

                return $hasSealed || $hasOpen;
            })
            ->map(function ($variant) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                $category = $variant->product->category ?? '';
                $isAlcoholic = stripos($category, 'alcoholic') !== false;

                $portionLabel = (function ($cat) {
                    $c = strtolower(trim($cat));
                    if (str_contains($c, 'wine')) {
                        return 'Glass';
                    }
                    if (str_contains($c, 'spirit') || str_contains($c, 'liquor') || str_contains($c, 'vodka') || str_contains($c, 'whiskey') || str_contains($c, 'gin')) {
                        return 'Shot';
                    }

                    return 'Tot';
                })($category);

                $qty = $counterStock->quantity;
                $m = $variant->measurement;
                if (is_numeric($m) && $m > 0) {
                    $m = ($m < 10) ? $m.'L' : $m.'ml';
                }
                $pkg = $variant->packaging;
                if (in_array(strtolower($pkg), ['crate', 'carton', 'box', 'pkg', 'case', 'piece', 'pieces', 'pcs', 'unit'])) {
                    $pkg = '';
                }

                $variantStr = trim($m.($pkg ? ' - '.$pkg : ''));
                $product_name = $variant->display_name ?: $variant->product->name;

                // Hide variant string if it's completely redundant with the display name
                if ($variantStr && stripos($product_name, $variantStr) !== false) {
                    $variantStr = '';
                }

                return [
                    'id' => $variant->id,
                    'product_name' => $product_name,
                    'variant_name' => $variant->name,
                    'variant' => $variantStr,
                    'quantity' => $qty,
                    'open_tots' => $variant->open_portions_count ?? 0,
                    'selling_price' => $counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0,
                    'selling_price_per_tot' => $counterStock->selling_price_per_tot ?? $variant->selling_price_per_tot ?? 0,
                    'can_sell_in_tots' => $variant->can_sell_in_tots,
                    'total_tots' => $variant->total_tots,
                    'items_per_package' => $variant->items_per_package ?? 1,
                    'measurement' => $variant->measurement,
                    'packaging_type' => $variant->packaging ?? 'pkg',
                    'unit' => $variant->inventory_unit,
                    'portion_label' => $portionLabel,
                    'category' => $category,
                    'is_alcoholic' => $isAlcoholic,
                    'product_image' => $variant->product->image ?? null,
                ];
            });

        // Get all active tables
        $tables = \App\Models\BarTable::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('table_number')
            ->get()
            ->map(function ($table) {
                return [
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                    'table_name' => $table->table_name,
                    'capacity' => $table->capacity,
                    'current_people' => $table->current_people,
                    'remaining_capacity' => $table->remaining_capacity,
                    'location' => $table->location ?? 'N/A',
                    'status' => $table->status,
                ];
            });

        /* Removed food items from counter dashboard as requested */

        // Get completed and served orders (for history view in POS)
        $completedOrders = BarOrder::where('user_id', $ownerId)
            ->where(function ($query) {
                $query->where('status', 'served')
                    ->orWhereHas('kitchenOrderItems', function ($q) {
                        $q->where('status', 'completed');
                    });
            })
            ->with(['kitchenOrderItems' => function ($query) {
                $query->where('status', 'completed')->orderBy('updated_at', 'desc');
            }, 'items.productVariant.product', 'table', 'waiter'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $staff = $this->getCurrentStaff();

        // Check for active shift ONLY for counter staff
        if (session('is_staff') && $staff && $staff->role) {
            $roleName = strtolower(trim($staff->role->name ?? ''));
            if (in_array($roleName, ['counter', 'bar counter'])) {
                $bar_shift = $this->getCurrentShift();
                if (! $bar_shift) {
                    return redirect()->route('bar.counter.open-shift');
                }
            }
        }

        $bar_shift = $this->getCurrentShift();

        return view('bar.counter.dashboard', compact(
            'todayOrders',
            'pendingOrders',
            'todayRevenue',
            'counterStockItems',
            'lowStockItems',
            'pendingTransfers',
            'warehouseStockItems',
            'lowStockItemsList',
            'recentTransferRequests',
            'recentOrders',
            'variants',
            'tables',
            'completedOrders',
            'staff',
            'bar_shift'
        ));
    }

    /**
     * View Open Shift Page
     */
    public function openShift()
    {
        $staff = $this->getCurrentStaff();
        if (! $staff) {
            return redirect()->route('dashboard');
        }

        // If already has an open shift, redirect to dashboard
        if ($this->getCurrentShift()) {
            return redirect()->route('bar.counter.dashboard');
        }

        $ownerId = $this->getOwnerId();

        // Get all products with counter stock for verification
        $counterStockItems = \App\Models\ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)
                    ->where('location', 'counter');
            }])
            ->get()
            ->filter(function ($variant) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();

                return $counterStock && $counterStock->quantity > 0;
            })
            ->map(function ($variant) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();

                // Logic for measurement unit
                $mUnit = '';
                if ($variant->measurement && ! preg_match('/[a-zA-Z]/', $variant->measurement)) {
                    // If measurement is purely numeric, try to find unit
                    // Usually beverages are ml or L
                    $mUnit = (float) $variant->measurement > 10 ? 'ml' : 'L';
                }

                return [
                    'item_name' => $variant->display_name,
                    'category' => $variant->product->category ?? 'General',
                    'quantity' => $counterStock->quantity,
                    'quantity_unit' => 'Btl',
                    'measurement' => $variant->measurement.$mUnit,
                ];
            });

        return view('bar.counter.open-shift', compact('staff', 'counterStockItems'));
    }

    /**
     * Store and Start New Shift
     */
    public function storeShift(Request $request)
    {
        $staff = $this->getCurrentStaff();
        if (! $staff) {
            return abort(403);
        }

        $ownerId = $this->getOwnerId();

        // All fields are now automated or optional
        $shift = \App\Models\BarShift::create([
            'user_id' => $ownerId,
            'staff_id' => $staff->id,
            'location_branch' => $staff->location_branch ?? 'Counter',
            'opened_at' => now(),
            'status' => 'open',
            'opening_cash' => 0, // Automated to zero as requested
            'notes' => $request->notes ?? 'Shift opened after stock verification.',
        ]);

        // Trigger SMS notification to Manager and Accountant
        try {
            $shiftSms = new \App\Services\ShiftSmsService();
            $shiftSms->sendShiftStartedSms($shift);
        } catch (\Exception $e) {
            \Log::error('Shift Started SMS failed: ' . $e->getMessage());
        }

        return redirect()->route('bar.counter.dashboard')->with('success', 'Shift opened successfully. Good luck!');
    }

    /**
     * Close Shift
     */
    public function closeShift(Request $request, \App\Models\BarShift $shift)
    {
        if ($shift->user_id !== $this->getOwnerId()) {
            return abort(403);
        }

        $validated = $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Link all unlinked bar orders created during this shift's timeframe to this shift
        // This captures waiter orders that were not previously linked
        \App\Models\BarOrder::where('user_id', $this->getOwnerId())
            ->whereNull('bar_shift_id')
            ->where('created_at', '>=', $shift->opened_at)
            ->where('created_at', '<=', now())
            ->where('status', '!=', 'cancelled')
            ->update(['bar_shift_id' => $shift->id]);

        // Calculate final expected amounts from ALL linked orders (including the ones we just linked)
        // We include ALL served orders, even if not yet paid, to see "Potential Sales" vs "Final Collections"
        $shiftOrders = \App\Models\BarOrder::where('bar_shift_id', $shift->id)
            ->where('status', '!=', 'cancelled')
            ->get();

        $cashSales = 0;
        $digitalSales = 0;

        foreach ($shiftOrders as $order) {
            $amt = (float) ($order->paid_amount ?: $order->total_amount);
            if (in_array($order->payment_method, ['mobile_money', 'bank_transfer', 'card', 'bank'])) {
                $digitalSales += $amt;
            } else {
                $cashSales += $amt;
            }
        }

        $shift->update([
            'closed_at' => now(),
            'status' => 'closed',
            'expected_cash' => $cashSales,
            'actual_cash' => $validated['actual_cash'],
            'digital_revenue' => $digitalSales,
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('bar.counter.reconciliation')->with('success', 'Shift closed and submitted for reconciliation.');
    }

    /**
     * View Warehouse Stock (available products from stock keeper)
     */
    public function warehouseStock()
    {
        // Check permission - allow inventory view or stock_transfer view, or counter/stock keeper roles
        $canView = $this->hasPermission('inventory', 'view') || $this->hasPermission('stock_transfer', 'view');

        // Allow counter and stock keeper roles even without explicit permission
        if (! $canView && session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                if (in_array($roleName, ['counter', 'bar counter', 'stock keeper', 'stockkeeper'])) {
                    $canView = true;
                }
            }
        }

        if (! $canView) {
            abort(403, 'You do not have permission to view warehouse stock.');
        }

        $ownerId = $this->getOwnerId();

        $variants = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId)
                ->where(function ($q) {
                    $q->where('category', 'like', '%beverage%')
                        ->orWhere('category', 'like', '%drink%')
                        ->orWhere('category', 'like', '%alcohol%')
                        ->orWhere('category', 'like', '%beer%')
                        ->orWhere('category', 'like', '%wine%')
                        ->orWhere('category', 'like', '%spirit%')
                        ->orWhere('category', 'like', '%water%')
                        ->orWhere('category', 'like', '%soda%')
                        ->orWhere('category', 'like', '%item%');
                });
        })
            ->with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
            }])
            ->get()
            ->filter(function ($variant) {
                $warehouseStock = $variant->stockLocations->where('location', 'warehouse')->first();

                return $warehouseStock && $warehouseStock->quantity > 0;
            });
        $variants = ProductVariant::whereIn('id', $variants->pluck('id'))
            ->get()
            ->map(function ($variant) use ($ownerId) {
                $warehouseStock = $variant->stockLocations->where('location', 'warehouse')->where('user_id', $ownerId)->first();
                $counterStock = $variant->stockLocations->where('location', 'counter')->where('user_id', $ownerId)->first();

                return [
                    'id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'brand' => $variant->product->brand ?? 'N/A',
                    'category' => $variant->product->category ?? 'General',
                    'variant' => $variant->measurement,
                    'packaging' => $variant->packaging,
                    'unit' => $variant->inventory_unit,
                    'items_per_package' => $variant->items_per_package ?? 1,
                    'is_alcoholic' => $variant->is_alcoholic ?? false,
                    'product_image' => $variant->product->image,
                    'warehouse_quantity' => $warehouseStock->quantity,
                    'counter_quantity' => $counterStock ? $counterStock->quantity : 0,
                    'buying_price' => $warehouseStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0,
                    'selling_price' => $variant->selling_price_per_unit ?? 0,
                ];
            });

        $categories = $variants->pluck('category')->unique()->sort()->values();
        $brands = $variants->pluck('brand')->unique()->filter()->sort()->values();

        return view('bar.counter.warehouse-stock', compact('variants', 'categories', 'brands'));
    }

    /**
     * Bar Stock Sheet - Downloadable stock summary for all items
     */
    public function stockSheet(Request $request, $location = 'warehouse')
    {
        $ownerId = $this->getOwnerId();

        // RESTRICT ACCESS - Counter staff only sees Counter stock sheet
        $staffMember = $this->getCurrentStaff();
        if ($staffMember && $location === 'warehouse') {
            $roleSlug = $staffMember->role->slug ?? strtolower(trim($staffMember->role->name ?? ''));
            if (in_array($roleSlug, ['counter', 'bar counter'])) {
                return redirect()->route('bar.stock-sheet', 'counter')->with('error', 'Unauthorized access to warehouse stock sheet.');
            }
        }

        // Basic filtering: warehouse or counter
        // We'll call the general report 'warehouse' by default or 'counter'

        $stockData = ProductVariant::with(['product', 'stockLocations' => function ($q) use ($ownerId) {
            $q->where('user_id', $ownerId);
        }])
            ->whereHas('product', fn ($q) => $q->where('user_id', $ownerId))
            ->get()
            ->map(function ($variant) {
                $warehouseStock = $variant->stockLocations->where('location', 'warehouse')->first();
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();

                $warehouseQty = $warehouseStock ? (float) $warehouseStock->quantity : 0;
                $counterQty = $counterStock ? (float) $counterStock->quantity : 0;
                $totalQty = $warehouseQty + $counterQty;

                $itemsPerPkg = (int) ($variant->items_per_package ?? 1);
                if ($itemsPerPkg <= 0) {
                    $itemsPerPkg = 1;
                }

                // CLEAN SPECIFIC ITEM NAME
                $displayName = $variant->name ?? $variant->product->name;
                if (in_array(strtolower($displayName), ['none', 'standard', 'regular', '-', 'default', '', 'standard packaging', 'none packaging'])) {
                    $displayName = $variant->product->name;
                }

                // If the product name is already in the variant name, simplify
                if (str_contains($displayName, $variant->product->name) && $displayName != $variant->product->name) {
                    $displayName = trim(str_replace($variant->product->name, '', $displayName), ' -');
                }

                return [
                    'item_id' => $variant->id,
                    'item_name' => $displayName,
                    'measurement' => $variant->measurement ?? '',
                    'packaging' => $variant->packaging ?? 'Piece',
                    'items_per_pkg' => $itemsPerPkg,
                    'brand' => $variant->product->brand ?? '-',
                    'category' => $variant->product->category ?? 'General',
                    'warehouse_qty' => $warehouseQty,
                    'counter_qty' => $counterQty,
                    'total_in_stock' => $totalQty,
                    'unit' => $variant->inventory_unit,
                    'buying_price' => $warehouseStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0,
                    'selling_price' => $counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0,
                ];
            })
            ->sortBy('item_name')
            ->values();

        $owner = \App\Models\User::find($ownerId);
        $staff = $this->getCurrentStaff();

        // Find specific staff for Signatures
        $allStaff = \App\Models\Staff::where('user_id', $ownerId)->with('role')->get();
        $accountant = $allStaff->filter(fn ($s) => str_contains(strtolower($s->role->name ?? ''), 'accountant'))->first()?->full_name ?? ($staff && str_contains(strtolower($staff->role->name ?? ''), 'accountant') ? $staff->full_name : 'Authorized Accountant');
        
        if ($location === 'warehouse') {
            $stockKeeper = $allStaff->filter(fn ($s) => str_contains(strtolower($s->role->name ?? ''), 'keeper'))->first()?->full_name ?? 'Authorized Stock Keeper';
        } else {
            $stockKeeper = $allStaff->filter(fn ($s) => str_contains(strtolower($s->role->name ?? ''), 'counter'))->first()?->full_name ?? ($staff ? $staff->full_name : 'Authorized Counter Staff');
        }

        $businessName = $owner->business_name ?? 'MEDALLION Bar';
        $generatedAt = now()->format('d M Y, H:i');

        // Basic Sales Stats for the Header
        $todayStats = [
            'bottles_sold' => \App\Models\OrderItem::whereHas('order', function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId)->whereDate('created_at', today());
            })->sum('quantity'),
            'total_revenue' => \App\Models\BarOrder::where('user_id', $ownerId)->whereDate('created_at', today())->where('payment_status', 'paid')->sum('total_amount'),
            'sales_variants' => $stockData->count(),
            'inventory_items' => $stockData->where('total_in_stock', '>', 0)->count(),
        ];

        // Export CSV if requested
        if ($request->get('export') === 'csv') {
            $locationRequested = $location;

            $filename = 'bar_stock_sheet_'.$locationRequested.'_'.date('Y-m-d').'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            $callback = function () use ($stockData, $businessName, $generatedAt, $locationRequested) {
                $handle = fopen('php://output', 'w');
                $title = strtoupper($locationRequested)." STOCK SHEET - $businessName";
                fputcsv($handle, [$title, "Generated: $generatedAt"]);
                fputcsv($handle, []);

                if ($locationRequested === 'warehouse') {
                    fputcsv($handle, ['#', 'ITEM NAME', 'VARIANT', 'BRAND', 'CATEGORY', 'QTY IN WAREHOUSE', 'UNIT', 'BUYING PRICE', 'STOCK VALUE (TZS)', 'STATUS']);
                    $filteredData = $stockData->filter(fn ($r) => $r['warehouse_qty'] > 0)->values();
                    foreach ($filteredData as $i => $row) {
                        fputcsv($handle, [
                            $i + 1, $row['item_name'], $row['variant'], $row['brand'], $row['category'],
                            $row['warehouse_qty'], $row['unit'], number_format($row['buying_price']),
                            number_format($row['warehouse_qty'] * $row['buying_price']), $row['status'],
                        ]);
                    }
                } else {
                    fputcsv($handle, ['#', 'ITEM NAME', 'VARIANT', 'BRAND', 'CATEGORY', 'QTY AT COUNTER', 'UNIT', 'SELLING PRICE', 'STOCK VALUE (TZS)', 'STATUS']);
                    $filteredData = $stockData->filter(fn ($r) => $r['counter_qty'] > 0)->values();
                    foreach ($filteredData as $i => $row) {
                        fputcsv($handle, [
                            $i + 1, $row['item_name'], $row['variant'], $row['brand'], $row['category'],
                            $row['counter_qty'], $row['unit'], number_format($row['selling_price']),
                            number_format($row['counter_qty'] * $row['selling_price']), $row['status'],
                        ]);
                    }
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        return view('bar.counter.stock-sheet', compact('stockData', 'businessName', 'generatedAt', 'location', 'owner', 'staff', 'todayStats', 'accountant', 'stockKeeper'));
    }

    /**
     * View Counter Stock (current counter inventory)
     */
    public function counterStock()
    {
        // Check permission - allow inventory view or stock_transfer view, or counter/stock keeper roles
        $canView = $this->hasPermission('inventory', 'view') || $this->hasPermission('stock_transfer', 'view');

        // Allow counter and stock keeper roles even without explicit permission
        if (! $canView && session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                if (in_array($roleName, ['counter', 'bar counter', 'stock keeper', 'stockkeeper'])) {
                    $canView = true;
                }
            }
        }

        if (! $canView) {
            abort(403, 'You do not have permission to view counter stock.');
        }

        $ownerId = $this->getOwnerId();

        // Get all product_variant_ids that have a counter stock entry for this owner
        $counterVariantIds = \App\Models\StockLocation::where('user_id', $ownerId)
            ->where('location', 'counter')
            ->pluck('product_variant_id');

        $openBottles = \App\Models\OpenBottle::where('user_id', $ownerId)
            ->get()
            ->groupBy('product_variant_id');

        $variants = ProductVariant::whereIn('id', $counterVariantIds)
            ->whereHas('product', function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
            })
            ->with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)->where('location', 'counter');
            }])
            ->get()
            ->map(function ($variant) use ($openBottles) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                $itemsPerPackage = $variant->items_per_package ?? 1;
                $packaging = $variant->packaging ?? 'Package';
                $quantity = $counterStock ? $counterStock->quantity : 0;
                $packages = $itemsPerPackage > 1 ? floor($quantity / $itemsPerPackage) : 0;
                $remainingBottles = $itemsPerPackage > 1 ? ($quantity % $itemsPerPackage) : $quantity;

                $openTots = 0;
                if ($openBottles->has($variant->id)) {
                    $openTots = $openBottles->get($variant->id)->sum('tots_remaining');
                }

                // Standardize Category extraction
                $rawCat = $variant->product->category ?? 'General';
                $cat = trim($rawCat);

                return [
                    'id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'product_image' => $variant->product->image,
                    'brand' => $variant->product->brand ?? 'N/A',
                    'category' => $cat,
                    'raw_category' => $rawCat,
                    'variant' => $variant->measurement,
                    'quantity' => $quantity,
                    'open_tots' => $openTots,
                    'items_per_package' => $itemsPerPackage,
                    'packaging' => $packaging,
                    'packages' => $packages,
                    'remaining_bottles' => $remainingBottles,
                    'selling_price' => $counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0,
                    'selling_price_per_tot' => $counterStock->selling_price_per_tot ?? $variant->selling_price_per_tot ?? 0,
                    'can_sell_in_tots' => $variant->can_sell_in_tots && ($variant->total_tots > 0),
                    'total_tots_capacity' => $variant->total_tots ?? 0,
                    'buying_price' => $counterStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0,
                    'is_low_stock' => $quantity < ($variant->counter_alert_threshold ?? 10),
                    'counter_alert_threshold' => $variant->counter_alert_threshold ?? 10,
                    'unit' => $variant->inventory_unit,
                ];
            })
            ->filter(fn ($v) => $v['quantity'] > 0 || $v['open_tots'] > 0)
            ->values();

        $totalValue = 0; // Hidden for counter staff confidentiality

        // Final Filters from Processed items - case-insensitive dedup
        $categories = $variants->pluck('category')
            ->map(fn($c) => ucwords(strtolower(trim($c ?? 'General'))))
            ->unique()
            ->sort()
            ->values();
        $brands = $variants->pluck('brand')->unique()->filter()->sort()->values();

        return view('bar.counter.counter-stock', compact('variants', 'totalValue', 'categories', 'brands'));
    }

    /**
     * Request Stock Transfer from Warehouse
     */
    public function requestStockTransfer(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('stock_transfer', 'create')) {
            return response()->json(['error' => 'You do not have permission to request stock transfers.'], 403);
        }

        $ownerId = $this->getOwnerId();
        $staff = $this->getCurrentStaff();

        $validated = $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity_requested' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $variant = ProductVariant::with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
            }])->findOrFail($validated['variant_id']);

            $warehouseStock = $variant->stockLocations->where('location', 'warehouse')->first();

            if (! $warehouseStock || $warehouseStock->quantity < ($validated['quantity_requested'] * ($variant->items_per_package ?? 1))) {
                throw new \Exception("Insufficient warehouse stock for {$variant->product->name}");
            }

            // Calculate total units
            $totalUnits = $validated['quantity_requested'] * ($variant->items_per_package ?? 1);

            // Generate transfer number
            $transferNumber = StockTransfer::generateTransferNumber($ownerId);

            // Get owner user ID
            $ownerUser = $this->getCurrentUser();

            // Create stock transfer request
            $transfer = StockTransfer::create([
                'user_id' => $ownerId,
                'product_variant_id' => $variant->id,
                'transfer_number' => $transferNumber,
                'quantity_requested' => $validated['quantity_requested'],
                'total_units' => $totalUnits,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'requested_by' => $ownerUser ? $ownerUser->id : null,
                'requested_by_staff_id' => $staff ? $staff->id : null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer request submitted successfully',
                'transfer' => $transfer->load('productVariant.product'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Product Trends and Revenue Analytics
     */
    public function analytics()
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to view analytics.');
        }

        $ownerId = $this->getOwnerId();

        // Get sales data for last 30 days
        $salesData = BarOrder::where('user_id', $ownerId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get top selling products
        $topProducts = OrderItem::whereHas('order', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId)
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', now()->subDays(30));
        })
            ->select(
                'product_variant_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy('product_variant_id')
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->with('productVariant.product')
            ->get();

        // Calculate expected revenue (based on counter stock)
        $counterStock = ProductVariant::whereHas('product', function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        })
            ->whereHas('stockLocations', function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)->where('location', 'counter')->where('quantity', '>', 0);
            })
            ->with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId)->where('location', 'counter');
            }])
            ->get()
            ->map(function ($variant) {
                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                $sellingPrice = $counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0;

                return [
                    'product_name' => $variant->product->name,
                    'quantity' => $counterStock->quantity,
                    'selling_price' => $sellingPrice,
                    'potential_revenue' => $counterStock->quantity * $sellingPrice,
                ];
            });

        $expectedRevenue = $counterStock->sum('potential_revenue');

        // Revenue by day of week
        $revenueByDay = BarOrder::where('user_id', $ownerId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DAYNAME(created_at) as day_name'),
                DB::raw('DAYOFWEEK(created_at) as day_number'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('day_name', 'day_number')
            ->orderBy('day_number')
            ->get();

        return view('bar.counter.analytics', compact(
            'salesData',
            'topProducts',
            'counterStock',
            'expectedRevenue',
            'revenueByDay'
        ));
    }

    /**
     * View Customer Orders (direct orders from customers)
     */
    public function customerOrders()
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to view customer orders.');
        }

        $ownerId = $this->getOwnerId();

        // Get orders without waiter_id (direct customer orders)
        $orders = BarOrder::where('user_id', $ownerId)
            ->whereNull('waiter_id')
            ->with(['items.productVariant.product', 'table'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get order counts
        $pendingCount = BarOrder::where('user_id', $ownerId)
            ->whereNull('waiter_id')
            ->where('status', 'pending')
            ->count();

        $preparedCount = BarOrder::where('user_id', $ownerId)
            ->whereNull('waiter_id')
            ->where('status', 'prepared')
            ->count();

        $servedCount = BarOrder::where('user_id', $ownerId)
            ->whereNull('waiter_id')
            ->where('status', 'served')
            ->where('payment_status', 'pending')
            ->count();

        return view('bar.counter.customer-orders', compact('orders', 'pendingCount', 'preparedCount', 'servedCount'));
    }

    /**
     * View Pending Stock Transfer Requests
     */
    public function stockTransferRequests()
    {
        // Check permission
        if (! $this->hasPermission('stock_transfer', 'view')) {
            abort(403, 'You do not have permission to view stock transfer requests.');
        }

        $ownerId = $this->getOwnerId();

        $transfers = StockTransfer::where('user_id', $ownerId)
            ->with(['productVariant.product', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('bar.counter.stock-transfer-requests', compact('transfers'));
    }

    /**
     * Show voice recording page
     */
    public function recordVoice()
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            abort(403, 'You do not have permission to record voice announcements.');
        }

        return view('bar.counter.record-voice');
    }

    /**
     * Save voice clip
     */
    public function saveVoiceClip(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission.'], 403);
        }

        // Validate based on whether it's a file upload or base64
        if ($request->hasFile('audio_file')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'required|in:static,number,waiter,product',
                'audio_file' => 'required|file|mimes:mp3,wav,ogg,webm,m4a,aac|max:10240', // 10MB max
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'required|in:static,number,waiter,product',
                'audio' => 'required|string', // Base64 encoded audio
            ]);
        }

        $ownerId = $this->getOwnerId();

        try {
            $audioBinary = null;
            $extension = 'webm';

            // Check if it's a file upload or base64
            if ($request->hasFile('audio_file')) {
                // Handle file upload
                $file = $request->file('audio_file');
                $extension = $file->getClientOriginalExtension();
                // Validate extension
                $allowedExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'];
                if (! in_array(strtolower($extension), $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid file format. Allowed: '.implode(', ', $allowedExtensions),
                    ], 400);
                }
                $audioBinary = file_get_contents($file->getRealPath());
            } else {
                // Handle base64 audio
                $audioData = $request->input('audio');

                // Extract MIME type and data from data URI
                if (preg_match('/data:audio\/([^;]+);base64,(.+)/', $audioData, $matches)) {
                    $extension = $matches[1]; // e.g., webm, mp3, wav
                    $audioData = $matches[2];
                } elseif (strpos($audioData, 'data:audio') === 0) {
                    // Fallback: extract after comma
                    $parts = explode(',', $audioData);
                    if (count($parts) > 1) {
                        $audioData = $parts[1];
                        // Try to detect extension from MIME type
                        if (preg_match('/data:audio\/([^;]+)/', $parts[0], $mimeMatch)) {
                            $extension = $mimeMatch[1];
                        }
                    }
                }

                $audioBinary = base64_decode($audioData);
            }

            if (! $audioBinary) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid audio data',
                ], 400);
            }

            // Generate filename with proper extension
            $filename = time().'_'.uniqid().'.'.$extension;
            $directory = public_path('storage/voice-clips');

            // Create directory if it doesn't exist
            if (! file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save audio file
            $filePath = $directory.'/'.$filename;
            file_put_contents($filePath, $audioBinary);

            // Save to database
            $voiceClip = \App\Models\VoiceClip::create([
                'user_id' => $ownerId,
                'name' => $validated['name'],
                'category' => $validated['category'],
                'audio_path' => 'voice-clips/'.$filename,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voice clip saved successfully',
                'clip' => $voiceClip,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to save voice clip: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get voice clips
     */
    public function getVoiceClips()
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission.'], 403);
        }

        $ownerId = $this->getOwnerId();

        $clips = \App\Models\VoiceClip::where('user_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($clip) {
                return [
                    'id' => $clip->id,
                    'name' => $clip->name,
                    'category' => $clip->category,
                    'audio_url' => asset('storage/'.$clip->audio_path),
                    'created_at' => $clip->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'clips' => $clips,
        ]);
    }

    /**
     * Update voice clip
     */
    public function updateVoiceClip(Request $request, $id)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission.'], 403);
        }

        $ownerId = $this->getOwnerId();

        // Find the clip
        $clip = \App\Models\VoiceClip::where('id', $id)
            ->where('user_id', $ownerId)
            ->first();

        if (! $clip) {
            return response()->json(['error' => 'Voice clip not found.'], 404);
        }

        // Validate based on whether it's a file upload or base64
        if ($request->hasFile('audio_file')) {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'category' => 'sometimes|in:static,number,waiter,product',
                'audio_file' => 'required|file|mimes:mp3,wav,ogg,webm,m4a,aac|max:10240', // 10MB max
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'category' => 'sometimes|in:static,number,waiter,product',
                'audio' => 'required|string', // Base64 encoded audio
            ]);
        }

        try {
            $audioBinary = null;
            $extension = pathinfo($clip->audio_path, PATHINFO_EXTENSION); // Keep original extension if no new audio

            // Check if it's a file upload or base64
            if ($request->hasFile('audio_file')) {
                // Handle file upload
                $file = $request->file('audio_file');
                $extension = $file->getClientOriginalExtension();
                // Validate extension
                $allowedExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'];
                if (! in_array(strtolower($extension), $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid file format. Allowed: '.implode(', ', $allowedExtensions),
                    ], 400);
                }
                $audioBinary = file_get_contents($file->getRealPath());
            } elseif ($request->has('audio')) {
                // Handle base64 audio
                $audioData = $request->input('audio');

                // Extract MIME type and data from data URI
                if (preg_match('/data:audio\/([^;]+);base64,(.+)/', $audioData, $matches)) {
                    $extension = $matches[1]; // e.g., webm, mp3, wav
                    $audioData = $matches[2];
                } elseif (strpos($audioData, 'data:audio') === 0) {
                    // Fallback: extract after comma
                    $parts = explode(',', $audioData);
                    if (count($parts) > 1) {
                        $audioData = $parts[1];
                        // Try to detect extension from MIME type
                        if (preg_match('/data:audio\/([^;]+)/', $parts[0], $mimeMatch)) {
                            $extension = $mimeMatch[1];
                        }
                    }
                }

                $audioBinary = base64_decode($audioData);
            }

            // Update name and category if provided
            if (isset($validated['name'])) {
                $clip->name = $validated['name'];
            }
            if (isset($validated['category'])) {
                $clip->category = $validated['category'];
            }

            // If new audio provided, replace the file
            if ($audioBinary) {
                // Delete old audio file
                $oldFilePath = public_path('storage/'.$clip->audio_path);
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }

                // Generate new filename with proper extension
                $filename = time().'_'.uniqid().'.'.$extension;
                $directory = public_path('storage/voice-clips');

                // Create directory if it doesn't exist
                if (! file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Save new audio file
                $filePath = $directory.'/'.$filename;
                file_put_contents($filePath, $audioBinary);

                // Update audio path
                $clip->audio_path = 'voice-clips/'.$filename;
            }

            $clip->save();

            return response()->json([
                'success' => true,
                'message' => 'Voice clip updated successfully',
                'clip' => $clip,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update voice clip: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete voice clip
     */
    public function deleteVoiceClip($id)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'view')) {
            return response()->json(['error' => 'You do not have permission.'], 403);
        }

        $ownerId = $this->getOwnerId();

        $clip = \App\Models\VoiceClip::where('id', $id)
            ->where('user_id', $ownerId)
            ->first();

        if (! $clip) {
            return response()->json(['error' => 'Voice clip not found.'], 404);
        }

        // Delete audio file
        $filePath = public_path('storage/'.$clip->audio_path);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete from database
        $clip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Voice clip deleted successfully',
        ]);
    }

    /**
     * Create Order from Counter
     */
    public function createOrder(Request $request)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'create')) {
            return response()->json(['error' => 'You do not have permission to create orders.'], 403);
        }

        $ownerId = $this->getOwnerId();
        $staff = $this->getCurrentStaff();

        if (! $staff || ! $staff->is_active) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // REQUIRE ACTIVE SHIFT
        $activeShift = $this->getCurrentShift();
        if (! $activeShift) {
            return response()->json(['error' => 'Please open a shift before creating orders.'], 403);
        }

        // Validate items
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'table_id' => 'nullable|exists:bar_tables,id',
            'existing_order_id' => 'nullable|exists:orders,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'order_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $existingOrderId = $request->input('existing_order_id');
            $existingOrder = $existingOrderId ? BarOrder::find($existingOrderId) : null;

            // Calculate total and prepare items
            $totalAmount = 0;
            $orderItems = [];
            $kitchenOrderItems = [];
            $foodItemsNotes = [];

            foreach ($request->input('items') as $item) {
                // Handle food items
                if (isset($item['food_item_id']) && $item['food_item_id'] !== null) {
                    $unitPrice = (float) $item['price'];
                    $quantity = (int) $item['quantity'];
                    $itemTotal = $quantity * $unitPrice;
                    $totalAmount += $itemTotal;

                    $kitchenOrderItems[] = [
                        'food_item_id' => $item['food_item_id'],
                        'food_item_name' => $item['product_name'] ?? 'Food Item',
                        'variant_name' => $item['variant_name'] ?? null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $itemTotal,
                        'special_instructions' => $item['notes'] ?? null,
                        'status' => 'pending',
                    ];

                    $foodItemNote = $quantity.'x '.($item['product_name'] ?? 'Food Item').
                                   (isset($item['variant_name']) && $item['variant_name'] ? ' ('.$item['variant_name'].')' : '').
                                   ' - Tsh '.number_format($unitPrice, 0);

                    if (isset($item['notes']) && $item['notes']) {
                        $foodItemNote .= ' [Note: '.$item['notes'].']';
                    }

                    $foodItemsNotes[] = $foodItemNote;

                    continue;
                }

                // Handle Regular product variants (drinks)
                if (! isset($item['variant_id'])) {
                    continue;
                }

                $sellType = $item['sell_type'] ?? 'unit';
                $variant = ProductVariant::with(['product', 'stockLocations' => function ($query) use ($ownerId) {
                    $query->where('user_id', $ownerId)->where('location', 'counter');
                }])->findOrFail($item['variant_id']);

                $counterStock = $variant->stockLocations->where('location', 'counter')->first();
                if (! $counterStock) {
                    throw new \Exception("Counter stock not found for {$variant->product->name}");
                }

                // Accurate stock check for shots vs units (Match Waiter logic)
                if ($sellType === 'tot') {
                    $totsPerBottle = $variant->total_tots ?: 1;
                    $openBottle = \App\Models\OpenBottle::where('user_id', $ownerId)
                        ->where('product_variant_id', $variant->id)
                        ->first();

                    $totalTotsAvailable = ($counterStock->quantity * $totsPerBottle) + ($openBottle ? $openBottle->tots_remaining : 0);

                    if ($totalTotsAvailable < $item['quantity']) {
                        throw new \Exception("Insufficient shots for {$variant->product->name}. [Available: {$totalTotsAvailable}]");
                    }
                } else {
                    if ($counterStock->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$variant->product->name}");
                    }
                }

                $sellingPrice = $sellType === 'tot'
                    ? ($counterStock->selling_price_per_tot ?? $variant->selling_price_per_tot ?? 0)
                    : ($counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0);

                $itemTotal = $item['quantity'] * $sellingPrice;
                $totalAmount += $itemTotal;

                $orderItems[] = [
                    'product_variant_id' => $variant->id,
                    'sell_type' => $sellType,
                    'quantity' => $item['quantity'],
                    'unit_price' => $sellingPrice,
                    'total_price' => $itemTotal,
                ];
            }

            // Build order notes
            $notesParts = [];
            if (! empty($foodItemsNotes)) {
                $notesParts[] = 'FOOD ITEMS: '.implode(', ', $foodItemsNotes);
            }
            if (! empty($validated['order_notes'])) {
                $notesParts[] = 'ORDER NOTES: '.$validated['order_notes'];
            }
            $newNotes = implode(' | ', $notesParts);

            if ($existingOrder && ! in_array($existingOrder->status, ['cancelled', 'voided', 'rejected'])) {
                // UPDATE EXISTING (active) ORDER
                $existingOrder->total_amount += $totalAmount;
                if (! empty($newNotes)) {
                    $existingOrder->notes = ($existingOrder->notes ? $existingOrder->notes.' | ' : '').$newNotes;
                }
                // Check if new items should be automatically served
                $autoServeNewItems = ($existingOrder->status === 'served');

                if ($existingOrder->status === 'completed') {
                    $existingOrder->status = 'pending'; // Completed orders must revert to be managed
                }
                // NOTE: We no longer revert 'served' status to 'pending' because new items
                // will be automatically served and stock will be deducted immediately.
                $existingOrder->save();
                $order = $existingOrder;
                $message = 'Items added to existing order successfully';
            } else {
                // CREATE NEW ORDER (also when existingOrder is cancelled/voided)
                $orderNumber = BarOrder::generateOrderNumber($ownerId);
                $order = BarOrder::create([
                    'user_id' => $ownerId,
                    'order_number' => $orderNumber,
                    'waiter_id' => $staff->id,
                    'order_source' => 'counter',
                    'table_id' => $validated['table_id'] ?? null,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'notes' => $newNotes,
                    'bar_shift_id' => $activeShift->id,
                ]);
                $message = 'Order created successfully';
            }

            // Create items and attribute via service
            $transferSaleService = new \App\Services\TransferSaleService;
            $autoServeNewItems = (isset($autoServeNewItems) && $autoServeNewItems);

            foreach ($orderItems as $item) {
                // Deduct stock before creating the item record ONLY if it is auto-served
                // Otherwise, stock will be deducted when counter marks the order as "Served"
                if ($autoServeNewItems) {
                    $variantId = $item['product_variant_id'];
                    $counterStock = StockLocation::where('user_id', $ownerId)
                        ->where('product_variant_id', $variantId)
                        ->where('location', 'counter')
                        ->first();

                    if ($counterStock) {
                        if ($item['sell_type'] === 'tot') {
                            $variant = ProductVariant::find($variantId);
                            $totsPerBottle = $variant->total_tots ?: 1;
                            $totsNeeded = $item['quantity'];

                            // Handle Tots/Shots (Opening bottles logic)
                            $openBottle = \App\Models\OpenBottle::where('user_id', $ownerId)
                                ->where('product_variant_id', $variantId)
                                ->first();

                            if ($openBottle) {
                                if ($openBottle->tots_remaining >= $totsNeeded) {
                                    $openBottle->decrement('tots_remaining', $totsNeeded);
                                    if ($openBottle->tots_remaining <= 0) $openBottle->delete();
                                    $totsNeeded = 0;
                                } else {
                                    $totsNeeded -= $openBottle->tots_remaining;
                                    $openBottle->delete();
                                }
                            }

                            while ($totsNeeded > 0) {
                                if ($counterStock->quantity >= 1) {
                                    $counterStock->decrement('quantity', 1);
                                    app(\App\Services\StockAlertService::class)->checkCounterStock($variantId, $ownerId);
                                    if ($totsNeeded >= $totsPerBottle) {
                                        $totsNeeded -= $totsPerBottle;
                                    } else {
                                        \App\Models\OpenBottle::create([
                                            'user_id' => $ownerId,
                                            'product_variant_id' => $variantId,
                                            'tots_remaining' => $totsPerBottle - $totsNeeded,
                                        ]);
                                        $totsNeeded = 0;
                                    }
                                    StockMovement::create([
                                        'user_id' => $ownerId,
                                        'product_variant_id' => $variantId,
                                        'movement_type' => 'usage',
                                        'from_location' => 'counter',
                                        'to_location' => null,
                                        'quantity' => 1,
                                        'unit_price' => $item['unit_price'],
                                        'reference_type' => BarOrder::class,
                                        'reference_id' => $order->id,
                                        'created_by' => $ownerId,
                                        'notes' => 'Bottle opened (Counter POS Auto-Serve): ' . $order->order_number,
                                    ]);
                                } else $totsNeeded = 0;
                            }
                        } else {
                            // Standard unit/bottle deduction
                            $counterStock->decrement('quantity', $item['quantity']);
                            app(\App\Services\StockAlertService::class)->checkCounterStock($variantId, $ownerId);

                            StockMovement::create([
                                'user_id' => $ownerId,
                                'product_variant_id' => $variantId,
                                'movement_type' => 'sale',
                                'from_location' => 'counter',
                                'to_location' => null,
                                'quantity' => $item['quantity'],
                                'unit_price' => $item['unit_price'],
                                'reference_type' => BarOrder::class,
                                'reference_id' => $order->id,
                                'created_by' => $ownerId,
                                'notes' => 'Counter POS Auto-Serve: ' . $order->order_number,
                            ]);
                        }
                    }
                }

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'sell_type' => $item['sell_type'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'is_served' => $autoServeNewItems, // Auto-serve if order is already served
                ]);
                
                $transferSaleService->attributeSaleToTransfer($orderItem, $ownerId);
            }

            foreach ($kitchenOrderItems as $item) {
                KitchenOrderItem::create([
                    'order_id' => $order->id,
                    'food_item_id' => $item['food_item_id'],
                    'food_item_name' => $item['food_item_name'],
                    'variant_name' => $item['variant_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'special_instructions' => $item['special_instructions'],
                    'status' => 'pending',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'order' => $order->load(['items.productVariant.product', 'table']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Request $request, BarOrder $order)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission to cancel orders.'], 403);
        }

        $ownerId = $this->getOwnerId();
        if ($order->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json(['error' => 'Only pending orders can be cancelled'], 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $message = $this->handleCounterCancellation($order, $ownerId, $validated['reason'] ?? null);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to cancel order: '.$e->getMessage()], 500);
        }
    }

    /**
     * Record payment for an order
     */
    public function recordPayment(Request $request, BarOrder $order)
    {
        // Check permission
        if (! $this->hasPermission('bar_orders', 'edit')) {
            return response()->json(['error' => 'You do not have permission.'], 403);
        }

        $ownerId = $this->getOwnerId();
        if ($order->user_id !== $ownerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,mobile_money,bank,card',
            'mobile_money_number' => 'required_if:payment_method,mobile_money|nullable|string|max:20',
            'transaction_reference' => 'required_if:payment_method,mobile_money|nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $staff = $this->getCurrentStaff();
            $activeShift = $this->getCurrentShift();

            if (! $activeShift) {
                return response()->json(['error' => 'Please open a shift before recording payments.'], 403);
            }

            // Map the frontend 'bank' string to the database enum 'bank_transfer'
            $paymentMethod = $validated['payment_method'] === 'bank' ? 'bank_transfer' : $validated['payment_method'];

            $remainingBalance = $order->total_amount - $order->paid_amount;

            // If already fully paid, don't record another payment
            if ($remainingBalance <= 0) {
                return response()->json(['error' => 'This order is already fully paid.'], 400);
            }

            $order->update([
                'payment_method' => $paymentMethod,
                'mobile_money_number' => $validated['mobile_money_number'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_status' => 'paid',
                'paid_amount' => $order->total_amount, // Mark as fully paid
                'paid_by_waiter_id' => $staff->id,
                'bar_shift_id' => $activeShift->id,
            ]);

            \App\Models\OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $remainingBalance, // ONLY record the remaining balance
                'mobile_money_number' => $validated['mobile_money_number'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_status' => 'verified',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'order' => $order->load(['items.productVariant.product', 'table', 'orderPayments']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to record payment: '.$e->getMessage()], 500);
        }
    }

    /**
     * View Shift History
     */
    public function shiftHistory()
    {
        $staff = $this->getCurrentStaff();
        if (! $staff) {
            return redirect()->route('dashboard');
        }

        $ownerId = $this->getOwnerId();

        // Get shifts for this owner, specifically for this staff member (if applicable)
        $shifts = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('staff_id', $staff->id)
            ->with(['orders' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }])
            ->orderBy('opened_at', 'desc')
            ->paginate(15);

        // Fix missing totals on-the-fly for the view
        foreach ($shifts as $shift) {

            if (! $shift->expected_cash || ! $shift->digital_revenue) {
            // Apply calculation for CLOSED shifts with zero values OR for OPEN shifts to show real-time data
            if (($shift->status === 'closed' && $shift->expected_cash == 0 && $shift->digital_revenue == 0) || $shift->status === 'open') {
                // Not summarized correctly? Let's check both linked and unlinked during that timeframe
                $shiftOrders = $shift->orders;

                if ($shiftOrders->isEmpty() || $shift->status === 'open') {
                    // Try to find unlinked orders during this timeframe or refresh open shift data
                    $shiftOrders = \App\Models\BarOrder::where('user_id', $ownerId)
                        ->where('status', '!=', 'cancelled')
                        ->where('created_at', '>=', $shift->opened_at)
                        ->where('created_at', '<=', $shift->closed_at ?? now())
                        ->get();
                }

                $cashSales = 0;
                $digitalSales = 0;
                foreach ($shiftOrders as $order) {
                    $amt = (float) ($order->paid_amount ?: $order->total_amount);
                    if (in_array($order->payment_method, ['mobile_money', 'bank_transfer', 'card', 'bank'])) {
                        $digitalSales += $amt;
                    } else {
                        $cashSales += $amt;
                    }
                }
                $shift->expected_cash = $cashSales;
                $shift->digital_revenue = $digitalSales;
            }
        }
    }

    return view('bar.counter.shift-history', compact('shifts', 'staff'));
}

    /**
     * Counter cancel: if active kitchen food remains, only remove drink lines and keep the ticket open.
     * Otherwise fully cancel the order (drinks + kitchen).
     */
    protected function handleCounterCancellation(BarOrder $order, int $ownerId, ?string $reason): string
    {
        $order->load(['items', 'kitchenOrderItems']);

        $hadDrinks = $order->items->isNotEmpty();
        $hasActiveFood = $order->kitchenOrderItems->contains(function (KitchenOrderItem $k) {
            return ! in_array($k->status, ['cancelled', 'completed'], true);
        });

        if ($hadDrinks && $hasActiveFood) {
            $removedBarAmount = $this->stripBarItemsFromOrder($order, $ownerId);
            $suffix = 'BAR LINES VOIDED AT COUNTER'.($reason ? ' — '.$reason : '').' | BAR VOID VALUE: '.number_format($removedBarAmount, 2, '.', '');
            $order->notes = $order->notes ? $order->notes.' | '.$suffix : $suffix;
            $order->save();

            return 'Drink lines removed at counter. Food items on this ticket stay active for the kitchen.';
        }

        if ($hadDrinks) {
            $removedBarAmount = $this->stripBarItemsFromOrder($order, $ownerId);
            $barValueSuffix = 'BAR VOID VALUE: '.number_format($removedBarAmount, 2, '.', '');
            $order->notes = $order->notes ? $order->notes.' | '.$barValueSuffix : $barValueSuffix;
        }

        $order->status = 'cancelled';
        $suffix = $reason ? 'CANCELLED - Reason: '.$reason : 'CANCELLED';
        $order->notes = $order->notes ? $order->notes.' | '.$suffix : $suffix;
        $order->save();

        $order->kitchenOrderItems()->whereNotIn('status', ['completed'])->update(['status' => 'cancelled']);

        return 'Order cancelled.';
    }

    /**
     * Remove all bar order lines with the same stock / transfer-sale rules as waiter full cancel.
     */
    protected function stripBarItemsFromOrder(BarOrder $order, int $ownerId): float
    {
        $order->load('items');
        if ($order->items->isEmpty()) {
            return 0.0;
        }

        $itemIds = $order->items->pluck('id');
        $removedTotal = (float) $order->items->sum('total_price');

        $affectedTransferIds = TransferSale::whereIn('order_item_id', $itemIds)
            ->pluck('stock_transfer_id')
            ->unique()
            ->filter();

        $transferSaleService = new TransferSaleService;

        foreach ($order->items as $item) {
            if (! $item->product_variant_id) {
                continue;
            }

            $counterStock = StockLocation::where('user_id', $order->user_id)
                ->where('product_variant_id', $item->product_variant_id)
                ->where('location', 'counter')
                ->first();

            if (! $counterStock) {
                continue;
            }

            $hasMovement = \App\Models\StockMovement::where('reference_type', BarOrder::class)
                ->where('reference_id', $order->id)
                ->where('product_variant_id', $item->product_variant_id)
                ->exists();

            if ($hasMovement && ($item->sell_type ?? 'unit') === 'unit') {
                $counterStock->increment('quantity', $item->quantity);
                
                // Also delete the movement to keep history clean
                \App\Models\StockMovement::where('reference_type', BarOrder::class)
                    ->where('reference_id', $order->id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->delete();
            }
        }

        TransferSale::whereIn('order_item_id', $itemIds)->delete();
        foreach ($affectedTransferIds as $transferId) {
            $transfer = StockTransfer::find($transferId);
            if ($transfer) {
                $transferSaleService->checkTransferCompletion($transfer, $order->user_id);
            }
        }

        OrderItem::whereIn('id', $itemIds)->delete();

        $order->total_amount = max(0, (float) $order->total_amount - $removedTotal);
        if ((float) $order->paid_amount > $order->total_amount) {
            $order->paid_amount = $order->total_amount;
        }

        $order->save();
        $order->refresh();

        return $removedTotal;
    }

    /**
     * Update the low stock alert threshold for a counter item.
     */
    public function updateThreshold(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product_variants,id',
            'threshold' => 'required|integer|min:0'
        ]);

        $ownerId = $this->getOwnerId();
        $variant = ProductVariant::where('id', $request->id)
            ->whereHas('product', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            })
            ->first();

        if (!$variant) {
            return response()->json(['success' => false, 'message' => 'Product variant not found.'], 404);
        }

        $variant->counter_alert_threshold = $request->threshold;
        
        // If they update threshold, reset the last alert time to allow immediate re-trigger if below new threshold
        $variant->last_counter_alert_at = null; 
        
        $variant->save();

        return response()->json([
            'success' => true,
            'message' => 'Threshold updated successfully.',
            'threshold' => $variant->counter_alert_threshold
        ]);
    }
}
