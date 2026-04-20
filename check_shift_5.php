<?php

use App\Models\BarShift;
use App\Models\FinancialHandover;

$shift = BarShift::find(5);
if ($shift) {
    echo "SHIFT #5:\n";
    echo "  Status: " . $shift->status . "\n";
    echo "  Opened: " . $shift->opened_at . "\n";
    echo "  Closed: " . ($shift->closed_at ?? 'STILL OPEN') . "\n";
} else {
    echo "Shift #5 not found.\n";
}

$handover = FinancialHandover::where('bar_shift_id', 5)->first();
if ($handover) {
    echo "HANDOVER:\n";
    echo "  ID: " . $handover->id . "\n";
    echo "  Status: " . $handover->status . "\n";
    echo "  Amount: " . number_format($handover->amount) . "\n";
} else {
    echo "No handover found for Shift #5.\n";
}
