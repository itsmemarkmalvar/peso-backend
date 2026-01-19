<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function stats(Request $request): JsonResponse
    {
        // TODO: Implement dashboard statistics
        return $this->success([
            'total_interns' => 0,
            'active_today' => 0,
            'pending_approvals' => 0,
            'attendance_rate' => 0,
        ], 'Dashboard statistics');
    }

    public function recentActivity(Request $request): JsonResponse
    {
        // TODO: Implement recent activity
        return $this->success([], 'Recent activity');
    }
}
