<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$shifts = \App\Models\BarShift::whereIn('id', [2, 3])->get();
foreach ($shifts as $s) {
    echo "ID: " . $s->id . " | Shift ID: " . $s->formatted_id . " | Opened: " . $s->opened_at . " | Closed: " . $s->closed_at . "\n";
}
