<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'check_in',
        'check_out',
        'duration_minutes',
        'status',
        'location_branch',
        'user_id',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    /**
     * Get the staff member associated with this attendance.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the owner (business) associated with this attendance.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
