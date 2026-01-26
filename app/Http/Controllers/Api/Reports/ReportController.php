<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
use App\Models\Intern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    /**
     * Daily Time Record (DTR) Report
     */
    public function dtr(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can view reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view reports');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        $internId = $request->intern_id;

        $query = Attendance::with(['intern', 'geofenceLocation', 'approver'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('clock_in_time');

        if ($internId) {
            $query->where('intern_id', $internId);
        }

        $attendance = $query->orderBy('date', 'asc')
            ->orderBy('clock_in_time', 'asc')
            ->get();

        $report = $attendance->map(function ($record) {
            return [
                'date' => $record->date->format('Y-m-d'),
                'day' => $record->date->format('l'),
                'intern_name' => $record->intern->full_name ?? 'Unknown',
                'student_id' => $record->intern->student_id ?? '',
                'clock_in' => $record->clock_in_time?->format('H:i:s'),
                'clock_out' => $record->clock_out_time?->format('H:i:s'),
                'total_hours' => $record->total_hours ?? 0,
                'status' => $record->status->value,
                'is_late' => $record->is_late,
                'is_undertime' => $record->is_undertime,
                'is_overtime' => $record->is_overtime,
                'location' => $record->location_address ?? 'N/A',
            ];
        });

        return $this->success([
            'report_type' => 'dtr',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_records' => $report->count(),
            'data' => $report,
        ], 'DTR report generated');
    }

    /**
     * Attendance Summary Report (Late/Absent)
     */
    public function attendance(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can view reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view reports');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        $status = $request->status ?? 'all'; // all, present, late, absent

        $query = Attendance::with(['intern'])
            ->whereBetween('date', [$startDate, $endDate]);

        // Filter by status
        if ($status === 'present') {
            $query->where('status', AttendanceStatus::APPROVED)
                ->whereNotNull('clock_in_time');
        } elseif ($status === 'late') {
            $query->where('is_late', true);
        } elseif ($status === 'absent') {
            // Absent = no attendance record for the date range
            // This requires a different query - get all interns and check for missing attendance
            $interns = Intern::where('is_active', true)->get();
            $dates = $this->getDateRange($startDate, $endDate);
            
            $absentRecords = [];
            foreach ($interns as $intern) {
                foreach ($dates as $date) {
                    $attendance = Attendance::where('intern_id', $intern->id)
                        ->where('date', $date)
                        ->first();
                    
                    if (!$attendance || !$attendance->clock_in_time) {
                        $absentRecords[] = [
                            'date' => $date,
                            'intern_name' => $intern->full_name,
                            'student_id' => $intern->student_id,
                            'status' => 'absent',
                        ];
                    }
                }
            }
            
            return $this->success([
                'report_type' => 'attendance',
                'status' => 'absent',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_records' => count($absentRecords),
                'data' => $absentRecords,
            ], 'Attendance report generated');
        }

        $attendance = $query->orderBy('date', 'asc')->get();

        $summary = [
            'total' => $attendance->count(),
            'present' => $attendance->where('status', AttendanceStatus::APPROVED)->count(),
            'pending' => $attendance->where('status', AttendanceStatus::PENDING)->count(),
            'rejected' => $attendance->where('status', AttendanceStatus::REJECTED)->count(),
            'late' => $attendance->where('is_late', true)->count(),
            'undertime' => $attendance->where('is_undertime', true)->count(),
            'overtime' => $attendance->where('is_overtime', true)->count(),
        ];

        $report = $attendance->map(function ($record) {
            return [
                'date' => $record->date->format('Y-m-d'),
                'intern_name' => $record->intern->full_name ?? 'Unknown',
                'student_id' => $record->intern->student_id ?? '',
                'clock_in' => $record->clock_in_time?->format('H:i:s'),
                'clock_out' => $record->clock_out_time?->format('H:i:s'),
                'status' => $record->status->value,
                'is_late' => $record->is_late,
                'is_undertime' => $record->is_undertime,
                'is_overtime' => $record->is_overtime,
            ];
        });

        return $this->success([
            'report_type' => 'attendance',
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => $summary,
            'data' => $report,
        ], 'Attendance report generated');
    }

    /**
     * Hours Rendered Report
     */
    public function hours(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can view reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view reports');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        $groupBy = $request->group_by ?? 'intern'; // intern, company, supervisor

        $query = Attendance::with(['intern'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', AttendanceStatus::APPROVED)
            ->whereNotNull('total_hours');

        $attendance = $query->get();

        if ($groupBy === 'intern') {
            $report = $attendance->groupBy('intern_id')->map(function ($records, $internId) {
                $intern = $records->first()->intern;
                $totalHours = $records->sum('total_hours');
                $totalDays = $records->count();
                
                return [
                    'intern_id' => $internId,
                    'intern_name' => $intern->full_name ?? 'Unknown',
                    'student_id' => $intern->student_id ?? '',
                    'total_hours' => round($totalHours, 2),
                    'total_days' => $totalDays,
                    'average_hours_per_day' => round($totalHours / max($totalDays, 1), 2),
                ];
            })->values();
        } elseif ($groupBy === 'company') {
            $report = $attendance->groupBy(function ($record) {
                return $record->intern->company_name ?? 'Unknown';
            })->map(function ($records, $company) {
                $totalHours = $records->sum('total_hours');
                $totalDays = $records->count();
                $internCount = $records->pluck('intern_id')->unique()->count();
                
                return [
                    'company' => $company,
                    'total_hours' => round($totalHours, 2),
                    'total_days' => $totalDays,
                    'intern_count' => $internCount,
                    'average_hours_per_intern' => round($totalHours / max($internCount, 1), 2),
                ];
            })->values();
        } else {
            // Default: just list all records
            $report = $attendance->map(function ($record) {
                return [
                    'date' => $record->date->format('Y-m-d'),
                    'intern_name' => $record->intern->full_name ?? 'Unknown',
                    'student_id' => $record->intern->student_id ?? '',
                    'hours' => $record->total_hours ?? 0,
                ];
            });
        }

        $summary = [
            'total_hours' => round($attendance->sum('total_hours'), 2),
            'total_days' => $attendance->count(),
            'total_interns' => $attendance->pluck('intern_id')->unique()->count(),
            'average_hours_per_day' => round($attendance->sum('total_hours') / max($attendance->count(), 1), 2),
        ];

        return $this->success([
            'report_type' => 'hours',
            'group_by' => $groupBy,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => $summary,
            'data' => $report,
        ], 'Hours report generated');
    }

    /**
     * Export report (PDF/Excel)
     * Note: This is a placeholder. For full implementation, install:
     * - PhpSpreadsheet (composer require phpoffice/phpspreadsheet) for Excel
     * - DomPDF or similar (composer require dompdf/dompdf) for PDF
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can export reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can export reports');
        }

        $type = $request->type ?? 'dtr'; // dtr, attendance, hours
        $format = $request->format ?? 'json'; // json, pdf, excel
        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');

        // For now, return JSON. PDF/Excel export can be added later with proper libraries
        if ($format === 'json') {
            if ($type === 'dtr') {
                return $this->dtr($request);
            } elseif ($type === 'attendance') {
                return $this->attendance($request);
            } else {
                return $this->hours($request);
            }
        }

        // PDF/Excel export would go here
        // For now, return error suggesting to use JSON format
        return $this->error('PDF and Excel export are not yet implemented. Please use format=json', 501);
    }

    /**
     * Get date range array
     */
    private function getDateRange(string $start, string $end): array
    {
        $dates = [];
        $current = strtotime($start);
        $end = strtotime($end);
        
        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
        
        return $dates;
    }
}
