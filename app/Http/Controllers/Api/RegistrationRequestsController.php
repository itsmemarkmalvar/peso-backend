<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseController;
use App\Mail\InvitationMail;
use App\Models\Intern;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationRequestsController extends BaseController
{
    /**
     * List all registration requests
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status', 'pending');

            $query = RegistrationRequest::with('approver')
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $requests = $query->get();

            return $this->success($requests);
        } catch (QueryException $e) {
            Log::error('Registration requests index failed: ' . $e->getMessage());
            $message = $this->getDatabaseErrorMessage($e);
            return $this->error($message, 503);
        } catch (\Throwable $e) {
            Log::error('Registration requests index failed: ' . $e->getMessage());
            return $this->error('Failed to load registration requests: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single registration request
     */
    public function show(int $id): JsonResponse
    {
        try {
            $request = RegistrationRequest::with('approver')->findOrFail($id);
            return $this->success($request);
        } catch (QueryException $e) {
            Log::error('Registration request show failed: ' . $e->getMessage());
            return $this->error($this->getDatabaseErrorMessage($e), 503);
        }
    }

    /**
     * List users created via registration approval (invitation sent).
     * Query: status=pending|active|all (default: all). pending = not yet accepted invitation; active = accepted.
     */
    public function approvedUsers(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status', 'all');
            $query = User::whereNotNull('invitation_sent_at')
                ->orderByDesc('invitation_sent_at');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $users = $query->get()->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'username' => $u->username,
                    'role' => $u->role->value,
                    'status' => $u->status,
                    'invitation_sent_at' => optional($u->invitation_sent_at)->toISOString(),
                    'invitation_accepted_at' => optional($u->invitation_accepted_at)->toISOString(),
                ];
            });

            return $this->success($users);
        } catch (QueryException $e) {
            Log::error('Approved users list failed: ' . $e->getMessage());
            return $this->error($this->getDatabaseErrorMessage($e), 503);
        } catch (\Throwable $e) {
            Log::error('Approved users list failed: ' . $e->getMessage());
            return $this->error('Failed to load approved users.', 500);
        }
    }

    /**
     * Return a user-friendly message for common DB errors (e.g. missing table)
     */
    private function getDatabaseErrorMessage(QueryException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table or view not found')) {
            return 'Registration requests table is missing or wrong DB. Run: php artisan migrate. Confirm .env DB_HOST/DB_DATABASE match the DB you see in phpMyAdmin.';
        }
        if (str_contains($msg, 'Column not found') || str_contains($msg, 'Unknown column')) {
            return 'registration_requests table is missing columns. In phpMyAdmin check Structure has: approved_by, approved_at, rejected_at, rejection_reason. Detail: ' . (config('app.debug') ? $msg : 'enable APP_DEBUG to see.');
        }
        return config('app.debug') ? $msg : 'A database error occurred. Check storage/logs/laravel.log';
    }

    /**
     * Approve a registration request and create user account
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
            'role' => 'required|string|in:admin,supervisor,gip,intern',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $actor = $request->user();
        $requestedRole = $validated['role'];
        if ($actor && $actor->isSupervisor()) {
            $requestedRole = 'intern';
        }

        $registrationRequest = RegistrationRequest::findOrFail($id);

        if ($registrationRequest->status !== 'pending') {
            return $this->error('This registration request has already been processed.', 422);
        }

        // Check if user already exists
        if (User::where('email', $registrationRequest->email)->exists()) {
            $registrationRequest->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_reason' => 'Email already exists in system',
            ]);
            return $this->error('A user with this email already exists.', 422);
        }

        // Validate department is required for intern and GIP roles
        if (in_array($requestedRole, ['intern', 'gip']) && !$validated['department_id']) {
            return $this->error('Department is required for ' . $requestedRole . ' role.', 422);
        }

        // Generate username from email
        $baseUsername = Str::of($registrationRequest->email)->before('@')->lower()->replaceMatches('/[^a-z0-9_\.]/', '');
        $username = (string) $baseUsername;
        if ($username === '') {
            $username = $requestedRole;
        }

        // Ensure username uniqueness
        $candidate = $username;
        $suffix = 0;
        while (User::where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $username.$suffix;
        }

        // Generate invitation token
        $invitationToken = Str::random(64);

        // Map role string to UserRole enum
        $role = match($requestedRole) {
            'admin' => UserRole::ADMIN,
            'supervisor' => UserRole::SUPERVISOR,
            'gip' => UserRole::GIP,
            'intern' => UserRole::INTERN,
            default => UserRole::INTERN,
        };

        // Create user account (without password - they'll set it via invitation)
        $user = User::create([
            'name' => $registrationRequest->full_name,
            'username' => $candidate,
            'email' => $registrationRequest->email,
            'password' => Str::random(32), // Temporary password, will be changed
            'role' => $role,
            'status' => 'pending', // Set to pending until invitation is accepted
            'invitation_token' => $invitationToken,
            'invitation_sent_at' => now(),
        ]);

        // Update registration request
        $registrationRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Create Intern record for intern/gip so they appear in People and can complete onboarding later
        if (in_array($requestedRole, ['intern', 'gip'])) {
            Intern::create([
                'user_id' => $user->id,
                'full_name' => $registrationRequest->full_name,
                'department_id' => $validated['department_id'],
                'school' => 'Pending',
                'course' => 'Pending',
                'phone' => 'Pending',
                'emergency_contact_name' => 'Pending',
                'emergency_contact_phone' => 'Pending',
                'is_active' => true,
            ]);
        }

        // Generate invitation URL
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $invitationUrl = "{$frontendUrl}/invitation/accept?token={$invitationToken}";

        // Send invitation email immediately so the recipient actually gets it. (Previously we queued it;
        // if no queue worker was running, no email was ever sent and nothing appeared in the sender's Sent folder.)
        $invitationSent = false;
        try {
            Mail::to($user->email)->send(new InvitationMail($user->id, $invitationUrl, $requestedRole));
            $invitationSent = true;
        } catch (\Exception $e) {
            Log::error('Invitation email send failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }

        return $this->success([
            'user' => $user,
            'role' => $requestedRole,
            'department_id' => $validated['department_id'],
            'invitation_sent' => $invitationSent,
        ], $invitationSent
            ? 'Registration request approved. Invitation email has been sent.'
            : 'Registration request approved, but the invitation email could not be sent. Check storage/logs/laravel.log and MAIL_* in .env.');
    } catch (ValidationException $e) {
        return $this->validationError($e->errors(), $e->getMessage());
    } catch (QueryException $e) {
        Log::error('Registration request approve failed: ' . $e->getMessage());
        return $this->error($this->getDatabaseErrorMessage($e), 503);
    } catch (\Throwable $e) {
        Log::error('Registration request approve failed: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        $message = config('app.debug')
            ? 'Failed to approve: ' . $e->getMessage()
            : 'Failed to approve. Set APP_DEBUG=true in .env to see the error, or check storage/logs/laravel.log';
        return $this->error($message, 500);
    }
    }

    /**
     * Reject a registration request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $registrationRequest = RegistrationRequest::findOrFail($id);

            if ($registrationRequest->status !== 'pending') {
                return $this->error('This registration request has already been processed.', 422);
            }

            $registrationRequest->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_reason' => $validated['reason'] ?? 'Registration request rejected by administrator',
            ]);

            return $this->success($registrationRequest, 'Registration request rejected successfully.');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), $e->getMessage());
        } catch (QueryException $e) {
            Log::error('Registration request reject failed: ' . $e->getMessage());
            return $this->error($this->getDatabaseErrorMessage($e), 503);
        } catch (\Throwable $e) {
            Log::error('Registration request reject failed: ' . $e->getMessage());
            return $this->error('Failed to reject: ' . $e->getMessage(), 500);
        }
    }
}
