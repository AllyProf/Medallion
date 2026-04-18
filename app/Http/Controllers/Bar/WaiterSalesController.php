<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\BarOrder;
use App\Models\WaiterDailyReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaiterSalesController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Display waiter sales dashboard
     */
    public function salesDashboard(Request $request)
    {
        $waiter = $this->getCurrentStaff();
        
        if (!$waiter || !$waiter->is_active) {
            abort(403, 'You must be logged in as an active waiter.');
        }

        // Check if staff has waiter role
        $role = $waiter->role;
        if (!$role || strtolower($role->name) !== 'waiter') {
            abort(403, 'You do not have permission to access the waiter sales dashboard.');
        }

        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));

        // [LOGIC FIX] Shift-Based Discovery
        // Find the most appropriate shift context for the waiter
        $activeShift = \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->orderBy('opened_at', 'desc')
            ->first();

        // Get all orders for this waiter - Grouped by Shift instead of Date for cross-midnight support
        $ordersQuery = BarOrder::where('user_id', $ownerId)
            ->where('waiter_id', $waiter->id)
            ->with(['items.productVariant.product', 'kitchenOrderItems', 'table', 'orderPayments'])
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

        // Calculate totals
        $totalSales = $orders->sum('total_amount');
        $cashCollected = $orders->where('payment_method', 'cash')->sum('paid_amount') + 
                        $orders->sum(function($order) {
                            return $order->orderPayments->where('payment_method', 'cash')->sum('amount');
                        });
        $mobileMoneyCollected = $orders->where('payment_method', 'mobile_money')->sum('paid_amount') + 
                               $orders->sum(function($order) {
                                   return $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount');
                               });
        $totalOrders = $orders->count();
        $expectedAmount = $totalSales; // Expected = Total sales

        // Check if reconciliation already submitted
        $reconciliation = WaiterDailyReconciliation::where('waiter_id', $waiter->id);
        
        if ($activeShift && $date === $activeShift->opened_at->format('Y-m-d')) {
            $reconciliation->where('bar_shift_id', $activeShift->id);
        } else {
            $reconciliation->where('reconciliation_date', $date);
        }
        $reconciliation = $reconciliation->first();

        return view('bar.waiter.sales', compact(
            'orders', 
            'totalSales', 
            'cashCollected', 
            'mobileMoneyCollected', 
            'totalOrders', 
            'expectedAmount',
            'date', 
            'reconciliation',
            'waiter'
        ));
    }

    /**
     * Submit daily reconciliation
     */
    public function submitReconciliation(Request $request)
    {
        $waiter = $this->getCurrentStaff();
        
        if (!$waiter || !$waiter->is_active) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

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
        $existingQuery = WaiterDailyReconciliation::where('waiter_id', $waiter->id);
        if ($activeShift) {
            $existingQuery->where('bar_shift_id', $activeShift->id);
        } else {
            $existingQuery->where('reconciliation_date', $date);
        }
        $existing = $existingQuery->first();

        if ($existing && $existing->isSubmitted()) {
            return response()->json([
                'error' => 'Reconciliation already submitted for this business day.'
            ], 400);
        }

        // Get all orders for this waiter - Prioritize Shift ID
        $ordersQuery = BarOrder::where('user_id', $ownerId)
            ->where('waiter_id', $waiter->id);

        if ($activeShift) {
            $ordersQuery->where('bar_shift_id', $activeShift->id);
        } else {
            $ordersQuery->whereDate('created_at', $date);
        }
        $orders = $ordersQuery->get();

        // Calculate totals
        $totalSales = $orders->sum('total_amount');
        $cashCollected = $orders->where('payment_method', 'cash')->sum('paid_amount') + 
                        $orders->sum(function($order) {
                            return $order->orderPayments->where('payment_method', 'cash')->sum('amount');
                        });
        $mobileMoneyCollected = $orders->where('payment_method', 'mobile_money')->sum('paid_amount') + 
                               $orders->sum(function($order) {
                                   return $order->orderPayments->where('payment_method', 'mobile_money')->sum('amount');
                               });
        $expectedAmount = $totalSales;
        $difference = $submittedAmount - $expectedAmount;

        DB::beginTransaction();
        try {
            $data = [
                'total_sales' => $totalSales,
                'cash_collected' => $cashCollected,
                'mobile_money_collected' => $mobileMoneyCollected,
                'expected_amount' => $expectedAmount,
                'submitted_amount' => $submittedAmount,
                'difference' => $difference,
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
                $reconciliation = WaiterDailyReconciliation::create($data);
            }

            // Link orders to reconciliation
            $ordersUpdateQuery = BarOrder::where('user_id', $ownerId)
                ->where('waiter_id', $waiter->id);

            if ($activeShift) {
                $ordersUpdateQuery->where('bar_shift_id', $activeShift->id);
            } else {
                $ordersUpdateQuery->whereDate('created_at', $date);
            }
            $ordersUpdateQuery->update(['reconciliation_id' => $reconciliation->id]);

            DB::commit();

            // NEW: Trigger SMS notification if shortage detected
            try {
                if ($reconciliation->difference < -100) {
                    $smsService = new \App\Services\HandoverSmsService;
                    $smsService->sendShortageAlertSms($reconciliation);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send waiter self-submission shortage SMS: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation submitted successfully.',
                'reconciliation' => $reconciliation
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error submitting reconciliation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to submit reconciliation: ' . $e->getMessage()
            ], 500);
        }
    }
}
