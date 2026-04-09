<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarShift extends Model
{
    protected $fillable = [
        'user_id',
        'staff_id',
        'location_branch',
        'opened_at',
        'closed_at',
        'status',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'digital_revenue',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'digital_revenue' => 'decimal:2',
    ];

    /**
     * Get the owner of the shift
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff member who opened the shift
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get formatted shift ID
     */
    public function getFormattedIdAttribute()
    {
        return 'S' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get all orders associated with this shift
     */
    public function orders()
    {
        return $this->hasMany(BarOrder::class, 'bar_shift_id');
    }
}
