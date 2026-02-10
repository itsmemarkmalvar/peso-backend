<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use App\Models\SchoolSchedule;
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
            'weekly_availability' => $intern->weekly_availability,
            'supervisor_user_id' => $intern->supervisor_user_id,
            'supervisor_name' => $intern->supervisor_name,
            'supervisor_email' => $intern->supervisor_email,
            'supervisor_contact' => $intern->supervisor_contact,
            'supervisor' => $intern->relationLoaded('supervisor') && $intern->supervisor
                ? [
                    'id' => $intern->supervisor->id,
                    'name' => $intern->supervisor->name,
                    'email' => $intern->supervisor->email,
                ]
                : null,
            'start_date' => optional($intern->start_date)->toDateString(),
            'end_date' => optional($intern->end_date)->toDateString(),
            'onboarded_at' => optional($intern->onboarded_at)->toISOString(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Intern::query()
            ->with(['user', 'supervisor', 'department'])
            ->orderBy('full_name');

        if ($user && $user->isSupervisor()) {
            $query->where('supervisor_user_id', $user->id);
        } elseif ($request->filled('supervisor_user_id')) {
            $query->where('supervisor_user_id', $request->input('supervisor_user_id'));
        }

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
                $user = $intern->user;
                $role = $user && $user->role instanceof \App\Enums\UserRole
                    ? $user->role->value
                    : (is_string(optional($user)->role) ? (string) $user->role : 'intern');

                return [
                    'id' => $intern->id,
                    'user_id' => $intern->user_id,
                    'name' => $intern->full_name,
                    'email' => optional($intern->user)->email,
                    'student_id' => $intern->student_id,
                    'course' => $intern->course,
                    'year_level' => $intern->year_level,
                    'company_name' => $intern->company_name,
                    'department_id' => $intern->department_id,
                    'department_name' => $intern->department?->name,
                    'supervisor_name' => $intern->supervisor_name,
                    'supervisor_user_id' => $intern->supervisor_user_id,
                    'supervisor_email' => $intern->supervisor?->email ?? $intern->supervisor_email,
                    'required_hours' => $intern->required_hours === null
                        ? null
                        : (int) $intern->required_hours,
                    'is_active' => (bool) $intern->is_active,
                    'role' => $role,
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
        $intern = Intern::with('supervisor')->where('user_id', $user->id)->first();

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
            'weekly_availability' => 'required|array',
            'weekly_availability.monday' => 'required|string|in:available,not_available,full_day,half_day',
            'weekly_availability.tuesday' => 'required|string|in:available,not_available,full_day,half_day',
            'weekly_availability.wednesday' => 'required|string|in:available,not_available,full_day,half_day',
            'weekly_availability.thursday' => 'required|string|in:available,not_available,full_day,half_day',
            'weekly_availability.friday' => 'required|string|in:available,not_available,full_day,half_day',
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
                'weekly_availability' => $validated['weekly_availability'],
                'onboarded_at' => now(),
                'is_active' => true,
            ]
        );

        // Sync school_schedules from weekly_availability for "Excused due to school schedule"
        // day_of_week: 0=Sunday, 1=Monday, ..., 5=Friday, 6=Saturday
        $weekdayToDayOfWeek = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
        ];
        $schoolDays = [];
        foreach ($weekdayToDayOfWeek as $dayKey => $dayOfWeek) {
            $value = $validated['weekly_availability'][$dayKey] ?? 'available';
            if ($value === 'not_available' || $value === 'half_day') {
                $schoolDays[] = $dayOfWeek;
            }
        }
        SchoolSchedule::where('intern_id', $intern->id)->whereBetween('day_of_week', [1, 5])->delete();
        foreach ($schoolDays as $dayOfWeek) {
            SchoolSchedule::create([
                'intern_id' => $intern->id,
                'day_of_week' => $dayOfWeek,
                'is_active' => true,
            ]);
        }

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
