<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Intern;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed default admin account (idempotent)
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('Admin123'),
                'role' => \App\Enums\UserRole::ADMIN,
                'status' => 'active',
            ]
        );

        // Seed sample OJT (intern) accounts with linked intern profiles
        $interns = [
            [
                'name' => 'Abamonga, Angelica Lou P.',
                'email' => 'angelica@example.com',
                'username' => 'angelica',
                'student_id' => 'OJT-2026-001',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'company_name' => 'LCR',
                'supervisor_name' => 'Juan Dela Cruz',
            ],
            [
                'name' => 'Aidalla, James Patrick C.',
                'email' => 'james@example.com',
                'username' => 'james',
                'student_id' => 'OJT-2026-002',
                'course' => 'BS Computer Science',
                'year_level' => '3rd Year',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Maria Santos',
            ],
            [
                'name' => 'Alimagno, Rio Myca P.',
                'email' => 'rio@example.com',
                'username' => 'rio',
                'student_id' => 'OJT-2026-003',
                'course' => 'BS Information Systems',
                'year_level' => '4th Year',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Carla Reyes',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'testuser',
                'student_id' => 'OJT-TEST-001',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'System Admin',
            ],
        ];

        // Generate additional sample OJT users so we have at least 50
        for ($i = 4; $i <= 50; $i++) {
            $interns[] = [
                'name' => "Sample Intern {$i}",
                'email' => "ojt{$i}@example.com",
                'username' => "ojt{$i}",
                'student_id' => sprintf('OJT-2026-%03d', $i),
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'System Admin',
            ];
        }

        foreach ($interns as $internData) {
            $user = User::updateOrCreate(
                ['email' => $internData['email']],
                [
                    'name' => $internData['name'],
                    'username' => $internData['username'],
                    // Use a simple default password for seeded accounts
                    'password' => Hash::make('Password123'),
                    'role' => \App\Enums\UserRole::INTERN,
                    'status' => 'active',
                ]
            );

            Intern::updateOrCreate(
                ['student_id' => $internData['student_id']],
                [
                    'user_id' => $user->id,
                    'full_name' => $internData['name'],
                    'course' => $internData['course'],
                    'year_level' => $internData['year_level'],
                    'company_name' => $internData['company_name'],
                    'supervisor_name' => $internData['supervisor_name'],
                    'supervisor_email' => null,
                    'supervisor_contact' => null,
                    'start_date' => now()->subWeeks(2)->toDateString(),
                    'end_date' => now()->addMonths(3)->toDateString(),
                    'is_active' => true,
                ]
            );
        }
    }
}
