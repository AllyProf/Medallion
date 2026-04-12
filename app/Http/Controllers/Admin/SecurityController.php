<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\HandoverSmsService;

class SecurityController extends Controller
{
    protected $sms;

    public function __construct()
    {
        $this->sms = new HandoverSmsService();
    }

    /**
     * Show active sessions (users & staff logged in recently)
     */
    public function activeSessions()
    {
        // Get all active database sessions
        $sessions = DB::table('sessions')
            ->orderByDesc('last_activity')
            ->limit(100)
            ->get()
            ->map(function ($session) {
                $payload = @unserialize(base64_decode($session->payload));
                $userId   = $payload['login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d'] ?? null;
                $isStaff  = isset($payload['is_staff']) && $payload['is_staff'];
                $staffId  = $payload['staff_id'] ?? null;

                $user  = $userId  ? User::find($userId)  : null;
                $staff = $staffId ? Staff::with('role')->find($staffId) : null;

                // Simple IP Location with caching
                $location = 'Unknown';
                if ($session->ip_address && $session->ip_address !== '127.0.0.1' && $session->ip_address !== '::1') {
                    $location = Cache::remember('ip_loc_' . $session->ip_address, now()->addDay(), function () use ($session) {
                        try {
                            $response = Http::timeout(2)->get("http://ip-api.com/json/{$session->ip_address}?fields=status,message,country,city");
                            if ($response->successful()) {
                                $data = $response->json();
                                if ($data['status'] === 'success') {
                                    return $data['city'] . ', ' . $data['country'];
                                }
                            }
                        } catch (\Exception $e) {}
                        return null; // Don't cache failures permanently
                    });
                }

                return (object)[
                    'id'            => $session->id,
                    'ip_address'    => $session->ip_address,
                    'location'      => $location ?? 'Unknown',
                    'user_agent'    => $session->user_agent,
                    'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity),
                    'is_staff'      => $isStaff,
                    'user'          => $user,
                    'staff'         => $staff,
                    'user_label'    => $staff
                        ? ($staff->full_name . ' (' . ($staff->role->name ?? 'Staff') . ')')
                        : ($user ? $user->name . ' (' . ucfirst($user->role) . ')' : 'Guest'),
                ];
            });

        return view('admin.security.active-sessions', compact('sessions'));
    }

    /**
     * Revoke / invalidate a session
     */
    public function revokeSession(Request $request, $sessionId)
    {
        DB::table('sessions')->where('id', $sessionId)->delete();
        return back()->with('success', 'Session revoked successfully.');
    }

    /**
     * Show activity / error logs — parsed into structured entries
     */
    public function logs(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');
        $entries = [];

        if (file_exists($logPath)) {
            $allLines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $allLines = array_reverse($allLines); // newest first

            $currentEntry = null;

            foreach ($allLines as $line) {
                // Try to match a new log entry header: [DATETIME] channel.LEVEL: message {context}
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                    if ($currentEntry) {
                        // Translate before saving
                        $decoded = $currentEntry->_raw_context ? json_decode($currentEntry->_raw_context, true) : [];
                        $friendly = $this->translateMessage($currentEntry->_raw_message, $currentEntry->level, $decoded ?? []);
                        if ($friendly !== null) {
                            $currentEntry->message = $friendly;
                            $entries[] = $currentEntry;
                        }
                    }

                    $context = '';
                    $rawContext = null;
                    $message = $m[4];

                    // Separate JSON context from message
                    if (preg_match('/^(.*?)\s*(\{.*\})\s*$/', $message, $cm)) {
                        $message = trim($cm[1]);
                        $rawJson = $cm[2];
                        $decoded = json_decode($rawJson, true);
                        $rawContext = $rawJson;
                        $context = $decoded
                            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : $rawJson;
                    }

                    $currentEntry = (object)[
                        'datetime'      => $m[1],
                        'channel'       => $m[2],
                        'level'         => strtoupper($m[3]),
                        'message'       => $message,
                        '_raw_message'  => $message,
                        '_raw_context'  => $rawContext,
                        'context'       => $context,
                        'trace'         => [],
                    ];
                } elseif ($currentEntry) {
                    // Additional lines (stack trace) belong to current entry
                    $currentEntry->trace[] = $line;
                }
            }

            if ($currentEntry) {
                $decoded = $currentEntry->_raw_context ? json_decode($currentEntry->_raw_context, true) : [];
                $friendly = $this->translateMessage($currentEntry->_raw_message, $currentEntry->level, $decoded ?? []);
                if ($friendly !== null) {
                    $currentEntry->message = $friendly;
                    $entries[] = $currentEntry;
                }
            }

        }

        // Limit to 500 entries max
        $entries = array_slice($entries, 0, 500);

        $levels = ['INFO', 'WARNING', 'ERROR', 'DEBUG', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        return view('admin.security.logs', compact('entries', 'levels'));
    }


