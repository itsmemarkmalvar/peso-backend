<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'intern_id',
        'geofence_location_id',
        'date',
        'clock_in_time',
        'clock_out_time',
        'break_start',
        'break_end',
        'location_lat',
        'location_lng',
        'location_address',
        'clock_in_photo',
        'clock_out_photo',
        'clock_in_method',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'total_hours',
        'is_late',
        'is_undertime',
        'is_overtime',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'location_lat' => 'decimal:8',
        'location_lng' => 'decimal:8',
        'total_hours' => 'decimal:2',
        'is_late' => 'boolean',
        'is_undertime' => 'boolean',
        'is_overtime' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the intern that owns this attendance record
     */
    public function intern()
    {
        return $this->belongsTo(Intern::class);
    }

    /**
     * Get the geofence location for this attendance
     */
    public function geofenceLocation()
    {
        return $this->belongsTo(GeofenceLocation::class);
    }

    /**
     * Get the user who approved this attendance
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get today's schedule for the intern
     */
    public function getTodaySchedule()
    {
        $dayOfWeek = $this->date->dayOfWeek; // 0 = Sunday, 6 = Saturday
        return Schedule::where('intern_id', $this->intern_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();
    }
}
