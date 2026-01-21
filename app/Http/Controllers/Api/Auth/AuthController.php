<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Models\PendingRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    /**
     * Register user - creates pending registration (requires admin approval)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email|unique:pending_registrations,email',
        ]);

        // Check if email already exists in pending registrations
        $existingPending = PendingRegistration::where('email', $validated['email'])
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return $this->error('A registration request with this email is already pending approval.', 422);
        }

        $pendingRegistration = PendingRegistration::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => 'pending',
        ]);

        return $this->success([
            'pending_registration' => $pendingRegistration,
        ], 'Registration request submitted successfully. Please wait for admin approval.', 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (isset($user->status) && $user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active. Please contact the administrator.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout successful');
    }
}
