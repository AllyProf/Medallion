<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::find(4);
if ($user) {
    echo "Current Name: '{$user->business_name}'\n";
    $user->business_name = 'MEDALLION RESTAURANT';
    $user->save();
    echo "Updated Name: '{$user->business_name}'\n";
} else {
    echo "User 4 not found.\n";
}
