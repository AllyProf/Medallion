<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$variants = \App\Models\ProductVariant::where('display_name', 'like', '%Dry%')
    ->orWhere('name', 'like', '%Dry%')
    ->get(['id','name','display_name']);

foreach ($variants as $v) {
    echo "ID: {$v->id} | name: {$v->name} | display_name: {$v->display_name}\n";
}
