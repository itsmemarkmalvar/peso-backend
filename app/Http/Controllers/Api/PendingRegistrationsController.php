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
     */
    public function index(Request $request): JsonResponse
    {
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
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $pending = PendingRegistration::findOrFail($id);

        if ($pending->status !== 'pending') {
            return $this->error('This registration has already been processed.', 422);
        }

        // Check if user already exists
        if (User::where('email', $pending->email)->exists()) {
            $pending->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
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
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->success([
            'user' => $user,
            'temp_password' => $tempPassword, // In production, send via email instead
        ], 'Registration approved. User account created successfully.');
    }

    /**
     * Reject a pending registration
     */
    public function reject(Request $request, int $id): JsonResponse
    {
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
     */
    public function show(int $id): JsonResponse
    {
        $pending = PendingRegistration::with('approver')->findOrFail($id);
        return $this->success($pending);
    }
}
