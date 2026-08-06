<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

try {
    echo "Attempting to create 'Medallion' folder in Google Drive...\n";
    
    $config = config('filesystems.disks.google');
    
    $client = new Client();
    $client->setClientId($config['clientId']);
    $client->setClientSecret($config['clientSecret']);
    $client->refreshToken($config['refreshToken']);

    $service = new Drive($client);
    
    $parentFolderId = $config['folderId'];
    echo "Parent Folder ID: $parentFolderId\n";
    
    // Check if it already exists
    $query = "name = 'Medallion' and '$parentFolderId' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    $results = $service->files->listFiles(['q' => $query]);
    
    if (count($results->getFiles()) > 0) {
        echo "Folder 'Medallion' already exists.\n";
    } else {
        echo "Creating 'Medallion' folder...\n";
        $fileMetadata = new DriveFile([
            'name' => 'Medallion',
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentFolderId]
        ]);
        $folder = $service->files->create($fileMetadata, ['fields' => 'id']);
        echo "Folder created successfully! ID: " . $folder->id . "\n";
    }
    
    echo "Now run the backup again.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
