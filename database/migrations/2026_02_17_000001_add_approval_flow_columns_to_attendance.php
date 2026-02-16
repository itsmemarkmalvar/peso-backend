<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds columns for approval flow: late, GPS correction, early out, overtime.
     * effective_clock_in/out store scheduled times used for total_hours calculation.
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('effective_clock_in_time')->nullable()->after('clock_in_time');
            $table->timestamp('effective_clock_out_time')->nullable()->after('clock_out_time');
            $table->string('approval_type', 50)->nullable()->after('status')->index();
            $table->boolean('is_gps_correction')->default(false)->after('is_overtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'effective_clock_in_time',
                'effective_clock_out_time',
                'approval_type',
                'is_gps_correction',
            ]);
        });
    }
};
