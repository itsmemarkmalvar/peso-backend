<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'intern_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_duration',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time' => 'string',
        'end_time' => 'string',
        'break_duration' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the intern that owns this schedule
     */
    public function intern()
    {
        return $this->belongsTo(Intern::class);
    }
}