    /**
     * Translate a raw log message into plain, human-friendly language.
     * Also returns null to suppress noisy/internal messages.
     */
    private function translateMessage(string $raw, string $level, ?array $context = []): ?string
    {
        // --- Suppress noisy internal messages ---
        $skipPatterns = [
            'hasPermission called',
            'Checking role for',
            'Permission check result',
            'Owner or Admin user detected - granting all permissions',
            'Manager/Accountant role detected - granting all permissions',
        ];
        foreach ($skipPatterns as $pattern) {
            if (str_contains($raw, $pattern)) {
                return null; // suppress
            }
        }

        // --- Friendly translations ---
        $email = $context['email'] ?? null;
        $who   = $email ? " (<strong>{$email}</strong>)" : '';

        $map = [
            'Login attempt'                        => "🔐 Login attempt{$who}",
            'User authentication successful'       => "✅ User logged in successfully{$who}",
            'User authentication failed, checking staff' => "🔍 User not found, checking staff accounts{$who}",
            'Staff not found'                      => "❌ No staff account found for{$who}",
            'Staff password correct, proceeding with login' => "🔑 Password verified for staff{$who}",
            'Staff session created successfully'   => "✅ Staff logged in successfully",
            'Staff found'                          => "👤 Staff account located",
            'Password check result'                => null, // suppress
            'Staff password incorrect'             => "❌ Wrong password for staff{$who}",
            'Staff account inactive'               => "🚫 Login blocked — staff account is inactive",
            'Session mismatch'                     => "⚠️ Session mismatch detected — user was logged out",
            'Admin reset password'                 => "🔑 Admin reset a user password",
            'Admin force-logged-out'               => "🚪 Admin force-logged out a user",
            'User found in BusinessConfigurationController' => "🏢 Business configuration page accessed",
            'BusinessConfigurationController'      => null, // suppress
        ];

        foreach ($map as $key => $friendly) {
            if (str_contains($raw, $key)) {
                return $friendly;
            }
        }

        // --- Database errors ---
        if (str_contains($raw, 'SQLSTATE')) {
            if (str_contains($raw, 'Table') && str_contains($raw, "doesn't exist")) {
                preg_match("/Table '[\w.]+\.([\w]+)' doesn't exist/", $raw, $m);
                $table = $m[1] ?? 'unknown';
                return "🗄️ Database table missing: <code>{$table}</code> — contact your developer";
            }
            if (str_contains($raw, 'Column not found')) {
                return "🗄️ Database column error — contact your developer";
            }
            return "🗄️ Database error occurred — contact your developer";
        }

        // --- PHP / Parse errors (tinker stuff) ---
        if (str_contains($raw, 'PHP Parse error') || str_contains($raw, 'Unexpected end of input') || str_contains($raw, 'psysh')) {
            return null; // suppress developer console errors
        }

        // --- SMS & Transfer ---
        if (str_contains($raw, 'SMS sent')) {
            return "💬 SMS notification sent — " . $raw;
        }
        if (str_contains($raw, 'Failed to send') && str_contains($raw, 'SMS')) {
            return "⚠️ SMS notification failed to send";
        }
        if (str_contains($raw, 'Transfer')) {
            return "📦 " . $raw;
        }
        if (str_contains($raw, 'Kiosk')) {
            return "🖥️ Kiosk event: " . $raw;
        }

        // Return raw for anything else
        return $raw;
    }


    /**
     * Show user & staff account management (for password resets)
     */
    public function userAccounts()
    {
        $users = User::where('role', 'customer')->orderByDesc('created_at')->get();
        $staff = Staff::with(['role', 'owner'])->orderByDesc('created_at')->get();
        return view('admin.security.user-accounts', compact('users', 'staff'));
    }

    /**
     * Reset a User's password
     */
    public function resetUserPassword(Request $request, User $user)
    {
        $newPassword = Str::random(8);
        $user->update(['password' => Hash::make($newPassword)]);
        
        $smsResult = $this->sms->sendPasswordResetSms($user, $newPassword);
        
        Log::info("Admin reset password for user #{$user->id} ({$user->email}). SMS: " . ($smsResult ? 'Sent' : 'Failed'));
        
        $msg = "Password for {$user->name} has been reset to: <strong>{$newPassword}</strong>";
        if (!$smsResult) {
            $msg .= " <br><span class='text-danger'>Warning: SMS failed to send. Please share the password manually.</span>";
        }
        
        return back()->with('success', $msg);
    }

    /**
     * Reset a Staff member's password
     */
    public function resetStaffPassword(Request $request, Staff $staff)
    {
        $newPassword = Str::random(8);
        $staff->update(['password' => Hash::make($newPassword)]);
        
        $smsResult = $this->sms->sendPasswordResetSms($staff, $newPassword);
        
        Log::info("Admin reset password for staff #{$staff->id} ({$staff->email}). SMS: " . ($smsResult ? 'Sent' : 'Failed'));
        
        $msg = "Password for {$staff->full_name} has been reset to: <strong>{$newPassword}</strong>";
        if (!$smsResult) {
            $msg .= " <br><span class='text-danger'>Warning: SMS failed to send. Please share the password manually.</span>";
        }
        
        return back()->with('success', $msg);
    }

    /**
     * Invalidate all sessions for a user (force logout)
     */
    public function forceLogoutUser(User $user)
    {
        // Delete their database sessions
        DB::table('sessions')
            ->where(function ($q) use ($user) {
                // Match by user_id stored in session payload (loose match via user_id column if indexed)
                $q->where('user_id', $user->id);
            })->delete();

        Log::info("Admin force-logged-out user #{$user->id}");
        return back()->with('success', "{$user->name} has been logged out of all devices.");
    }
}
