<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$receiptOwnerId = \App\Models\StockReceipt::latest()->first()->user_id ?? 1;

$allStaff = \App\Models\Staff::where('user_id', $receiptOwnerId)->with('role')->get();
echo "All staff for ownerId {$receiptOwnerId}:\n";
foreach($allStaff as $s) {
    echo " - ID: {$s->id}, Name: {$s->name}, Phone: {$s->phone_number}, Active: {$s->is_active}, Role: " . ($s->role->name ?? 'None') . " (Slug: " . ($s->role->slug ?? 'None') . ")\n";
}

$adminMatches = \App\Models\Staff::where('user_id', $receiptOwnerId)
    ->whereHas('role', function($query) { 
        $rolesToNotify = ['manager', 'accountant', 'stock-keeper']; 
        $query->whereIn('slug', $rolesToNotify)
              ->orWhere('slug', 'like', 'super-admin%')
              ->orWhere('slug', 'like', 'superadmin%')
              ->orWhere('name', 'like', 'Super Admin%');
    })->with('role')->get();

echo "\nMatched by query:\n";
foreach($adminMatches as $s) {
    echo " - {$s->name} ({$s->role->slug})\n";
}
