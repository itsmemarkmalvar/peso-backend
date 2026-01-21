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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendance_id')
                ->constrained('attendance')
                ->cascadeOnDelete();

            $table->foreignId('approver_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('status', ['pending', 'approved', 'rejected'])->index();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(['attendance_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};

