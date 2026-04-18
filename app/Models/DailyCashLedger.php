<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCashLedger extends Model
{
    // Virtual attributes for view logic (Not persisted in DB)
    public $grossProfit;
    public $expectedRevenue;
    public $totalDayShortage;
    public $circulationDebt;
    public $adjustedProfit;
    public $circulationRefill;
    public $netAvailableProfit;
    public $shortageRecoveredToday;

    protected static function booted()
    {
        static::saving(function ($ledger) {
            // Automatically re-calculate derived totals whenever the ledger is updated
            $ledger->syncTotals();
        });
    }

    protected $fillable = [
        'user_id',
        'accountant_id',
        'ledger_date',
        'opening_cash',
        'total_cash_received',
        'total_digital_received',
        'total_expenses',
        'total_expenses_from_circulation',
        'total_expenses_from_profit',
        'expected_closing_cash',
        'actual_closing_cash',
        'profit_generated',
        'money_in_circulation',
        'profit_submitted_to_boss',
        'carried_forward',
        'status',
        'closed_at'
    ];

    protected $casts = [
        'ledger_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountant()
    {
        return $this->belongsTo(Staff::class, 'accountant_id');
    }

    public function expenses()
    {
        return $this->hasMany(DailyExpense::class);
    }

    /**
     * Synchronize all metrics for this ledger day.
     */
    public function syncTotals()
    {
        // 1. Daily Expenses (Directly linked)
        $this->loadMissing('expenses');
        $dailyExpenses = $this->expenses;
        
        // 2. Petty Cash Issues (Linked via Date + Owner)
        $pettyCash = \App\Models\PettyCashIssue::where('user_id', $this->user_id)
            ->whereDate('issue_date', $this->ledger_date)
            ->where('status', 'issued')
            ->where('purpose', 'NOT LIKE', '[FOOD]%') // Only Bar petty cash
            ->get();

        $circExp = $dailyExpenses->where('fund_source', 'circulation')->sum('amount') + 
                  (float)$pettyCash->where('fund_source', 'circulation')->sum('amount');

        $profExp = $dailyExpenses->where('fund_source', 'profit')->sum('amount') + 
                  (float)$pettyCash->where('fund_source', 'profit')->sum('amount');

        $this->total_expenses_from_circulation = $circExp;
        $this->total_expenses_from_profit = $profExp;
        $this->total_expenses = $circExp + $profExp;

        // 3. CALCULATE EXPECTED TOTALS & GROSS PROFIT (Shift-Aware)
        $dailyShiftIds = \App\Models\BarShift::where('user_id', $this->user_id)
            ->whereDate('opened_at', $this->ledger_date)
            ->pluck('id')
            ->toArray();

        $dailyRecIds = \App\Models\BarOrder::whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
            ->whereNotNull('reconciliation_id')
            ->pluck('reconciliation_id')
            ->unique()
            ->toArray();

        // [EXPECTED REVENUE] Total sum of all served/delivered orders for the day
        $expectedRevenue = \App\Models\BarOrder::whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
            ->whereIn('status', ['served', 'delivered'])
            ->sum('total_amount');

        // [GROSS PROFIT] Expected margin (Selling - Buying) for the day
        $grossProfit = \App\Models\BarOrder::whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
            ->whereIn('status', ['served', 'delivered'])
            ->with('items.productVariant')
            ->get()
            ->sum(function($order) {
                return $order->items->sum(function($item) {
                    $buyingPrice = $item->productVariant->buying_price_per_unit ?? 0;
                    return ($item->unit_price - $buyingPrice) * $item->quantity;
                });
            });

        // [SHORTAGES] Total missing money from waiter handovers (Only include finalized reconciliations)
        $totalDayShortage = \App\Models\WaiterDailyReconciliation::where('user_id', $this->user_id)
            ->where(function($q) use ($dailyShiftIds, $dailyRecIds) {
                $q->whereIn('bar_shift_id', !empty($dailyShiftIds) ? $dailyShiftIds : [0])
                  ->orWhereIn('id', !empty($dailyRecIds) ? $dailyRecIds : [0]);
            })
            ->whereIn('status', ['submitted', 'verified', 'settled']) // Ignore draft/pending/cancelled
            ->where('difference', '<', 0)
            ->sum('difference');
        $totalDayShortage = abs($totalDayShortage);

        // 4. PROPORTIONAL PROFIT CALCULATION
        // Profit is the proportional share of the ACTUAL money collected (Net Collections)
        $actualCollections = $this->total_cash_received + $this->total_digital_received;
        
        // Use max(0, ...) to ensure negative margins (data errors) don't break the ratio
        $safeGrossProfit = max(0, $grossProfit);
        $profitMargin = $expectedRevenue > 0 ? ($safeGrossProfit / $expectedRevenue) : 0;
        
        $this->profit_generated = (float)round($actualCollections * $profitMargin);
        
        // [INSIGHT] How much Capital (Circulation) was lost due to the shortage?
        // We proportion the shortage: part of it was "would-be profit", the rest is "business capital" (COGS).
        $this->circulationDebt = max(0, $totalDayShortage * (1 - $profitMargin));

        // 5. CALCULATE BALANCES
        $totalPhysicalAssets = ($this->opening_cash + $actualCollections);
        $this->expected_closing_cash = $totalPhysicalAssets - $this->total_expenses;
        
        /**
         * BUSINESS RULE: Rollover (carried_forward)
         * Shortages are excluded upfront in collection totals.
         * Rollover = (Opening + Net Collections) - ExpensesFromCirculation - ProfitPayouts.
         */
        $this->carried_forward = $totalPhysicalAssets 
                                - $this->total_expenses_from_circulation 
                                - $this->profit_submitted_to_boss;
        
        // Ensure non-negative rollover
        if ($this->carried_forward < 0) {
            $this->carried_forward = 0;
        }

        $this->money_in_circulation = $this->carried_forward;
        
        $this->shortageRecoveredToday = \App\Models\WaiterDailyReconciliation::where('user_id', $this->user_id)
            ->whereDate('updated_at', $this->ledger_date)
            ->where('status', 'settled')
            ->sum(\Illuminate\Support\Facades\DB::raw('ABS(difference)'));

        // Attach virtual properties for view insight
        $this->grossProfit = $grossProfit;
        $this->expectedRevenue = $expectedRevenue;
        $this->totalDayShortage = $totalDayShortage;
        $this->adjustedProfit = $grossProfit - $totalDayShortage;
        $this->circulationRefill = $actualCollections - $this->profit_generated;
        $this->netAvailableProfit = $this->profit_generated - $this->total_expenses_from_profit;
        
        return $this;
    }

}
