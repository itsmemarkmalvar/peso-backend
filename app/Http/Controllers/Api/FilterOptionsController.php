<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns roles and groups (departments) for admin/supervisor filter dropdowns.
 * Roles come from UserRole enum; groups are departments from the Department model.
 */
class FilterOptionsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $roles = array_map(
            fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()],
            UserRole::cases()
        );

        $groups = Department::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        return $this->success([
            'roles' => $roles,
            'groups' => $groups,
        ], 'Filter options');
    }
}
