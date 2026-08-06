<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

try {
    echo "Testing Write to Google Drive...\n";
    $filename = 'test_' . time() . '.txt';
    $content = 'Testing write at ' . date('Y-m-d H:i:s');
    
    echo "Attempting to write $filename to root...\n";
    $result = Storage::disk('google')->put($filename, $content);
    
    if ($result) {
        echo "Write SUCCESSFUL!\n";
    } else {
        echo "Write FAILED (returned false).\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // If it's a Google Service Exception, it might have more info
    if (method_exists($e, 'getErrors')) {
        print_r($e->getErrors());
    }
}
