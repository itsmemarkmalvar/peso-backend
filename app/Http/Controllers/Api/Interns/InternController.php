<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Intern::query()
            ->with('user')
            ->orderBy('full_name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $interns = $query
            ->limit(200)
            ->get()
            ->map(function (Intern $intern) {
                return [
                    'id' => $intern->id,
                    'user_id' => $intern->user_id,
                    'name' => $intern->full_name,
                    'email' => optional($intern->user)->email,
                    'student_id' => $intern->student_id,
                    'course' => $intern->course,
                    'year_level' => $intern->year_level,
                    'company_name' => $intern->company_name,
                    'supervisor_name' => $intern->supervisor_name,
                    'is_active' => (bool) $intern->is_active,
                ];
            });

        return $this->success($interns, 'Interns list');
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
