<?php

namespace App\Http\Controllers\Api;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends BaseController
{
    /**
     * Get system settings.
     * Any authenticated user can read (so interns/GIP receive rules that apply to them).
     */
    public function index(Request $request): JsonResponse
    {
        $settings = SystemSetting::get();
        return $this->success([
            'grace_period_minutes' => $settings->grace_period_minutes,
            'verification_gps' => $settings->verification_gps,
            'verification_selfie' => $settings->verification_selfie,
        ], 'Settings retrieved successfully');
    }

    /**
     * Update system settings.
     * Admin only (RBAC: only admin can configure system).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return $this->forbidden('Only administrators can update system settings.');
        }

        $validator = Validator::make($request->all(), [
            'grace_period_minutes' => 'sometimes|integer|min:0|max:120',
            'minimum_overtime_minutes' => 'sometimes|integer|min:0|max:480',
            'verification_gps' => 'sometimes|boolean',
            'verification_selfie' => 'sometimes|boolean',
            'verification_qr' => 'sometimes|boolean',
            'verification_device_fingerprint' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $settings = SystemSetting::get();
        $settings->update($validator->validated());

        return $this->success([
            'grace_period_minutes' => $settings->grace_period_minutes,
            'verification_gps' => $settings->verification_gps,
            'verification_selfie' => $settings->verification_selfie,
        ], 'Settings updated successfully');
    }
}
