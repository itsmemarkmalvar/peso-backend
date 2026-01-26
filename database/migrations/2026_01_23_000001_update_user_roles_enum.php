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
        // Step 1: Temporarily expand enum to include both old and new roles
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'coordinator', 'supervisor', 'gip', 'intern') NOT NULL DEFAULT 'intern'");
        
        // Step 2: Update any existing 'coordinator' roles to 'supervisor'
        DB::table('users')
            ->where('role', 'coordinator')
            ->update(['role' => 'supervisor']);
        
        // Step 3: Now remove 'coordinator' from enum, keeping only the new roles
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'supervisor', 'gip', 'intern') NOT NULL DEFAULT 'intern'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, update any 'supervisor' roles back to 'coordinator'
        DB::table('users')
            ->where('role', 'supervisor')
            ->update(['role' => 'coordinator']);

        // Revert back to old roles: admin, coordinator, intern
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'coordinator', 'intern') NOT NULL DEFAULT 'intern'");
    }
};
