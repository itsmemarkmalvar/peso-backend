<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the status enum/check to include 'pending'
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status in ('active','inactive','suspended','pending'))"
            );
            return;
        }

        // MySQL uses ENUM type
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pending' from the enum/check
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status in ('active','inactive','suspended'))"
            );
            return;
        }

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active'");
    }
};
