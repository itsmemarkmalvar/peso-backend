<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentsController extends BaseController
{
    /**
     * List all active departments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::where('is_active', true)
            ->orderBy('name', 'asc');

        $departments = $query->get();

        return $this->success($departments);
    }
}
