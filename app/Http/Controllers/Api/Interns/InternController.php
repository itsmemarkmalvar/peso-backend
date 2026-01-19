<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement list interns
        return $this->success([], 'Interns list');
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Implement create intern
        return $this->success(null, 'Intern created');
    }

    public function show(int $id): JsonResponse
    {
        // TODO: Implement show intern
        return $this->success(null, 'Intern details');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: Implement update intern
        return $this->success(null, 'Intern updated');
    }

    public function destroy(int $id): JsonResponse
    {
        // TODO: Implement delete intern
        return $this->success(null, 'Intern deleted');
    }
}
