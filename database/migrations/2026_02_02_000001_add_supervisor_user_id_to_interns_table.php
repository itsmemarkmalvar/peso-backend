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
        Schema::table('interns', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete()
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('interns', 'supervisor_user_id')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE interns DROP CONSTRAINT IF EXISTS interns_supervisor_user_id_foreign"
            );
            Schema::table('interns', function (Blueprint $table) {
                $table->dropColumn('supervisor_user_id');
            });
            return;
        }

        Schema::table('interns', function (Blueprint $table) {
            $table->dropForeign(['supervisor_user_id']);
            $table->dropColumn('supervisor_user_id');
        });
    }
};
