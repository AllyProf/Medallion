<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFeedback extends Model
{
    protected $table = 'customer_feedback';

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_phone',
        'waiter_name',
        'rating',
        'comments',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
