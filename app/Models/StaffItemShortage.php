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
        'recorded_by_staff_id',
        'approved_by_staff_id',
        'approved_at',
    ];

    protected $casts = [
        'quantity_short'   => 'decimal:2',
        'buying_price'     => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'expected_revenue' => 'decimal:2',
        'money_in_supply'  => 'decimal:2',
        'lost_profit'      => 'decimal:2',
        'approved_at'      => 'datetime',
    ];

    /** Business owner (user) */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Staff attributed/responsible for the shortage */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /** Product variant that had the shortage */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Bar shift context */
    public function shift()
    {
        return $this->belongsTo(BarShift::class, 'bar_shift_id');
    }

    /** User (owner/admin) who created this record — kept for legacy */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Counter staff member who physically recorded this shortage */
    public function recorderStaff()
    {
        return $this->belongsTo(Staff::class, 'recorded_by_staff_id');
    }

    /** Manager/accountant who approved this shortage */
    public function approvedBy()
    {
        return $this->belongsTo(Staff::class, 'approved_by_staff_id');
    }
}
