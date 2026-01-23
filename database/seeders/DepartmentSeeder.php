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
                'name' => 'Office of the City Mayor',
                'code' => 'OCM',
                'description' => 'Office of the City Mayor',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Vice Mayor',
                'code' => 'OCVM',
                'description' => 'Office of the City Vice Mayor',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the Sangguniang Panlungsod Secretariat',
                'code' => 'OSPS',
                'description' => 'Office of the Sangguniang Panlungsod Secretariat',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Accountant',
                'code' => 'OCA',
                'description' => 'Office of the City Accountant',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Administrator',
                'code' => 'OCAD',
                'description' => 'Office of the City Administrator',
                'is_active' => true,
            ],
            [
                'name' => 'Human Resource Management Office',
                'code' => 'HRMO',
                'description' => 'Human Resource Management Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Assessor',
                'code' => 'OCAS',
                'description' => 'Office of the City Assessor',
                'is_active' => true,
            ],
            [
                'name' => 'Business Permit and Licensing Office',
                'code' => 'BPLO',
                'description' => 'Business Permit and Licensing Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Budget',
                'code' => 'OCB',
                'description' => 'Office of the City Budget',
                'is_active' => true,
            ],
            [
                'name' => 'Youth Dev\'t Office/Sports',
                'code' => 'YDO',
                'description' => 'Youth Development Office/Sports',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Agriculturist',
                'code' => 'OCAG',
                'description' => 'Office of the City Agriculturist',
                'is_active' => true,
            ],
            [
                'name' => 'City Cooperatives and Livelihood Office',
                'code' => 'CCLO',
                'description' => 'City Cooperatives and Livelihood Office',
                'is_active' => true,
            ],
            [
                'name' => 'City Civil Registrar',
                'code' => 'CCR',
                'description' => 'City Civil Registrar',
                'is_active' => true,
            ],
            [
                'name' => 'City Disaster Risk Reduction and Management Office',
                'code' => 'CDRRMO',
                'description' => 'City Disaster Risk Reduction and Management Office',
                'is_active' => true,
            ],
            [
                'name' => 'City Environment and Natural Resources Office',
                'code' => 'CENRO',
                'description' => 'City Environment and Natural Resources Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Health Officer I',
                'code' => 'OCHO1',
                'description' => 'Office of the City Health Officer I',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Health Officer II',
                'code' => 'OCHO2',
                'description' => 'Office of the City Health Officer II',
                'is_active' => true,
            ],
            [
                'name' => 'City Information Office',
                'code' => 'CIO',
                'description' => 'City Information Office',
                'is_active' => true,
            ],
            [
                'name' => 'Person with Disability Affairs Office',
                'code' => 'PDAO',
                'description' => 'Person with Disability Affairs Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Architect',
                'code' => 'OCAR',
                'description' => 'Office of the City Architect',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Veterinarian',
                'code' => 'OCV',
                'description' => 'Office of the City Veterinarian',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Planning and Development Officer',
                'code' => 'OCPDO',
                'description' => 'Office of the City Planning and Development Officer',
                'is_active' => true,
            ],
            [
                'name' => 'City Legal Office',
                'code' => 'CLO',
                'description' => 'City Legal Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the Special Services',
                'code' => 'OSS',
                'description' => 'Office of the Special Services',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Building Official',
                'code' => 'OCBO',
                'description' => 'Office of the City Building Official',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Engineer',
                'code' => 'OCE',
                'description' => 'Office of the City Engineer',
                'is_active' => true,
            ],
            [
                'name' => 'Public Employment Service Office',
                'code' => 'PESO',
                'description' => 'Public Employment Service Office',
                'is_active' => true,
            ],
            [
                'name' => 'City Population Office',
                'code' => 'CPO',
                'description' => 'City Population Office',
                'is_active' => true,
            ],
            [
                'name' => 'Public Order and Safety Office',
                'code' => 'POSO',
                'description' => 'Public Order and Safety Office',
                'is_active' => true,
            ],
            [
                'name' => 'City General Services Office',
                'code' => 'CGSO',
                'description' => 'City General Services Office',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Treasurer',
                'code' => 'OCT',
                'description' => 'Office of the City Treasurer',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Tourism',
                'code' => 'OCTO',
                'description' => 'Office of the City Tourism',
                'is_active' => true,
            ],
            [
                'name' => 'Cabuyao City Hospital',
                'code' => 'CCH',
                'description' => 'Cabuyao City Hospital',
                'is_active' => true,
            ],
            [
                'name' => 'City Social Welfare and Development Office',
                'code' => 'CSWDO',
                'description' => 'City Social Welfare and Development Office',
                'is_active' => true,
            ],
            [
                'name' => 'City Slaughterhouse Office',
                'code' => 'CSO',
                'description' => 'City Slaughterhouse Office',
                'is_active' => true,
            ],
            [
                'name' => 'Systems Programming Infotech Department',
                'code' => 'SPID',
                'description' => 'Systems Programming Infotech Department',
                'is_active' => true,
            ],
            [
                'name' => 'City Urban Development and Housing Affairs Office',
                'code' => 'CUDHAO',
                'description' => 'City Urban Development and Housing Affairs Office',
                'is_active' => true,
            ],
            [
                'name' => 'Department of Trade and Industry',
                'code' => 'DTI',
                'description' => 'Department of Trade and Industry',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the City Market',
                'code' => 'OCMKT',
                'description' => 'Office of the City Market',
                'is_active' => true,
            ],
            [
                'name' => 'Office of the Senior Citizen Affairs',
                'code' => 'OSCA',
                'description' => 'Office of the Senior Citizen Affairs',
                'is_active' => true,
            ],
        ];

        // Use updateOrCreate to avoid duplicates if seeder is run multiple times
        // Check by code first (since code is unique), then by name as fallback
        foreach ($departments as $department) {
            // First try to find by code (if code exists)
            if (!empty($department['code'])) {
                $existing = Department::where('code', $department['code'])->first();
                if ($existing) {
                    // Update existing department with same code
                    $existing->update($department);
                    continue;
                }
            }
            
            // If no match by code, try by name
            $existing = Department::where('name', $department['name'])->first();
            if ($existing) {
                // Update existing department with same name
                $existing->update($department);
            } else {
                // Create new department
                Department::create($department);
            }
        }
    }
}
