<?php

namespace App\Helpers;

use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceHours
{
    /**
     * Format hours as "Xh Ym" (e.g., "8h 30m").
     */
    public static function formatHours(float $hours): string
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

    /**
     * Compute hours for completed attendance (clock-in + clock-out).
     * Uses effective_clock_in_time and effective_clock_out_time when set (scheduled times for approval flow).
     */
    public static function computeCompletedHours(Attendance $attendance): float
    {
        $start = $attendance->effective_clock_in_time ?? $attendance->clock_in_time;
        $end = $attendance->effective_clock_out_time ?? $attendance->clock_out_time;
        if (!$start || !$end) {
            return 0;
        }

        $totalMinutes = $start->diffInMinutes($end);
        if ($attendance->break_start && $attendance->break_end) {
            if ($attendance->break_end->gte($attendance->break_start)) {
                $totalMinutes -= $attendance->break_start->diffInMinutes($attendance->break_end);
            }
        }

        $totalMinutes = max(0, $totalMinutes);
        return round($totalMinutes / 60, 2);
    }

    /**
     * Estimate hours when attendance is in progress (clock-in without clock-out).
     * Uses effective_clock_in_time when set (scheduled start for approval flow).
     */
    public static function estimateInProgressHours(
        Attendance $attendance,
        ?Carbon $now = null
    ): float {
        $start = $attendance->effective_clock_in_time ?? $attendance->clock_in_time;
        if (!$start) {
            return 0;
        }

        $end = $now ?? Carbon::now();
        $totalMinutes = $start->diffInMinutes($end);

        if ($attendance->break_start && $attendance->break_end) {
            if ($attendance->break_start->lte($end) && $attendance->break_end->gte($attendance->break_start)) {
                $totalMinutes -= $attendance->break_start->diffInMinutes($attendance->break_end);
            }
        } elseif ($attendance->break_start && !$attendance->break_end) {
            if ($attendance->break_start->lte($end)) {
                $totalMinutes -= $attendance->break_start->diffInMinutes($end);
            }
        }

        $totalMinutes = max(0, $totalMinutes);
        return round($totalMinutes / 60, 2);
    }
}
