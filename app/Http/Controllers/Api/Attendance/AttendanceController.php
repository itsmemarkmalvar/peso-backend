<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Enums\AttendanceStatus;
use App\Helpers\AttendanceHours;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\Attendance;
use App\Models\GeofenceLocation;
use App\Models\Intern;
use App\Models\Schedule;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends BaseController
{
    /**
     * Clock in
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }
        
        // Refresh user to ensure role is loaded correctly
        $user->refresh();
        
        // Get raw role value from database for debugging
        $rawRole = $user->getRawOriginal('role') ?? $user->getAttribute('role');
        
        // Only intern and GIP can clock in
        // Check both enum comparison and raw string comparison as fallback
        $isAllowed = false;
        if ($user->role instanceof \App\Enums\UserRole) {
            $isAllowed = $user->isInternOrGip();
        } else {
            // Fallback: check raw string value if enum casting failed
            $isAllowed = in_array(strtolower($rawRole), ['intern', 'gip'], true);
        }
        
        if (!$isAllowed) {
            // Debug: Get the actual role value for troubleshooting
            $actualRole = 'unknown';
            if ($user->role instanceof \App\Enums\UserRole) {
                $actualRole = $user->role->value;
            } elseif (is_string($rawRole)) {
                $actualRole = $rawRole;
            } elseif (is_null($rawRole)) {
                $actualRole = 'null';
            }
            
            return $this->forbidden(
                "Only interns and GIP can clock in. Your current role is: '{$actualRole}'. Please contact an administrator if this is incorrect."
            );
        }

        // Get intern profile
        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $this->mergeAttendanceLocationInput($request);

        $settings = SystemSetting::get();
        $rules = [
            'location_lat' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-90,90',
            'location_lng' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-180,180',
            'photo' => ($settings->verification_selfie ? 'required' : 'nullable') . '|string',
            'geofence_location_id' => 'nullable|exists:geofence_locations,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Use Asia/Manila so attendance date matches timesheet week boundaries (same timezone for admin & intern views)
        $manilaTz = 'Asia/Manila';
        $today = Carbon::now($manilaTz)->startOfDay();
        
        // Check if already clocked in today
        $existing = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->clock_in_time) {
            return $this->error('You have already clocked in today', 400);
        }

        // Verify geofence location
        $geofenceLocation = null;
        if ($request->geofence_location_id) {
            $geofenceLocation = GeofenceLocation::where('id', $request->geofence_location_id)
                ->where('is_active', true)
                ->first();
            
            if (!$geofenceLocation) {
                return $this->error('Invalid geofence location', 400);
            }

            // Calculate distance
            $distance = $this->calculateDistance(
                $request->location_lat,
                $request->location_lng,
                $geofenceLocation->latitude,
                $geofenceLocation->longitude
            );

            if ($distance > $geofenceLocation->radius_meters) {
                return $this->error('You are outside the allowed geofence area', 400);
            }
        }

        // Get reverse geocoded address (when GPS verification is enabled)
        $locationAddress = null;
        if ($request->location_lat !== null && $request->location_lng !== null) {
            $locationAddress = $this->getAddressFromCoordinates(
                $request->location_lat,
                $request->location_lng
            );
        }

        // Save photo when selfie verification is enabled
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoPath = $this->saveBase64Image($request->photo, 'clock-in');
        }

        // Get today's schedule and grace period (Asia/Manila)
        $manilaTz = config('app.timezone', 'Asia/Manila');
        $nowManila = now($manilaTz);

        $dayOfWeek = $nowManila->dayOfWeek;
        $schedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $scheduledStart = $schedule
            ? $nowManila->copy()->setTimeFromTimeString($schedule->start_time)
            : $nowManila->copy()->startOfDay()->setTime(8, 0, 0);

        $graceMinutes = $settings->grace_period_minutes ?? 10;
        $graceEnd = $scheduledStart->copy()->addMinutes($graceMinutes);

        // Determine approval flow: early/on-time/within-grace = auto-ok; after grace = late (approval)
        $isLate = $nowManila->gt($graceEnd);

        // Effective clock-in is ALWAYS scheduled start (timer uses this)
        $effectiveClockIn = $scheduledStart->copy();

        // Actual clock-in time (audit)
        $clockInTime = $nowManila->copy();

        try {
            DB::beginTransaction();

            if ($existing) {
                // Update existing record
                $existing->update([
                    'clock_in_time' => $clockInTime,
                    'effective_clock_in_time' => $effectiveClockIn,
                    'clock_in_photo' => $photoPath,
                    'location_lat' => $request->location_lat,
                    'location_lng' => $request->location_lng,
                    'location_address' => $locationAddress,
                    'geofence_location_id' => $geofenceLocation?->id,
                    'clock_in_method' => 'web',
                    'status' => $isLate ? AttendanceStatus::PENDING : AttendanceStatus::APPROVED,
                    'is_late' => $isLate,
                    'approval_type' => $isLate ? 'late_clock_in' : null,
                ]);
                $attendance = $existing;
            } else {
                // Create new record
                $attendance = Attendance::create([
                    'intern_id' => $intern->id,
                    'date' => $today,
                    'clock_in_time' => $clockInTime,
                    'effective_clock_in_time' => $effectiveClockIn,
                    'clock_in_photo' => $photoPath,
                    'location_lat' => $request->location_lat,
                    'location_lng' => $request->location_lng,
                    'location_address' => $locationAddress,
                    'geofence_location_id' => $geofenceLocation?->id,
                    'clock_in_method' => 'web',
                    'status' => $isLate ? AttendanceStatus::PENDING : AttendanceStatus::APPROVED,
                    'is_late' => $isLate,
                    'approval_type' => $isLate ? 'late_clock_in' : null,
                ]);
            }

            DB::commit();

            $message = $isLate
                ? 'Clocked in (late). Awaiting supervisor approval.'
                : 'Clocked in successfully';

            return $this->success([
                'attendance' => $attendance->load(['intern', 'geofenceLocation']),
                'message' => $message,
            ], $message);
        } catch (\Exception $e) {
            DB::rollBack();
            // Delete uploaded photo on error
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            return $this->error('Failed to clock in: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clock-in correction (GPS/device failure within grace period)
     * Intern submits reason when GPS fails but they are within grace period.
     */
    public function clockInCorrection(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }
        $user->refresh();
        $rawRole = $user->getRawOriginal('role') ?? $user->getAttribute('role');
        $isAllowed = $user->role instanceof \App\Enums\UserRole
            ? $user->isInternOrGip()
            : in_array(strtolower((string) $rawRole), ['intern', 'gip'], true);
        if (!$isAllowed) {
            return $this->forbidden('Only interns and GIP can request clock-in correction');
        }

        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $settings = SystemSetting::get();
        // GPS correction: location not required, but selfie and reason are required
        $rules = [
            'photo' => ($settings->verification_selfie ? 'required' : 'nullable') . '|string',
            'reason' => 'required|string|max:500',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $manilaTz = 'Asia/Manila';
        $nowManila = now($manilaTz);
        $today = Carbon::now($manilaTz)->startOfDay();

        $existing = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->clock_in_time) {
            return $this->error('You have already clocked in today', 400);
        }

        $dayOfWeek = $nowManila->dayOfWeek;
        $schedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $scheduledStart = $schedule
            ? $nowManila->copy()->setTimeFromTimeString($schedule->start_time)
            : $nowManila->copy()->startOfDay()->setTime(8, 0, 0);

        $graceMinutes = $settings->grace_period_minutes ?? 10;
        $graceEnd = $scheduledStart->copy()->addMinutes($graceMinutes);
        $isLate = $nowManila->gt($graceEnd);

        // Allow correction regardless of grace period - intern may have GPS failure and be late
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoPath = $this->saveBase64Image($request->photo, 'clock-in');
        }

        $effectiveClockIn = $scheduledStart->copy();
        $clockInTime = $nowManila->copy();
        $reason = trim($request->reason);

        try {
            DB::beginTransaction();

            if ($existing) {
                $existing->update([
                    'clock_in_time' => $clockInTime,
                    'effective_clock_in_time' => $effectiveClockIn,
                    'clock_in_photo' => $photoPath,
                    'clock_in_method' => 'web',
                    'status' => AttendanceStatus::PENDING,
                    'is_late' => $isLate,
                    'is_gps_correction' => true,
                    'approval_type' => 'gps_correction',
                    'notes' => $reason,
                ]);
                $attendance = $existing;
            } else {
                $attendance = Attendance::create([
                    'intern_id' => $intern->id,
                    'date' => $today,
                    'clock_in_time' => $clockInTime,
                    'effective_clock_in_time' => $effectiveClockIn,
                    'clock_in_photo' => $photoPath,
                    'clock_in_method' => 'web',
                    'status' => AttendanceStatus::PENDING,
                    'is_late' => $isLate,
                    'is_gps_correction' => true,
                    'approval_type' => 'gps_correction',
                    'notes' => $reason,
                ]);
            }

            DB::commit();

            $message = $isLate
                ? 'Late clock-in correction requested. Awaiting supervisor approval.'
                : 'Clock-in correction requested. Awaiting supervisor approval.';

            return $this->success([
                'attendance' => $attendance->load(['intern', 'geofenceLocation']),
                'message' => $message,
            ], $message);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            return $this->error('Failed to submit correction: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clock out
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }
        
        // Refresh user to ensure role is loaded correctly
        $user->refresh();
        
        // Get raw role value from database for debugging
        $rawRole = $user->getRawOriginal('role') ?? $user->getAttribute('role');
        
        // Only intern and GIP can clock out
        // Check both enum comparison and raw string comparison as fallback
        $isAllowed = false;
        if ($user->role instanceof \App\Enums\UserRole) {
            $isAllowed = $user->isInternOrGip();
        } else {
            // Fallback: check raw string value if enum casting failed
            $isAllowed = in_array(strtolower($rawRole), ['intern', 'gip'], true);
        }
        
        if (!$isAllowed) {
            // Debug: Get the actual role value for troubleshooting
            $actualRole = 'unknown';
            if ($user->role instanceof \App\Enums\UserRole) {
                $actualRole = $user->role->value;
            } elseif (is_string($rawRole)) {
                $actualRole = $rawRole;
            } elseif (is_null($rawRole)) {
                $actualRole = 'null';
            }
            
            return $this->forbidden(
                "Only interns and GIP can clock out. Your current role is: '{$actualRole}'. Please contact an administrator if this is incorrect."
            );
        }

        // Get intern profile
        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $this->mergeAttendanceLocationInput($request);

        $settings = SystemSetting::get();
        $rules = [
            'location_lat' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-90,90',
            'location_lng' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-180,180',
            'photo' => ($settings->verification_selfie ? 'required' : 'nullable') . '|string',
            'geofence_location_id' => 'nullable|exists:geofence_locations,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Use Asia/Manila so attendance date matches timesheet week boundaries
        $today = Carbon::now('Asia/Manila')->startOfDay();
        
        // Get today's attendance
        $attendance = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->clock_in_time) {
            return $this->error('You must clock in first', 400);
        }

        if ($attendance->clock_out_time) {
            return $this->error('You have already clocked out today', 400);
        }

        // Verify geofence location
        $geofenceLocation = null;
        if ($request->geofence_location_id) {
            $geofenceLocation = GeofenceLocation::where('id', $request->geofence_location_id)
                ->where('is_active', true)
                ->first();
            
            if (!$geofenceLocation) {
                return $this->error('Invalid geofence location', 400);
            }

            // Calculate distance
            $distance = $this->calculateDistance(
                $request->location_lat,
                $request->location_lng,
                $geofenceLocation->latitude,
                $geofenceLocation->longitude
            );

            if ($distance > $geofenceLocation->radius_meters) {
                return $this->error('You are outside the allowed geofence area', 400);
            }
        }

        // Get reverse geocoded address (when GPS verification is enabled)
        $locationAddress = null;
        if ($request->location_lat !== null && $request->location_lng !== null) {
            $locationAddress = $this->getAddressFromCoordinates(
                $request->location_lat,
                $request->location_lng
            );
        }

        // Save photo when selfie verification is enabled
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoPath = $this->saveBase64Image($request->photo, 'clock-out');
        }

        $manilaTz = 'Asia/Manila';
        $clockOutTime = Carbon::now($manilaTz);

        // Get today's schedule and grace period
        $dayOfWeek = $clockOutTime->dayOfWeek;
        $schedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $graceMinutes = $settings->grace_period_minutes ?? 10;
        $scheduledStart = $schedule
            ? $clockOutTime->copy()->setTimeFromTimeString($schedule->start_time)
            : $clockOutTime->copy()->startOfDay()->setTime(8, 0, 0);
        $scheduledEnd = $schedule
            ? $clockOutTime->copy()->setTimeFromTimeString($schedule->end_time)
            : $clockOutTime->copy()->setTime(17, 0, 0);
        $graceEndTime = $scheduledEnd->copy()->addMinutes($graceMinutes);

        $effectiveStart = $attendance->effective_clock_in_time ?? $attendance->clock_in_time;

        // Determine: early out, normal (within grace), or overtime (past grace)
        $isEarlyOut = $clockOutTime->lt($scheduledEnd);
        $isOvertime = $clockOutTime->gt($graceEndTime);

        $effectiveClockOut = null;
        $approvalType = null;
        $status = AttendanceStatus::APPROVED;

        if ($isEarlyOut) {
            $effectiveClockOut = $clockOutTime->copy();
            $approvalType = 'early_clock_out';
            $status = AttendanceStatus::PENDING;
        } elseif ($isOvertime) {
            $effectiveClockOut = null;
            $approvalType = 'overtime';
            $status = AttendanceStatus::PENDING;
        } else {
            $effectiveClockOut = $scheduledEnd->copy();
        }

        // Normal day (no late, no undertime, no overtime) → auto-approve so hours count in Time Tracking.
        // Exception days (late/undertime/overtime) stay pending and go to Approvals for admin to approve/reject.
        $isNormalDay = !$attendance->is_late && !$isUndertime && !$isOvertime;
        $status = $isNormalDay ? AttendanceStatus::APPROVED : AttendanceStatus::PENDING;

        // Compute total hours using effective times
        $endForCalc = $effectiveClockOut ?? $clockOutTime;
        $totalMinutes = $effectiveStart->diffInMinutes($endForCalc);
        if ($attendance->break_start && $attendance->break_end) {
            $totalMinutes -= $attendance->break_start->diffInMinutes($attendance->break_end);
        }
        $totalMinutes = max(0, $totalMinutes);
        $totalHours = round($totalMinutes / 60, 2);

        try {
            DB::beginTransaction();

            $attendance->update([
                'clock_out_time' => $clockOutTime,
                'effective_clock_out_time' => $effectiveClockOut,
                'clock_out_photo' => $photoPath,
                'location_lat' => $request->location_lat,
                'location_lng' => $request->location_lng,
                'location_address' => $locationAddress,
                'geofence_location_id' => $geofenceLocation?->id,
                'total_hours' => $totalHours,
                'is_undertime' => $isEarlyOut,
                'is_overtime' => $isOvertime,
                'approval_type' => $approvalType ?? $attendance->approval_type,
                'status' => $status,
            ]);

            DB::commit();

            $message = 'Clocked out successfully';
            if ($status === AttendanceStatus::PENDING) {
                $message = $isEarlyOut
                    ? 'Clocked out early. Awaiting supervisor approval.'
                    : 'Clocked out (overtime). Awaiting supervisor approval.';
            }

            return $this->success([
                'attendance' => $attendance->load(['intern', 'geofenceLocation']),
                'total_hours' => $totalHours,
                'message' => $message,
            ], $message);
        } catch (\Exception $e) {
            DB::rollBack();
            // Delete uploaded photo on error
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            return $this->error('Failed to clock out: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record break start (intern went on break)
     */
    public function breakStart(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }
        $user->refresh();
        $rawRole = $user->getRawOriginal('role') ?? $user->getAttribute('role');
        $isAllowed = $user->role instanceof \App\Enums\UserRole
            ? $user->isInternOrGip()
            : in_array(strtolower((string) $rawRole), ['intern', 'gip'], true);
        if (!$isAllowed) {
            return $this->forbidden('Only interns and GIP can record break');
        }

        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $this->mergeAttendanceLocationInput($request);

        $settings = SystemSetting::get();
        $rules = [
            'location_lat' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-90,90',
            'location_lng' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-180,180',
            'photo' => ($settings->verification_selfie ? 'required' : 'nullable') . '|string',
            'geofence_location_id' => 'nullable|exists:geofence_locations,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $today = now()->startOfDay();
        $attendance = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->clock_in_time) {
            return $this->error('You must clock in first', 400);
        }
        if ($attendance->clock_out_time) {
            return $this->error('You have already clocked out today', 400);
        }
        if ($attendance->break_start) {
            return $this->error('You have already started a break today', 400);
        }

        if ($request->geofence_location_id) {
            $geofenceLocation = GeofenceLocation::where('id', $request->geofence_location_id)
                ->where('is_active', true)
                ->first();
            if (!$geofenceLocation) {
                return $this->error('Invalid geofence location', 400);
            }
            $distance = $this->calculateDistance(
                (float) $request->location_lat,
                (float) $request->location_lng,
                $geofenceLocation->latitude,
                $geofenceLocation->longitude
            );
            if ($distance > $geofenceLocation->radius_meters) {
                return $this->error('You are outside the allowed geofence area', 400);
            }
        }

        // Get reverse geocoded address (when GPS is provided)
        $locationAddress = null;
        if ($request->location_lat !== null && $request->location_lng !== null) {
            $locationAddress = $this->getAddressFromCoordinates(
                $request->location_lat,
                $request->location_lng
            );
        }

        // Save photo when selfie verification is enabled
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoPath = $this->saveBase64Image($request->photo, 'break-start');
        }

        $breakStartTime = now();
        try {
            $attendance->update([
                'break_start' => $breakStartTime,
                'break_start_photo' => $photoPath,
                'location_lat' => $request->location_lat,
                'location_lng' => $request->location_lng,
                'location_address' => $locationAddress,
                'geofence_location_id' => $request->geofence_location_id,
            ]);
            return $this->success([
                'attendance' => $attendance->fresh(['intern', 'geofenceLocation']),
                'message' => 'Break started',
            ], 'Break started');
        } catch (\Exception $e) {
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            return $this->error('Failed to record break start: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record break end (intern returned from break)
     */
    public function breakEnd(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('User not authenticated');
        }
        $user->refresh();
        $rawRole = $user->getRawOriginal('role') ?? $user->getAttribute('role');
        $isAllowed = $user->role instanceof \App\Enums\UserRole
            ? $user->isInternOrGip()
            : in_array(strtolower((string) $rawRole), ['intern', 'gip'], true);
        if (!$isAllowed) {
            return $this->forbidden('Only interns and GIP can record break');
        }

        $intern = Intern::where('user_id', $user->id)->first();
        if (!$intern) {
            return $this->notFound('Intern profile not found');
        }

        $this->mergeAttendanceLocationInput($request);

        $settings = SystemSetting::get();
        $rules = [
            'location_lat' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-90,90',
            'location_lng' => ($settings->verification_gps ? 'required' : 'nullable') . '|numeric|between:-180,180',
            'photo' => ($settings->verification_selfie ? 'required' : 'nullable') . '|string',
            'geofence_location_id' => 'nullable|exists:geofence_locations,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $today = now()->startOfDay();
        $attendance = Attendance::where('intern_id', $intern->id)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->clock_in_time) {
            return $this->error('You must clock in first', 400);
        }
        if ($attendance->clock_out_time) {
            return $this->error('You have already clocked out today', 400);
        }
        if (!$attendance->break_start) {
            return $this->error('You have not started a break yet', 400);
        }
        if ($attendance->break_end) {
            return $this->error('You have already ended your break today', 400);
        }

        if ($request->geofence_location_id) {
            $geofenceLocation = GeofenceLocation::where('id', $request->geofence_location_id)
                ->where('is_active', true)
                ->first();
            if (!$geofenceLocation) {
                return $this->error('Invalid geofence location', 400);
            }
            $distance = $this->calculateDistance(
                (float) $request->location_lat,
                (float) $request->location_lng,
                $geofenceLocation->latitude,
                $geofenceLocation->longitude
            );
            if ($distance > $geofenceLocation->radius_meters) {
                return $this->error('You are outside the allowed geofence area', 400);
            }
        }

        // Get reverse geocoded address (when GPS is provided)
        $locationAddress = null;
        if ($request->location_lat !== null && $request->location_lng !== null) {
            $locationAddress = $this->getAddressFromCoordinates(
                $request->location_lat,
                $request->location_lng
            );
        }

        // Save photo when selfie verification is enabled
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoPath = $this->saveBase64Image($request->photo, 'break-end');
        }

        $breakEndTime = now();
        try {
            $attendance->update([
                'break_end' => $breakEndTime,
                'break_end_photo' => $photoPath,
                'location_lat' => $request->location_lat,
                'location_lng' => $request->location_lng,
                'location_address' => $locationAddress,
                'geofence_location_id' => $request->geofence_location_id,
            ]);
            return $this->success([
                'attendance' => $attendance->fresh(['intern', 'geofenceLocation']),
                'message' => 'Break ended',
            ], 'Break ended');
        } catch (\Exception $e) {
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            return $this->error('Failed to record break end: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List attendance records
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['intern', 'geofenceLocation', 'approver']);

        // Intern/GIP can only see their own attendance
        if ($user->isInternOrGip()) {
            $intern = Intern::where('user_id', $user->id)->first();
            if (!$intern) {
                return $this->success([], 'No attendance records');
            }
            $query->where('intern_id', $intern->id);
        } elseif ($request->intern_id) {
            // Admin/Supervisor can filter by intern
            $query->where('intern_id', $request->intern_id);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $attendance = $query->orderBy('date', 'desc')
            ->orderBy('clock_in_time', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->success($attendance, 'Attendance records retrieved');
    }

    /**
     * Get today's attendance (single record for one intern, or filtered)
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['intern', 'geofenceLocation'])
            ->where('date', now()->startOfDay());

        // Intern/GIP can only see their own attendance
        if ($user->isInternOrGip()) {
            $intern = Intern::where('user_id', $user->id)->first();
            if (!$intern) {
                return $this->success(null, 'No attendance record for today');
            }
            $query->where('intern_id', $intern->id);
        } elseif ($request->intern_id) {
            $query->where('intern_id', $request->intern_id);
        }

        $attendance = $query->first();

        if (!$attendance) {
            return $this->success(null, 'No attendance record for today');
        }

        $this->appendComputedHours($attendance, now());
        return $this->success($attendance, 'Today\'s attendance');
    }

    /**
     * Get all today's attendance records (admin/supervisor only)
     * Used so admin dashboard reflects real clock-in/out and break start/end from interns
     */
    public function todayAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view all today\'s attendance');
        }

        $today = now()->startOfDay();
        $attendance = Attendance::with(['intern.user', 'geofenceLocation'])
            ->where('date', $today)
            ->orderBy('clock_in_time', 'desc')
            ->get();

        $now = now();
        $attendance->each(function (Attendance $record) use ($now) {
            $this->appendComputedHours($record, $now);
        });

        return $this->success($attendance, 'Today\'s attendance retrieved');
    }

    /**
     * Get live locations: interns currently clocked in (today, no clock-out yet).
     * Admin/supervisor only. Returns last activity time, location, and verification (GPS/selfie).
     */
    public function liveLocations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view live locations.');
        }

        $today = now()->startOfDay();
        $records = Attendance::with(['intern.department', 'geofenceLocation'])
            ->where('date', $today)
            ->whereNotNull('clock_in_time')
            ->whereNull('clock_out_time')
            ->orderByDesc('clock_in_time')
            ->get();

        $now = now();
        $items = $records->map(function (Attendance $att) use ($now) {
            $intern = $att->intern;
            $lastAt = $att->clock_in_time;
            if ($att->break_end) {
                $lastAt = $att->break_end->isAfter($lastAt) ? $att->break_end : $lastAt;
            }
            if ($att->break_start) {
                $lastAt = $att->break_start->isAfter($lastAt) ? $att->break_start : $lastAt;
            }
            $lastAt = $lastAt ? \Carbon\Carbon::parse($lastAt) : null;

            $status = 'Clocked in';
            if ($att->break_start && !$att->break_end) {
                $status = 'On break';
            }

            $location = $att->location_address
                ?? ($att->geofenceLocation ? $att->geofenceLocation->name : null)
                ?? '—';

            $verification = [];
            if ($att->location_lat !== null && $att->location_lng !== null) {
                $verification[] = 'GPS';
            }
            if (!empty($att->clock_in_photo)) {
                $verification[] = 'Selfie';
            }

            return [
                'intern_id' => $att->intern_id,
                'intern_name' => $intern ? $intern->full_name : 'Unknown',
                'student_id' => $intern ? $intern->student_id : '',
                'company_name' => $intern && $intern->department ? $intern->department->name : '',
                'status' => $status,
                'last_seen_at' => $lastAt ? $lastAt->toIso8601String() : null,
                'location' => $location,
                'verification' => $verification,
            ];
        })->values()->all();

        return $this->success($items, 'Live locations retrieved');
    }

    /**
     * Get attendance history
     */
    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Get total approved hours grouped by intern (admin/supervisor only).
     */
    public function approvedHoursSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can view approved hours summary');
        }

        $records = Attendance::where('status', AttendanceStatus::APPROVED)
            ->get([
                'intern_id',
                'total_hours',
                'clock_in_time',
                'clock_out_time',
                'break_start',
                'break_end',
            ]);

        $totals = [];
        foreach ($records as $attendance) {
            $hoursValue = null;
            if ($attendance->total_hours !== null && (float) $attendance->total_hours > 0) {
                $hoursValue = (float) $attendance->total_hours;
            } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
                $hoursValue = AttendanceHours::computeCompletedHours($attendance);
            }

            if ($hoursValue === null) {
                continue;
            }

            $internId = (string) $attendance->intern_id;
            if (!isset($totals[$internId])) {
                $totals[$internId] = 0.0;
            }
            $totals[$internId] += $hoursValue;
        }

        $summary = collect($totals)->map(function (float $hours, string $internId) {
            return [
                'intern_id' => (int) $internId,
                'hours_rendered' => round($hours, 2),
            ];
        })->values();

        return $this->success($summary, 'Approved hours summary retrieved');
    }

    /**
     * Show attendance details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $attendance = Attendance::with(['intern', 'geofenceLocation', 'approver'])->find($id);

        if (!$attendance) {
            return $this->notFound('Attendance record not found');
        }

        // Intern/GIP can only see their own attendance
        if ($user->isInternOrGip()) {
            $intern = Intern::where('user_id', $user->id)->first();
            if (!$intern || $attendance->intern_id !== $intern->id) {
                return $this->forbidden('You can only view your own attendance');
            }
        }

        return $this->success($attendance, 'Attendance details');
    }

    /**
     * Update attendance (Admin/Supervisor only)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can update attendance');
        }

        $attendance = Attendance::find($id);
        if (!$attendance) {
            return $this->notFound('Attendance record not found');
        }

        $validator = Validator::make($request->all(), [
            'clock_in_time' => 'nullable|date',
            'clock_out_time' => 'nullable|date|after:clock_in_time',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $attendance->update($request->only(['clock_in_time', 'clock_out_time', 'notes']));

        // Recalculate total hours if times changed
        if ($attendance->clock_in_time && $attendance->clock_out_time) {
            $totalMinutes = $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);
            if ($attendance->break_start && $attendance->break_end) {
                $breakMinutes = $attendance->break_start->diffInMinutes($attendance->break_end);
                $totalMinutes -= $breakMinutes;
            }
            $attendance->total_hours = round($totalMinutes / 60, 2);
            $attendance->save();
        }

        return $this->success($attendance->load(['intern', 'geofenceLocation']), 'Attendance updated');
    }

    /**
     * Ensure location_lat/location_lng are on the request: merge raw JSON if missing, and accept nested location.lat / location.lng.
     */
    private function mergeAttendanceLocationInput(Request $request): void
    {
        $input = $request->all();
        if (empty($input) && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $request->merge($decoded);
                $input = $request->all();
            }
        }
        if ($request->has('location_lat') && $request->has('location_lng')) {
            return;
        }
        $location = $request->input('location');
        if (is_array($location) && isset($location['lat'], $location['lng'])) {
            $request->merge([
                'location_lat' => $location['lat'],
                'location_lng' => $location['lng'],
            ]);
        }
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Get address from coordinates (reverse geocoding)
     */
    private function getAddressFromCoordinates($lat, $lng): ?string
    {
        try {
            $url = "https://photon.komoot.io/reverse?lat={$lat}&lon={$lng}&lang=en";
            $response = @file_get_contents($url);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['features'][0]['properties'])) {
                    $props = $data['features'][0]['properties'];
                    $parts = array_filter([
                        $props['name'] ?? null,
                        $props['street'] ?? null,
                        $props['city'] ?? null,
                        $props['country'] ?? null,
                    ]);
                    return implode(', ', $parts) ?: null;
                }
            }
        } catch (\Exception $e) {
            // Silently fail - address is optional
        }
        return null;
    }

    /**
     * Save base64 image to storage
     */
    private function saveBase64Image(string $base64, string $prefix = 'photo'): string
    {
        // Remove data URL prefix if present
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

        $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
        $path = 'attendance/' . $filename;

        Storage::disk('public')->put($path, $imageData);

        return $path;
    }

    /**
     * Attach computed hours and label for response payloads.
     */
    private function appendComputedHours(Attendance $attendance, Carbon $now): void
    {
        $hoursValue = null;
        if ($attendance->total_hours !== null && (float) $attendance->total_hours > 0) {
            $hoursValue = (float) $attendance->total_hours;
        } elseif ($attendance->clock_in_time && $attendance->clock_out_time) {
            $hoursValue = AttendanceHours::computeCompletedHours($attendance);
        } elseif ($attendance->clock_in_time && !$attendance->clock_out_time) {
            $hoursValue = AttendanceHours::estimateInProgressHours($attendance, $now);
        }

        if ($hoursValue !== null) {
            $attendance->total_hours = $hoursValue;
        }
        $attendance->total_hours_label = AttendanceHours::formatHours($hoursValue ?? 0);
    }
}
