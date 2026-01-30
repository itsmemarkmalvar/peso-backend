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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('grace_period_minutes')->default(10);
            $table->unsignedSmallInteger('minimum_overtime_minutes')->default(30);
            $table->boolean('verification_gps')->default(true);
            $table->boolean('verification_selfie')->default(true);
            $table->boolean('verification_qr')->default(true);
            $table->boolean('verification_device_fingerprint')->default(false);
            $table->timestamps();
        });

        // Insert default row
        DB::table('system_settings')->insert([
            'grace_period_minutes' => 10,
            'minimum_overtime_minutes' => 30,
            'verification_gps' => true,
            'verification_selfie' => true,
            'verification_qr' => true,
            'verification_device_fingerprint' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
