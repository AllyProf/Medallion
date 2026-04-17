<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaiterFoodSalesController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Display waiter food sales dashboard
     */
    public function salesDashboard(Request $request)
    {
        $waiter = $this->getCurrentStaff();
        
        if (!$waiter || !$waiter->is_active) {
            abort(403, 'You must be logged in as an active waiter.');
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));

        // [LOGIC FIX] Shift-Based Discovery
        // Find the most appropriate shift context for the waiter to handle cross-midnight orders
        $activeShift = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->orderBy('opened_at', 'desc')
            ->first();

        // Get all orders for this waiter - Grouped by Shift instead of Date for cross-midnight support
        $ordersQuery = BarOrder::where('user_id', $ownerId)
            ->where('waiter_id', $waiter->id)
            ->with(['kitchenOrderItems', 'table', 'orderPayments'])
            ->orderBy('created_at', 'desc');

        if ($activeShift && (!$request->has('date') || $request->get('date') === $activeShift->opened_at->format('Y-m-d'))) {
            // Priority: Show orders for the active operational shift
            $ordersQuery->where('bar_shift_id', $activeShift->id);
            $date = $activeShift->opened_at->format('Y-m-d');
        } else {
            // Fallback: Group by date if looking at history
            $ordersQuery->whereDate('created_at', $date);
        }

        $orders = $ordersQuery->get();

        // Calculate Food Totals ONLY
        $foodSales = 0;
        $foodCash = 0;
        $foodDigital = 0;
        $foodOrdersCount = 0;

        foreach ($orders as $order) {
            if ($order->status === 'cancelled') continue;
            
            $orderFoodItems = $order->kitchenOrderItems->where('status', '!=', 'cancelled');
            $orderFoodTotal = $orderFoodItems->sum('total_price');
            
            if ($orderFoodTotal > 0) {
                $foodSales += $orderFoodTotal;
                $foodOrdersCount++;
                
                // Calculate share of payment for food
                $orderBarTotal = $order->items->sum('total_price');
                $orderGrandTotal = $orderBarTotal + $orderFoodTotal;
                
                if ($orderGrandTotal > 0) {
                    $foodShare = $orderFoodTotal / $orderGrandTotal;
                    
                    $orderCash = $order->orderPayments->where('payment_method', 'cash')->sum('amount');
                    $orderDigital = $order->orderPayments->where('payment_method', '!=', 'cash')->sum('amount');
                    
                    // Legacy check
                    if ($order->payment_method === 'cash') $orderCash += $order->paid_amount;
                    if ($order->payment_method === 'mobile_money') $orderDigital += $order->paid_amount;

                    $foodCash += ($orderCash * $foodShare);
                    $foodDigital += ($orderDigital * $foodShare);
                }
            }
        }

        // Check if food reconciliation already submitted
        $reconciliation = WaiterDailyReconciliation::where('waiter_id', $waiter->id)
            ->where('reconciliation_date', $date)
            ->where('reconciliation_type', 'food')
            ->first();

        return view('bar.waiter.sales-food', [
            'orders' => $orders,
            'totalSales' => $foodSales,
            'cashCollected' => $foodCash,
            'mobileMoneyCollected' => $foodDigital,
            'totalOrders' => $foodOrdersCount,
            'expectedAmount' => $foodSales,
            'date' => $date,
            'reconciliation' => $reconciliation,
            'waiter' => $waiter
        ]);
    }

    /**
     * Submit food reconciliation
     */
    public function submitReconciliation(Request $request)
    {
        $waiter = $this->getCurrentStaff();
        if (!$waiter || !$waiter->is_active) return response()->json(['error' => 'Unauthorized'], 401);

        $ownerId = $this->getOwnerId();
        $date = $request->input('date', now()->format('Y-m-d'));
        $submittedAmount = $request->input('submitted_amount', 0);
        $notes = $request->input('notes', '');

        // [LOGIC FIX] Determine Business Shift Context
        $activeShift = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->orderBy('opened_at', 'desc')
            ->first();

        // If we have an active shift, LOCK the date to the business start date
        if ($activeShift) {
            $date = $activeShift->opened_at->format('Y-m-d');
        }

        // Check if already submitted for this specific shift or date
        $existingQuery = WaiterDailyReconciliation::where('waiter_id', $waiter->id)
            ->where('reconciliation_type', 'food');
            
        if ($activeShift) {
            $existingQuery->where('bar_shift_id', $activeShift->id);
        } else {
            $existingQuery->where('reconciliation_date', $date);
        }
        $existing = $existingQuery->first();

        if ($existing && $existing->status !== 'pending') {
            return response()->json(['error' => 'Food reconciliation already submitted.'], 400);
        }

        // Get all orders for this waiter - Prioritize Shift ID
        $ordersQuery = BarOrder::where('user_id', $ownerId)
            ->where('waiter_id', $waiter->id);

        if ($activeShift) {
            $ordersQuery->where('bar_shift_id', $activeShift->id);
        } else {
            $ordersQuery->whereDate('created_at', $date);
        }
        
        $orders = $ordersQuery->with(['kitchenOrderItems', 'items', 'orderPayments'])
            ->get();

        $foodSales = 0;
        foreach ($orders as $order) {
            if ($order->status === 'cancelled') continue;
            $foodSales += $order->kitchenOrderItems->where('status', '!=', 'cancelled')->sum('total_price');
        }

        DB::beginTransaction();
        try {
            $data = [
                'total_sales' => $foodSales,
                'expected_amount' => $foodSales,
                'submitted_amount' => $submittedAmount,
                'difference' => $submittedAmount - $foodSales,
                'status' => 'submitted',
                'submitted_at' => now(),
                'notes' => $notes,
                'bar_shift_id' => $activeShift ? $activeShift->id : ($existing ? $existing->bar_shift_id : null),
            ];

            if ($existing) {
                $existing->update($data);
                $reconciliation = $existing;
            } else {
                $data['user_id'] = $ownerId;
                $data['waiter_id'] = $waiter->id;
                $data['reconciliation_date'] = $date;
                $data['reconciliation_type'] = 'food';
                $reconciliation = WaiterDailyReconciliation::create($data);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Food reconciliation submitted to Chef.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
