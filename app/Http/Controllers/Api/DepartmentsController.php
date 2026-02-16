<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentsController extends BaseController
{
    /**
     * List all active departments
     */
    public function index(Request $request): JsonResponse
    {
        $departments = \App\Models\Department::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return $this->success($departments);
    }

    /**
     * List supervisors assigned to a department.
     * Used by New Users approval flow to auto-fill supervisor for intern/GIP.
     */
    public function supervisors(Request $request, int $id): JsonResponse
    {
        $supervisors = User::where('department_id', $id)
            ->where('role', UserRole::SUPERVISOR)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return $this->success($supervisors);
    }
}
