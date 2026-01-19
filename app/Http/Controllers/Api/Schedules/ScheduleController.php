<?php

namespace App\Http\Controllers\Api\Schedules;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement list schedules
        return $this->success([], 'Schedules list');
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Implement create schedule
        return $this->success(null, 'Schedule created');
    }

    public function show(int $id): JsonResponse
    {
        // TODO: Implement show schedule
        return $this->success(null, 'Schedule details');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: Implement update schedule
        return $this->success(null, 'Schedule updated');
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: Implement delete schedule
        return $this->success(null, 'Schedule deleted');
    }

    public function assign(Request $request): JsonResponse
    {
        // TODO: Implement assign schedule
        return $this->success(null, 'Schedule assigned');
    }
}
