<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'grace_period_minutes',
        'verification_gps',
        'verification_selfie',
        'default_lunch_break_start',
        'default_lunch_break_end',
    ];

    protected $casts = [
        'grace_period_minutes' => 'integer',
        'verification_gps' => 'boolean',
        'verification_selfie' => 'boolean',
    ];

    /**
     * Get the single system settings row (singleton).
     */
    public static function get(): self
    {
        $setting = self::first();
        if (!$setting) {
            $setting = self::create([
                'grace_period_minutes' => 10,
                'verification_gps' => true,
                'verification_selfie' => true,
            ]);
        }
        return $setting;
    }
}
