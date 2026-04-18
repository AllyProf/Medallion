<?php

/**
 * MEDALLION DATABASE MASTER REPAIR SCRIPT
 * ---------------------------------------
 * Purpose: Synchronizes all Financial Ledgers, re-calculates Profit Isolation,
 * and heals the opening cash chain after the April 2026 system updates.
 * 
 * Usage: php database_master_repair.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashLedger;
use Illuminate\Support\Facades\DB;

echo "--- MEDALLION DATABASE REPAIR STARTED ---\n";

DB::beginTransaction();

try {
    // 1. Sanity Check: Ensure required columns exist
    $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('financial_handovers', 'bar_shift_id');
    if (!$hasColumn) {
        echo "[!] CRITICAL: 'bar_shift_id' column missing in 'financial_handovers'. Attempting to add...\n";
        DB::statement("ALTER TABLE financial_handovers ADD COLUMN bar_shift_id BIGINT UNSIGNED NULL AFTER recipient_id");
        echo "[+] Column added successfully.\n";
    } else {
        echo "[✓] Schema verification passed.\n";
    }

    // 2. Fetch all unique Owners (Users) to process their ledgers separately
    $ownerIds = DailyCashLedger::distinct()->pluck('user_id');

    foreach ($ownerIds as $ownerId) {
        $ownerName = \App\Models\User::find($ownerId)->name ?? "ID: $ownerId";
        echo "\nProcessing Business: $ownerName\n";

        // Get all ledgers for this owner sorted by date
        $ledgers = DailyCashLedger::where('user_id', $ownerId)
            ->orderBy('ledger_date', 'asc')
            ->get();

        $previousCarriedForward = null;

        foreach ($ledgers as $ledger) {
            $dateStr = $ledger->ledger_date->format('Y-m-d');
            echo "  > Repairing $dateStr... ";

            // A. HEAL OPENING CASH CHAIN (Except for the very first ledger)
            if ($previousCarriedForward !== null) {
                if (abs($ledger->opening_cash - $previousCarriedForward) > 0.01) {
                    echo "[Healed Opening: " . number_format($ledger->opening_cash) . " -> " . number_format($previousCarriedForward) . "] ";
                    $ledger->opening_cash = $previousCarriedForward;
                }
            }

            // B. SYNC TOTALS (Triggers the new Profit Isolation logic in the model)
            // This will recalculate: profit_generated, carried_forward, etc.
            $ledger->syncTotals();
            
            // C. Persist changes
            $ledger->save();

            // Track for next day's opening
            $previousCarriedForward = $ledger->carried_forward;
            echo "[✓ Done]\n";
        }
    }

    DB::commit();
    echo "\n--- ALL REPAIRS COMPLETED SUCCESSFULLY ---\n";
    echo "Summary: All ledgers synchronized with the new Profit Isolation rules.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n[!!!] REPAIR FAILED: " . $e->getMessage() . "\n";
    echo "Stack Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
