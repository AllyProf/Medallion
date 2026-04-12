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
        foreach ($staffToNotify as $recipient) {
            if ($recipient->phone_number) {
                $result = $this->smsService->sendSms($recipient->phone_number, $message);
                if ($result['success']) {
                    $sentCount++;
                }
            }
        }

        return $sentCount > 0;
    }
}
