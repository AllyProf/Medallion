<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$ref = new ReflectionClass('App\Http\Controllers\Bar\WaiterController');
foreach ($ref->getMethods() as $m) {
    if ($m->class == 'App\Http\Controllers\Bar\WaiterController') {
        echo $m->name . PHP_EOL;
    }
}
