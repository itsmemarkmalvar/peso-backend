<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
use App\Models\Intern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimesheetsController extends BaseController
{
    /**
     * Get weekly timesheet data for all interns (admin view)
     * Returns data grouped by intern with daily hours for the selected week
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin and supervisor can view all timesheets
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view timesheets');
        }

        // Get week start date (default to current week)
        $weekStart = $request->query('week_start');
        if ($weekStart) {
            try {
                $startDate = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
            } catch (\Exception $e) {
                $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
            }
        } else {
            $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        }

        $endDate = $startDate->copy()->endOfWeek(Carbon::SUNDAY);

        // Get all active interns
        $interns = Intern::where('is_active', true)
            ->with('user')
            ->get();

        // Get attendance records for the week
        $attendances = Attendance::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereIn('intern_id', $interns->pluck('id'))
            ->get()
            ->groupBy('intern_id');

        // Build response data
        $weekDates = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDates[] = $startDate->copy()->addDays($i)->format('Y-m-d');
        }

        $timesheetRows = [];
        foreach ($interns as $intern) {
            $internAttendances = $attendances->get($intern->id, collect());
            
            $days = [];
            $weekTotal = 0;

            foreach ($weekDates as $date) {
                $attendance = $internAttendances->firstWhere('date', $date);
                
                if ($attendance && $attendance->total_hours) {
                    $hours = (float) $attendance->total_hours;
                    $hoursFormatted = $this->formatHours($hours);
                    $days[] = [
                        'date' => $date,
                        'hours' => $hoursFormatted,
                        'label' => $hoursFormatted,
                        'isRestDay' => false,
                    ];
                    $weekTotal += $hours;
                } else {
                    $days[] = [
                        'date' => $date,
                        'hours' => '-',
                        'label' => '-',
                        'isRestDay' => false,
                    ];
                }
            }

            $timesheetRows[] = [
                'intern_id' => $intern->id,
                'intern' => $intern->full_name ?? $intern->user->name ?? 'Unknown',
                'company' => $intern->company_name ?? '-',
                'id' => $intern->student_id ?? "INT-{$intern->id}",
                'days' => $days,
                'total' => $this->formatHours($weekTotal),
            ];
        }

        return $this->success([
            'week_start' => $startDate->format('Y-m-d'),
            'week_end' => $endDate->format('Y-m-d'),
            'week_dates' => $weekDates,
            'rows' => $timesheetRows,
        ], 'Timesheet data retrieved successfully');
    }

    /**
     * Get detailed timesheet for a specific intern
     * Returns all attendance records with photos, times, and hours
     */
    public function show(Request $request, int $internId): JsonResponse
    {
        $user = $request->user();

        // Only admin and supervisor can view intern timesheets
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view timesheets');
        }

        $intern = Intern::with('user')->find($internId);

        if (!$intern) {
            return $this->notFound('Intern not found');
        }

        // Get date range (default to last 30 days, or use query params)
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : Carbon::now()->subDays(30);
        
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : Carbon::now();

        // Get attendance records
        $attendances = Attendance::where('intern_id', $internId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date', 'desc')
            ->get();

        // Format attendance records
        $records = $attendances->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'date' => $attendance->date->format('Y-m-d'),
                'date_label' => $attendance->date->format('M d, Y'),
                'day_name' => $attendance->date->format('l'),
                'clock_in_time' => $attendance->clock_in_time
                    ? $attendance->clock_in_time->format('H:i:s')
                    : null,
                'clock_in_time_label' => $attendance->clock_in_time
                    ? $attendance->clock_in_time->format('g:i A')
                    : null,
                'clock_out_time' => $attendance->clock_out_time
                    ? $attendance->clock_out_time->format('H:i:s')
                    : null,
                'clock_out_time_label' => $attendance->clock_out_time
                    ? $attendance->clock_out_time->format('g:i A')
                    : null,
                'clock_in_photo' => $attendance->clock_in_photo
                    ? (str_starts_with($attendance->clock_in_photo, 'http') 
                        ? $attendance->clock_in_photo 
                        : asset('storage/' . $attendance->clock_in_photo))
                    : null,
                'clock_out_photo' => $attendance->clock_out_photo
                    ? (str_starts_with($attendance->clock_out_photo, 'http')
                        ? $attendance->clock_out_photo
                        : asset('storage/' . $attendance->clock_out_photo))
                    : null,
                'total_hours' => $attendance->total_hours
                    ? (float) $attendance->total_hours
                    : null,
                'total_hours_label' => $attendance->total_hours
                    ? $this->formatHours((float) $attendance->total_hours)
                    : '-',
                'status' => $attendance->status,
                'is_late' => $attendance->is_late,
                'is_undertime' => $attendance->is_undertime,
                'is_overtime' => $attendance->is_overtime,
                'location_address' => $attendance->location_address,
            ];
        });

        // Calculate totals
        $totalHours = $attendances->sum(function ($attendance) {
            return $attendance->total_hours ? (float) $attendance->total_hours : 0;
        });

        return $this->success([
            'intern' => [
                'id' => $intern->id,
                'name' => $intern->full_name ?? $intern->user->name ?? 'Unknown',
                'company' => $intern->company_name ?? '-',
                'student_id' => $intern->student_id ?? "INT-{$intern->id}",
            ],
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'records' => $records,
            'summary' => [
                'total_days' => $records->count(),
                'total_hours' => $totalHours,
                'total_hours_label' => $this->formatHours($totalHours),
            ],
        ], 'Intern timesheet retrieved successfully');
    }

    /**
     * Format hours as "Xh Ym" (e.g., "8h 30m")
     */
    private function formatHours(float $hours): string
    {
        if ($hours <= 0) {
            return '0h 0m';
        }

        $wholeHours = floor($hours);
        $minutes = round(($hours - $wholeHours) * 60);

        if ($minutes >= 60) {
            $wholeHours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }

        return "{$wholeHours}h {$minutes}m";
    }
}
