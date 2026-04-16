<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\Staff;

echo "--- ROLES ---\n";
foreach (Role::all() as $role) {
    echo "- {$role->name} (slug: {$role->slug})\n";
}

echo "\n--- RECENT STAFF ---\n";
$staffs = Staff::limit(10)->with('role')->get();
foreach ($staffs as $s) {
    echo "Name: {$s->full_name} | Role: " . ($s->role->name ?? 'N/A') . "\n";
}
