<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseController;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PendingRegistrationsController extends BaseController
{
    /**
     * List all pending registrations
     * Admin and supervisor only
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can list pending registrations.');
        }

        $status = $request->query('status', 'pending');
        
        $query = PendingRegistration::with('approver')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $registrations = $query->get();

        return $this->success($registrations);
    }

    /**
     * Approve a pending registration and create user account
     * Admin and supervisor only. Never returns temp_password in response.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can approve registrations.');
        }

        $pending = PendingRegistration::findOrFail($id);

        if ($pending->status !== 'pending') {
            return $this->error('This registration has already been processed.', 422);
        }

        // Check if user already exists
        if (User::where('email', $pending->email)->exists()) {
            $pending->update([
                'status' => 'rejected',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => 'Email already exists in system',
            ]);
            return $this->error('A user with this email already exists.', 422);
        }

        // Generate username from email
        $baseUsername = Str::of($pending->email)->before('@')->lower()->replaceMatches('/[^a-z0-9_\.]/', '');
        $username = (string) $baseUsername;
        if ($username === '') {
            $username = 'intern';
        }

        // Ensure username uniqueness
        $candidate = $username;
        $suffix = 0;
        while (User::where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $username.$suffix;
        }

        // Generate a temporary password (user will need to reset it)
        $tempPassword = Str::random(12);

        // Create user account
        $user = User::create([
            'name' => $pending->name,
            'username' => $candidate,
            'email' => $pending->email,
            'password' => $tempPassword, // Will be hashed via User model cast
            'role' => UserRole::INTERN,
            'status' => 'active',
        ]);

        // Update pending registration
        $pending->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return $this->success([
            'user' => $user,
        ], 'Registration approved. User account created successfully. Send credentials via secure channel.');
    }

    /**
     * Reject a pending registration
     * Admin and supervisor only
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can reject registrations.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $pending = PendingRegistration::findOrFail($id);

        if ($pending->status !== 'pending') {
            return $this->error('This registration has already been processed.', 422);
        }

        $pending->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['reason'] ?? 'Registration rejected by administrator',
        ]);

        return $this->success($pending, 'Registration rejected successfully.');
    }

    /**
     * Get a single pending registration
     * Admin and supervisor only
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view pending registrations.');
        }

        $pending = PendingRegistration::with('approver')->findOrFail($id);
        return $this->success($pending);
    }
}
