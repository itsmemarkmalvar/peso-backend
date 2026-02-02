<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intern extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'supervisor_user_id',
        'student_id',
        'full_name',
        'school',
        'course',
        'year_level',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'required_hours',
        'weekly_availability',
        'company_name',
        'supervisor_name',
        'supervisor_email',
        'supervisor_contact',
        'start_date',
        'end_date',
        'is_active',
        'onboarded_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'required_hours' => 'integer',
        'weekly_availability' => 'array',
        'onboarded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    /**
     * Get schedules for this intern
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Get attendance records for this intern
     */
    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get school schedules for this intern
     */
    public function schoolSchedules()
    {
        return $this->hasMany(SchoolSchedule::class);
    }
}
