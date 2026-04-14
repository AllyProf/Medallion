<?php
/**
 * MEDALLION DATA OWNERSHIP REPAIR
 * 
 * Target: Resolves PIN "Not Found" and empty product lists after a manual backup import.
 * Fixes: Moves all backup data (User 1) to Live account (User 4) and merges roles.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "--- MEDALLION OWNERSHIP REPAIR ---\n\n";

$fromUser = 1;
$toUser = 4;

try {
    DB::beginTransaction();

    // 1. Repair Roles (The Root of PIN Failures)
    // Many staff members for User 4 are pointing to Role ID 13 (Waiter), which is owned by User 1.
    // We need to transfer role ownership first.
    $rolesToMove = DB::table('roles')->where('user_id', $fromUser)->get();
    foreach ($rolesToMove as $role) {
        // Check if U4 already has a role with the same name
        $existing = DB::table('roles')->where('user_id', $toUser)->where('name', $role->name)->first();
        if ($existing) {
            // Role name exists. Update all staff pointing to old role to use existing role.
            DB::table('staff')->where('role_id', $role->id)->update(['role_id' => $existing->id]);
            // Now delete the redundant U1 role
            DB::table('roles')->where('id', $role->id)->delete();
            echo "Merged Role '{$role->name}' (U1 -> U4)\n";
        } else {
            // Role name unique to U4. Just transfer ownership.
            DB::table('roles')->where('id', $role->id)->update(['user_id' => $toUser]);
            echo "Transferred Role '{$role->name}' to User $toUser\n";
        }
    }

    // 2. Transfer Staff, Products, Food Items
    $tables = ['staff', 'products', 'food_items']; // Handle ledgers separately
    foreach ($tables as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->where('user_id', $fromUser)->count();
            if ($count > 0) {
                DB::table($table)->where('user_id', $fromUser)->update(['user_id' => $toUser]);
                echo "Moved $count records from '$table' to User $toUser\n";
            }
        }
    }

    // 2b. Handle Daily Cash Ledgers (Avoid Duplicate Date Errors)
    if (Schema::hasTable('daily_cash_ledgers')) {
        $u1Ledgers = DB::table('daily_cash_ledgers')->where('user_id', $fromUser)->get();
        foreach ($u1Ledgers as $ledger) {
            // Delete any existing (likely empty) entry for U4 on this date to favor the imported data
            DB::table('daily_cash_ledgers')->where('user_id', $toUser)->where('ledger_date', $ledger->ledger_date)->delete();
            DB::table('daily_cash_ledgers')->where('id', $ledger->id)->update(['user_id' => $toUser]);
        }
        echo "Moved " . $u1Ledgers->count() . " Daily Cash Ledgers to User $toUser\n";
    }

    // 3. Initialize Bar Tables for User 4 (Required for Kiosk)
    for ($i = 1; $i <= 10; $i++) {
        $exists = DB::table('bar_tables')->where('user_id', $toUser)->where('table_number', $i)->exists();
        if (!$exists) {
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
    echo "Verified 10 Bar Tables for User $toUser\n";

    // 4. Critical Stock Sync (Kiosk requires stock to show products)
    // If variants were moved, ensure User 4 has stock location entries for them.
    $variantsWithoutStock = DB::table('product_variants')
        ->join('products', 'product_variants.product_id', '=', 'products.id')
        ->where('products.user_id', $toUser)
        ->leftJoin('stock_locations', function($join) use ($toUser) {
            $join->on('product_variants.id', '=', 'stock_locations.product_variant_id')
                 ->where('stock_locations.user_id', '=', $toUser)
                 ->where('stock_locations.location', '=', 'counter');
        })
        ->whereNull('stock_locations.id')
        ->select('product_variants.id')
        ->get();

    if ($variantsWithoutStock->count() > 0) {
        foreach ($variantsWithoutStock as $v) {
            DB::table('stock_locations')->insert([
                'user_id' => $toUser,
                'product_variant_id' => $v->id,
                'location' => 'counter',
                'quantity' => 0, // Default to 0 so they at least appear in UI as "Out of stock"
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        echo "Created " . $variantsWithoutStock->count() . " missing stock location records for User $toUser\n";
    }

    DB::commit();
    echo "\n🏆 REPAIR COMPLETED SUCCESSFULLY.";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage();
}
