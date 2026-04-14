<?php
/**
 * MEDALLION DATABASE FIX SCRIPT
 * 
 * Purpose: 
 * 1. Transfers all data ownership from User 1 (Test User) to User 4 (Admin).
 * 2. Reconfigures User 4 as a 'restaurant' business type so Kiosk auto-detection works.
 * 3. Initializes 10 Bar Tables for User 4.
 * 4. Cleans up existing 'Drink' products for User 4 to provide a clean slate (as requested).
 * 
 * Instructions:
 * 1. Upload this file to your website root directory.
 * 2. Run it via your browser: yourdomain.com/fix_live_database.php
 * 3. DELETE THIS FILE IMMEDIATELY AFTER RUNNING.
 */

// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "<pre>Starting Database Migration...\n\n";

$fromUser = 1;
$toUser = 4;

try {
    DB::beginTransaction();

    // 1. Update User Business Types for proper Kiosk auto-detection
    DB::table('users')->where('id', $fromUser)->update(['business_type' => 'software_company']);
    DB::table('users')->where('id', $toUser)->update(['business_type' => 'restaurant']);
    echo "✔ Reconfigured User Roles (User 4 is now the primary Restaurant owner).\n";

    // 2. Transfer Ownership of core modules
    $tablesToMigrate = [
        'food_items',
        'products',
        'staff',
        'user_business_types',
        'bar_tables',
        'daily_cash_ledgers',
    ];

    foreach ($tablesToMigrate as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->where('user_id', $fromUser)->count();
            if ($count > 0) {
                // For tables with unique constraints or where User 4 already has data, 
                // we delete User 4's data first to allow the migration of User 1's "Live" data.
                if (in_array($table, ['user_business_types', 'daily_cash_ledgers'])) {
                    DB::table($table)->where('user_id', $toUser)->delete();
                }
                
                DB::table($table)->where('user_id', $fromUser)->update(['user_id' => $toUser]);
                echo "✔ Migrated $count records from '$table' to User ID $toUser.\n";
            }
        }
    }

    // 3. Special handling for Roles (unique slug/name constraints)
    if (Schema::hasTable('roles')) {
        $u1Roles = DB::table('roles')->where('user_id', $fromUser)->get();
        foreach ($u1Roles as $role) {
            $exists = DB::table('roles')->where('user_id', $toUser)
                ->where(function($q) use ($role) {
                    $q->where('slug', $role->slug)->orWhere('name', $role->name);
                })->exists();
            
            if (!$exists) {
                DB::table('roles')->where('id', $role->id)->update(['user_id' => $toUser]);
            } else {
                // If role already exists for User 4, move staff members to the existing role first
                $u4Role = DB::table('roles')->where('user_id', $toUser)
                    ->where(function($q) use ($role) {
                        $q->where('slug', $role->slug)->orWhere('name', $role->name);
                    })->first();
                
                DB::table('staff')->where('role_id', $role->id)->update(['role_id' => $u4Role->id]);
                DB::table('roles')->where('id', $role->id)->delete();
            }
        }
        echo "✔ Migrated and merged User Roles.\n";
    }

    // 4. Cleanup 'Drink' products for User 4 (as requested for a clean slate)
    $drinkProductIds = DB::table('products')->where('user_id', $toUser)->pluck('id');
    DB::table('stock_locations')->where('user_id', $toUser)->delete();
    DB::table('product_variants')->whereIn('product_id', $drinkProductIds)->delete();
    DB::table('products')->where('user_id', $toUser)->delete();
    echo "✔ Cleaned up existing Drink Product list for User 4.\n";

    // 5. Initialize Bar Tables for User 4 (Kiosk requires these)
    for ($i = 1; $i <= 10; $i++) {
        $tableExists = DB::table('bar_tables')->where('user_id', $toUser)->where('table_number', $i)->exists();
        if (!$tableExists) {
            DB::table('bar_tables')->insert([
                'user_id' => $toUser,
                'table_number' => $i,
                'is_active' => 1,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    echo "✔ Verified 10 Bar Tables for User ID $toUser.\n";

    DB::commit();
    echo "\n🏆 ALL TASKS COMPLETED SUCCESSFULLY.";
    echo "\n\n<b>IMPORTANT: PLEASE DELETE THIS FILE FROM YOUR SERVER IMMEDIATELY.</b>";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR DURING MIGRATION:\n" . $e->getMessage();
}

echo "</pre>";
