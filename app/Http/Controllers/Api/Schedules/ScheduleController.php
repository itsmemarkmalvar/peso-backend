<?php

namespace App\Http\Controllers\Api\Schedules;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use App\Models\Schedule;
use App\Models\SchoolSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can assign schedules');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'days' => 'required|array|min:1',
            'days.*.day_of_week' => 'required|integer|min:0|max:6',
            'days.*.start_time' => 'required|date_format:H:i',
            'days.*.end_time' => 'required|date_format:H:i|after:days.*.start_time',
            'lunch_break_start' => 'required|date_format:H:i',
            'lunch_break_end' => 'required|date_format:H:i|after:lunch_break_start',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $days = $request->input('days');
        $lunchBreakStart = $request->input('lunch_break_start');
        $lunchBreakEnd = $request->input('lunch_break_end');

        // Calculate break duration in minutes
        $breakStart = \Carbon\Carbon::createFromFormat('H:i', $lunchBreakStart);
        $breakEnd = \Carbon\Carbon::createFromFormat('H:i', $lunchBreakEnd);
        $breakDuration = $breakStart->diffInMinutes($breakEnd);

        try {
            DB::beginTransaction();

            // Get all active interns
            $interns = Intern::where('is_active', true)->get();

            $schedulesCreated = 0;
            $schedulesUpdated = 0;

            foreach ($interns as $intern) {
                foreach ($days as $dayData) {
                    $schedule = Schedule::updateOrCreate(
                        [
                            'intern_id' => $intern->id,
                            'day_of_week' => $dayData['day_of_week'],
                        ],
                        [
                            'start_time' => $dayData['start_time'],
                            'end_time' => $dayData['end_time'],
                            'break_duration' => $breakDuration,
                            'is_active' => true,
                        ]
                    );

                    if ($schedule->wasRecentlyCreated) {
                        $schedulesCreated++;
                    } else {
                        $schedulesUpdated++;
                    }
                }
            }

            DB::commit();

            return $this->success([
                'schedules_created' => $schedulesCreated,
                'schedules_updated' => $schedulesUpdated,
                'interns_affected' => $interns->count(),
            ], 'Default schedule assigned to all active interns');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign schedule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get excused interns by day (those with school schedules)
     */
    public function excused(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view excused interns');
        }

        $dayOfWeek = $request->input('day_of_week');
        
        if ($dayOfWeek === null) {
            return $this->validationError(['day_of_week' => ['The day_of_week field is required.']]);
        }

        $dayOfWeek = (int) $dayOfWeek;
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            return $this->validationError(['day_of_week' => ['The day_of_week must be between 0 and 6.']]);
        }

        // Get all interns with school schedules on this day
        $excusedInterns = Intern::where('is_active', true)
            ->whereHas('schoolSchedules', function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->where('is_active', true);
            })
            ->with(['schoolSchedules' => function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->where('is_active', true);
            }])
            ->get()
            ->map(function (Intern $intern) {
                $schoolSchedule = $intern->schoolSchedules->first();
                return [
                    'id' => $intern->id,
                    'name' => $intern->full_name,
                    'student_id' => $intern->student_id,
                    'course' => $intern->course,
                    'class_time' => $schoolSchedule 
                        ? sprintf(
                            '%s - %s',
                            \Carbon\Carbon::createFromFormat('H:i:s', $schoolSchedule->start_time)->format('g:i A'),
                            \Carbon\Carbon::createFromFormat('H:i:s', $schoolSchedule->end_time)->format('g:i A')
                        )
                        : null,
                    'subject' => $schoolSchedule->subject ?? null,
                    'room' => $schoolSchedule->room ?? null,
                ];
            })
            ->values();

        return $this->success($excusedInterns, 'Excused interns retrieved');
    }
}
