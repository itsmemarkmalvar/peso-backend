<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseController;
use App\Mail\InvitationMail;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $status = $request->query('status', 'pending');
        
        $query = RegistrationRequest::with('approver')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->get();

        return $this->success($requests);
    }

    /**
     * Get a single registration request
     */
    public function show(int $id): JsonResponse
    {
        $request = RegistrationRequest::with('approver')->findOrFail($id);
        return $this->success($request);
    }

    /**
     * Approve a registration request and create user account
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:admin,coordinator,intern',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

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

        // Validate department is required for intern role
        if ($validated['role'] === 'intern' && !$validated['department_id']) {
            return $this->error('Department is required for intern role.', 422);
        }

        // Generate username from email
        $baseUsername = Str::of($registrationRequest->email)->before('@')->lower()->replaceMatches('/[^a-z0-9_\.]/', '');
        $username = (string) $baseUsername;
        if ($username === '') {
            $username = $validated['role'];
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
        $role = match($validated['role']) {
            'admin' => UserRole::ADMIN,
            'coordinator' => UserRole::COORDINATOR,
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

        // Generate invitation URL
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $invitationUrl = "{$frontendUrl}/invitation/accept?token={$invitationToken}";

        // Send invitation email
        try {
            Mail::to($user->email)->send(new InvitationMail($user, $invitationUrl, $validated['role']));
        } catch (\Exception $e) {
            // Log error but don't fail the approval
            \Log::error('Failed to send invitation email: ' . $e->getMessage());
        }

        return $this->success([
            'user' => $user,
            'role' => $validated['role'],
            'department_id' => $validated['department_id'],
            'invitation_sent' => true,
        ], 'Registration request approved. Invitation email sent successfully.');
    }

    /**
     * Reject a registration request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
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
    }
}
