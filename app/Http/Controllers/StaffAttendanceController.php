<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StaffAttendanceController extends Controller
{
    private function getOwnerId()
    {
        return session('is_staff') ? Staff::find(session('staff_id'))->user_id : Auth::id();
    }

    /**
     * Dashboard view for manager to see attendance report.
     */
    public function managerIndex(Request $request)
    {
        $ownerId = $this->getOwnerId();
        $date = $request->get('date', now()->format('Y-m-d'));
        $statusFilter = $request->get('status', 'all');

        // Fetch shift settings
        $shiftStart = SystemSetting::get('attendance_shift_start', '08:00');
        $shiftEnd = SystemSetting::get('attendance_shift_end', '17:00');

        $query = StaffAttendance::with('staff.role')
            ->where('user_id', $ownerId)
            ->whereDate('check_in', $date)
            ->orderBy('check_in', 'asc'); // Ascending to find first arrival easily

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $attendances = $query->get();
        
        // Find first arrival record ID
        $firstArrivalId = $attendances->first() ? $attendances->first()->id : null;

        $activeNow = StaffAttendance::where('user_id', $ownerId)
            ->where('status', 'active')
            ->count();

        return view('manager.attendance.index', compact(
            'attendances', 'date', 'activeNow', 'statusFilter', 
            'shiftStart', 'shiftEnd', 'firstArrivalId'
        ));
    }

    /**
     * Update attendance settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'shift_start' => 'required|string',
            'shift_end' => 'required|string',
        ]);

        SystemSetting::set('attendance_shift_start', $request->shift_start, 'text', 'attendance', 'Standard shift start time');
        SystemSetting::set('attendance_shift_end', $request->shift_end, 'text', 'attendance', 'Standard shift end time');

        return redirect()->back()->with('success', 'Attendance settings updated successfully!');
    }

    /**
     * Kiosk toggle for check-in/check-out.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $ownerId = $this->getOwnerId();
        $staff = Staff::where('user_id', $ownerId)
            ->where('pin', $request->pin)
            ->where('is_active', true)
            ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN. Please try again or contact manager.'
            ], 401);
        }

        // Check current active session
        $activeAttendance = StaffAttendance::where('staff_id', $staff->id)
            ->where('status', 'active')
            ->orderBy('check_in', 'desc')
            ->first();

        // ── Identify-only mode: return staff info without recording ──
        if ($request->boolean('identify_only')) {
            return response()->json([
                'success'        => true,
                'staff_name'     => $staff->full_name,
                'current_status' => $activeAttendance ? 'active' : 'inactive',
            ]);
        }

        if ($activeAttendance) {
            // Check-out logic
            $checkOut = now();
            $duration = $activeAttendance->check_in->diffInMinutes($checkOut);

            $activeAttendance->update([
                'check_out' => $checkOut,
                'duration_minutes' => $duration,
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'status' => 'out',
                'staff_name' => $staff->full_name,
                'message' => "Checked Out! Worked for " . round($duration/60, 1) . " hours."
            ]);
        }

        // Check-in logic
        StaffAttendance::create([
            'staff_id' => $staff->id,
            'user_id' => $ownerId,
            'check_in' => now(),
            'status' => 'active',
            'location_branch' => session('active_location')
        ]);

        return response()->json([
            'success' => true,
            'status' => 'in',
            'staff_name' => $staff->full_name,
            'message' => "Successfully Checked In! Have a great shift."
        ]);
    }
}
