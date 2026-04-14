<?php

namespace App\Http\Controllers\Traits;

use App\Models\Staff;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

trait HandlesStaffPermissions
{
    /**
     * Get the current user or staff owner
     */
    protected function getCurrentUser()
    {
        if (session('is_staff')) {
            $staff = Staff::find(session('staff_id'));
            return $staff ? $staff->owner : null;
        }
        
        return Auth::user();
    }

    /**
     * Get the owner ID (for staff, get their owner's ID)
     */
    protected function getOwnerId()
    {
        if (session('is_staff')) {
            return session('staff_user_id');
        }
        
        $user = Auth::user();
        return $user ? $user->id : null;
    }

    /**
     * Check if current user/staff has permission
     */
    protected function hasPermission($module, $action)
    {
        // Check if staff member
        if (session('is_staff')) {
            $staffId = session('staff_id');
            $staff = Staff::with('role.permissions')->find($staffId);
            
            if (!$staff || !$staff->is_active) {
                return false;
            }

            // Get staff's role
            $role = $staff->role;
            if (!$role) return false;

            // Power Roles for staff
            $roleName = strtolower(trim($role->name ?? ''));
            $roleSlug = strtolower(trim($role->slug ?? ''));
            
            if (in_array($roleName, ['manager', 'accountant', 'finance officer', 'finance', 'super admin', 'super administrator']) || 
                in_array($roleSlug, ['manager', 'accountant', 'super-admin', 'superadmin'])) {
                return true;
            }

            // Ensure permissions are loaded
            if (!$role->relationLoaded('permissions')) {
                $role->load('permissions');
            }

            return $role->hasPermission($module, $action);
        }

        // Regular user (not staff)
        $user = Auth::user();
        if (!$user) return false;

        // Check if user is owner or admin (owners and admins have all permissions)
        if ($user->role === 'customer' || $user->role === 'admin' || $user->hasRole('owner')) {
            return true;
        }

        return $user->hasPermission($module, $action);
    }

    /**
     * Check if current user/staff has a specific role
     */
    protected function hasRole($roleSlug)
    {
        if (session('is_staff')) {
            return false;
        }

        $user = Auth::user();
        return $user ? $user->hasRole($roleSlug) : false;
    }

    /**
     * Get current staff member if logged in as staff
     */
    protected function getCurrentStaff()
    {
        if (session('is_staff')) {
            return Staff::find(session('staff_id'));
        }
        return null;
    }

    /**
     * Check if the current user is a Platform-Wide Site Admin
     */
    protected function isSiteAdmin()
    {
        if (session('is_staff')) return false;
        $user = Auth::user();
        return $user && $user->role === 'admin';
    }

    /**
     * Check if current user/staff is a Business Power User (Managers, Owners, accountants)
     */
    protected function isBusinessPowerUser()
    {
        // Owners are power users
        if (!session('is_staff') && Auth::check()) return true;

        $staff = $this->getCurrentStaff();
        if (!$staff) return false;

        $role = $staff->role;
        if (!$role) return false;

        $roleName = strtolower(trim($role->name ?? ''));
        $roleSlug = strtolower(trim($role->slug ?? ''));

        $powerRoles = ['manager', 'accountant', 'finance officer', 'finance', 'super admin', 'super administrator', 'super_admin'];
        $powerSlugs = ['manager', 'accountant', 'super-admin', 'superadmin', 'super_admin'];

        return in_array($roleName, $powerRoles) || in_array($roleSlug, $powerSlugs);
    }

    /**
     * Check if the current user/staff is a Super Admin (Global Visibility)
     */
    protected function isSuperAdminRole()
    {
        // Platform Admin check
        if ($this->isSiteAdmin()) return true;

        // Staff check
        if (session('is_staff')) {
            $staff = $this->getCurrentStaff();
            if (!$staff) return false;
            
            $role = $staff->role;
            if (!$role) return false;

            $roleName = strtolower(trim($role->name ?? ''));
            $roleSlug = strtolower(trim($role->slug ?? ''));
            
            // Allow Manager and Super Admin roles to see everything platform-wide
            return in_array($roleName, ['manager', 'super admin', 'super administrator', 'super_admin', 'superadmin']) || 
                   in_array($roleSlug, ['manager', 'super-admin', 'superadmin', 'super_admin']) ||
                   !empty($role->is_super_admin_virtual);
        }

        return false;
    }

    /**
     * Get the active/current shift for the staff or owner
     */
    protected function getCurrentShift()
    {
        $ownerId = $this->getOwnerId();
        
        // If logged in as staff, prioritizing their specific open shift
        if (session('is_staff')) {
            return \App\Models\BarShift::where('user_id', $ownerId)
                ->where('staff_id', session('staff_id'))
                ->where('status', 'open')
                ->first();
        }

        // For owner/management, return the first open shift found for the business
        return \App\Models\BarShift::where('user_id', $ownerId)
            ->where('status', 'open')
            ->first();
    }
}
