<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffItemShortage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_id',
        'product_variant_id',
        'bar_shift_id',
        'quantity_short',
        'buying_price',
        'selling_price',
        'expected_revenue',
        'money_in_supply',
        'lost_profit',
        'status',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'quantity_short' => 'decimal:2',
        'buying_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'expected_revenue' => 'decimal:2',
        'money_in_supply' => 'decimal:2',
        'lost_profit' => 'decimal:2',
    ];

    /**
     * Get the restaurant/bar owner
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the staff attributed to the shortage
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the product variant that had a shortage
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the bar shift context if applicable
     */
    public function shift()
    {
        return $this->belongsTo(BarShift::class, 'bar_shift_id');
    }

    /**
     * Get the manager or accountant who recorded this shortage
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
