<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'intern_id',
        'day_of_week',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the intern that owns this school schedule
     */
    public function intern()
    {
        return $this->belongsTo(Intern::class);
    }
}
