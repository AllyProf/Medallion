<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\BarOrder;
use App\Models\OrderItem;
use App\Models\KitchenOrderItem;
use App\Models\OrderPayment;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LiveSalesController extends Controller
{
    private function getOwnerId()
    {
        return session('is_staff') ? Staff::find(session('staff_id'))->user_id : Auth::id();
    }

    public function index(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $location = session('active_location');
        $today = Carbon::today();

        // [SHIFT DISCOVERY] - Prioritize active trading shift
        $activeShift = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->when($location && $location !== 'all', function($q) use ($location) {
                $q->where('location_branch', $location);
            })
            ->orderBy('opened_at', 'desc')
            ->first();

        // Define a contextual filter closure for reuse
        $applyContext = function($query, $table = 'orders') use ($activeShift, $today) {
            if ($activeShift) {
                return $query->where($table . '.bar_shift_id', $activeShift->id);
            }
            return $query->whereDate($table . '.created_at', $today);
        };

        // 1. Live Revenue (Today) - Join with payments for accurate split
        $paymentsToday = OrderPayment::whereHas('order', function($q) use ($ownerId, $location, $applyContext) {
                $q->where('user_id', $ownerId)
                  ->where('status', '!=', 'cancelled');
                
                $applyContext($q, 'orders');
                
                if ($location) {
                    $q->where(function($sq) use ($location) {
                        $sq->whereExists(function ($ssq) use ($location) {
                            $ssq->select(DB::raw(1))
                               ->from('staff')
                               ->whereColumn('staff.id', 'orders.waiter_id')
                               ->where('staff.location_branch', $location);
                        })->orWhereHas('table', function($ssq) use ($location) {
                            $sq->where('location', $location);
                        });
                    });
                }
            })
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get();

        $todayCash = $paymentsToday->where('payment_method', 'cash')->first()->total ?? 0;
        $todayDigital = $paymentsToday->whereIn('payment_method', ['mobile_money', 'bank_transfer', 'm-pesa', 'tigopesa', 'airtel_money'])->sum('total');
        $totalRevenue = $todayCash + $todayDigital;

        // 2. Order Volume & Pulse
        $ordersTodayQuery = BarOrder::where('orders.user_id', $ownerId)
            ->where('orders.status', '!=', 'cancelled');
        $applyContext($ordersTodayQuery, 'orders');
        
        if ($location) {
            $ordersTodayQuery->where(function($q) use ($location) {
                $q->whereExists(function ($sq) use ($location) {
                    $sq->select(DB::raw(1))
                       ->from('staff')
                       ->whereColumn('staff.id', 'orders.waiter_id')
                       ->where('staff.location_branch', $location);
                })->orWhereHas('table', function($sq) use ($location) {
                    $sq->where('location', $location);
                });
            });
        }

        $totalOrders = (clone $ordersTodayQuery)->count();
        $activeOrders = (clone $ordersTodayQuery)->whereIn('status', ['pending', 'preparing', 'ready'])->count();
        $servedOrders = (clone $ordersTodayQuery)->where('status', 'served')->count();

        // 3. Hourly Velocity (Contextual)
        $hourlySalesQuery = BarOrder::where('user_id', $ownerId)
            ->where('status', '!=', 'cancelled');
        $applyContext($hourlySalesQuery, 'orders');

        $hourlySales = $hourlySalesQuery->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Fill in missing hours for a continuous chart
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = $hourlySales[$i] ?? 0;
        }

        // 4. Live Activity Feed (Last 15 Orders)
        $liveFeed = (clone $ordersTodayQuery)
            ->with(['waiter', 'items', 'kitchenOrderItems', 'table'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // 5. Staff Pulse (Top 10 Waiters Today)
        $staffPulse = (clone $ordersTodayQuery)
            ->join('staff', 'orders.waiter_id', '=', 'staff.id')
            ->select('staff.full_name', DB::raw('COUNT(orders.id) as orders_count'), DB::raw('SUM(orders.total_amount) as total_sales'))
            ->groupBy('staff.id', 'staff.full_name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        // 6. Top Items Pulse (Contextual)
        $topDrinks = OrderItem::whereHas('order', function($q) use ($ownerId, $applyContext, $location) {
                $q->where('orders.user_id', $ownerId)->where('orders.status', '!=', 'cancelled');
                $applyContext($q, 'orders');
                if ($location) {
                    $q->whereExists(function($sq) use ($location) {
                        $sq->select(DB::raw(1))->from('staff')->whereColumn('staff.id', 'orders.waiter_id')->where('staff.location_branch', $location);
                    });
                }
            })
            ->with('productVariant.product')
            ->select('product_variant_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_rev'))
            ->groupBy('product_variant_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function($item) {
                $item->display_name = $item->productVariant ? $item->productVariant->display_name : 'Unknown';
                return $item;
            });

        $topFood = KitchenOrderItem::whereHas('order', function($q) use ($ownerId, $applyContext, $location) {
                $q->where('orders.user_id', $ownerId)->where('orders.status', '!=', 'cancelled');
                $applyContext($q, 'orders');
                if ($location) {
                    $q->whereExists(function($sq) use ($location) {
                        $sq->select(DB::raw(1))->from('staff')->whereColumn('staff.id', 'orders.waiter_id')->where('staff.location_branch', $location);
                    });
                }
            })
            ->select('food_item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_rev'))
            ->groupBy('food_item_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'revenue' => [
                    'total' => number_format($totalRevenue),
                    'cash' => number_format($todayCash),
                    'digital' => number_format($todayDigital),
                ],
                'pulse' => [
                    'total_orders' => $totalOrders,
                    'active_orders' => $activeOrders,
                    'served_orders' => $servedOrders,
                ],
                'hourly_data' => array_values($hourlyData),
                'live_feed' => view('manager.partials.live_feed_items', compact('liveFeed'))->render(),
                'staff_pulse' => view('manager.partials.staff_pulse_items', compact('staffPulse'))->render(),
            ]);
        }

        return view('manager.live_sales', compact(
            'totalRevenue', 'todayCash', 'todayDigital',
            'totalOrders', 'activeOrders', 'servedOrders',
            'hourlyData', 'liveFeed', 'staffPulse', 'topDrinks', 'topFood',
            'activeShift'
        ));
    }
}
