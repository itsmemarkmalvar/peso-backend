<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NsrpForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_information',
        'job_preferences',
        'language_proficiency',
        'educational_background',
        'technical_vocational_training',
        'eligibility_license',
        'work_experience',
        'other_skills',
        'certification',
        'is_completed',
        'submitted_at',
    ];

    protected $casts = [
        'personal_information' => 'array',
        'job_preferences' => 'array',
        'language_proficiency' => 'array',
        'educational_background' => 'array',
        'technical_vocational_training' => 'array',
        'eligibility_license' => 'array',
        'work_experience' => 'array',
        'other_skills' => 'array',
        'certification' => 'array',
        'is_completed' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
