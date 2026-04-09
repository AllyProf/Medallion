<?php

namespace App\Services;

use App\Models\FinancialHandover;
use App\Models\Staff;
use App\Models\SystemSetting;

class HandoverSmsService
{
    protected $smsService;

    public function __construct()
    {
        $this->smsService = new SmsService();
    }

    /**
     * Send handover submission notification to accountants
     */
    public function sendHandoverSubmissionSms(FinancialHandover $handover, $ownerId)
    {
        // Check if notifications are enabled
        $settingKey = 'enable_handover_notifications_' . $ownerId;
        $enableNotifications = SystemSetting::get($settingKey, true);
        if (!$enableNotifications) return false;

        $sender = $handover->staff; // The staff (Counter/Chef/Waiter) who submitted
        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $total = number_format((float)$handover->amount, 0);
        
        $breakdown = $handover->payment_breakdown ?? [];
        $cash = $breakdown['cash'] ?? 0;
        $digital = 0;
        foreach($breakdown as $key => $val) {
            if ($key !== 'cash') $digital += (float)$val;
        }

        $cashFormatted = number_format($cash, 0);
        $digitalFormatted = number_format($digital, 0);

        $message = "FINANCIAL HANDOVER SUBMITTED\n\n";
        $message .= "From: " . ($sender->full_name ?? 'Counter') . "\n";
        $message .= "Date: {$date}\n";
        $message .= "Total: TSh {$total}\n";
        $message .= "Cash: TSh {$cashFormatted}\n";
        $message .= "Digital: TSh {$digitalFormatted}\n";
        $message .= "\nPlease login to verify this handover.";

        $accountants = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function($q) {
                $q->where('slug', 'accountant');
            })
            ->get();

        $sentCount = 0;
        foreach ($accountants as $accountant) {
            if ($accountant->phone_number) {
                $result = $this->smsService->sendSms($accountant->phone_number, $message);
                if ($result['success']) {
                    $sentCount++;
                }
            }
        }

        return $sentCount > 0;
    }

    /**
     * Send waiter verification SMS when their shift money is confirmed by counter
     */
    public function sendWaiterVerificationSms(\App\Models\WaiterDailyReconciliation $reconciliation)
    {
        $waiter = $reconciliation->waiter;
        // Kill the script if the waiter has no phone number on file
        if (!$waiter || !$waiter->phone_number) return false;

        $date = \Carbon\Carbon::parse($reconciliation->reconciliation_date)->format('M d, Y');
        $total = number_format((float)$reconciliation->submitted_amount, 0);

        $message = "RECONCILIATION VERIFIED\n\n";
        $message .= "Hello " . ($waiter->full_name ?? 'Waiter') . ",\n";
        $message .= "Your handover of TSh {$total} for {$date} has been VERIFIED & ACCEPTED successfully.\n";
        $message .= "Thank you for the shift!";

        return $this->smsService->sendSms($waiter->phone_number, $message);
    }

    /**
     * Send confirmation SMS to the Chef after their handover is reconciled
     */
    public function sendChefHandoverConfirmationSms(FinancialHandover $handover)
    {
        $chef = $handover->staff;
        if (!$chef || !$chef->phone_number) return false;

        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $total = number_format((float)$handover->amount, 0);
        
        $breakdown = $handover->payment_breakdown ?? [];
        $cash = $breakdown['cash'] ?? 0;
        
        $message = "CHEK HANDOVER RECEIVED\n\n";
        $message .= "Hello " . ($chef->full_name ?? 'Chef') . ",\n";
        $message .= "Your handover for {$date} has been RECEIVED.\n";
        $message .= "TOTAL: TSh {$total}\n";
        $message .= "CASH: TSh " . number_format($cash, 0) . "\n";
        
        // Add digital breakdown
        foreach(['mpesa', 'tigopesa', 'airtelmoney', 'halopesa', 'crdb', 'nmb'] as $platform) {
            if (isset($breakdown[$platform]) && $breakdown[$platform] > 0) {
                $message .= strtoupper($platform) . ": TSh " . number_format($breakdown[$platform], 0) . "\n";
            }
        }

        // Add Shortage info if present in breakdown (Multi-Waiter Support)
        if (isset($breakdown['attributed_shortages']) && is_array($breakdown['attributed_shortages'])) {
            $message .= "\nSHORTAGES DETECTED:\n";
            foreach ($breakdown['attributed_shortages'] as $short) {
                $message .= "- " . $short['waiter_name'] . ": TSh " . number_format($short['amount'], 0) . "\n";
            }
        } 
        elseif (isset($breakdown['shortage_amount']) && $breakdown['shortage_amount'] > 0) {
            // Fallback for legacy records
            $message .= "\nSHORTAGE: TSh " . number_format($breakdown['shortage_amount'], 0) . "\n";
            $message .= "Attributed to: " . ($breakdown['shortage_waiter_name'] ?? 'Waiter') . "\n";
        }

        if ($handover->notes) {
            $message .= "\nNOTE: " . $handover->notes;
        }

        $message .= "\n\nMauzoLink - Financial Audit.";

        $result = $this->smsService->sendSms($chef->phone_number, $message);
        return $result['success'] ?? false;
    }

    /**
     * Send SMS to staff when they are issued petty cash/funds
     */
    public function sendPettyCashIssuanceSms(\App\Models\PettyCashIssue $issue)
    {
        $recipient = $issue->recipient;
        if (!$recipient || !$recipient->phone_number) return false;

        $amount = number_format((float)$issue->amount, 0);
        $date = \Carbon\Carbon::parse($issue->issue_date)->format('M d, Y');
        $purpose = str_replace('[FOOD] ', '', $issue->purpose);
        $issuer = $issue->issuer->name ?? 'Accountant';

        $message = "PETTY CASH ISSUED\n\n";
        $message .= "Hello " . ($recipient->full_name ?? 'Staff') . ",\n";
        $message .= "You have been issued TSh {$amount} on {$date}.\n";
        $message .= "PURPOSE: {$purpose}\n";
        $message .= "SOURCE: " . strtoupper($issue->fund_source) . "\n";
        $message .= "\nPlease confirm receipt by signing the voucher provided by {$issuer}.";

        $message .= "\n\nMauzoLink Financials.";

        return $this->smsService->sendSms($recipient->phone_number, $message);
    }
}
