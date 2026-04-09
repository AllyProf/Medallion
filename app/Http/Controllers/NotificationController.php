<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BarOrder;
use App\Models\Staff;
use App\Models\PurchaseRequest;
use App\Models\StockTransfer;
use App\Models\WaiterDailyReconciliation;
use App\Models\WaiterNotification;
use App\Models\ProductVariant;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Return real-time notifications as JSON for the bell icon.
     */
    public function index()
    {
        $ownerId = $this->getOwnerId();
        if (!$ownerId) return response()->json(['count' => 0, 'notifications' => []]);

        $notifications = collect();
        $staffId = session('staff_id');

        // 0. Fetch recent staff-specific notifications if logged in as staff
        if ($staffId) {
            $staffNotifications = WaiterNotification::where('waiter_id', $staffId)
                ->where('is_read', false)
                ->latest()
                ->take(5)
                ->get();
            
            foreach ($staffNotifications as $sn) {
                // Determine icon and color based on type
                $icon = 'fa-bell-o';
                $color = 'text-primary';
                $link = '#';

                if (str_contains($sn->type, 'stock_transfer')) {
                    $icon = 'fa-truck';
                    $link = route('bar.stock-transfers.index');
                    if (str_contains($sn->type, 'success')) $color = 'text-success';
                    if (str_contains($sn->type, 'danger')) $color = 'text-danger';
                    if (str_contains($sn->type, 'info')) $color = 'text-info';
                } elseif (str_contains($sn->type, 'order')) {
                    $icon = 'fa-shopping-cart';
                }

                $notifications->push([
                    'icon'    => $icon,
                    'color'   => $color,
                    'message' => $sn->title . ': ' . $sn->message,
                    'time'    => $sn->created_at->diffForHumans(),
                    'link'    => $link,
                ]);
            }
        }

        // 1. New orders in last 24 hours (pending/unpaid)
        $newOrders = BarOrder::where('user_id', $ownerId)
            ->where('payment_status', '!=', 'paid')
            ->where('status', 'served')
            ->whereDate('created_at', today())
            ->count();
        if ($newOrders > 0) {
            $notifications->push([
                'icon'    => 'fa-shopping-cart',
                'color'   => 'text-primary',
                'message' => "{$newOrders} order(s) served but unpaid today",
                'time'    => 'Today',
                'link'    => '#',
            ]);
        }

        // 2. Pending purchase requests
        $pendingPR = PurchaseRequest::where('user_id', $ownerId)
            ->where('status', 'pending')
            ->count();
        if ($pendingPR > 0) {
            $notifications->push([
                'icon'    => 'fa-file-text-o',
                'color'   => 'text-warning',
                'message' => "{$pendingPR} purchase request(s) awaiting approval",
                'time'    => 'Pending',
                'link'    => '#',
            ]);
        }

        // 3. Pending stock transfers
        $pendingTransfers = StockTransfer::where('user_id', $ownerId)
            ->where('status', 'pending')
            ->count();
        if ($pendingTransfers > 0) {
            $notifications->push([
                'icon'    => 'fa-truck',
                'color'   => 'text-info',
                'message' => "{$pendingTransfers} stock transfer(s) pending",
                'time'    => 'Pending',
                'link'    => '#',
            ]);
        }

        // 4. Unverified reconciliations (submitted but not verified)
        $unverified = WaiterDailyReconciliation::where('user_id', $ownerId)
            ->where('status', 'submitted')
            ->count();
        if ($unverified > 0) {
            $notifications->push([
                'icon'    => 'fa-balance-scale',
                'color'   => 'text-danger',
                'message' => "{$unverified} reconciliation(s) need verification",
                'time'    => 'Action needed',
                'link'    => '#',
            ]);
        }

        // 5. Low stock variants (quantity <= 5)
        $lowStock = ProductVariant::whereHas('product', fn($q) => $q->where('user_id', $ownerId))
            ->whereHas('stockLocations', fn($sq) => $sq->where('quantity', '<=', 5)->where('quantity', '>', 0))
            ->count();
        if ($lowStock > 0) {
            $notifications->push([
                'icon'    => 'fa-exclamation-triangle',
                'color'   => 'text-warning',
                'message' => "{$lowStock} product variant(s) running low on stock",
                'time'    => 'Stock Alert',
                'link'    => '#',
            ]);
        }

        // 6. Out of stock
        $outOfStock = ProductVariant::whereHas('product', fn($q) => $q->where('user_id', $ownerId))
            ->whereHas('stockLocations', fn($sq) => $sq->where('quantity', '<=', 0))
            ->count();
        if ($outOfStock > 0) {
            $notifications->push([
                'icon'    => 'fa-times-circle',
                'color'   => 'text-danger',
                'message' => "{$outOfStock} product(s) are out of stock",
                'time'    => 'Critical',
                'link'    => '#',
            ]);
        }

        return response()->json([
            'count'         => $notifications->count(),
            'notifications' => $notifications->values(),
        ]);
    }

    /**
     * Global search across orders, staff, products.
     */
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));
        $ownerId = $this->getOwnerId();

        if (!$ownerId || strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = collect();

        // Orders
        $orders = BarOrder::where('user_id', $ownerId)
            ->where(function ($query) use ($q) {
                $query->where('order_number', 'like', "%{$q}%")
                      ->orWhere('customer_name', 'like', "%{$q}%");
            })
            ->latest()->take(5)->get();
        foreach ($orders as $order) {
            $results->push([
                'type'     => 'Order',
                'icon'     => 'fa-shopping-cart',
                'label'    => "#{$order->order_number} — " . ($order->customer_name ?? 'Walk-in'),
                'sub'      => 'TSh ' . number_format($order->total_amount) . ' · ' . ucfirst($order->payment_status),
                'link'     => '#',
            ]);
        }

        // Staff
        $staff = Staff::where('user_id', $ownerId)
            ->where('full_name', 'like', "%{$q}%")
            ->take(4)->get();
        foreach ($staff as $s) {
            $results->push([
                'type'  => 'Staff',
                'icon'  => 'fa-user',
                'label' => $s->full_name,
                'sub'   => $s->staff_id . ' · ' . ($s->role->name ?? 'No Role'),
                'link'  => '#',
            ]);
        }

        // Products
        $products = ProductVariant::whereHas('product', fn($pq) => $pq->where('user_id', $ownerId))
            ->where('variant_name', 'like', "%{$q}%")
            ->with('product')
            ->take(4)->get();
        foreach ($products as $pv) {
            $results->push([
                'type'  => 'Product',
                'icon'  => 'fa-cube',
                'label' => $pv->product->name . ' — ' . $pv->variant_name,
                'sub'   => 'Stock: ' . number_format($pv->stock_quantity),
                'link'  => '#',
            ]);
        }

        return response()->json(['results' => $results->take(12)->values()]);
    }

    private function getOwnerId(): ?int
    {
        if (auth()->check() && !session('is_staff')) return auth()->id();
        if (session('is_staff'))                       return session('staff_user_id');
        return null;
    }
}
