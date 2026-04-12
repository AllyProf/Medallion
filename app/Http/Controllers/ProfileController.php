<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Show profile info
     */
    public function index()
    {
        $isStaff = session('is_staff');
        $staff = $isStaff ? Staff::find(session('staff_id')) : null;
        $user = $isStaff ? null : auth()->user();

        return view('profile.index', compact('isStaff', 'staff', 'user'));
    }

    /**
     * Update profile details (Image, Phone)
     */
    public function update(Request $request)
    {
        $isStaff = session('is_staff');
        
        $rules = [
            'phone' => 'nullable|string|max:20',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $model = $isStaff ? Staff::find(session('staff_id')) : auth()->user();

        if (!$model) {
            return redirect()->back()->with('error', 'Profile not found.');
        }

        // Update Phone (field name differs)
        if ($isStaff) {
            $model->phone_number = $request->phone;
        } else {
            $model->phone = $request->phone;
        }

        // Handle Image Upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($model->profile_image) {
                Storage::disk('public')->delete($model->profile_image);
            }
            
            $path = $request->file('profile_image')->store('profiles', 'public');
            $model->profile_image = $path;
        }

        $model->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed|min:8',
        ]);

        $isStaff = session('is_staff');
        $model = $isStaff ? Staff::find(session('staff_id')) : auth()->user();

        if (!$model) {
            return redirect()->back()->with('error', 'Profile not found.');
        }

        $model->password = Hash::make($request->password);
        $model->save();

        return redirect()->back()->with('success', 'Password updated successfully.');
    }
}
