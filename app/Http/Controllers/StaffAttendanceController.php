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
        
        // Handle Range Presets
        $range = $request->get('range', 'today'); // today, week, month, custom
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($range === 'today') {
            $startDate = $endDate = now()->format('Y-m-d');
        } elseif ($range === 'week') {
            $startDate = now()->startOfWeek()->format('Y-m-d');
            $endDate = now()->endOfWeek()->format('Y-m-d');
        } elseif ($range === 'month') {
            $startDate = now()->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');
        } elseif (!$startDate || !$endDate) {
            $startDate = $endDate = now()->format('Y-m-d');
            $range = 'today';
        } else {
            $range = 'custom';
        }

        $statusFilter = $request->get('status', 'all');

        // Fetch shift settings
        $shiftStart = SystemSetting::get('attendance_shift_start', '08:00');
        $shiftEnd = SystemSetting::get('attendance_shift_end', '17:00');

        $query = StaffAttendance::with('staff.role')
            ->where('user_id', $ownerId)
            ->whereBetween('check_in', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('check_in', 'desc');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $attendances = $query->get();
        
        // Performance helpers
        $activeNow = StaffAttendance::where('user_id', $ownerId)
            ->where('status', 'active')
            ->count();

        // For "First Arrival" badges, we ideally want to know the first arrival PER day in the range
        // but for simplicity we'll just show the latest set of arrivals or allow the view to handle it.
        // We'll pass the firstArrivalIds as a collection of IDs.
        $firstArrivalIds = StaffAttendance::where('user_id', $ownerId)
            ->whereBetween('check_in', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->selectRaw('MIN(id) as id')
            ->groupBy(\DB::raw('DATE(check_in)'))
            ->pluck('id')
            ->toArray();

        return view('manager.attendance.index', compact(
            'attendances', 'startDate', 'endDate', 'range', 'activeNow', 'statusFilter', 
            'shiftStart', 'shiftEnd', 'firstArrivalIds'
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
            'user_id' => 'nullable|integer',
        ]);

        $ownerId = $this->getOwnerId() ?: $request->user_id;

        if (!$ownerId) {
            return response()->json([
                'success' => false,
                'message' => 'Business ID missing. Please refresh the page.'
            ], 400);
        }

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
