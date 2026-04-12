<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\Staff;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StockAlertService
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Check if counter stock is low and send alerts if necessary.
     */
    public function checkCounterStock($variantId, $ownerId)
    {
        try {
            $variant = ProductVariant::with('product')->find($variantId);
            if (!$variant) return;

            // Get current counter stock
            $counterStock = StockLocation::where('user_id', $ownerId)
                ->where('product_variant_id', $variantId)
                ->where('location', 'counter')
                ->first();

            if (!$counterStock) return;

            $currentQty = $counterStock->quantity;
            $threshold = $variant->counter_alert_threshold ?? 10;

            // Check if alert is needed
            if ($currentQty < $threshold) {
                // Check if we already sent an alert recently (prevent spam)
                // We reset the 'sent_at' if the stock goes above threshold (handled in replenishment)
                if (!$variant->last_counter_alert_at) {
                    $this->sendLowStockAlert($variant, $currentQty, $ownerId);
                    
                    $variant->last_counter_alert_at = now();
                    $variant->save();
                }
            } else {
                // If stock is above threshold, clear the alert timestamp to re-arm the trigger
                if ($variant->last_counter_alert_at) {
                    $variant->last_counter_alert_at = null;
                    $variant->save();
                }
            }
        } catch (\Exception $e) {
            Log::error('StockAlertService Error: ' . $e->getMessage());
        }
    }

    /**
     * Send SMS alerts to Stock Keepers and Accountants.
     */
    protected function sendLowStockAlert($variant, $qty, $ownerId)
    {
        $productName = $variant->product->name . ($variant->name ? ' (' . $variant->name . ')' : '');
        $unit = $variant->measurement ?: 'units';
        
        $message = "LOW STOCK ALERT: {$productName} at Counter. Only {$qty} {$unit} remaining. Please replenish.";

        // Fetch recipients (Stock Keepers and Accountants)
        $roles = Role::whereIn('name', ['Stock Keeper', 'Accountant'])->pluck('id');
        
        $recipients = Staff::where('user_id', $ownerId)
            ->whereIn('role_id', $roles)
            ->where('is_active', true)
            ->whereNotNull('phone_number')
            ->get();

        foreach ($recipients as $staff) {
            $this->smsService->sendSms($staff->phone_number, $message);
            Log::info("Low stock alert sent to {$staff->full_name} ({$staff->phone_number}) for {$productName}");
        }
    }
}
