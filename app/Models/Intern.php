<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intern extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'full_name',
        'school',
        'course',
        'year_level',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'required_hours',
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
        'onboarded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
