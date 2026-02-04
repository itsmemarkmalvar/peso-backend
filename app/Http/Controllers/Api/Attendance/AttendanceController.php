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

        // Get today's schedule
        $dayOfWeek = now()->dayOfWeek;
        $schedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $clockInTime = now();
        $isLate = false;

        // Check if late based on schedule (grace period from system settings)
        if ($schedule) {
            $scheduledStart = now()->setTimeFromTimeString($schedule->start_time);
            $isLate = $clockInTime->gt($scheduledStart->addMinutes($settings->grace_period_minutes));
        }

        try {
            DB::beginTransaction();

            if ($existing) {
                // Update existing record
                $existing->update([
                    'clock_in_time' => $clockInTime,
                    'clock_in_photo' => $photoPath,
                    'location_lat' => $request->location_lat,
                    'location_lng' => $request->location_lng,
                    'location_address' => $locationAddress,
                    'geofence_location_id' => $geofenceLocation?->id,
                    'clock_in_method' => 'web',
                    'is_late' => $isLate,
                ]);
                $attendance = $existing;
            } else {
                // Create new record
                $attendance = Attendance::create([
                    'intern_id' => $intern->id,
                    'date' => $today,
                    'clock_in_time' => $clockInTime,
                    'clock_in_photo' => $photoPath,
                    'location_lat' => $request->location_lat,
                    'location_lng' => $request->location_lng,
                    'location_address' => $locationAddress,
                    'geofence_location_id' => $geofenceLocation?->id,
                    'clock_in_method' => 'web',
                    'status' => AttendanceStatus::PENDING,
                    'is_late' => $isLate,
                ]);
            }

            DB::commit();

            return $this->success([
                'attendance' => $attendance->load(['intern', 'geofenceLocation']),
                'message' => $isLate ? 'Clocked in (late)' : 'Clocked in successfully',
            ], $isLate ? 'Clocked in (late)' : 'Clocked in successfully');
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

        // Calculate total hours
        $clockOutTime = now();
        $totalMinutes = $attendance->clock_in_time->diffInMinutes($clockOutTime);
        
        // Subtract break time if exists
        if ($attendance->break_start && $attendance->break_end) {
            $breakMinutes = $attendance->break_start->diffInMinutes($attendance->break_end);
            $totalMinutes -= $breakMinutes;
        }
        
        $totalHours = round($totalMinutes / 60, 2);

        // Get today's schedule
        $dayOfWeek = now()->dayOfWeek;
        $schedule = Schedule::where('intern_id', $intern->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        $isUndertime = false;
        $isOvertime = false;

        // Check undertime/overtime based on schedule (30 min tolerance for overtime)
        if ($schedule) {
            $scheduledStart = now()->setTimeFromTimeString($schedule->start_time);
            $scheduledEnd = now()->setTimeFromTimeString($schedule->end_time);
            $scheduledHours = $scheduledEnd->diffInMinutes($scheduledStart) / 60;
            
            if ($schedule->break_duration > 0) {
                $scheduledHours -= ($schedule->break_duration / 60);
            }

            if ($totalHours < $scheduledHours - 0.5) { // 30 minute undertime tolerance
                $isUndertime = true;
            } elseif ($totalHours > $scheduledHours + 0.5) { // 30 minute overtime threshold
                $isOvertime = true;
            }
        }

        try {
            DB::beginTransaction();

            $attendance->update([
                'clock_out_time' => $clockOutTime,
                'clock_out_photo' => $photoPath,
                'location_lat' => $request->location_lat,
                'location_lng' => $request->location_lng,
                'location_address' => $locationAddress,
                'geofence_location_id' => $geofenceLocation?->id,
                'total_hours' => $totalHours,
                'is_undertime' => $isUndertime,
                'is_overtime' => $isOvertime,
            ]);

            DB::commit();

            return $this->success([
                'attendance' => $attendance->load(['intern', 'geofenceLocation']),
                'total_hours' => $totalHours,
                'message' => 'Clocked out successfully',
            ], 'Clocked out successfully');
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

        $breakStartTime = now();
        try {
            $attendance->update(['break_start' => $breakStartTime]);
            return $this->success([
                'attendance' => $attendance->fresh(['intern', 'geofenceLocation']),
                'message' => 'Break started',
            ], 'Break started');
        } catch (\Exception $e) {
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

        $breakEndTime = now();
        try {
            $attendance->update(['break_end' => $breakEndTime]);
            return $this->success([
                'attendance' => $attendance->fresh(['intern', 'geofenceLocation']),
                'message' => 'Break ended',
            ], 'Break ended');
        } catch (\Exception $e) {
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
     * Get attendance history
     */
    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
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
