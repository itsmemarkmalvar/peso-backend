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
        // Modify the status ENUM to include 'pending'
        // MySQL doesn't support direct ENUM modification, so we use raw SQL
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pending' from the ENUM (revert to original)
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active'");
    }
};
