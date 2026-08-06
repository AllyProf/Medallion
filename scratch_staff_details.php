<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== HANDOVER SUBMITTER DETAILS ===\n";

$h63 = \App\Models\FinancialHandover::find(63);
if ($h63) {
    $sub = \App\Models\User::find($h63->accountant_id) ?? \App\Models\Staff::find($h63->accountant_id);
    $rec = \App\Models\User::find($h63->recipient_id) ?? \App\Models\Staff::find($h63->recipient_id);
    echo "Handover 63 | Submitter: " . ($sub ? ($sub->name ?? $sub->full_name) : 'Unknown') . " (ID: {$h63->accountant_id}) | Recipient: " . ($rec ? ($rec->name ?? $rec->full_name) : 'Unknown') . " (ID: {$h63->recipient_id})\n";
}

$h66 = \App\Models\FinancialHandover::find(66);
if ($h66) {
    $sub = \App\Models\User::find($h66->accountant_id) ?? \App\Models\Staff::find($h66->accountant_id);
    $rec = \App\Models\User::find($h66->recipient_id) ?? \App\Models\Staff::find($h66->recipient_id);
    echo "Handover 66 | Submitter: " . ($sub ? ($sub->name ?? $sub->full_name) : 'Unknown') . " (ID: {$h66->accountant_id}) | Recipient: " . ($rec ? ($rec->name ?? $rec->full_name) : 'Unknown') . " (ID: {$h66->recipient_id})\n";
}

echo "\n=== STAFF TABLE ===\n";
$staff = \App\Models\Staff::all();
foreach ($staff as $s) {
    echo "ID: {$s->id} | Name: {$s->full_name} | User ID: {$s->user_id} | Role ID: {$s->role_id}\n";
}

