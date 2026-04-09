<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodItemExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'food_item_id',
        'name',
        'price',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    /**
     * Get the food item that owns this extra.
     */
    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }
}
