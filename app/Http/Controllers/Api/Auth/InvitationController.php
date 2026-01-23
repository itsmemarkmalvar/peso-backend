<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationController extends BaseController
{
    /**
     * Verify invitation token
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::where('invitation_token', $validated['token'])
            ->whereNull('invitation_accepted_at')
            ->first();

        if (!$user) {
            return $this->error('Invalid or expired invitation token.', 404);
        }

        // Check if invitation is expired (7 days)
        if ($user->invitation_sent_at) {
            $expiryDate = $user->invitation_sent_at->copy()->addDays(7);
            if ($expiryDate->isPast()) {
                return $this->error('This invitation has expired. Please contact the administrator.', 422);
            }
        }

        return $this->success([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ], 'Invitation token is valid.');
    }

    /**
     * Accept invitation and set password
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('invitation_token', $validated['token'])
            ->whereNull('invitation_accepted_at')
            ->first();

        if (!$user) {
            return $this->error('Invalid or expired invitation token.', 404);
        }

        // Check if invitation is expired (7 days)
        if ($user->invitation_sent_at) {
            $expiryDate = $user->invitation_sent_at->copy()->addDays(7);
            if ($expiryDate->isPast()) {
                return $this->error('This invitation has expired. Please contact the administrator.', 422);
            }
        }

        // Update user password and mark invitation as accepted
        $user->update([
            'password' => $validated['password'],
            'status' => 'active',
            'invitation_accepted_at' => now(),
            'invitation_token' => null, // Clear token after acceptance
        ]);

        // Generate authentication token
        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Invitation accepted. Account activated successfully.');
    }
}
