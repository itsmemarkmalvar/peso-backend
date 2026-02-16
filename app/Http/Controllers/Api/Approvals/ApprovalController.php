<?php

namespace App\Http\Controllers\Api\Approvals;

use App\Enums\AttendanceStatus;
use App\Helpers\AttendanceHours;
use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
<<<<<<< Updated upstream
use App\Models\Intern;
=======
use App\Models\Schedule;
use Carbon\Carbon;
>>>>>>> Stashed changes
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApprovalController extends BaseController
{
    /**
     * List all approval requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can view approvals
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view approvals');
        }

        $query = Attendance::with(['intern', 'geofenceLocation', 'approver'])
            ->whereIn('status', [AttendanceStatus::PENDING, AttendanceStatus::APPROVED, AttendanceStatus::REJECTED]);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by intern
        if ($request->intern_id) {
            $query->where('intern_id', $request->intern_id);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        $approvals = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Transform to match frontend expectations
        $transformed = $approvals->getCollection()->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'attendance_id' => $attendance->id,
                'intern_id' => $attendance->intern_id,
                'intern_name' => $attendance->intern->full_name ?? 'Unknown',
                'intern_student_id' => $attendance->intern->student_id ?? '',
                'type' => $this->determineApprovalType($attendance),
                'reason_title' => $this->getReasonTitle($attendance),
                'status' => ucfirst(is_object($attendance->status) ? $attendance->status->value : (string) ($attendance->status ?? '')),
                'date' => $attendance->date->format('Y-m-d'),
                'clock_in_time' => $attendance->clock_in_time?->format('H:i:s'),
                'clock_out_time' => $attendance->clock_out_time?->format('H:i:s'),
                'notes' => $attendance->notes,
                'rejection_reason' => $attendance->rejection_reason,
                'approved_by' => $attendance->approved_by,
                'approved_at' => $attendance->approved_at?->toISOString(),
                'created_at' => $attendance->created_at->toISOString(),
                'updated_at' => $attendance->updated_at->toISOString(),
            ];
        });

        return $this->success([
            'data' => $transformed,
            'pagination' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
        ], 'Approvals list');
    }

    /**
     * Get pending approvals only
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can view pending approvals
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view pending approvals');
        }

        $query = Attendance::with(['intern', 'geofenceLocation'])
            ->where('status', AttendanceStatus::PENDING);

        // Filter by intern
        if ($request->intern_id) {
            $query->where('intern_id', $request->intern_id);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        $approvals = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Transform to match frontend expectations
        $transformed = $approvals->getCollection()->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'attendance_id' => $attendance->id,
                'intern_id' => $attendance->intern_id,
                'intern_name' => $attendance->intern->full_name ?? 'Unknown',
                'intern_student_id' => $attendance->intern->student_id ?? '',
                'type' => $this->determineApprovalType($attendance),
                'reason_title' => $this->getReasonTitle($attendance),
                'status' => 'Pending',
                'date' => $attendance->date->format('Y-m-d'),
                'clock_in_time' => $attendance->clock_in_time?->format('H:i:s'),
                'clock_out_time' => $attendance->clock_out_time?->format('H:i:s'),
                'notes' => $attendance->notes,
                'rejection_reason' => null,
                'approved_by' => null,
                'approved_at' => null,
                'created_at' => $attendance->created_at->toISOString(),
                'updated_at' => $attendance->updated_at->toISOString(),
            ];
        });

        return $this->success([
            'data' => $transformed,
            'pagination' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
        ], 'Pending approvals');
    }

    /**
     * Approve attendance
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can approve
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can approve attendance');
        }

        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $attendance = Attendance::with(['intern'])->find($id);
        if (!$attendance) {
            return $this->notFound('Attendance record not found');
        }

        if ($attendance->status !== AttendanceStatus::PENDING) {
            return $this->error('This attendance has already been processed', 400);
        }

        try {
            DB::beginTransaction();

            $updates = [
                'status' => AttendanceStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $request->comments ?? $attendance->notes,
            ];

            if ($attendance->approval_type === 'overtime') {
                $updates['effective_clock_out_time'] = $attendance->clock_out_time;
                $attendance->update($updates);
                $totalMinutes = ($attendance->effective_clock_in_time ?? $attendance->clock_in_time)->diffInMinutes($attendance->clock_out_time);
                if ($attendance->break_start && $attendance->break_end) {
                    $totalMinutes -= $attendance->break_start->diffInMinutes($attendance->break_end);
                }
                $attendance->total_hours = round(max(0, $totalMinutes) / 60, 2);
                $attendance->save();
            } else {
                $attendance->update($updates);
            }

            // Auto-fill end_date when intern completes their total required OJT hours (cumulative, not daily).
            $intern = Intern::find($attendance->intern_id);
            if ($intern && $intern->required_hours > 0 && $intern->end_date === null) {
                $totalApprovedHours = $this->getTotalApprovedHours($attendance->intern_id);
                if ($totalApprovedHours >= (float) $intern->required_hours) {
                    // Use the date of the attendance that completed the requirement (the work day), not the approval date.
                    $intern->update(['end_date' => $attendance->date->toDateString()]);
                }
            }

            DB::commit();

            $transformed = [
                'id' => $attendance->id,
                'attendance_id' => $attendance->id,
                'intern_id' => $attendance->intern_id,
                'intern_name' => $attendance->intern->full_name ?? 'Unknown',
                'intern_student_id' => $attendance->intern->student_id ?? '',
                'type' => $this->determineApprovalType($attendance),
                'reason_title' => $this->getReasonTitle($attendance),
                'status' => 'Approved',
                'date' => $attendance->date->format('Y-m-d'),
                'clock_in_time' => $attendance->clock_in_time?->format('H:i:s'),
                'clock_out_time' => $attendance->clock_out_time?->format('H:i:s'),
                'notes' => $attendance->notes,
                'rejection_reason' => null,
                'approved_by' => $attendance->approved_by,
                'approved_at' => $attendance->approved_at->toISOString(),
                'created_at' => $attendance->created_at->toISOString(),
                'updated_at' => $attendance->updated_at->toISOString(),
            ];

            return $this->success([
                'data' => $transformed,
            ], 'Attendance approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to approve attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject attendance
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        // Only admin and supervisor can reject
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can reject attendance');
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $attendance = Attendance::with(['intern'])->find($id);
        if (!$attendance) {
            return $this->notFound('Attendance record not found');
        }

        if ($attendance->status !== AttendanceStatus::PENDING) {
            return $this->error('This attendance has already been processed', 400);
        }

        try {
            DB::beginTransaction();

            $updates = [
                'status' => AttendanceStatus::REJECTED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->reason,
            ];

            if ($attendance->approval_type === 'overtime') {
                $schedule = Schedule::where('intern_id', $attendance->intern_id)
                    ->where('day_of_week', $attendance->date->dayOfWeek)
                    ->where('is_active', true)
                    ->first();
                $scheduledEnd = $schedule
                    ? Carbon::parse($attendance->date->format('Y-m-d') . ' ' . $schedule->end_time)
                    : Carbon::parse($attendance->date->format('Y-m-d') . ' 17:00:00');
                $updates['effective_clock_out_time'] = $scheduledEnd;
                $attendance->update($updates);
                $start = $attendance->effective_clock_in_time ?? $attendance->clock_in_time;
                $totalMinutes = $start->diffInMinutes($scheduledEnd);
                if ($attendance->break_start && $attendance->break_end) {
                    $totalMinutes -= $attendance->break_start->diffInMinutes($attendance->break_end);
                }
                $attendance->total_hours = round(max(0, $totalMinutes) / 60, 2);
                $attendance->save();
            } else {
                $attendance->update($updates);
            }

            DB::commit();

            $transformed = [
                'id' => $attendance->id,
                'attendance_id' => $attendance->id,
                'intern_id' => $attendance->intern_id,
                'intern_name' => $attendance->intern->full_name ?? 'Unknown',
                'intern_student_id' => $attendance->intern->student_id ?? '',
                'type' => $this->determineApprovalType($attendance),
                'reason_title' => $this->getReasonTitle($attendance),
                'status' => 'Rejected',
                'date' => $attendance->date->format('Y-m-d'),
                'clock_in_time' => $attendance->clock_in_time?->format('H:i:s'),
                'clock_out_time' => $attendance->clock_out_time?->format('H:i:s'),
                'notes' => $attendance->notes,
                'rejection_reason' => $attendance->rejection_reason,
                'approved_by' => $attendance->approved_by,
                'approved_at' => $attendance->approved_at->toISOString(),
                'created_at' => $attendance->created_at->toISOString(),
                'updated_at' => $attendance->updated_at->toISOString(),
            ];

            return $this->success([
                'data' => $transformed,
            ], 'Attendance rejected');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to reject attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Determine approval type based on attendance flags and approval_type
     */
    private function determineApprovalType(Attendance $attendance): string
    {
        if ($attendance->approval_type) {
            return match ($attendance->approval_type) {
                'late_clock_in' => 'Late',
                'gps_correction' => 'Correction',
                'early_clock_out' => 'Early out',
                'overtime' => 'Overtime',
                default => 'Correction',
            };
        }
        if ($attendance->is_overtime) {
            return 'Overtime';
        }
        if ($attendance->is_undertime) {
            return 'Early out';
        }
        if ($attendance->is_gps_correction) {
            return 'Correction';
        }
        if ($attendance->is_late) {
            return 'Late';
        }
        return 'Correction';
    }

    /**
     * Compute total approved hours for an intern (used to auto-fill end_date at 100% completion).
     */
    private function getTotalApprovedHours(int $internId): float
    {
        $records = Attendance::where('intern_id', $internId)
            ->where('status', AttendanceStatus::APPROVED)
            ->get();

        $total = 0.0;
        foreach ($records as $att) {
            $hours = null;
            if ($att->total_hours !== null && (float) $att->total_hours > 0) {
                $hours = (float) $att->total_hours;
            } elseif ($att->clock_in_time && $att->clock_out_time) {
                $hours = AttendanceHours::computeCompletedHours($att);
            }
            if ($hours !== null) {
                $total += $hours;
            }
        }
        return $total;
    }

    /**
     * Get reason title for approval
     */
    private function getReasonTitle(Attendance $attendance): string
    {
        if ($attendance->approval_type) {
            return match ($attendance->approval_type) {
                'late_clock_in' => 'Late clock-in',
                'gps_correction' => 'GPS/device correction request',
                'early_clock_out' => 'Early clock-out',
                'overtime' => 'Overtime hours worked',
                default => 'Attendance correction request',
            };
        }
        if ($attendance->is_overtime) {
            return 'Overtime hours worked';
        }
        if ($attendance->is_undertime) {
            return 'Early clock-out';
        }
        if ($attendance->is_late) {
            return 'Late clock-in';
        }
        return 'Attendance correction request';
    }
}
