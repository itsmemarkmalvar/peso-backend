<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
use App\Models\Intern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportController extends BaseController
{
    /**
     * Daily Time Record (DTR) Report
     */
    public function dtr(Request $request)
    {
        try {
            $user = $request->user();
            
            // Only admin and supervisor can view reports
            if (!$user->isAdmin() && !$user->isSupervisor()) {
                return $this->forbidden('Only administrators and supervisors can view reports');
            }

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->format('Y-m-d');
            $internId = $request->intern_id;
            $format = $request->format ?? 'json';

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

            // Handle export formats
            if ($format === 'pdf') {
                return $this->exportDTRToPDF($report->values(), $startDate, $endDate);
            } elseif ($format === 'excel') {
                return $this->exportDTRToExcel($report->values(), $startDate, $endDate);
            }

            return $this->success([
                'report_type' => 'dtr',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_records' => $report->count(),
                'data' => $report,
            ], 'DTR report generated');
        } catch (\Exception $e) {
            Log::error('DTR report failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to generate DTR report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Attendance Summary Report (Late/Absent)
     */
    public function attendance(Request $request)
    {
        $user = $request->user();
        
        // Only admin and supervisor can view reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view reports');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        $status = $request->status ?? 'all'; // all, present, late, absent
        $format = $request->format ?? 'json';

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
                            'clock_in' => null,
                            'clock_out' => null,
                            'is_late' => false,
                        ];
                    }
                }
            }
            
            $report = collect($absentRecords);
            $summary = [
                'total' => count($absentRecords),
                'present' => 0,
                'pending' => 0,
                'rejected' => 0,
                'late' => 0,
                'undertime' => 0,
                'overtime' => 0,
            ];
            
            // Handle export formats
            if ($format === 'pdf') {
                return $this->exportAttendanceToPDF($report->values(), $summary, $startDate, $endDate, $status);
            } elseif ($format === 'excel') {
                return $this->exportAttendanceToExcel($report->values(), $summary, $startDate, $endDate, $status);
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

        // Handle export formats
        if ($format === 'pdf') {
            return $this->exportAttendanceToPDF($report->values(), $summary, $startDate, $endDate, $status);
        } elseif ($format === 'excel') {
            return $this->exportAttendanceToExcel($report->values(), $summary, $startDate, $endDate, $status);
        }

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
    public function hours(Request $request)
    {
        $user = $request->user();
        
        // Only admin and supervisor can view reports
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view reports');
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        $groupBy = $request->group_by ?? 'intern'; // intern, company, supervisor
        $format = $request->format ?? 'json';

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

        // Handle export formats
        if ($format === 'pdf') {
            return $this->exportHoursToPDF($report->values(), $summary, $startDate, $endDate, $groupBy);
        } elseif ($format === 'excel') {
            return $this->exportHoursToExcel($report->values(), $summary, $startDate, $endDate, $groupBy);
        }

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
     * Export DTR to PDF
     */
    private function exportDTRToPDF($report, string $startDate, string $endDate)
    {
        try {
            $html = $this->generateDTRHTML($report, $startDate, $endDate);
            return $this->generatePDF($html, 'dtr-report-' . $startDate . '-to-' . $endDate . '.pdf');
        } catch (\Exception $e) {
            Log::error('DTR PDF export failed: ' . $e->getMessage());
            return $this->error('Failed to export DTR PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export DTR to Excel
     */
    private function exportDTRToExcel($report, string $startDate, string $endDate)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set title
            $sheet->setCellValue('A1', 'Daily Time Record (DTR)');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('A2', 'Period: ' . $startDate . ' to ' . $endDate);
            $sheet->mergeCells('A2:H2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Headers
            $headers = ['Date', 'Day', 'Intern Name', 'Student ID', 'Clock In', 'Clock Out', 'Total Hours', 'Status'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '4', $header);
                $sheet->getStyle($col . '4')->getFont()->setBold(true);
                $sheet->getStyle($col . '4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0E0E0');
                $col++;
            }
            
            // Data
            $row = 5;
            if ($report && count($report) > 0) {
                foreach ($report as $record) {
                    $sheet->setCellValue('A' . $row, $record['date'] ?? '');
                    $sheet->setCellValue('B' . $row, $record['day'] ?? '');
                    $sheet->setCellValue('C' . $row, $record['intern_name'] ?? '');
                    $sheet->setCellValue('D' . $row, $record['student_id'] ?? '');
                    $sheet->setCellValue('E' . $row, $record['clock_in'] ?? 'N/A');
                    $sheet->setCellValue('F' . $row, $record['clock_out'] ?? 'N/A');
                    $sheet->setCellValue('G' . $row, $record['total_hours'] ?? 0);
                    $sheet->setCellValue('H' . $row, ucfirst($record['status'] ?? ''));
                    $row++;
                }
            } else {
                $sheet->setCellValue('A5', 'No data available for the selected period');
                $sheet->mergeCells('A5:H5');
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            
            // Auto-size columns
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            return $this->downloadExcel($spreadsheet, 'dtr-report-' . $startDate . '-to-' . $endDate . '.xlsx');
        } catch (\Exception $e) {
            Log::error('DTR Excel export failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to export DTR Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export Attendance to PDF
     */
    private function exportAttendanceToPDF($report, $summary, string $startDate, string $endDate, string $status)
    {
        try {
            $html = $this->generateAttendanceHTML($report, $summary, $startDate, $endDate, $status);
            return $this->generatePDF($html, 'attendance-report-' . $startDate . '-to-' . $endDate . '.pdf');
        } catch (\Exception $e) {
            Log::error('Attendance PDF export failed: ' . $e->getMessage());
            return $this->error('Failed to export Attendance PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export Attendance to Excel
     */
    private function exportAttendanceToExcel($report, $summary, string $startDate, string $endDate, string $status)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set title
            $sheet->setCellValue('A1', 'Attendance Report - ' . ucfirst($status));
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('A2', 'Period: ' . $startDate . ' to ' . $endDate);
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Headers
            $headers = ['Date', 'Intern Name', 'Student ID', 'Clock In', 'Clock Out', 'Status', 'Late'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '4', $header);
                $sheet->getStyle($col . '4')->getFont()->setBold(true);
                $sheet->getStyle($col . '4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0E0E0');
                $col++;
            }
            
            // Data
            $row = 5;
            if ($report && (is_countable($report) ? count($report) : 0) > 0) {
                foreach ($report as $record) {
                    $sheet->setCellValue('A' . $row, $record['date'] ?? '');
                    $sheet->setCellValue('B' . $row, $record['intern_name'] ?? '');
                    $sheet->setCellValue('C' . $row, $record['student_id'] ?? '');
                    $sheet->setCellValue('D' . $row, $record['clock_in'] ?? 'N/A');
                    $sheet->setCellValue('E' . $row, $record['clock_out'] ?? 'N/A');
                    $sheet->setCellValue('F' . $row, ucfirst($record['status'] ?? ''));
                    $sheet->setCellValue('G' . $row, ($record['is_late'] ?? false) ? 'Yes' : 'No');
                    $row++;
                }
            } else {
                $sheet->setCellValue('A5', 'No data available for the selected period');
                $sheet->mergeCells('A5:G5');
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            
            // Auto-size columns
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            return $this->downloadExcel($spreadsheet, 'attendance-report-' . $startDate . '-to-' . $endDate . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Attendance Excel export failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to export Attendance Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export Hours to PDF
     */
    private function exportHoursToPDF($report, $summary, string $startDate, string $endDate, string $groupBy)
    {
        try {
            $html = $this->generateHoursHTML($report, $summary, $startDate, $endDate, $groupBy);
            return $this->generatePDF($html, 'hours-report-' . $startDate . '-to-' . $endDate . '.pdf');
        } catch (\Exception $e) {
            Log::error('Hours PDF export failed: ' . $e->getMessage());
            return $this->error('Failed to export Hours PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export Hours to Excel
     */
    private function exportHoursToExcel($report, $summary, string $startDate, string $endDate, string $groupBy)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set title
            $sheet->setCellValue('A1', 'Hours Rendered Report');
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('A2', 'Period: ' . $startDate . ' to ' . $endDate . ' | Grouped by: ' . ucfirst($groupBy));
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Headers based on group_by
            if ($groupBy === 'intern') {
                $headers = ['Intern Name', 'Student ID', 'Total Hours', 'Total Days', 'Average Hours/Day'];
                $dataCols = ['A', 'B', 'C', 'D', 'E'];
            } elseif ($groupBy === 'company') {
                $headers = ['Company', 'Total Hours', 'Total Days', 'Intern Count', 'Average Hours/Intern'];
                $dataCols = ['A', 'B', 'C', 'D', 'E'];
            } else {
                $headers = ['Date', 'Intern Name', 'Student ID', 'Hours'];
                $dataCols = ['A', 'B', 'C', 'D'];
            }
            
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '4', $header);
                $sheet->getStyle($col . '4')->getFont()->setBold(true);
                $sheet->getStyle($col . '4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0E0E0');
                $col++;
            }
            
            // Data
            $row = 5;
            if ($report && (is_countable($report) ? count($report) : 0) > 0) {
                foreach ($report as $record) {
                    $col = 'A';
                    foreach ($dataCols as $dataCol) {
                        $key = $this->getHoursReportKey($dataCol, $record, $groupBy);
                        $sheet->setCellValue($col . $row, $record[$key] ?? '');
                        $col++;
                    }
                    $row++;
                }
            } else {
                $sheet->setCellValue('A5', 'No data available for the selected period');
                $sheet->mergeCells('A5:' . end($dataCols) . '5');
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            
            // Auto-size columns
            foreach ($dataCols as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            return $this->downloadExcel($spreadsheet, 'hours-report-' . $startDate . '-to-' . $endDate . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Hours Excel export failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to export Hours Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get key for hours report based on column
     */
    private function getHoursReportKey(string $col, array $record, string $groupBy): string
    {
        if ($groupBy === 'intern') {
            $keys = ['A' => 'intern_name', 'B' => 'student_id', 'C' => 'total_hours', 'D' => 'total_days', 'E' => 'average_hours_per_day'];
        } elseif ($groupBy === 'company') {
            $keys = ['A' => 'company', 'B' => 'total_hours', 'C' => 'total_days', 'D' => 'intern_count', 'E' => 'average_hours_per_intern'];
        } else {
            $keys = ['A' => 'date', 'B' => 'intern_name', 'C' => 'student_id', 'D' => 'hours'];
        }
        return $keys[$col] ?? '';
    }

    /**
     * Generate PDF from HTML
     */
    private function generatePDF(string $html, string $filename)
    {
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            $pdfContent = $dompdf->output();
            
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($pdfContent),
            ]);
        } catch (\Exception $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to generate PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download Excel file
     */
    private function downloadExcel(Spreadsheet $spreadsheet, string $filename)
    {
        try {
            $writer = new Xlsx($spreadsheet);
            
            return new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Excel generation failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->error('Failed to generate Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate HTML for DTR PDF
     */
    private function generateDTRHTML($report, string $startDate, string $endDate): string
    {
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            h2 { text-align: center; color: #666; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #4A90E2; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f2f2f2; }
        </style></head><body>';
        $html .= '<h1>Daily Time Record (DTR)</h1>';
        $html .= '<h2>Period: ' . $startDate . ' to ' . $endDate . '</h2>';
        $html .= '<table><tr>
            <th>Date</th><th>Day</th><th>Intern Name</th><th>Student ID</th>
            <th>Clock In</th><th>Clock Out</th><th>Total Hours</th><th>Status</th>
        </tr>';
        
        foreach ($report as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record['date']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['day']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['intern_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['student_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['clock_in'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($record['clock_out'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($record['total_hours']) . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($record['status'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Generate HTML for Attendance PDF
     */
    private function generateAttendanceHTML($report, $summary, string $startDate, string $endDate, string $status): string
    {
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            h2 { text-align: center; color: #666; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #4A90E2; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f2f2f2; }
        </style></head><body>';
        $html .= '<h1>Attendance Report - ' . ucfirst($status) . '</h1>';
        $html .= '<h2>Period: ' . $startDate . ' to ' . $endDate . '</h2>';
        $html .= '<table><tr>
            <th>Date</th><th>Intern Name</th><th>Student ID</th>
            <th>Clock In</th><th>Clock Out</th><th>Status</th><th>Late</th>
        </tr>';
        
        foreach ($report as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record['date']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['intern_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['student_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($record['clock_in'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($record['clock_out'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($record['status'])) . '</td>';
            $html .= '<td>' . ($record['is_late'] ? 'Yes' : 'No') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Generate HTML for Hours PDF
     */
    private function generateHoursHTML($report, $summary, string $startDate, string $endDate, string $groupBy): string
    {
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            h2 { text-align: center; color: #666; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #4A90E2; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f2f2f2; }
        </style></head><body>';
        $html .= '<h1>Hours Rendered Report</h1>';
        $html .= '<h2>Period: ' . $startDate . ' to ' . $endDate . ' | Grouped by: ' . ucfirst($groupBy) . '</h2>';
        
        if ($groupBy === 'intern') {
            $html .= '<table><tr>
                <th>Intern Name</th><th>Student ID</th><th>Total Hours</th><th>Total Days</th><th>Average Hours/Day</th>
            </tr>';
            foreach ($report as $record) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($record['intern_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['student_id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['total_hours']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['total_days']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['average_hours_per_day']) . '</td>';
                $html .= '</tr>';
            }
        } elseif ($groupBy === 'company') {
            $html .= '<table><tr>
                <th>Company</th><th>Total Hours</th><th>Total Days</th><th>Intern Count</th><th>Average Hours/Intern</th>
            </tr>';
            foreach ($report as $record) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($record['company']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['total_hours']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['total_days']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['intern_count']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['average_hours_per_intern']) . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<table><tr>
                <th>Date</th><th>Intern Name</th><th>Student ID</th><th>Hours</th>
            </tr>';
            foreach ($report as $record) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($record['date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['intern_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['student_id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($record['hours']) . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</table></body></html>';
        return $html;
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
