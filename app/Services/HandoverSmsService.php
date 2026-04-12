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
        if (!$enableNotifications)
            return false;

        $sender = $handover->staff; // The staff (Counter/Chef/Waiter) who submitted
        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $total = number_format((float) $handover->amount, 0);

        $breakdown = $handover->payment_breakdown ?? [];
        $cash = $breakdown['cash'] ?? 0;
        $digital = 0;
        foreach ($breakdown as $key => $val) {
            if ($key !== 'cash')
                $digital += (float) $val;
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
            ->whereHas('role', function ($q) {
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
        if (!$waiter || !$waiter->phone_number)
            return false;

        $date = \Carbon\Carbon::parse($reconciliation->reconciliation_date)->format('M d, Y');
        $total = number_format((float) $reconciliation->submitted_amount, 0);

        $message = "RECONCILIATION VERIFIED\n\n";
        $message .= "Hello " . ($waiter->full_name ?? 'Waiter') . ",\n";
        $message .= "Your handover of TSh {$total} for {$date} has been VERIFIED & ACCEPTED by Accountant successfully.\n";
        $message .= "Thank you for the shift!";

        return $this->smsService->sendSms($waiter->phone_number, $message);
    }

    /**
     * Send waiter reconciliation submission SMS when counter staff records their money
     */
    public function sendWaiterReconciliationSubmissionSms(\App\Models\WaiterDailyReconciliation $reconciliation)
    {
        $waiter = $reconciliation->waiter;
        if (!$waiter || !$waiter->phone_number)
            return false;

        $date = \Carbon\Carbon::parse($reconciliation->reconciliation_date)->format('M d, Y');
        $submitted = number_format((float) $reconciliation->submitted_amount, 0);
        $expected = number_format((float) $reconciliation->expected_amount, 0);
        $diff = (float) $reconciliation->difference;
        $diffText = $diff == 0 ? "Match" : ($diff > 0 ? "+TSh " . number_format($diff, 0) : "-TSh " . number_format(abs($diff), 0));

        $message = "SHIFT RECONCILIATION RECEIVED\n\n";
        $message .= "Hello " . ($waiter->full_name ?? 'Waiter') . ",\n";
        $message .= "Counter has received your shift handover for {$date}:\n";
        $message .= "Total Submitted: TSh {$submitted}\n";
        $message .= "Expected Amount: TSh {$expected}\n";
        $message .= "Difference: {$diffText}\n";
        $message .= "\nWaiting for final Accountant verification.";
        $message .= "\n\nThank you for the shift!";

        return $this->smsService->sendSms($waiter->phone_number, $message);
    }

    /**
     * Send confirmation SMS to the Chef after their handover is reconciled
     */
    public function sendChefHandoverConfirmationSms(FinancialHandover $handover)
    {
        $chef = $handover->staff;
        if (!$chef || !$chef->phone_number)
            return false;

        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $total = number_format((float) $handover->amount, 0);

        $breakdown = $handover->payment_breakdown ?? [];
        $cash = $breakdown['cash'] ?? 0;

        $message = "CHEK HANDOVER RECEIVED\n\n";
        $message .= "Hello " . ($chef->full_name ?? 'Chef') . ",\n";
        $message .= "Your handover for {$date} has been RECEIVED.\n";
        $message .= "TOTAL: TSh {$total}\n";
        $message .= "CASH: TSh " . number_format($cash, 0) . "\n";

        // Add digital breakdown
        foreach (['mpesa', 'tigopesa', 'airtelmoney', 'halopesa', 'crdb', 'nmb'] as $platform) {
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
        } elseif (isset($breakdown['shortage_amount']) && $breakdown['shortage_amount'] > 0) {
            // Fallback for legacy records
            $message .= "\nSHORTAGE: TSh " . number_format($breakdown['shortage_amount'], 0) . "\n";
            $message .= "Attributed to: " . ($breakdown['shortage_waiter_name'] ?? 'Waiter') . "\n";
        }

        if ($handover->notes) {
            $message .= "\nNOTE: " . $handover->notes;
        }

        $message .= "\n\nMEDALLION - Financial Audit.";

        $result = $this->smsService->sendSms($chef->phone_number, $message);
        return $result['success'] ?? false;
    }

    /**
     * Send SMS to staff when they are issued petty cash/funds
     */
    public function sendPettyCashIssuanceSms(\App\Models\PettyCashIssue $issue)
    {
        $recipient = $issue->recipient;
        if (!$recipient || !$recipient->phone_number)
            return false;

        $amount = number_format((float) $issue->amount, 0);
        $date = \Carbon\Carbon::parse($issue->issue_date)->format('M d, Y');
        $purpose = str_replace('[FOOD] ', '', $issue->purpose);
        $issuer = $issue->issuer->name ?? 'Accountant';

        $message = "PETTY CASH ISSUED\n\n";
        $message .= "Hello " . ($recipient->full_name ?? 'Staff') . ",\n";
        $message .= "You have been issued TSh {$amount} on {$date}.\n";
        $message .= "PURPOSE: {$purpose}\n";
        $message .= "SOURCE: " . strtoupper($issue->fund_source) . "\n";
        $message .= "\nPlease confirm receipt by signing the voucher provided by {$issuer}.";

        $message .= "\n\nMEDALLION Financials.";

        return $this->smsService->sendSms($recipient->phone_number, $message);
    }

    /**
     * Send Daily Master Sheet Closing SMS to Manager, Accountant and Counter Staff
     */
    public function sendDailyMasterSheetClosedSms(\App\Models\DailyCashLedger $ledger)
    {
        $date = \Carbon\Carbon::parse($ledger->ledger_date)->format('M d, Y');
        $accountant = $ledger->accountant;
        $manager = $ledger->user;

        // 1. Prepare MANAGEMENT message (Full Report)
        $pft = $ledger->profit_generated - $ledger->total_expenses_from_profit;
        $vault = number_format($ledger->actual_closing_cash, 0);
        $profit = number_format($pft, 0);
        $circ = number_format($ledger->carried_forward, 0);
        $exp = number_format($ledger->total_expenses, 0);

        $mgmtMsg = "DAILY RECONCILIATION - {$date}\n";
        $mgmtMsg .= "Status: CLOSED & VERIFIED\n";
        $mgmtMsg .= "-------------------------\n";
        $mgmtMsg .= "VAULT CASH: TSh {$vault}\n";
        $mgmtMsg .= "NET PROFIT: TSh {$profit}\n";
        $mgmtMsg .= "ROLLOVER: TSh {$circ}\n";
        $mgmtMsg .= "EXPENSES: TSh {$exp}\n";
        $mgmtMsg .= "-------------------------\n";
        $mgmtMsg .= "Verified by: " . ($accountant->full_name ?? 'Accountant') . "\n";
        $mgmtMsg .= "Kindly login to your account to verify.\n";
        $mgmtMsg .= "MEDALLION - Financial Audit.";

        // 2. Prepare STAFF message (Privacy Protected)
        $staffMsg = "DAILY SHIFT CLOSED - {$date}\n";
        $staffMsg .= "Status: VERIFIED & LOCKED\n";
        $staffMsg .= "-------------------------\n";
        $staffMsg .= "Hello,\n";
        $staffMsg .= "Your shift account has been finalized and verified by the Accountant.\n";
        $staffMsg .= "\nThank you for the shift!\n";
        $staffMsg .= "MEDALLION - Financial Audit.";

        // Step 1: Send to Manager
        if ($manager && $manager->phone_number) {
            $this->smsService->sendSms($manager->phone_number, $mgmtMsg);
        }

        // Step 2: Send to Accountant
        if ($accountant && $accountant->phone_number) {
            $this->smsService->sendSms($accountant->phone_number, $mgmtMsg);
        }

        // Step 3: Send to Counter Staff (Find all who submitted bar handovers today)
        $counterStaffIds = \App\Models\FinancialHandover::where('user_id', $ledger->user_id)
            ->where('department', 'bar')
            ->whereDate('handover_date', $ledger->ledger_date)
            ->pluck('staff_id')
            ->unique();

        $counterStaffMembers = \App\Models\Staff::whereIn('id', $counterStaffIds)->get();
        foreach ($counterStaffMembers as $staff) {
            if ($staff->phone_number && (!isset($accountant) || $staff->id !== $accountant->id)) {
                $personalMsg = str_replace("Hello,", "Hello " . ($staff->full_name ?? 'Staff') . ",", $staffMsg);
                $this->smsService->sendSms($staff->phone_number, $personalMsg);
            }
        }

        return true;
    }

    /**
     * Send SMS to Accountants when Manager confirms receiving profit
     */
    public function sendManagerProfitReceiptSms(\App\Models\FinancialHandover $handover)
    {
        $ownerId = $handover->user_id;
        $managerName = $handover->user->name ?? 'Manager';
        $amount = number_format($handover->amount, 0);
        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $dept = ($handover->department === 'food') ? 'Kitchen/Food' : 'Bar/Counter';

        $message = "PROFIT RECEIVED BY MANAGER\n";
        $message .= "-------------------------\n";
        $message .= "STATUS: RECEIVED & CONFIRMED\n";
        $message .= "AMOUNT: TSh {$amount}\n";
        $message .= "DEPT: {$dept}\n";
        $message .= "DATE: {$date}\n";
        $message .= "-------------------------\n";
        $message .= "Confirmed by: {$managerName}\n";
        $message .= "MEDALLION Financial Audit.";

        $staffMembers = Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('slug', ['accountant', 'manager']);
            })
            ->get();

        $sentCount = 0;
        foreach ($staffMembers as $staff) {
            if ($staff->phone_number) {
                $result = $this->smsService->sendSms($staff->phone_number, $message);
                if ($result['success'] ?? false) {
                    $sentCount++;
                }
            }
        }

        return $sentCount > 0;
    }
    /**
     * Send SMS to the Chef after the Accountant verifies their handover
     */
    public function sendChefHandoverVerifiedSms(FinancialHandover $handover)
    {
        $chef = $handover->staff;
        if (!$chef || !$chef->phone_number) return false;

        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $total = number_format((float) $handover->amount, 0);

        $message = "KITCHEN HANDOVER VERIFIED\n\n";
        $message .= "Hello " . ($chef->full_name ?? 'Chef') . ",\n";
        $message .= "Your kitchen handover for {$date} of TSh {$total} has been VERIFIED & ACCEPTED by the Accountant.\n";
        $message .= "Shift records are now locked.\n";
        $message .= "\nMEDALLION - Financial Audit.";

        $result = $this->smsService->sendSms($chef->phone_number, $message);
        return $result['success'] ?? false;
    }

    /**
     * Send SMS to Waiter after the Chef handover for that day is verified (Final Finalization)
     */
    public function sendWaiterFoodReconciliationVerifiedSms(\App\Models\Staff $waiter, $date, $amount)
    {
        if (!$waiter || !$waiter->phone_number) return false;

        $dateFormatted = \Carbon\Carbon::parse($date)->format('M d, Y');
        $total = number_format((float) $amount, 0);

        $message = "FOOD SALES FINALIZED\n\n";
        $message .= "Hello " . ($waiter->full_name ?? 'Waiter') . ",\n";
        $message .= "Your food sales shift for {$dateFormatted} has been FINALIZED by the Accountant.\n";
        $message .= "Total Reconciled: TSh {$total}\n";
        $message .= "\nThank you for the shift!";

        return $this->smsService->sendSms($waiter->phone_number, $message);
    }
    /**
     * Send SMS to the Manager when the Accountant submits profit to the Boss
     */
    public function sendProfitSubmissionToBossSms(\App\Models\FinancialHandover $handover)
    {
        $ownerId = $handover->user_id;
        $accountantName = auth()->check() ? (auth()->user()->staff->full_name ?? auth()->user()->name) : 'Accountant';

        $date = \Carbon\Carbon::parse($handover->handover_date)->format('M d, Y');
        $amount = number_format((float) $handover->amount, 0);
        
        $dept = ($handover->department === 'food') ? 'KITCHEN' : (($handover->department === 'Master Sheet') ? 'BAR/MASTER' : strtoupper($handover->department));

        $message = "PROFIT SENT TO BOSS\n\n";
        $message .= "Dept: {$dept}\n";
        $message .= "Date: {$date}\n";
        $message .= "Amount: TSh {$amount}\n";
        $message .= "Sent by: {$accountantName}\n";
        $message .= "\nMEDALLION - Financial Audit.";

        $managers = \App\Models\Staff::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->where('slug', 'manager');
            })
            ->get();

        $sentCount = 0;
        foreach ($managers as $manager) {
            if ($manager->phone_number) {
                $result = $this->smsService->sendSms($manager->phone_number, $message);
                if ($result['success'] ?? false) {
                    $sentCount++;
                }
            }
        }
        return $sentCount > 0;
    }

    /**
     * Send password reset SMS to User or Staff
     */
    public function sendPasswordResetSms($recipient, $newPassword)
    {
        $phone = ($recipient instanceof \App\Models\User) ? $recipient->phone : $recipient->phone_number;
        
        if (!$phone) {
            return false;
        }

        $name = ($recipient instanceof \App\Models\User) ? $recipient->name : ($recipient->full_name ?? 'Staff');

        $message = "ACCESS SECURITY - MEDALLION\n\n";
        $message .= "Hello {$name},\n";
        $message .= "Your password has been reset by the Super Admin.\n";
        $message .= "NEW PASSWORD: {$newPassword}\n\n";
        $message .= "Please login and change it for your security.\n";
        $message .= "Keep this information secure.";

        return $this->smsService->sendSms($phone, $message);
    }
}

