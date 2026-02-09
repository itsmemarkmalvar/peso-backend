<?php

namespace App\Http\Controllers\Api\Interns;

use App\Http\Controllers\Api\BaseController;
use App\Helpers\AttendanceHours;
use App\Models\Attendance;
use App\Models\Intern;
use App\Models\Schedule;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternDashboardController extends BaseController
{
    /**
     * Get intern time clock data
     * Returns current attendance status, schedule, and recent activity
     */
    public function timeClock(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }

        // Get intern profile
        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $dayOfWeek = $now->dayOfWeek;

        // Get today's attendance
        $todayAttendance = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->with(['geofenceLocation'])
            ->first();

        // Get today's schedule
        $todaySchedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        // Get this week's attendance (Monday to Sunday)
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $weekAttendance = Attendance::where('intern_id', $intern->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->orderBy('date', 'asc')
            ->get();

        // Get recent attendance history (last 5 records)
        $recentAttendance = Attendance::where('intern_id', $intern->id)
            ->orderBy('date', 'desc')
            ->orderBy('clock_in_time', 'desc')
            ->limit(5)
            ->get();

        // Build header data
        $currentTime = $now->format('h:i');
        $meridiem = $now->format('A');
        $dateLabel = $now->format('l, F j, Y');
        
        $statusLabel = 'Not Clocked In';
        $statusTone = 'inactive';
        
        if ($todayAttendance) {
            if ($todayAttendance->clock_out_time) {
                $statusLabel = 'Clocked Out';
                $statusTone = 'inactive';
            } elseif ($todayAttendance->break_start && !$todayAttendance->break_end) {
                $statusLabel = 'On Break';
                $statusTone = 'active';
            } elseif ($todayAttendance->clock_in_time) {
                $statusLabel = 'Clocked In';
                $statusTone = 'active';
            }
        }

        $shiftLabel = 'No Schedule';
        if ($todaySchedule) {
            $startTime = Carbon::createFromTimeString($todaySchedule->start_time)->format('g:i A');
            $endTime = Carbon::createFromTimeString($todaySchedule->end_time)->format('g:i A');
            $shiftLabel = "{$startTime} - {$endTime}";
        }

        // Build snapshot data
        $lastClock = 'No activity today';
        if ($todayAttendance) {
            if ($todayAttendance->clock_out_time) {
                $lastClock = 'Clocked out at ' . $todayAttendance->clock_out_time->format('g:i A');
            } elseif ($todayAttendance->clock_in_time) {
                $lastClock = 'Clocked in at ' . $todayAttendance->clock_in_time->format('g:i A');
            }
        }

        $breakLabel = 'No break taken';
        if ($todayAttendance && $todayAttendance->break_start) {
            if ($todayAttendance->break_end) {
                $breakDuration = $todayAttendance->break_start->diffInMinutes($todayAttendance->break_end);
                $breakLabel = "{$breakDuration} minutes";
            } else {
                $breakLabel = 'Currently on break';
            }
        }

        $locationLabel = 'No location recorded';
        if ($todayAttendance && $todayAttendance->location_address) {
            $locationLabel = $todayAttendance->location_address;
        } elseif ($todayAttendance && $todayAttendance->geofenceLocation) {
            $locationLabel = $todayAttendance->geofenceLocation->name;
        }

        // Build summary stats
        $todayHoursValue = 0.0;
        if ($todayAttendance && $todayAttendance->clock_in_time) {
            if ($todayAttendance->total_hours !== null && (float) $todayAttendance->total_hours > 0) {
                $todayHoursValue = (float) $todayAttendance->total_hours;
            } elseif ($todayAttendance->clock_out_time) {
                $todayHoursValue = AttendanceHours::computeCompletedHours($todayAttendance);
            } else {
                $todayHoursValue = AttendanceHours::estimateInProgressHours($todayAttendance, $now);
            }
        }
        $todayHoursLabel = AttendanceHours::formatHours($todayHoursValue);

        $todayDate = $now->format('Y-m-d');
        $weekTotalHours = 0.0;
        foreach ($weekAttendance as $attendance) {
            $hoursValue = null;
            if ($attendance->total_hours !== null && (float) $attendance->total_hours > 0) {
                $hoursValue = (float) $attendance->total_hours;
            } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
                $hoursValue = AttendanceHours::computeCompletedHours($attendance);
            } elseif ($attendance->clock_in_time && !$attendance->clock_out_time) {
                $attendanceDate = $attendance->date ? $attendance->date->format('Y-m-d') : null;
                if ($attendanceDate === $todayDate) {
                    $hoursValue = AttendanceHours::estimateInProgressHours($attendance, $now);
                }
            }
            if ($hoursValue !== null) {
                $weekTotalHours += $hoursValue;
            }
        }
        $weekHoursLabel = AttendanceHours::formatHours($weekTotalHours);

        $totalHoursValue = (float) Attendance::where('intern_id', $intern->id)
            ->whereNotNull('total_hours')
            ->sum('total_hours');
        if ($todayAttendance && $todayAttendance->clock_in_time && !$todayAttendance->clock_out_time) {
            $totalHoursValue += $todayHoursValue;
        }
        $totalHoursLabel = AttendanceHours::formatHours($totalHoursValue);

        if ($todayAttendance) {
            $todayAttendance->total_hours = $todayHoursValue;
            $todayAttendance->total_hours_label = $todayHoursLabel;
        }

        $summary = [
            [
                'label' => 'Today',
                'value' => $todayHoursLabel,
                'sub' => 'hours',
            ],
            [
                'label' => 'This Week',
                'value' => $weekHoursLabel,
                'sub' => 'hours',
            ],
            [
                'label' => 'Total',
                'value' => $totalHoursLabel,
                'sub' => 'hours',
            ],
        ];

        // Build week breakdown
        $week = [];
        $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $attendance = $weekAttendance->firstWhere('date', $date->format('Y-m-d'));
            $hoursValue = null;
            if ($attendance) {
                if ($attendance->total_hours !== null && (float) $attendance->total_hours > 0) {
                    $hoursValue = (float) $attendance->total_hours;
                } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
                    $hoursValue = AttendanceHours::computeCompletedHours($attendance);
                } elseif ($attendance->clock_in_time && !$attendance->clock_out_time) {
                    $attendanceDate = $attendance->date ? $attendance->date->format('Y-m-d') : null;
                    if ($attendanceDate === $todayDate) {
                        $hoursValue = AttendanceHours::estimateInProgressHours($attendance, $now);
                    }
                }
            }
            $hours = AttendanceHours::formatHours($hoursValue ?? 0);
            
            $week[] = [
                'day' => $daysOfWeek[$date->dayOfWeek],
                'hours' => $hours,
            ];
        }

        // Build recent activity
        $recentActivity = [];
        foreach ($recentAttendance as $attendance) {
            if ($attendance->clock_in_time) {
                $time = $attendance->clock_in_time->format('g:i A');
                $date = $attendance->date->format('M j');
                $status = $attendance->is_late ? ' (Late)' : '';
                $recentActivity[] = [
                    'time' => $time,
                    'title' => 'Clocked In' . $status,
                    'detail' => $date,
                ];
            }
            if ($attendance->clock_out_time) {
                $time = $attendance->clock_out_time->format('g:i A');
                $date = $attendance->date->format('M j');
                $hours = number_format($attendance->total_hours ?? 0, 1);
                $recentActivity[] = [
                    'time' => $time,
                    'title' => 'Clocked Out',
                    'detail' => "{$date} ({$hours}h)",
                ];
            }
        }

        // Limit recent activity to 5 items
        $recentActivity = array_slice($recentActivity, 0, 5);

        // Clock-in allowed only until scheduled start + grace period
        $clockInCutoff = null;
        if ($todaySchedule) {
            $scheduledStart = Carbon::createFromTimeString($todaySchedule->start_time);
            $settings = SystemSetting::get();
            $graceMinutes = $settings?->grace_period_minutes ?? 10;
            $cutoff = $scheduledStart->copy()->addMinutes($graceMinutes);
            $clockInCutoff = [
                'time' => $cutoff->format('H:i:s'),
                'label' => $cutoff->format('g:i A'),
            ];
        }

        return $this->success([
            'header' => [
                'currentTime' => $currentTime,
                'meridiem' => $meridiem,
                'dateLabel' => $dateLabel,
                'statusLabel' => $statusLabel,
                'statusTone' => $statusTone,
                'shiftLabel' => $shiftLabel,
            ],
            'clock_in_cutoff' => $clockInCutoff,
            'snapshot' => [
                'lastClock' => $lastClock,
                'breakLabel' => $breakLabel,
                'locationLabel' => $locationLabel,
            ],
            'summary' => $summary,
            'week' => $week,
            'recentActivity' => $recentActivity,
            'todayAttendance' => $todayAttendance,
        ], 'Time clock data retrieved');
    }

    /**
     * Get intern dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }

        // Get intern profile
        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();

        // Get today's attendance
        $todayAttendance = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        // Get this week's attendance
        $weekAttendance = Attendance::where('intern_id', $intern->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        // Get total hours
        $totalHours = Attendance::where('intern_id', $intern->id)
            ->whereNotNull('total_hours')
            ->sum('total_hours') ?? 0;

        // Calculate today's hours
        $todayHoursValue = 0.0;
        if ($todayAttendance && $todayAttendance->clock_in_time) {
            if ($todayAttendance->total_hours !== null && (float) $todayAttendance->total_hours > 0) {
                $todayHoursValue = (float) $todayAttendance->total_hours;
            } elseif ($todayAttendance->clock_out_time) {
                $todayHoursValue = AttendanceHours::computeCompletedHours($todayAttendance);
            } else {
                $todayHoursValue = AttendanceHours::estimateInProgressHours($todayAttendance, $now);
            }
        }
        $todayHoursLabel = AttendanceHours::formatHours($todayHoursValue);

        $todayDate = $now->format('Y-m-d');
        $weekTotalHours = 0.0;
        foreach ($weekAttendance as $attendance) {
            $hoursValue = null;
            if ($attendance->total_hours !== null && (float) $attendance->total_hours > 0) {
                $hoursValue = (float) $attendance->total_hours;
            } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
                $hoursValue = AttendanceHours::computeCompletedHours($attendance);
            } elseif ($attendance->clock_in_time && !$attendance->clock_out_time) {
                $attendanceDate = $attendance->date ? $attendance->date->format('Y-m-d') : null;
                if ($attendanceDate === $todayDate) {
                    $hoursValue = AttendanceHours::estimateInProgressHours($attendance, $now);
                }
            }
            if ($hoursValue !== null) {
                $weekTotalHours += $hoursValue;
            }
        }
        $weekHoursLabel = AttendanceHours::formatHours($weekTotalHours);

        $stats = [
            [
                'label' => 'Today',
                'value' => $todayHoursLabel,
                'sub' => 'hours',
            ],
            [
                'label' => 'This Week',
                'value' => $weekHoursLabel,
                'sub' => 'hours',
            ],
            [
                'label' => 'Total',
                'value' => AttendanceHours::formatHours($totalHours),
                'sub' => 'hours',
            ],
        ];

        // Get recent activity
        $recentAttendance = Attendance::where('intern_id', $intern->id)
            ->orderBy('date', 'desc')
            ->orderBy('clock_in_time', 'desc')
            ->limit(5)
            ->get();

        $recentActivity = [];
        foreach ($recentAttendance as $attendance) {
            if ($attendance->clock_in_time) {
                $time = $attendance->clock_in_time->format('g:i A');
                $date = $attendance->date->format('M j');
                $status = $attendance->is_late ? ' (Late)' : '';
                $recentActivity[] = [
                    'time' => $time,
                    'title' => 'Clocked In' . $status,
                    'detail' => $date,
                ];
            }
            if ($attendance->clock_out_time) {
                $time = $attendance->clock_out_time->format('g:i A');
                $date = $attendance->date->format('M j');
                $hours = number_format($attendance->total_hours ?? 0, 1);
                $recentActivity[] = [
                    'time' => $time,
                    'title' => 'Clocked Out',
                    'detail' => "{$date} ({$hours}h)",
                ];
            }
        }

        $recentActivity = array_slice($recentActivity, 0, 5);

        return $this->success([
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ], 'Dashboard data retrieved');
    }
}
