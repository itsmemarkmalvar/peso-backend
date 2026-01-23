<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Public Employment Service Office (PESO)',
                'code' => 'PESO',
                'description' => 'Main PESO office handling employment services',
                'is_active' => true,
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'description' => 'Human Resources Department',
                'is_active' => true,
            ],
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'description' => 'Information Technology Department',
                'is_active' => true,
            ],
            [
                'name' => 'Administration',
                'code' => 'ADMIN',
                'description' => 'Administrative Services',
                'is_active' => true,
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Finance and Accounting Department',
                'is_active' => true,
            ],
            [
                'name' => 'Legal',
                'code' => 'LEGAL',
                'description' => 'Legal Affairs Department',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
