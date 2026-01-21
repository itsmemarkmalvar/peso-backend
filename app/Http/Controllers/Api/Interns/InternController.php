<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternController extends BaseController
{
    private function formatInternProfile(Intern $intern): array
    {
        return [
            'id' => $intern->id,
            'user_id' => $intern->user_id,
            'full_name' => $intern->full_name,
            'school' => $intern->school,
            'program' => $intern->course,
            'phone' => $intern->phone,
            'emergency_contact_name' => $intern->emergency_contact_name,
            'emergency_contact_phone' => $intern->emergency_contact_phone,
            'required_hours' => $intern->required_hours === null
                ? null
                : (int) $intern->required_hours,
            'start_date' => optional($intern->start_date)->toDateString(),
            'end_date' => optional($intern->end_date)->toDateString(),
            'onboarded_at' => optional($intern->onboarded_at)->toISOString(),
        ];
    }

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

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) {
            return $this->success(null, 'Intern profile not found');
        }

        return $this->success($this->formatInternProfile($intern), 'Intern profile');
    }

    public function storeProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'school' => 'required|string|max:255',
            'program' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:50',
            'required_hours' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        $intern = Intern::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'full_name' => $validated['full_name'],
                'school' => $validated['school'],
                'course' => $validated['program'],
                'phone' => $validated['phone'],
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
                'required_hours' => $validated['required_hours'],
                'onboarded_at' => now(),
                'is_active' => true,
            ]
        );

        return $this->success(
            $this->formatInternProfile($intern),
            'Intern profile saved',
            201
        );
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
