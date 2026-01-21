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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();

            $table->foreignId('intern_id')
                ->constrained('interns')
                ->cascadeOnDelete();

            $table->foreignId('geofence_location_id')
                ->nullable()
                ->constrained('geofence_locations')
                ->nullOnDelete();

            $table->date('date');

            $table->timestamp('clock_in_time')->nullable();
            $table->timestamp('clock_out_time')->nullable();
            $table->timestamp('break_start')->nullable();
            $table->timestamp('break_end')->nullable();

            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();
            $table->text('location_address')->nullable();

            $table->string('clock_in_photo')->nullable();
            $table->string('clock_out_photo')->nullable();
            $table->enum('clock_in_method', ['web', 'qr_code', 'manual'])->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();

            $table->boolean('is_late')->default(false);
            $table->boolean('is_undertime')->default(false);
            $table->boolean('is_overtime')->default(false);

            $table->timestamps();

            $table->unique(['intern_id', 'date']);
            $table->index(['intern_id', 'date']);
            $table->index(['approved_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};

