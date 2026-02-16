<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove unused intern columns: year_level, company_name, supervisor_contact.
     * These fields are not collected or used meaningfully in the system.
     */
    public function up(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            $table->dropColumn(['year_level', 'company_name', 'supervisor_contact']);
        });
    }

    public function down(): void
    {
        Schema::table('interns', function (Blueprint $table) {
            $table->string('year_level', 50)->nullable()->after('course');
            $table->string('company_name')->nullable()->after('weekly_availability');
            $table->string('supervisor_contact', 50)->nullable()->after('supervisor_email');
        });
    }
};
