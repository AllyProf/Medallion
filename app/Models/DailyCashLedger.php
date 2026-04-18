<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCashLedger extends Model
{
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
     * Synchronize all expenses (DailyExpense + PettyCashIssue) for this ledger day.
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

        // Re-calculate derived financial balances for the rollover cycle
        // Available = (Opening + Collections) - Profit - Expenses (from circulation)
        $this->expected_closing_cash = ($this->opening_cash + $this->total_cash_received + $this->total_digital_received) - $this->total_expenses;
        
        // Business Rule: Rollover (carried_forward) for next day is the money left after profit is accounted for
        // and expenses from circulation are deducted.
        $this->carried_forward = ($this->opening_cash + $this->total_cash_received + $this->total_digital_received) - $this->profit_generated - $this->total_expenses_from_circulation;
        
        // Ensure non-negative rollover
        if ($this->carried_forward < 0) {
            $this->carried_forward = 0;
        }

        $this->money_in_circulation = $this->carried_forward;
        
        return $this;
    }

}
