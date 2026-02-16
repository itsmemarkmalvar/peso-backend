<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GeofenceSeeder extends Seeder
{
    /**
     * No initial geofence is seeded to avoid data discrepancy.
     * Geofence locations are created by admins via the dashboard.
     */
    public function run(): void
    {
        // Intentionally empty.
    }
}
