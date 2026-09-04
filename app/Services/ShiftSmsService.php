<?php

namespace App\Services;

use App\Models\BarShift;
use App\Models\Staff;
use App\Models\SystemSetting;
use Carbon\Carbon;

class ShiftSmsService
{
    protected $smsService;

    public function __construct()
    {
        $this->smsService = new SmsService();
    }

    /**
     * Send SMS to Accountant and Manager when a shift is started
     */
    public function sendShiftStartedSms(BarShift $shift)
    {
        $ownerId = $shift->user_id;
        
        // Check if notifications are enabled
        $enableNotifications = SystemSetting::get('enable_shift_notifications_' . $ownerId, true);
        if (!$enableNotifications) {
            return false;
        }

        $staff = $shift->staff;
        $branch = $shift->location_branch ?? 'Counter';
        $time = Carbon::parse($shift->opened_at)->format('H:i');
        $businessName = SystemSetting::get('company_name', 'MEDALLION');

        $message = "SHIFT STARTED\n\n";
        $message .= ($staff->full_name ?? 'Staff') . " has started a new shift at {$branch} as of {$time}.\n";
        $message .= "- {$businessName}";

        // Target roles: accountant, manager
        $rolesToNotify = ['manager', 'accountant'];

        $staffToNotify = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function($query) use ($rolesToNotify) {
                $query->whereIn('slug', $rolesToNotify);
            })
            ->get();

        $sentCount = 0;

        // Notify the Business Owner (Boss) directly
        $owner = \App\Models\User::find($ownerId);
        if ($owner && $owner->phone) {
            $result = $this->smsService->sendSms($owner->phone, $message);
            if ($result['success'] ?? false) {
                $sentCount++;
            }
        }

        foreach ($staffToNotify as $recipient) {
            if ($recipient->phone_number) {
                $result = $this->smsService->sendSms($recipient->phone_number, $message);
                if ($result['success'] ?? false) {
                    $sentCount++;
                }
            }
        }

        return $sentCount > 0;
    }

    /**
     * Notify owner, managers, accountants, previous counter, and new counter
     * when an open shift is transferred.
     */
    public function sendShiftTransferredSms(BarShift $shift, Staff $fromStaff, Staff $toStaff, ?Staff $transferredBy = null)
    {
        $ownerId = $shift->user_id;

        $enableNotifications = SystemSetting::get('enable_shift_notifications_' . $ownerId, true);
        if (!$enableNotifications) {
            return false;
        }

        $shift->loadMissing('staff');
        $branch = $shift->location_branch ?? 'Counter';
        $shiftLabel = $shift->formatted_id;
        $time = now()->format('H:i');
        $businessName = SystemSetting::get('company_name', 'MEDALLION');
        $fromName = $fromStaff->full_name ?? 'Counter';
        $toName = $toStaff->full_name ?? 'Counter';
        $byName = $transferredBy->full_name ?? (auth()->user()->name ?? 'Manager');

        $message = "SHIFT TRANSFERRED\n\n";
        $message .= "Shift {$shiftLabel} at {$branch} was transferred at {$time}.\n";
        $message .= "From: {$fromName}\n";
        $message .= "To: {$toName}\n";
        $message .= "By: {$byName}\n";
        $message .= "Orders stay on this shift. {$toName} now owns the live session.\n";
        $message .= "- {$businessName}";

        $sentCount = 0;
        $phonesSent = [];

        $sendTo = function (?string $phone) use ($message, &$sentCount, &$phonesSent) {
            $phone = trim((string) $phone);
            if ($phone === '' || in_array($phone, $phonesSent, true)) {
                return;
            }
            $result = $this->smsService->sendSms($phone, $message);
            if ($result['success'] ?? false) {
                $sentCount++;
                $phonesSent[] = $phone;
            }
        };

        $owner = \App\Models\User::find($ownerId);
        if ($owner) {
            $sendTo($owner->phone ?? null);
        }

        $staffToNotify = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['manager', 'accountant']);
            })
            ->get();

        foreach ($staffToNotify as $recipient) {
            $sendTo($recipient->phone_number);
        }

        $sendTo($fromStaff->phone_number);
        $sendTo($toStaff->phone_number);

        return $sentCount > 0;
    }
}
