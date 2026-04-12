<?php
$role = \App\Models\Role::where('slug', 'manager')->first();
$roleName = strtolower($role->name ?? '');
$roleSlug = strtolower($role->slug ?? '');
$isManager = in_array($roleName, ['manager', 'general manager', 'administrator']) || in_array($roleSlug, ['manager', 'admin']);
echo "Is Manager? " . ($isManager ? "YES" : "NO") . PHP_EOL;

$menu = \App\Models\MenuItem::where('slug', 'restaurant-management')->first();
if ($menu) {
    if ($isManager && in_array($menu->slug, ['restaurant-management', 'manager-master-sheet-root'])) {
        echo "Will hide: YES" . PHP_EOL;
    } else {
        echo "Will hide: NO" . PHP_EOL;
    }
} else {
    echo "Menu restaurant-management not found!" . PHP_EOL;
}
