<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'minimum_overtime_minutes',
                'verification_qr',
                'verification_device_fingerprint',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('minimum_overtime_minutes')->default(30)->after('grace_period_minutes');
            $table->boolean('verification_qr')->default(true)->after('verification_selfie');
            $table->boolean('verification_device_fingerprint')->default(false)->after('verification_qr');
        });
    }
};
