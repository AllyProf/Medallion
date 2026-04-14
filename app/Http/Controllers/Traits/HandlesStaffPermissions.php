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
            \Log::info('hasPermission called for staff', [
                'staff_id' => $staffId,
                'module' => $module,
                'action' => $action
            ]);
            
            $staff = Staff::with('role.permissions')->find($staffId);
            
            if (!$staff || !$staff->is_active) {
                \Log::warning('Staff not found or inactive', [
                    'staff_id' => $staffId,
                    'staff_exists' => $staff !== null,
                    'is_active' => $staff->is_active ?? false
                ]);
                return false;
            }

            // Get staff's role
            $role = $staff->role;
            
            if (!$role) {
                \Log::warning('Staff has no role assigned', [
                    'staff_id' => $staffId,
                    'role_id' => $staff->role_id
                ]);
                return false;
            }

            // Manager role has all permissions
            // Check both name and slug for exact match (case-insensitive)
            $roleName = strtolower(trim($role->name ?? ''));
            $roleSlug = strtolower(trim($role->slug ?? ''));
            
            \Log::info('Checking role for Manager', [
                'staff_id' => $staff->id,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_slug' => $role->slug,
                'role_name_lower' => $roleName,
                'role_slug_lower' => $roleSlug
            ]);
            
            // Check if role is Manager, Accountant or Super Admin (all get full access)
            if (in_array($roleName, ['manager', 'accountant', 'finance officer', 'finance', 'super admin', 'super administrator']) || 
                in_array($roleSlug, ['manager', 'accountant', 'super-admin', 'superadmin'])) {
                \Log::info('✅ Manager/Accountant/Super Admin role detected - granting all permissions', [
                    'staff_id' => $staff->id,
                    'role_name' => $role->name,
                    'role_slug' => $role->slug,
                    'module' => $module,
                    'action' => $action
                ]);
                return true;
            }

            // Ensure permissions are loaded
            if (!$role->relationLoaded('permissions')) {
                $role->load('permissions');
            }

            // Check permission through role
            $hasPermission = $role->hasPermission($module, $action);
            \Log::info('Permission check result', [
                'staff_id' => $staff->id,
                'role_name' => $role->name,
                'module' => $module,
                'action' => $action,
                'has_permission' => $hasPermission
            ]);
            return $hasPermission;
        }

        // Regular user (not staff)
        $user = Auth::user();
        
        if (!$user) {
            \Log::warning('hasPermission called but no user authenticated', [
                'module' => $module,
                'action' => $action
            ]);
            return false;
        }

        // Check if user is owner or admin (owners and admins have all permissions)
        if ($user->role === 'customer' || $user->role === 'admin' || $user->hasRole('owner')) {
            \Log::info('Owner or Admin user detected - granting all permissions', [
                'user_id' => $user->id,
                'role' => $user->role,
                'module' => $module,
                'action' => $action
            ]);
            return true;
        }

        // Check user's permissions
        $hasPermission = $user->hasPermission($module, $action);
        \Log::info('Regular user permission check', [
            'user_id' => $user->id,
            'module' => $module,
            'action' => $action,
            'has_permission' => $hasPermission
        ]);
        return $hasPermission;
    }

    /**
     * Check if current user/staff has a specific role
     */
    protected function hasRole($roleSlug)
    {
        // Staff members don't have user roles
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
     * Get the current active bar shift for the logged-in staff member
     */
    protected function getCurrentShift()
    {
        $staff = $this->getCurrentStaff();
        if (!$staff) return null;

        return \App\Models\BarShift::where('staff_id', $staff->id)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Check if the current user is a Platform-Wide Site Admin (AllyProf/Medallion Owner)
     */
    protected function isSiteAdmin()
    {
        if (session('is_staff')) return false;

        $user = Auth::user();
        if (!$user) return false;

        return $user->role === 'admin';
    }

    /**
     * Check if current user/staff has a high-level business role (Manager, Super Admin, Owner)
     */
    protected function isBusinessPowerUser()
    {
        // 1. Owners are always power users
        if (!session('is_staff') && Auth::check()) {
            return true;
        }

        // 2. Staff check
        $staff = $this->getCurrentStaff();
        if (!$staff) return false;

        $role = $staff->role;
        if (!$role) return false;

        $roleName = strtolower(trim($role->name ?? ''));
        $roleSlug = strtolower(trim($role->slug ?? ''));

        $powerRoles = ['manager', 'accountant', 'finance officer', 'finance', 'super admin', 'super administrator', 'super_admin'];
        $powerSlugs = ['manager', 'accountant', 'super-admin', 'superadmin', 'super_admin'];

        $isPower = in_array($roleName, $powerRoles) || in_array($roleSlug, $powerSlugs);
        
        \Log::info('Power User Check details', [
            'role_name' => $roleName,
            'role_slug' => $roleSlug,
            'is_power' => $isPower
        ]);

        return $isPower;
    }

    /**
     * Check if the current user/staff has a Super Admin role (for specialized UI elements)
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
            
            return in_array($roleName, ['super admin', 'super administrator', 'super_admin', 'superadmin']) || 
                   in_array($roleSlug, ['super-admin', 'superadmin', 'super_admin']) ||
                   !empty($role->is_super_admin_virtual);
        }

        return false;
    }
}

