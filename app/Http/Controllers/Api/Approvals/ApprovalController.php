<?php

namespace App\Http\Controllers\Api\Approvals;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement list approvals
        return $this->success([], 'Approvals list');
    }

    public function pending(Request $request): JsonResponse
    {
        // TODO: Implement pending approvals
        return $this->success([], 'Pending approvals');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        // TODO: Implement approve attendance
        return $this->success(null, 'Attendance approved');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        // TODO: Implement reject attendance
        return $this->success(null, 'Attendance rejected');
    }
}
