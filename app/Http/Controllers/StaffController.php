<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesStaffPermissions;
use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Role;
use App\Models\BusinessType;
use App\Services\SmsService;
use App\Services\RoleSuggestionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StaffController extends Controller
{
    use HandlesStaffPermissions;

    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Display staff registration form
     */
    public function create()
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission (Manager role has all permissions)
        if (!$this->hasPermission('staff', 'create')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to register staff members.');
        }
        
        // For staff members or Super Admin, skip plan check
        // Only check plan for regular users (owners) who are not platform admins
        if (!session('is_staff') && !(auth()->check() && auth()->user()->isAdmin())) {
            // Check if user's plan allows staff registration (Free or Pro only)
            $plan = $user->currentPlan();
            if (!$plan || !in_array($plan->slug, ['free', 'pro'])) {
                return redirect()->route('dashboard')
                    ->with('error', 'Staff registration is only available for Free and Pro plans.');
            }
        }

        // Get user's enabled business types
        $businessTypes = $user->enabledBusinessTypes()->get();

        $isAdmin = $this->isSuperAdminRole();
        
        // Repair and Deduplicate roles before loading the page
        $this->repairAndDeduplicateRoles();

        // Get roles: Super Admin sees ALL distinct roles; owners only see their own
        if ($isAdmin) {
            $roles = \App\Models\Role::where('is_active', true)->get()->unique(function ($item) {
                return strtolower(trim($item->name));
            });
        } else {
            $roles = $user->ownedRoles()->where('is_active', true)->get();

            if ($roles->count() == 0) {
                return redirect()->route('business-configuration.edit')
                    ->with('warning', 'Please create at least one role in Business Configuration before registering staff members.');
            }
        }

        // EXTREMELY ROBUST Super Admin role injection (Fuzzy Matching)
        $superAdminRole = \App\Models\Role::query()
            ->where(function($q) {
                $q->where('name', 'LIKE', 'Super Admin%')
                  ->orWhere('name', 'LIKE', 'SuperAdmin%')
                  ->orWhere('slug', 'LIKE', 'super-admin%')
                  ->orWhere('slug', 'LIKE', 'superadmin%')
                  ->orWhere('slug', '=', 'admin');
            })
            ->where('is_active', true)
            ->first();

        return view('staff.create', compact('roles', 'businessTypes', 'isAdmin', 'superAdminRole'));
    }

    /**
     * Store new staff member
     */
    public function store(Request $request)
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission (Manager role has all permissions)
        if (!$this->hasPermission('staff', 'create')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to register staff members.');
        }
        
        // For staff members or Super Admin, skip plan check
        // Only check plan for regular users (owners) who are not platform admins
        if (!session('is_staff') && !(auth()->check() && auth()->user()->isAdmin())) {
            // Check if user's plan allows staff registration
            $plan = $user->currentPlan();
            if (!$plan || !in_array($plan->slug, ['free', 'pro'])) {
                return back()->with('error', 'Staff registration is only available for Free and Pro plans.');
            }
        }

        // Ensure roles are repaired before storing
        $this->repairAndDeduplicateRoles();

        // If role_id is 'super_admin' string, replace it with the actual ID
        if ($request->input('role_id') === 'super_admin') {
            $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->orWhere('slug', 'super-admin')->first();
            if ($superAdminRole) {
                $request->merge(['role_id' => $superAdminRole->id]);
            }
        }

        // Validate request
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('staff', 'email'),
                \Illuminate\Validation\Rule::unique('users', 'email'),
            ],
            'gender' => 'required|in:male,female,other',
            'nida' => 'nullable|string|max:50',
            'phone_number' => 'required|string|max:20',
            'next_of_kin' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:20',
            'location_branch' => 'nullable|string|max:255',
            'business_type_id' => 'nullable|exists:business_types,id',
            'role_id' => 'required|exists:roles,id',
            'salary_paid' => 'nullable|numeric|min:0',
            'religion' => 'nullable|string|max:100',
            'nida_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'voter_id_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'professional_certificate_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pin' => 'nullable|string|size:4|regex:/^[0-9]+$/',
        ]);

        // Verify role belongs to the owner (Super Admins can use any role)
        $role = Role::findOrFail($validated['role_id']);
        $ownerId = $this->getOwnerId();
        if (!(auth()->check() && auth()->user()->isAdmin()) && $role->user_id !== $ownerId) {
            return back()->with('error', 'Invalid role selected.');
        }

        // Generate staff ID
        $staffId = Staff::generateStaffId($ownerId);

        // Generate password from last name
        $password = Staff::generatePasswordFromLastName($validated['full_name']);
        $hashedPassword = Hash::make($password);
        
        // Process Kiosk PIN
        $pin = $request->input('pin');
        if (empty($pin)) {
            $pin = Staff::generatePin();
        }

        // Handle file uploads
        $nidaAttachment = null;
        $voterIdAttachment = null;
        $professionalCertificateAttachment = null;

        if ($request->hasFile('nida_attachment')) {
            $nidaAttachment = $request->file('nida_attachment')->store('staff/documents/nida', 'public');
        }

        if ($request->hasFile('voter_id_attachment')) {
            $voterIdAttachment = $request->file('voter_id_attachment')->store('staff/documents/voter-id', 'public');
        }

        if ($request->hasFile('professional_certificate_attachment')) {
            $professionalCertificateAttachment = $request->file('professional_certificate_attachment')->store('staff/documents/certificates', 'public');
        }

        // Create staff record
        $staff = Staff::create([
            'user_id' => $ownerId,
            'staff_id' => $staffId,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'nida' => $validated['nida'] ?? null,
            'phone_number' => $validated['phone_number'],
            'password' => $hashedPassword,
            'next_of_kin' => $validated['next_of_kin'] ?? null,
            'next_of_kin_phone' => $validated['next_of_kin_phone'] ?? null,
            'location_branch' => $validated['location_branch'] ?? null,
            'business_type_id' => $validated['business_type_id'] ?? null,
            'role_id' => $validated['role_id'],
            'salary_paid' => $validated['salary_paid'] ?? 0,
            'pin' => $pin,
            'religion' => $validated['religion'] ?? null,
            'nida_attachment' => $nidaAttachment,
            'voter_id_attachment' => $voterIdAttachment,
            'professional_certificate_attachment' => $professionalCertificateAttachment,
            'is_active' => true,
        ]);

        // Send SMS with credentials
        $this->sendStaffCredentialsSms($staff, $password, $pin);

        return redirect()->route('staff.index')
            ->with('success', 'Staff member registered successfully! SMS with credentials has been sent to ' . $staff->phone_number);
    }

    /**
     * Display list of staff members
     */
    public function index()
    {
        if (auth()->check() && auth()->user()->role === 'admin') {
            // dd(\App\Models\Staff::count()); // Temporary check removed to keep app running, but noted.
            // Using Log instead to not crash the user's UI
            \Log::info('DEBUG: Staff count seen by Admin:', ['count' => \App\Models\Staff::count()]);
        }
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission (Manager role has all permissions)
        if (!$this->hasPermission('staff', 'view')) {
            \Log::warning('User denied access to staff list', [
                'is_staff' => session('is_staff'),
                'staff_id' => session('staff_id'),
                'has_permission' => $this->hasPermission('staff', 'view')
            ]);
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to view staff members.');
        }
        
        // Get owner ID (for staff, get their owner's ID)
        $ownerId = $this->getOwnerId();
        
        \Log::info('Staff Page Debug', [
            'user_id' => auth()->id(),
            'user_role' => auth()->user()?->role ?? 'none',
            'is_admin_check' => auth()->check() && auth()->user()->isAdmin(),
            'owner_id' => $ownerId,
            'session_is_staff' => session('is_staff')
        ]);
        
        // For staff members or Super Admin, skip plan check
        // Only check plan for regular users (owners) who are not platform admins
        $isAdmin = $this->isSuperAdminRole();
        if (!session('is_staff') && !$isAdmin) {
            // Check if user's plan allows staff registration
            $plan = $user->currentPlan();
            if (!$plan || !in_array($plan->slug, ['free', 'pro'])) {
                return redirect()->route('dashboard')
                    ->with('error', 'Staff management is only available for Free and Pro plans.');
            }
        }

        // 1. Platform-Wide Visibility (Site Admin, Global Managers, Super Admins)
        if ($this->isSiteAdmin() || $this->isSuperAdminRole()) {
            $staff = Staff::with(['role', 'businessType'])->orderBy('created_at', 'desc')->get();
        } 
        // 2. Business Power User (Owners/Accountants - Sees ALL staff for their business)
        else if ($this->isBusinessPowerUser()) {
            $staff = Staff::where('user_id', $ownerId)
                ->with(['role', 'businessType'])
                ->orderBy('created_at', 'desc')
                ->get();
        }
        // 3. Regular Staff (Sees staff for their business, restricted by current branch/location)
        else {
            $staffQuery = Staff::where('user_id', $ownerId)
                ->with(['role', 'businessType']);
                
            if (session('active_location')) {
                $staffQuery->where('location_branch', session('active_location'));
            }
            $staff = $staffQuery->orderBy('created_at', 'desc')->get();
        }

        // Calculate statistics
        $stats = [
            'total' => $staff->count(),
            'active' => $staff->where('is_active', true)->count(),
            'total_salary' => $staff->sum('salary_paid'),
            'branches' => $staff->pluck('location_branch')->unique()->filter()->count() ?: 1
        ];

        return view('staff.index', compact('staff', 'stats'));
    }

    /**
     * Get roles for a specific business type (AJAX endpoint)
     */
    public function getRolesByBusinessType(Request $request)
    {
        // Only allow authenticated users (not staff) to access this
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['roles' => [], 'error' => 'Unauthorized'], 401);
        }
        
        $businessTypeId = $request->input('business_type_id');
        
        if (!$businessTypeId) {
            return response()->json(['roles' => [], 'error' => 'Business type ID is required']);
        }
        
        // Get the business type
        $businessType = BusinessType::find($businessTypeId);
        if (!$businessType) {
            return response()->json(['roles' => [], 'error' => 'Business type not found']);
        }
        
        // Ensure default roles exist for this business type
        $this->ensureDefaultRolesExist($user, [$businessType->id]);
        
        // Get suggested role names for this business type
        $suggestedRoles = RoleSuggestionService::getSuggestedRolesForBusinessType($businessType->slug);
        $suggestedRoleNames = array_map(function($role) {
            return strtolower(trim($role['name']));
        }, $suggestedRoles);
        
        // Get all user's active roles
        $allRoles = Role::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();
            
        // EXTREMELY IMPORTANT: Always include the Super Admin role in the AJAX response
        // Use FUZZY MATCHING to ensure it is found regardless of naming variations on the server
        $superAdminRole = Role::where(function($q) {
                $q->where('name', 'LIKE', 'Super Admin%')
                  ->orWhere('name', 'LIKE', 'SuperAdmin%')
                  ->orWhere('slug', 'LIKE', 'super-admin%')
                  ->orWhere('slug', 'LIKE', 'superadmin%')
                  ->orWhere('slug', '=', 'admin');
            })
            ->where('is_active', true)
            ->first();

        if ($superAdminRole && !$allRoles->contains('id', $superAdminRole->id)) {
            $allRoles->push($superAdminRole);
        }

        // Clean response with filtered roles
        return response()->json([
            'roles' => $allRoles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                ];
            })->values()
        ]);
    }

    /**
     * Ensure default roles exist for business types
     */
    private function ensureDefaultRolesExist($user, $businessTypeIds)
    {
        $ownerId = $this->getOwnerId();
        
        $plan = $user->currentPlan();
        $isBasicPlan = $plan && $plan->slug === 'basic';
        
        // Don't auto-create roles for Basic plan
        if ($isBasicPlan) {
            return;
        }

        // Get business type slugs
        $businessTypes = BusinessType::whereIn('id', $businessTypeIds)->get();
        $businessTypeSlugs = $businessTypes->pluck('slug')->toArray();

        // Get all suggested roles for these business types
        $suggestedRoles = RoleSuggestionService::getSuggestedRolesForBusinessTypes($businessTypeSlugs);

        // Get existing roles for this user (by name, case-insensitive)
        $existingRoles = $user->ownedRoles()
            ->where('is_active', true)
            ->get()
            ->keyBy(function($role) {
                return strtolower($role->name);
            });

        // Create default roles that don't exist yet
        foreach ($suggestedRoles as $roleSuggestion) {
            $roleNameLower = strtolower(trim($roleSuggestion['name']));
            
            // Check if role already exists (Strict check across all business types)
            $existingCount = Role::where('user_id', $ownerId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$roleNameLower])
                ->where('is_active', true)
                ->count();

            if ($existingCount > 0) {
                continue; // Skip if role already exists
            }

            // Create the role
            $role = Role::create([
                'user_id' => $ownerId,
                'name' => $roleSuggestion['name'],
                'slug' => \Illuminate\Support\Str::slug($roleSuggestion['name'] . '-' . $ownerId . '-' . time()),
                'description' => $roleSuggestion['description'] ?? null,
                'is_system_role' => false,
                'is_active' => true,
            ]);

            // Get permission IDs for this role
            $permissionIds = RoleSuggestionService::getPermissionIdsForRole($roleSuggestion);
            
            // Assign permissions to the role
            if (!empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }

    /**
     * Display staff member details
     */
    public function show($id)
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission
        if (!$this->hasPermission('staff', 'view')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to view staff details.');
        }
        
        $ownerId = $this->getOwnerId();
        $staffQuery = Staff::query()->with(['role', 'businessType']);
        if (!$this->isSuperAdminRole()) {
            $staffQuery->where('user_id', $ownerId);
        }
        $staff = $staffQuery->findOrFail($id);

        return view('staff.show', compact('staff'));
    }

    /**
     * Show the form for editing a staff member
     */
    public function edit($id)
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission
        if (!$this->hasPermission('staff', 'edit')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to edit staff members.');
        }
        
        $ownerId = $this->getOwnerId();
        $staffQuery = Staff::where('user_id', $ownerId);
        if ($this->isSuperAdminRole()) {
            $staffQuery = Staff::query();
        }
        $staff = $staffQuery->findOrFail($id);
        
        // Get user's enabled business types
        $businessTypes = $user->enabledBusinessTypes()->get();
        
        // Get user's roles for dropdown
        if ($this->isSuperAdminRole()) {
            $roles = \App\Models\Role::where('is_active', true)->get()->unique(function ($item) {
                return strtolower(trim($item->name));
            });
        } else {
            $roles = $user->ownedRoles()->where('is_active', true)->get();
        }

        return view('staff.edit', compact('staff', 'roles', 'businessTypes'));
    }

    /**
     * Update a staff member
     */
    public function update(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission
        if (!$this->hasPermission('staff', 'edit')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to edit staff members.');
        }
        
        $ownerId = $this->getOwnerId();
        $staffQuery = Staff::where('user_id', $ownerId);
        if ($this->isSuperAdminRole()) {
            $staffQuery = Staff::query();
        }
        $staff = $staffQuery->findOrFail($id);

        // Validate request
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('staff', 'email')->ignore($staff->id),
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($staff->id),
            ],
            'gender' => 'required|in:male,female,other',
            'nida' => 'nullable|string|max:50',
            'phone_number' => 'required|string|max:20',
            'next_of_kin' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:20',
            'location_branch' => 'nullable|string|max:255',
            'business_type_id' => 'nullable|exists:business_types,id',
            'role_id' => 'required|exists:roles,id',
            'salary_paid' => 'nullable|numeric|min:0',
            'religion' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'professional_certificate_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pin' => 'nullable|string|size:4|regex:/^[0-9]+$/',
        ]);

        // Verify role belongs to the owner (Super Admin can use any role)
        $role = Role::findOrFail($validated['role_id']);
        $ownerId = $this->getOwnerId();
        if (!(auth()->check() && auth()->user()->isAdmin()) && $role->user_id !== $ownerId) {
            return back()->with('error', 'Invalid role selected.');
        }

        // Handle file uploads (only if new files are provided)
        if ($request->hasFile('nida_attachment')) {
            // Delete old file if exists
            if ($staff->nida_attachment) {
                Storage::disk('public')->delete($staff->nida_attachment);
            }
            $validated['nida_attachment'] = $request->file('nida_attachment')->store('staff/documents/nida', 'public');
        }

        if ($request->hasFile('voter_id_attachment')) {
            if ($staff->voter_id_attachment) {
                Storage::disk('public')->delete($staff->voter_id_attachment);
            }
            $validated['voter_id_attachment'] = $request->file('voter_id_attachment')->store('staff/documents/voter-id', 'public');
        }

        if ($request->hasFile('professional_certificate_attachment')) {
            if ($staff->professional_certificate_attachment) {
                Storage::disk('public')->delete($staff->professional_certificate_attachment);
            }
            $validated['professional_certificate_attachment'] = $request->file('professional_certificate_attachment')->store('staff/documents/certificates', 'public');
        }

        // Remove file fields from validated if not updated
        if (!$request->hasFile('nida_attachment')) {
            unset($validated['nida_attachment']);
        }
        if (!$request->hasFile('voter_id_attachment')) {
            unset($validated['voter_id_attachment']);
        }
        if (!$request->hasFile('professional_certificate_attachment')) {
            unset($validated['professional_certificate_attachment']);
        }

        // Handle optional Salary
        if (!isset($validated['salary_paid'])) {
            $validated['salary_paid'] = 0;
        }

        // Update staff record
        $staff->update($validated);

        return redirect()->route('staff.index')
            ->with('success', 'Staff member updated successfully!');
    }

    /**
     * Delete a staff member
     */
    public function destroy($id)
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Check permission
        if (!$this->hasPermission('staff', 'edit')) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to delete staff members.');
        }
        
        $ownerId = $this->getOwnerId();
        $staffQuery = Staff::where('user_id', $ownerId);
        if ($this->isSuperAdminRole()) {
            $staffQuery = Staff::query();
        }
        $staff = $staffQuery->findOrFail($id);

        // Delete associated files
        if ($staff->nida_attachment) {
            Storage::disk('public')->delete($staff->nida_attachment);
        }
        if ($staff->voter_id_attachment) {
            Storage::disk('public')->delete($staff->voter_id_attachment);
        }
        if ($staff->professional_certificate_attachment) {
            Storage::disk('public')->delete($staff->professional_certificate_attachment);
        }

        $staff->delete();

        return redirect()->route('staff.index')
            ->with('success', 'Staff member deleted successfully!');
    }

    /**
     * Send SMS with staff credentials
     */
    private function sendStaffCredentialsSms($staff, $password, $pin)
    {
        $roleName = $staff->role ? $staff->role->name : 'Staff';
        $isWaiter = stripos($roleName, 'waiter') !== false;

        if ($isWaiter) {
            // Waiters: PIN only — no dashboard access needed
            $message  = "Welcome to MEDALLION RESTAURANT!\n\n";
            $message .= "Your staff account has been created.\n\n";
            $message .= "YOUR ACCOUNT DETAILS:\n";
            $message .= "Name: " . $staff->full_name . "\n";
            $message .= "Staff ID: " . $staff->staff_id . "\n";
            $message .= "Role: " . $roleName . "\n";
            $message .= "Kiosk PIN: " . $pin . "\n\n";
            $message .= "Use your PIN to log in at the POS Kiosk to take orders.\n\n";
            $message .= "Thank you!";
        } else {
            // All other staff: Full credentials (email + password) — no PIN needed
            $message  = "Welcome to MEDALLION RESTAURANT!\n\n";
            $message .= "Your staff account has been created.\n\n";
            $message .= "YOUR ACCOUNT DETAILS:\n";
            $message .= "Name: " . $staff->full_name . "\n";
            $message .= "Staff ID: " . $staff->staff_id . "\n";
            $message .= "Role: " . $roleName . "\n";
            $message .= "Email: " . $staff->email . "\n";
            $message .= "Password: " . $password . "\n\n";
            $message .= "Please log in using the credentials above.\n";
            $message .= "For security, you will be required to change your password on first login.\n\n";
            $message .= "Thank you!";
        }

        $this->smsService->sendSms($staff->phone_number, $message);
    }

    /**
     * Repair missing Super Admin role and deduplicate existing active roles
     */
    private function repairAndDeduplicateRoles()
    {
        $ownerId = $this->getOwnerId();
        if (!$ownerId) return;

        // 1. Ensure Super Admin role exists
        $superAdmin = \App\Models\Role::where(function($q) {
                $q->where('name', 'LIKE', 'Super Admin%')
                  ->orWhere('name', 'LIKE', 'SuperAdmin%')
                  ->orWhere('slug', 'LIKE', 'super-admin%')
                  ->orWhere('slug', 'LIKE', 'superadmin%')
                  ->orWhere('slug', '=', 'admin');
            })
            ->where('is_active', true)
            ->first();

        if (!$superAdmin) {
            \Log::info('REPAIR: Creating missing Super Admin role for owner ' . $ownerId);
            $superAdmin = \App\Models\Role::create([
                'user_id' => $ownerId,
                'name' => 'Super Admin',
                'slug' => 'super-admin-' . $ownerId . '-' . time(),
                'description' => 'Full system administrator with the highest level of access.',
                'is_system_role' => false,
                'is_active' => true,
            ]);
            
            // Assign some base permissions if possible
            $managerSuggestion = \App\Services\RoleSuggestionService::getSuggestedRolesForBusinessType('restaurant')[0] ?? null;
            if ($managerSuggestion) {
                $permissionIds = \App\Services\RoleSuggestionService::getPermissionIdsForRole($managerSuggestion);
                if (!empty($permissionIds)) {
                    $superAdmin->permissions()->sync($permissionIds);
                }
            }
        }

        // 2. Deduplicate roles (Keep the oldest one for each name, deactivate others)
        $roles = \App\Models\Role::where('user_id', $ownerId)
            ->where('is_active', true)
            ->whereNotIn('id', [$superAdmin->id]) // Don't deactivate the Super Admin we just ensured
            ->orderBy('id', 'asc')
            ->get();

        $seenNames = [];
        foreach ($roles as $role) {
            $nameLower = strtolower(trim($role->name));
            if (isset($seenNames[$nameLower])) {
                \Log::info('DEDUPLICATION: Deactivating duplicate role ID ' . $role->id . ' (' . $role->name . ')');
                $role->update(['is_active' => false]);
            } else {
                $seenNames[$nameLower] = $role->id;
            }
        }
    }

    /**
     * Bulk generate unique PINs for all staff members who don't have one.
     */
    public function generateMissingPins()
    {
        $user = $this->getCurrentUser();
        if (!$user || !$this->hasPermission('staff', 'edit')) {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        $ownerId = $this->getOwnerId();
        
        // Find staff with NULL pins belonging to this owner
        $staffWithoutPin = Staff::where('user_id', $ownerId)
            ->where(function($q) {
                $q->whereNull('pin')->orWhere('pin', '');
            })
            ->get();

        $count = 0;
        foreach ($staffWithoutPin as $staff) {
            $staff->update(['pin' => Staff::generatePin()]);
            $count++;
        }

        return redirect()->route('staff.index')
            ->with('success', "Success! Generated secondary Kiosk PINs for {$count} staff members. They can now use the Attendance Kiosk.");
    }

    /**
     * Toggle staff active status
     */
    public function toggleStatus($id)
    {
        $user = $this->getCurrentUser();
        if (!$user || !$this->hasPermission('staff', 'edit')) {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        $ownerId = $this->getOwnerId();
        $staffQuery = Staff::where('user_id', $ownerId);
        if ($this->isSuperAdminRole()) {
            $staffQuery = Staff::query();
        }
        $staff = $staffQuery->findOrFail($id);

        $staff->is_active = !$staff->is_active;
        $staff->save();

        $status = $staff->is_active ? 'activated' : 'deactivated';
        
        // Send SMS notification
        $message = "Hello {$staff->full_name}, your account on MEDALLION RESTAURANT has been {$status} by the administrator.";
        if ($staff->is_active) {
            $message .= " You can now log in to your account.";
        } else {
            $message .= " Please contact your manager if you think this is a mistake.";
        }
        
        try {
            $this->smsService->sendSms($staff->phone_number, $message);
        } catch (\Exception $e) {
            \Log::error("Failed to send status toggle SMS to staff {$staff->id}: " . $e->getMessage());
        }

        return redirect()->back()->with('success', "Staff member {$status} successfully and notified via SMS!");
    }
}
