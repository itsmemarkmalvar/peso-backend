<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\GeofenceLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeofenceLocationsController extends BaseController
{
    /**
     * List all geofence locations
     * Admin/Supervisor: all locations
     * Intern/GIP: only active locations (for clock-in UI)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeOnly = $request->boolean('active_only', false);

        $query = GeofenceLocation::query();

        // Intern/GIP can only see active geofences
        if ($user->isIntern() || $user->isGip()) {
            $query->where('is_active', true);
        } elseif ($activeOnly) {
            // Admin/Supervisor can filter by active_only if requested
            $query->where('is_active', true);
        }

        $locations = $query->orderBy('name', 'asc')->get();

        return $this->success($locations, 'Geofence locations retrieved successfully');
    }

    /**
     * Get a single geofence location
     * Admin/Supervisor: any location
     * Intern/GIP: only active locations
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $query = GeofenceLocation::query()->where('id', $id);

        // Intern/GIP can only see active geofences
        if ($user->isIntern() || $user->isGip()) {
            $query->where('is_active', true);
        }

        $location = $query->first();

        if (!$location) {
            return $this->notFound('Geofence location not found');
        }

        return $this->success($location, 'Geofence location retrieved successfully');
    }

    /**
     * Create a new geofence location
     * Admin/Supervisor only
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin and supervisor can create geofences
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can create geofence locations');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:5000',
            'is_active' => 'sometimes|boolean',
        ]);

        $location = GeofenceLocation::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'radius_meters' => $validated['radius_meters'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($location, 'Geofence location created successfully', 201);
    }

    /**
     * Update a geofence location
     * Admin/Supervisor only
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only admin and supervisor can update geofences
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can update geofence locations');
        }

        $location = GeofenceLocation::find($id);

        if (!$location) {
            return $this->notFound('Geofence location not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'radius_meters' => 'sometimes|integer|min:10|max:5000',
            'is_active' => 'sometimes|boolean',
        ]);

        $location->update($validated);

        return $this->success($location, 'Geofence location updated successfully');
    }

    /**
     * Delete a geofence location
     * Admin/Supervisor only
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only admin and supervisor can delete geofences
        if (!$user->isAdmin() && !$user->isSupervisor()) {
            return $this->forbidden('Only administrators and supervisors can delete geofence locations');
        }

        $location = GeofenceLocation::find($id);

        if (!$location) {
            return $this->notFound('Geofence location not found');
        }

        // Check if any attendance records reference this geofence
        // Using DB query directly since Attendance model may not exist yet
        $hasAttendance = DB::table('attendance')
            ->where('geofence_location_id', $location->id)
            ->exists();

        if ($hasAttendance) {
            // Soft delete: set is_active to false instead of hard delete
            $location->update(['is_active' => false]);
            return $this->success(null, 'Geofence location deactivated (has attendance records)');
        }

        // Hard delete if no attendance records
        $location->delete();

        return $this->success(null, 'Geofence location deleted successfully');
    }
}
