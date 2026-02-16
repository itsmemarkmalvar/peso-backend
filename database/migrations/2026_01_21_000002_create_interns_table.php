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
        Schema::create('interns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('supervisor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('student_id', 50)->unique()->nullable();

            $table->string('full_name');
            $table->string('school');
            $table->string('course');
            $table->string('year_level', 50)->nullable();
            $table->string('phone', 50);
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone', 50);

            $table->unsignedInteger('required_hours')->nullable();
            $table->json('weekly_availability')->nullable();

            // department_id added in add_department_id migration (departments created later at 000010)

            $table->string('company_name')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_email')->nullable();
            $table->string('supervisor_contact', 50)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('onboarded_at')->nullable();
            $table->string('profile_photo')->nullable();

            $table->timestamps();

            $table->index('student_id');
            $table->index('supervisor_user_id');
            $table->index('company_name');
            $table->index('supervisor_name');
            $table->index(['start_date', 'end_date']);
            $table->index('is_active');
            $table->index('onboarded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interns');
    }
};
