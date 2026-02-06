<?php

namespace App\Http\Controllers\Api\Approvals;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
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
                'status' => ucfirst($attendance->status->value),
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

            $attendance->update([
                'status' => AttendanceStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $request->comments ?? $attendance->notes,
            ]);

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

            $attendance->update([
                'status' => AttendanceStatus::REJECTED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->reason,
            ]);

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
     * Determine approval type based on attendance flags
     */
    private function determineApprovalType(Attendance $attendance): string
    {
        if ($attendance->is_overtime) {
            return 'Overtime';
        }
        if ($attendance->is_undertime) {
            return 'Undertime';
        }
        if ($attendance->is_late) {
            return 'Correction';
        }
        return 'Correction'; // Default
    }

    /**
     * Get reason title for approval
     */
    private function getReasonTitle(Attendance $attendance): string
    {
        if ($attendance->is_overtime) {
            return 'Overtime hours worked';
        }
        if ($attendance->is_undertime) {
            return 'Undertime - less than required hours';
        }
        if ($attendance->is_late) {
            return 'Late clock-in';
        }
        return 'Attendance correction request';
    }
}
