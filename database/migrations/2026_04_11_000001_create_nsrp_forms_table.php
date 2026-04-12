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
        Schema::create('nsrp_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('personal_information');
            $table->json('job_preferences');
            $table->json('language_proficiency');
            $table->json('educational_background');
            $table->json('technical_vocational_training');
            $table->json('eligibility_license');
            $table->json('work_experience');
            $table->json('other_skills');
            $table->json('certification');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nsrp_forms');
    }
};
