<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenOrderItemExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'kitchen_order_item_id',
        'food_item_extra_id',
        'extra_name',
        'unit_price',
        'quantity',
        'total_price',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Get the kitchen order item that owns this extra.
     */
    public function kitchenOrderItem()
    {
        return $this->belongsTo(KitchenOrderItem::class);
    }

    /**
     * Get the source food item extra.
     */
    public function foodItemExtra()
    {
        return $this->belongsTo(FoodItemExtra::class);
    }
}
