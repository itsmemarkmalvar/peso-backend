<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Enums\UserRole;
use App\Models\User;

class CreateAdminAndCoordinatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('Admin123'),
                'role' => UserRole::ADMIN,
                'status' => 'active',
            ]
        );

        $supervisors = [
            [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan.delacruz@cabuyao.gov.ph',
                'username' => 'juan.delacruz',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@cabuyao.gov.ph',
                'username' => 'maria.santos',
            ],
            [
                'name' => 'Carla Reyes',
                'email' => 'carla.reyes@cabuyao.gov.ph',
                'username' => 'carla.reyes',
            ],
            [
                'name' => 'Roberto Garcia',
                'email' => 'roberto.garcia@cabuyao.gov.ph',
                'username' => 'roberto.garcia',
            ],
            [
                'name' => 'Luis Mendoza',
                'email' => 'luis.mendoza@cabuyao.gov.ph',
                'username' => 'luis.mendoza',
            ],
            [
                'name' => 'Carmen Villanueva',
                'email' => 'carmen.villanueva@cabuyao.gov.ph',
                'username' => 'carmen.villanueva',
            ],
            [
                'name' => 'Ricardo Torres',
                'email' => 'ricardo.torres@cabuyao.gov.ph',
                'username' => 'ricardo.torres',
            ],
            [
                'name' => 'Elena Ramos',
                'email' => 'elena.ramos@cabuyao.gov.ph',
                'username' => 'elena.ramos',
            ],
        ];

        foreach ($supervisors as $supervisor) {
            User::updateOrCreate(
                ['email' => $supervisor['email']],
                [
                    'name' => $supervisor['name'],
                    'username' => $supervisor['username'],
                    'password' => Hash::make('Supervisor123'),
                    'role' => UserRole::SUPERVISOR,
                    'status' => 'active',
                ]
            );
        }
    }
}
