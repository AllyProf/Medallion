<?php

namespace App\Services;

use App\Models\StockReceipt;
use App\Models\Staff;
use App\Models\SystemSetting;
use Carbon\Carbon;

class StockReceiptSmsService
{
    protected $smsService;

    public function __construct()
    {
        $this->smsService = new SmsService();
    }

    /**
     * Send summary notification SMS for a batch of stock receipts
     */
    public function sendBatchStockReceiptNotification($receiptNumber, $ownerId)
    {
        // Check if notifications are enabled
        $enableNotifications = SystemSetting::get('enable_stock_receipt_sms_' . $ownerId, true);
        
        if (!$enableNotifications) {
            \Log::info('Stock receipt SMS notifications are disabled', [
                'receipt_number' => $receiptNumber,
                'owner_id' => $ownerId
            ]);
            return false;
        }

        $receipts = StockReceipt::where('user_id', $ownerId)
            ->where('receipt_number', $receiptNumber)
            ->with(['productVariant.product', 'supplier', 'receivedBy'])
            ->get();

        if ($receipts->isEmpty()) {
            return false;
        }

        $firstReceipt = $receipts->first();
        $supplierName = $firstReceipt->supplier->company_name ?? 'Unknown Supplier';
        $receivedByName = $firstReceipt->receivedBy->name ?? 'System';
        $date = Carbon::parse($firstReceipt->received_date)->format('M d, Y');
        $itemCount = $receipts->count();

        $sentCount = 0;
        $failedCount = 0;

        // Roles to notify: manager, accountant, stock-keeper
        $rolesToNotify = ['manager', 'accountant', 'stock-keeper'];

        $staffToNotify = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function($query) use ($rolesToNotify) {
                $query->whereIn('slug', $rolesToNotify)
                      ->orWhere('slug', 'like', 'super-admin%')
                      ->orWhere('slug', 'like', 'superadmin%')
                      ->orWhere('name', 'like', 'Super Admin%');
            })
            ->with('role')
            ->get();

        // Build product list
        $productLines = $receipts->map(function($r) {
            $productName = $r->productVariant->product->name ?? 'Unknown';
            $variantName = $r->productVariant->name ?? '';
            $label = $variantName ? "{$productName} ({$variantName})" : $productName;
            $qty = number_format($r->quantity_received, 0);
            $units = number_format($r->total_units, 0);
            return "  - {$label}: {$qty} pkgs / {$units} units";
        })->implode("\n");

        // Pre-build default message for general notifications
        $message = "STOCK RECEIVED - {$supplierName}\n\n";
        $message .= "Batch: #{$receiptNumber}\n";
        $message .= "Products:\n{$productLines}\n";
        $message .= "Received By: {$receivedByName}\n";
        $message .= "Date: {$date}\n";
        $message .= "\nPlease verify the receipt in the dashboard.";

        foreach ($staffToNotify as $staff) {
            if ($staff->phone_number) {
                $roleSlug = $staff->role->slug ?? '';
                
                // Build role-specific message
                
                $result = $this->smsService->sendSms($staff->phone_number, $message);
                
                if ($result['success']) {
                    $sentCount++;
                    \Log::info('Stock receipt localized SMS sent', [
                        'staff_id' => $staff->id,
                        'role' => $roleSlug,
                        'receipt_number' => $receiptNumber,
                    ]);
                } else {
                    $failedCount++;
                }
            }
        }

        // Also send to additional phone numbers from settings
        $additionalPhones = SystemSetting::get('low_stock_notification_phones_' . $ownerId, '');
        if ($additionalPhones) {
            $phones = array_map('trim', explode(',', $additionalPhones));
            foreach ($phones as $phone) {
                if (!empty($phone)) {
                    $this->smsService->sendSms($phone, $message);
                }
            }
        }

        return $sentCount > 0;
    }

    /**
     * Legacy method for single receipt notification (kept for backward compatibility if needed)
     */
    public function sendStockReceiptNotification(StockReceipt $stockReceipt, $ownerId)
    {
        return $this->sendBatchStockReceiptNotification($stockReceipt->receipt_number, $ownerId);
    }
}
