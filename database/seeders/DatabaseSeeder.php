<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed departments first
        $this->call(DepartmentSeeder::class);

        // Seed admin and supervisor accounts (idempotent)
        $this->call(CreateAdminAndCoordinatorSeeder::class);

        // Seed comprehensive intern data with schedules, attendance, and leaves
        $this->call(InternSeeder::class);
    }
}
