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
        // Seed departments first
        $this->call(DepartmentSeeder::class);

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
                'school' => 'Laguna State Polytechnic University',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'phone' => '09123456789',
                'emergency_contact_name' => 'Maria Abamonga',
                'emergency_contact_phone' => '09123456780',
                'company_name' => 'LCR',
                'supervisor_name' => 'Juan Dela Cruz',
            ],
            [
                'name' => 'Aidalla, James Patrick C.',
                'email' => 'james@example.com',
                'username' => 'james',
                'student_id' => 'OJT-2026-002',
                'school' => 'University of the Philippines Los Baños',
                'course' => 'BS Computer Science',
                'year_level' => '3rd Year',
                'phone' => '09123456790',
                'emergency_contact_name' => 'John Aidalla',
                'emergency_contact_phone' => '09123456791',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Maria Santos',
            ],
            [
                'name' => 'Alimagno, Rio Myca P.',
                'email' => 'rio@example.com',
                'username' => 'rio',
                'student_id' => 'OJT-2026-003',
                'school' => 'De La Salle University',
                'course' => 'BS Information Systems',
                'year_level' => '4th Year',
                'phone' => '09123456792',
                'emergency_contact_name' => 'Rosa Alimagno',
                'emergency_contact_phone' => '09123456793',
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Carla Reyes',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'testuser',
                'student_id' => 'OJT-TEST-001',
                'school' => 'Test University',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'phone' => '09123456794',
                'emergency_contact_name' => 'Test Contact',
                'emergency_contact_phone' => '09123456795',
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
                'school' => 'Sample University',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'phone' => sprintf('0912345%04d', $i),
                'emergency_contact_name' => "Emergency Contact {$i}",
                'emergency_contact_phone' => sprintf('0912345%04d', $i + 1000),
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
                    'school' => $internData['school'],
                    'course' => $internData['course'],
                    'year_level' => $internData['year_level'],
                    'phone' => $internData['phone'],
                    'emergency_contact_name' => $internData['emergency_contact_name'],
                    'emergency_contact_phone' => $internData['emergency_contact_phone'],
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
