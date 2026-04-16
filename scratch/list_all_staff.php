<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staff;

echo "--- LISTING ALL STAFF ---\n";
$staff = Staff::with('role')->get();
foreach($staff as $s) {
    echo "ID: {$s->id} | Name: {$s->first_name} {$s->last_name} | Role: " . ($s->role->slug ?? 'N/A') . " | Active: " . ($s->is_active ? 'Yes' : 'No') . "\n";
}
