<?php

namespace App\Services;

use App\Models\WaiterNotification;
use App\Models\Staff;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class StaffNotificationService
{
    /**
     * Send a notification to a specific staff member.
     * 
     * @param int $staffId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @return WaiterNotification|null
     */
    public function notifyStaff($staffId, $type, $title, $message, $data = null)
    {
        try {
            return WaiterNotification::create([
                'waiter_id' => $staffId,
                'type'      => $type,
                'title'     => $title,
                'message'   => $message,
                'data'      => $data,
                'is_read'   => false,
            ]);
        } catch (\Exception $e) {
            Log::error('StaffNotificationService: Failed to notify staff member', [
                'staff_id' => $staffId,
                'error'    => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send a notification to all staff members with specific roles.
     * 
     * @param array $roleNames
     * @param int $ownerId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @return int Count of notifications sent
     */
    public function notifyRoles(array $roleNames, $ownerId, $type, $title, $message, $data = null)
    {
        $staffMembers = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function ($query) use ($roleNames) {
                $query->whereIn('name', $roleNames);
            })
            ->get();

        $count = 0;
        foreach ($staffMembers as $staff) {
            if ($this->notifyStaff($staff->id, $type, $title, $message, $data)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Specialized notification for stock transfers.
     */
    public function notifyStockTransferStatus($transfer, $status, $ownerId, $reason = null)
    {
        $variant = $transfer->productVariant;
        $fullName = $variant->display_name ?? ($variant->product->name ?? 'Product');
        $qtyStr = "{$transfer->quantity_requested} " . ($variant->packaging ?? 'packages');
        
        $title = "Stock Transfer Status: " . ucfirst($status);
        $type = 'stock_transfer';
        
        if ($status === 'approved') {
            $message = "Stock transfer #{$transfer->transfer_number} for {$fullName} ({$qtyStr}) has been APPROVED.";
            $color = 'success';
        } elseif ($status === 'rejected') {
            $message = "Stock transfer #{$transfer->transfer_number} for {$fullName} has been REJECTED.";
            if ($reason) $message .= " Reason: {$reason}";
            $color = 'danger';
        } elseif ($status === 'prepared') {
            $message = "Stock transfer #{$transfer->transfer_number} for {$fullName} is now PREPARED and ready for movement.";
            $color = 'info';
        } elseif ($status === 'completed' || $status === 'moved') {
            $message = "Stock transfer #{$transfer->transfer_number} for {$fullName} has been completed.";
            $color = 'success';
        } else {
            return;
        }

        // Always notify the staff who requested it
        $requestedByStaff = null;
        if ($transfer->requested_by) {
            // Find staff by user_id
            $requestedByStaff = Staff::where('user_id', $transfer->requested_by)->first();
        }

        if ($requestedByStaff) {
            $this->notifyStaff($requestedByStaff->id, $type . '_' . $color, $title, $message, ['transfer_id' => $transfer->id]);
        }

        // Also notify Counter roles since multiple staff might be on duty
        $this->notifyRoles(['Counter', 'Bar Counter'], $ownerId, $type . '_' . $color, $title, $message, ['transfer_id' => $transfer->id]);
    }

    /**
     * Specialized notification for stock transfer requests.
     */
    public function notifyStockTransferRequest($transfer, $ownerId)
    {
        $variant = $transfer->productVariant;
        $fullName = $variant->display_name ?? ($variant->product->name ?? 'Product');
        $qtyStr = "{$transfer->quantity_requested} " . ($variant->packaging ?? 'packages');
        
        $title = "New Stock Transfer Request";
        $message = "A new request for {$fullName} ({$qtyStr}) has been submitted #{$transfer->transfer_number}.";
        $type = 'stock_transfer_request';

        // Notify Stock Keepers
        $this->notifyRoles(['Stock Keeper', 'Stockkeeper', 'Manager'], $ownerId, $type, $title, $message, ['transfer_id' => $transfer->id]);
        
        // Notify Requester (Confirmation)
        $requestedByStaff = null;
        if ($transfer->requested_by) {
            $requestedByStaff = Staff::where('user_id', $transfer->requested_by)->first();
        }
        if ($requestedByStaff) {
            $this->notifyStaff($requestedByStaff->id, $type, "Request Submitted", "Your request for {$fullName} has been sent successfully.", ['transfer_id' => $transfer->id]);
        }
    }
}
