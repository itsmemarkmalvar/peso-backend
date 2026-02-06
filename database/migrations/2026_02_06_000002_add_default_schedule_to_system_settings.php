<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('default_lunch_break_start', 5)->default('12:00')->after('verification_selfie');
            $table->string('default_lunch_break_end', 5)->default('13:00')->after('default_lunch_break_start');
        });

        DB::table('system_settings')->update([
            'default_lunch_break_start' => '12:00',
            'default_lunch_break_end' => '13:00',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['default_lunch_break_start', 'default_lunch_break_end']);
        });
    }
};
