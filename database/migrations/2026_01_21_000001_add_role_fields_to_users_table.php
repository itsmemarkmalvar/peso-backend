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
        Schema::table('users', function (Blueprint $table) {
            // Keep Laravel default `name` column; add username + RBAC fields used by the API layer.
            $table->string('username')->nullable()->unique()->after('name');

            $table->enum('role', ['admin', 'intern', 'supervisor', 'coordinator'])
                ->default('intern')
                ->after('password')
                ->index();

            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->after('role')
                ->index();

            $table->string('device_fingerprint')->nullable()->after('status')->index();
            $table->timestamp('last_login_at')->nullable()->after('device_fingerprint');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'role',
                'status',
                'device_fingerprint',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};

