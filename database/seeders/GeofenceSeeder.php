<?php

namespace Database\Seeders;

use App\Models\GeofenceLocation;
use Illuminate\Database\Seeder;

class GeofenceSeeder extends Seeder
{
    /**
     * Seed at least one geofence so clock-in validation and Live Locations work.
     */
    public function run(): void
    {
        GeofenceLocation::updateOrCreate(
            ['name' => 'Cabuyao City Hall'],
            [
                'address' => 'Cabuyao City Hall, Laguna',
                'latitude' => 14.2486,
                'longitude' => 121.1258,
                'radius_meters' => 100,
                'is_active' => true,
            ]
        );
    }
}
