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
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role in ('admin','coordinator','supervisor','gip','intern'))"
            );

            DB::table('users')
                ->where('role', 'coordinator')
                ->update(['role' => 'supervisor']);

            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role in ('admin','supervisor','gip','intern'))"
            );
            return;
        }

        // MySQL enum mutation
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'coordinator', 'supervisor', 'gip', 'intern') NOT NULL DEFAULT 'intern'");

        DB::table('users')
            ->where('role', 'coordinator')
            ->update(['role' => 'supervisor']);

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'supervisor', 'gip', 'intern') NOT NULL DEFAULT 'intern'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::table('users')
                ->where('role', 'supervisor')
                ->update(['role' => 'coordinator']);

            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role in ('admin','coordinator','intern'))"
            );
            return;
        }

        DB::table('users')
            ->where('role', 'supervisor')
            ->update(['role' => 'coordinator']);

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'coordinator', 'intern') NOT NULL DEFAULT 'intern'");
    }
};
