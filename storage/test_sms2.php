<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerId = \App\Models\StockReceipt::latest()->first()->user_id ?? 1;

$staffToNotify = \App\Models\Staff::where('user_id', $ownerId)
    ->where('is_active', true)
    ->whereHas('role', function($query) { 
        $rolesToNotify = ['manager', 'accountant', 'stock-keeper']; 
        $query->whereIn('slug', $rolesToNotify)
              ->orWhere('slug', 'like', 'super-admin%')
              ->orWhere('slug', 'like', 'superadmin%')
              ->orWhere('name', 'like', 'Super Admin%');
    })->with('role')->get();

$smsService = new \App\Services\SmsService();
$message = "Test SMS from Stock Receipt fix";

foreach($staffToNotify as $staff) {
    if ($staff->phone_number) {
        echo "Sending to: {$staff->phone_number} ({$staff->role->slug})\n";
        $result = $smsService->sendSms($staff->phone_number, $message);
        echo "Result: " . json_encode($result) . "\n\n";
    }
}
