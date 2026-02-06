<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make address optional for geofence locations.
     * Uses raw SQL to avoid requiring doctrine/dbal.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE geofence_locations MODIFY address TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE geofence_locations ALTER COLUMN address DROP NOT NULL');
        } else {
            // SQLite: recreate column as nullable not needed for SQLite (no strict null on text)
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE geofence_locations MODIFY address TEXT NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE geofence_locations ALTER COLUMN address SET NOT NULL');
        }
    }
};
