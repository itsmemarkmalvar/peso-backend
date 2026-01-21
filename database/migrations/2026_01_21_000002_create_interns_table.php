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

            $table->string('student_id', 50)->unique()->nullable();
            $table->string('full_name');
            $table->string('school');
            $table->string('course');
            $table->string('year_level', 50)->nullable();
            $table->string('phone', 50);
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone', 50);

            $table->unsignedInteger('required_hours')->nullable();
            $table->string('company_name')->nullable()->index();
            $table->string('supervisor_name')->nullable()->index();
            $table->string('supervisor_email')->nullable();
            $table->string('supervisor_contact', 50)->nullable();

            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('onboarded_at')->nullable()->index();

            $table->timestamps();
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
