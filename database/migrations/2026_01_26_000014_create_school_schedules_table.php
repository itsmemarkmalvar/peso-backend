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
        Schema::create('school_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('intern_id')
                ->constrained('interns')
                ->cascadeOnDelete();

            // 0 = Sunday ... 6 = Saturday
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->unique(['intern_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_schedules');
    }
};
