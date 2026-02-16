<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use App\Models\SchoolSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InternController extends BaseController
{
    /**
     * Check if the current user can view or modify the given intern.
     * Admin: any. Supervisor: only interns they supervise. Intern/GIP: only their own.
     */
    private function canAccessIntern(Request $request, Intern $intern): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isSupervisor() && (int) $intern->supervisor_user_id === (int) $user->id) {
            return true;
        }
        if ($user->isInternOrGip() && (int) $intern->user_id === (int) $user->id) {
            return true;
        }
        return false;
    }
    private function formatInternProfile(Intern $intern): array
    {
        $profilePhotoUrl = $intern->profile_photo
            ? (str_starts_with($intern->profile_photo, 'http')
                ? $intern->profile_photo
                : '/storage/' . $intern->profile_photo)
            : null;

        return [
            'id' => $intern->id,
            'user_id' => $intern->user_id,
            'full_name' => $intern->full_name,
            'profile_photo' => $profilePhotoUrl,
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

        // Admin and supervisor both see all interns (supervisor restrictions are on actions, not on viewing)
        if ($request->filled('supervisor_user_id')) {
            $query->where('supervisor_user_id', $request->input('supervisor_user_id'));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhereHas('department', function ($deptQuery) use ($search) {
                        $deptQuery->where('name', 'like', "%{$search}%");
                    })
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

                $profilePhotoUrl = $intern->profile_photo
                    ? (str_starts_with($intern->profile_photo, 'http')
                        ? $intern->profile_photo
                        : '/storage/' . $intern->profile_photo)
                    : null;

                return [
                    'id' => $intern->id,
                    'user_id' => $intern->user_id,
                    'name' => $intern->full_name,
                    'email' => optional($intern->user)->email,
                    'student_id' => $intern->student_id,
                    'course' => $intern->course,
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
                    'profile_photo' => $profilePhotoUrl,
                ];
            });

        return $this->success($interns, 'Interns list');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return $this->forbidden('Only administrators can create intern records.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'school' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (Intern::where('user_id', $validated['user_id'])->exists()) {
            return $this->error('User already has an intern profile.', 422);
        }

        $intern = Intern::create([
            'user_id' => $validated['user_id'],
            'full_name' => $validated['full_name'] ?? User::find($validated['user_id'])->name ?? 'Pending',
            'department_id' => $validated['department_id'] ?? null,
            'supervisor_user_id' => $validated['supervisor_user_id'] ?? null,
            'school' => $validated['school'] ?? 'Pending',
            'course' => $validated['course'] ?? 'Pending',
            'phone' => $validated['phone'] ?? 'Pending',
            'emergency_contact_name' => 'Pending',
            'emergency_contact_phone' => 'Pending',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $intern->load(['user', 'supervisor', 'department']);
        return $this->success($this->formatInternProfile($intern), 'Intern created', 201);
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
        // Ensure JSON body is merged (e.g. when proxy doesn't populate input)
        if (! $request->has('full_name') && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $request->merge($decoded);
            }
        }

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
            'profile_photo' => 'nullable|string',
        ]);

        $user = $request->user();

        $profilePhotoPath = null;
        if (! empty($validated['profile_photo'] ?? '')) {
            $profilePhotoPath = $this->saveProfilePhoto($validated['profile_photo']);
        }

        $intern = Intern::firstOrNew(['user_id' => $user->id]);

        if ($profilePhotoPath && $intern->profile_photo) {
            Storage::disk('public')->delete($intern->profile_photo);
        }

        $internData = [
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
            'start_date' => $intern->start_date ?? now()->toDateString(), // Auto-fill on first onboarding completion
        ];
        if ($profilePhotoPath !== null) {
            $internData['profile_photo'] = $profilePhotoPath;
        }
        $intern->fill($internData);
        $intern->save();
        $intern->load('supervisor');

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

    public function show(Request $request, int $id): JsonResponse
    {
        $intern = Intern::with(['supervisor', 'department', 'user'])->find($id);
        if (!$intern) {
            return $this->notFound('Intern not found');
        }
        if (!$this->canAccessIntern($request, $intern)) {
            return $this->forbidden('You do not have permission to view this intern.');
        }
        return $this->success($this->formatInternDetail($intern), 'Intern details');
    }

    /**
     * Full intern detail for admin/supervisor (includes all onboarding fields).
     */
    private function formatInternDetail(Intern $intern): array
    {
        $profilePhotoUrl = $intern->profile_photo
            ? (str_starts_with($intern->profile_photo, 'http')
                ? $intern->profile_photo
                : '/storage/' . $intern->profile_photo)
            : null;

        $user = $intern->user;
        $role = $user && $user->role instanceof \App\Enums\UserRole
            ? $user->role->value
            : (is_string(optional($user)->role) ? (string) $user->role : 'intern');

        return [
            'id' => $intern->id,
            'user_id' => $intern->user_id,
            'full_name' => $intern->full_name,
            'profile_photo' => $profilePhotoUrl,
            'email' => optional($intern->user)->email,
            'student_id' => $intern->student_id,
            'school' => $intern->school,
            'course' => $intern->course,
            'phone' => $intern->phone,
            'emergency_contact_name' => $intern->emergency_contact_name,
            'emergency_contact_phone' => $intern->emergency_contact_phone,
            'required_hours' => $intern->required_hours === null ? null : (int) $intern->required_hours,
            'weekly_availability' => $intern->weekly_availability,
            'department_id' => $intern->department_id,
            'department_name' => $intern->department?->name,
            'supervisor_user_id' => $intern->supervisor_user_id,
            'supervisor_name' => $intern->supervisor_name,
            'supervisor_email' => $intern->supervisor?->email ?? $intern->supervisor_email,
            'start_date' => optional($intern->start_date)->toDateString(),
            'end_date' => optional($intern->end_date)->toDateString(),
            'onboarded_at' => optional($intern->onboarded_at)->toISOString(),
            'is_active' => (bool) $intern->is_active,
            'role' => $role,
        ];
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $intern = Intern::with(['supervisor', 'department'])->find($id);
        if (!$intern) {
            return $this->notFound('Intern not found');
        }
        if (!$this->canAccessIntern($request, $intern)) {
            return $this->forbidden('You do not have permission to update this intern.');
        }

        $user = $request->user();
        $rules = [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'school' => ['sometimes', 'string', 'max:255'],
            'course' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'emergency_contact_name' => ['sometimes', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'string', 'max:50'],
            'required_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'supervisor_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'supervisor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'supervisor_email' => ['sometimes', 'nullable', 'string', 'email'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
        if ($user && $user->isAdmin()) {
            $rules['student_id'] = ['sometimes', 'nullable', 'string', 'max:100'];
        }
        $validated = $request->validate($rules);

        $intern->update($validated);
        return $this->success($this->formatInternProfile($intern->fresh(['supervisor', 'department'])), 'Intern updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $intern = Intern::find($id);
        if (!$intern) {
            return $this->notFound('Intern not found');
        }
        if (!$this->canAccessIntern($request, $intern)) {
            return $this->forbidden('You do not have permission to delete this intern.');
        }
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators or supervisors can deactivate interns.');
        }
        if ($user->isSupervisor() && (int) $intern->supervisor_user_id !== (int) $user->id) {
            return $this->forbidden('You can only deactivate interns you supervise.');
        }

        $intern->update(['is_active' => false]);
        return $this->success(
            ['id' => $intern->id, 'is_active' => false],
            'Intern deactivated. Record retained for history.'
        );
    }

    /**
     * Save base64 profile photo to storage.
     */
    private function saveProfilePhoto(string $base64): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
            $extension = $matches[1];
        } else {
            $extension = 'jpg';
        }

        $imageData = base64_decode($base64);
        if ($imageData === false) {
            throw new \Exception('Invalid base64 image data');
        }

        $filename = 'profile_' . uniqid() . '_' . time() . '.' . $extension;
        $path = 'profile/' . $filename;

        Storage::disk('public')->put($path, $imageData);

        return $path;
    }
}
