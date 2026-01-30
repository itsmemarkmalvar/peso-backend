<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\GeofenceLocation;
use App\Models\Intern;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\SchoolSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class InternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a geofence location for attendance
        $geofenceLocation = GeofenceLocation::first();
        if (!$geofenceLocation) {
            $geofenceLocation = GeofenceLocation::create([
                'name' => 'Cabuyao City Hall',
                'address' => 'Cabuyao City Hall, Laguna, Philippines',
                'latitude' => 14.2486,
                'longitude' => 121.1258,
                'radius_meters' => 100,
                'is_active' => true,
            ]);
        }

        // Get a supervisor/admin user for approvals
        $approver = User::whereIn('role', [UserRole::ADMIN, UserRole::SUPERVISOR])->first();
        if (!$approver) {
            $approver = User::where('email', 'admin@example.com')->first();
        }

        // Realistic Filipino intern data
        $internsData = [
            [
                'name' => 'Abamonga, Angelica Lou P.',
                'email' => 'angelica.abamonga@example.com',
                'username' => 'angelica.abamonga',
                'student_id' => 'OJT-2026-001',
                'school' => 'Laguna State Polytechnic University',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'phone' => '09123456789',
                'emergency_contact_name' => 'Maria Abamonga',
                'emergency_contact_phone' => '09123456780',
                'required_hours' => 400,
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Juan Dela Cruz',
                'supervisor_email' => 'juan.delacruz@cabuyao.gov.ph',
                'supervisor_contact' => '09123456700',
                'work_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60, // 1 hour
                'school_days' => [], // No school days
            ],
            [
                'name' => 'Aidalla, James Patrick C.',
                'email' => 'james.aidalla@example.com',
                'username' => 'james.aidalla',
                'student_id' => 'OJT-2026-002',
                'school' => 'University of the Philippines Los Baños',
                'course' => 'BS Computer Science',
                'year_level' => '3rd Year',
                'phone' => '09123456790',
                'emergency_contact_name' => 'John Aidalla',
                'emergency_contact_phone' => '09123456791',
                'required_hours' => 200,
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Maria Santos',
                'supervisor_email' => 'maria.santos@cabuyao.gov.ph',
                'supervisor_contact' => '09123456701',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'break_duration' => 60,
                'school_days' => [1, 3, 5], // Monday, Wednesday, Friday (has classes)
            ],
            [
                'name' => 'Alimagno, Rio Myca P.',
                'email' => 'rio.alimagno@example.com',
                'username' => 'rio.alimagno',
                'student_id' => 'OJT-2026-003',
                'school' => 'De La Salle University',
                'course' => 'BS Information Systems',
                'year_level' => '4th Year',
                'phone' => '09123456792',
                'emergency_contact_name' => 'Rosa Alimagno',
                'emergency_contact_phone' => '09123456793',
                'required_hours' => 400,
                'company_name' => 'HRMO',
                'supervisor_name' => 'Carla Reyes',
                'supervisor_email' => 'carla.reyes@cabuyao.gov.ph',
                'supervisor_contact' => '09123456702',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Bautista, Maria Cristina L.',
                'email' => 'maria.bautista@example.com',
                'username' => 'maria.bautista',
                'student_id' => 'OJT-2026-004',
                'school' => 'Polytechnic University of the Philippines',
                'course' => 'BS Business Administration',
                'year_level' => '3rd Year',
                'phone' => '09123456794',
                'emergency_contact_name' => 'Pedro Bautista',
                'emergency_contact_phone' => '09123456795',
                'required_hours' => 200,
                'company_name' => 'Office of the City Mayor',
                'supervisor_name' => 'Roberto Garcia',
                'supervisor_email' => 'roberto.garcia@cabuyao.gov.ph',
                'supervisor_contact' => '09123456703',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [2, 4], // Tuesday, Thursday
            ],
            [
                'name' => 'Cruz, John Michael R.',
                'email' => 'john.cruz@example.com',
                'username' => 'john.cruz',
                'student_id' => 'OJT-2026-005',
                'school' => 'Mapúa University',
                'course' => 'BS Computer Engineering',
                'year_level' => '4th Year',
                'phone' => '09123456796',
                'emergency_contact_name' => 'Ana Cruz',
                'emergency_contact_phone' => '09123456797',
                'required_hours' => 400,
                'company_name' => 'IT Department',
                'supervisor_name' => 'Luis Mendoza',
                'supervisor_email' => 'luis.mendoza@cabuyao.gov.ph',
                'supervisor_contact' => '09123456704',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Dela Cruz, Sarah Jane M.',
                'email' => 'sarah.delacruz@example.com',
                'username' => 'sarah.delacruz',
                'student_id' => 'OJT-2026-006',
                'school' => 'University of Santo Tomas',
                'course' => 'BS Accountancy',
                'year_level' => '4th Year',
                'phone' => '09123456798',
                'emergency_contact_name' => 'Jose Dela Cruz',
                'emergency_contact_phone' => '09123456799',
                'required_hours' => 400,
                'company_name' => 'Office of the City Accountant',
                'supervisor_name' => 'Carmen Villanueva',
                'supervisor_email' => 'carmen.villanueva@cabuyao.gov.ph',
                'supervisor_contact' => '09123456705',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Fernandez, Mark Anthony T.',
                'email' => 'mark.fernandez@example.com',
                'username' => 'mark.fernandez',
                'student_id' => 'OJT-2026-007',
                'school' => 'Ateneo de Manila University',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'phone' => '09123456800',
                'emergency_contact_name' => 'Lourdes Fernandez',
                'emergency_contact_phone' => '09123456801',
                'required_hours' => 200,
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Ricardo Torres',
                'supervisor_email' => 'ricardo.torres@cabuyao.gov.ph',
                'supervisor_contact' => '09123456706',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [1, 3], // Monday, Wednesday
            ],
            [
                'name' => 'Garcia, Patricia Ann S.',
                'email' => 'patricia.garcia@example.com',
                'username' => 'patricia.garcia',
                'student_id' => 'OJT-2026-008',
                'school' => 'Laguna State Polytechnic University',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'phone' => '09123456802',
                'emergency_contact_name' => 'Fernando Garcia',
                'emergency_contact_phone' => '09123456803',
                'required_hours' => 400,
                'company_name' => 'City Information Office',
                'supervisor_name' => 'Elena Ramos',
                'supervisor_email' => 'elena.ramos@cabuyao.gov.ph',
                'supervisor_contact' => '09123456707',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Hernandez, Kevin Paul D.',
                'email' => 'kevin.hernandez@example.com',
                'username' => 'kevin.hernandez',
                'student_id' => 'OJT-2026-009',
                'school' => 'University of the Philippines Los Baños',
                'course' => 'BS Computer Science',
                'year_level' => '3rd Year',
                'phone' => '09123456804',
                'emergency_contact_name' => 'Ramon Hernandez',
                'emergency_contact_phone' => '09123456805',
                'required_hours' => 200,
                'company_name' => 'IT Department',
                'supervisor_name' => 'Luis Mendoza',
                'supervisor_email' => 'luis.mendoza@cabuyao.gov.ph',
                'supervisor_contact' => '09123456704',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [2, 4, 6], // Tuesday, Thursday, Saturday
            ],
            [
                'name' => 'Lopez, Jennifer Rose A.',
                'email' => 'jennifer.lopez@example.com',
                'username' => 'jennifer.lopez',
                'student_id' => 'OJT-2026-010',
                'school' => 'De La Salle University',
                'course' => 'BS Information Systems',
                'year_level' => '4th Year',
                'phone' => '09123456806',
                'emergency_contact_name' => 'Antonio Lopez',
                'emergency_contact_phone' => '09123456807',
                'required_hours' => 400,
                'company_name' => 'HRMO',
                'supervisor_name' => 'Carla Reyes',
                'supervisor_email' => 'carla.reyes@cabuyao.gov.ph',
                'supervisor_contact' => '09123456702',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Martinez, Christian Paul B.',
                'email' => 'christian.martinez@example.com',
                'username' => 'christian.martinez',
                'student_id' => 'OJT-2026-011',
                'school' => 'Polytechnic University of the Philippines',
                'course' => 'BS Business Administration',
                'year_level' => '3rd Year',
                'phone' => '09123456808',
                'emergency_contact_name' => 'Teresa Martinez',
                'emergency_contact_phone' => '09123456809',
                'required_hours' => 200,
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Juan Dela Cruz',
                'supervisor_email' => 'juan.delacruz@cabuyao.gov.ph',
                'supervisor_contact' => '09123456700',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'break_duration' => 60,
                'school_days' => [1, 3, 5],
            ],
            [
                'name' => 'Reyes, Michelle Ann C.',
                'email' => 'michelle.reyes@example.com',
                'username' => 'michelle.reyes',
                'student_id' => 'OJT-2026-012',
                'school' => 'Mapúa University',
                'course' => 'BS Computer Engineering',
                'year_level' => '4th Year',
                'phone' => '09123456810',
                'emergency_contact_name' => 'Carlos Reyes',
                'emergency_contact_phone' => '09123456811',
                'required_hours' => 400,
                'company_name' => 'IT Department',
                'supervisor_name' => 'Luis Mendoza',
                'supervisor_email' => 'luis.mendoza@cabuyao.gov.ph',
                'supervisor_contact' => '09123456704',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Santos, Ryan Joseph E.',
                'email' => 'ryan.santos@example.com',
                'username' => 'ryan.santos',
                'student_id' => 'OJT-2026-013',
                'school' => 'University of Santo Tomas',
                'course' => 'BS Accountancy',
                'year_level' => '4th Year',
                'phone' => '09123456812',
                'emergency_contact_name' => 'Imelda Santos',
                'emergency_contact_phone' => '09123456813',
                'required_hours' => 400,
                'company_name' => 'Office of the City Accountant',
                'supervisor_name' => 'Carmen Villanueva',
                'supervisor_email' => 'carmen.villanueva@cabuyao.gov.ph',
                'supervisor_contact' => '09123456705',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
            [
                'name' => 'Torres, Angela Marie F.',
                'email' => 'angela.torres@example.com',
                'username' => 'angela.torres',
                'student_id' => 'OJT-2026-014',
                'school' => 'Ateneo de Manila University',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'phone' => '09123456814',
                'emergency_contact_name' => 'Manuel Torres',
                'emergency_contact_phone' => '09123456815',
                'required_hours' => 200,
                'company_name' => 'City Information Office',
                'supervisor_name' => 'Elena Ramos',
                'supervisor_email' => 'elena.ramos@cabuyao.gov.ph',
                'supervisor_contact' => '09123456707',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '09:00',
                'work_end' => '18:00',
                'break_duration' => 60,
                'school_days' => [2, 4],
            ],
            [
                'name' => 'Villanueva, Daniel James G.',
                'email' => 'daniel.villanueva@example.com',
                'username' => 'daniel.villanueva',
                'student_id' => 'OJT-2026-015',
                'school' => 'Laguna State Polytechnic University',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'phone' => '09123456816',
                'emergency_contact_name' => 'Rosa Villanueva',
                'emergency_contact_phone' => '09123456817',
                'required_hours' => 400,
                'company_name' => 'PESO Office',
                'supervisor_name' => 'Maria Santos',
                'supervisor_email' => 'maria.santos@cabuyao.gov.ph',
                'supervisor_contact' => '09123456701',
                'work_days' => [1, 2, 3, 4, 5],
                'work_start' => '08:00',
                'work_end' => '17:00',
                'break_duration' => 60,
                'school_days' => [],
            ],
        ];

        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(2); // Start 2 months ago
        $endDate = $now->copy()->addMonths(3); // End 3 months from now

        foreach ($internsData as $index => $internData) {
            // Create user account
            $user = User::updateOrCreate(
                ['email' => $internData['email']],
                [
                    'name' => $internData['name'],
                    'username' => $internData['username'],
                    'password' => Hash::make('Password123'),
                    'role' => UserRole::INTERN,
                    'status' => 'active',
                ]
            );

            // Create intern profile
            $intern = Intern::updateOrCreate(
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
                    'required_hours' => $internData['required_hours'],
                    'company_name' => $internData['company_name'],
                    'supervisor_name' => $internData['supervisor_name'],
                    'supervisor_email' => $internData['supervisor_email'],
                    'supervisor_contact' => $internData['supervisor_contact'],
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'is_active' => true,
                    'onboarded_at' => $startDate->copy()->subDays(rand(1, 7)),
                ]
            );

            // Create work schedules
            foreach ($internData['work_days'] as $dayOfWeek) {
                Schedule::updateOrCreate(
                    [
                        'intern_id' => $intern->id,
                        'day_of_week' => $dayOfWeek,
                    ],
                    [
                        'start_time' => $internData['work_start'],
                        'end_time' => $internData['work_end'],
                        'break_duration' => $internData['break_duration'],
                        'is_active' => true,
                    ]
                );
            }

            // Create school schedules (days they have classes)
            foreach ($internData['school_days'] as $dayOfWeek) {
                SchoolSchedule::updateOrCreate(
                    [
                        'intern_id' => $intern->id,
                        'day_of_week' => $dayOfWeek,
                    ],
                    [
                        'is_active' => true,
                    ]
                );
            }

            // Generate attendance records for the last 45 days
            $attendanceStartDate = $now->copy()->subDays(45);
            $currentDate = $attendanceStartDate->copy();

            while ($currentDate->lte($now)) {
                $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday, 6 = Saturday

                // Only create attendance for work days (not school days)
                if (in_array($dayOfWeek, $internData['work_days']) && 
                    !in_array($dayOfWeek, $internData['school_days'])) {
                    
                    // Skip some days randomly (10% chance of absence)
                    if (rand(1, 100) <= 10) {
                        $currentDate->addDay();
                        continue;
                    }

                    // Determine attendance scenario
                    $scenario = $this->getAttendanceScenario($index);

                    $workStart = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $internData['work_start']);
                    $workEnd = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $internData['work_end']);

                    // Clock in time (with variations)
                    $clockInTime = $workStart->copy();
                    switch ($scenario) {
                        case 'late':
                            $clockInTime->addMinutes(rand(15, 60)); // 15-60 minutes late
                            break;
                        case 'early':
                            $clockInTime->subMinutes(rand(5, 30)); // 5-30 minutes early
                            break;
                        default: // on_time
                            $clockInTime->addMinutes(rand(-5, 5)); // ±5 minutes
                            break;
                    }

                    // Clock out time
                    $clockOutTime = $workEnd->copy();
                    switch ($scenario) {
                        case 'undertime':
                            $clockOutTime->subMinutes(rand(30, 90)); // 30-90 minutes early
                            break;
                        case 'overtime':
                            $clockOutTime->addMinutes(rand(30, 120)); // 30-120 minutes overtime
                            break;
                        default:
                            $clockOutTime->addMinutes(rand(-10, 10)); // ±10 minutes
                            break;
                    }

                    // Break times (lunch break)
                    $breakStart = $clockInTime->copy()->addHours(4)->addMinutes(rand(0, 30));
                    $breakEnd = $breakStart->copy()->addMinutes($internData['break_duration']);

                    // Calculate total hours
                    $totalMinutes = $clockOutTime->diffInMinutes($clockInTime) - $internData['break_duration'];
                    $totalHours = round($totalMinutes / 60, 2);

                    // Determine status (mix of pending, approved, rejected)
                    $statusRand = rand(1, 100);
                    if ($statusRand <= 70) {
                        $status = AttendanceStatus::APPROVED;
                        $approvedBy = $approver?->id;
                        $approvedAt = $currentDate->copy()->addHours(rand(1, 3));
                    } elseif ($statusRand <= 90) {
                        $status = AttendanceStatus::PENDING;
                        $approvedBy = null;
                        $approvedAt = null;
                    } else {
                        $status = AttendanceStatus::REJECTED;
                        $approvedBy = $approver?->id;
                        $approvedAt = $currentDate->copy()->addHours(rand(1, 3));
                    }

                    // Determine if late/undertime/overtime
                    $isLate = $clockInTime->gt($workStart->copy()->addMinutes(15));
                    $isUndertime = $clockOutTime->lt($workEnd->copy()->subMinutes(30));
                    $isOvertime = $clockOutTime->gt($workEnd->copy()->addMinutes(30));

                    Attendance::updateOrCreate(
                        [
                            'intern_id' => $intern->id,
                            'date' => $currentDate->toDateString(),
                        ],
                        [
                            'geofence_location_id' => $geofenceLocation->id,
                            'clock_in_time' => $clockInTime,
                            'clock_out_time' => $clockOutTime,
                            'break_start' => $breakStart,
                            'break_end' => $breakEnd,
                            'location_lat' => 14.2486 + (rand(-100, 100) / 10000), // Slight variation
                            'location_lng' => 121.1258 + (rand(-100, 100) / 10000),
                            'location_address' => 'Cabuyao City Hall, Laguna, Philippines',
                            'clock_in_method' => 'web',
                            'status' => $status->value,
                            'approved_by' => $approvedBy,
                            'approved_at' => $approvedAt,
                            'rejection_reason' => $status === AttendanceStatus::REJECTED 
                                ? 'Incomplete documentation' 
                                : null,
                            'notes' => $scenario !== 'on_time' ? ucfirst($scenario) . ' attendance' : null,
                            'total_hours' => $totalHours,
                            'is_late' => $isLate,
                            'is_undertime' => $isUndertime,
                            'is_overtime' => $isOvertime,
                        ]
                    );
                }

                $currentDate->addDay();
            }

            // Create some leave records (optional, 20% chance per intern)
            if (rand(1, 100) <= 20) {
                $leaveStart = $now->copy()->subDays(rand(5, 30));
                $leaveEnd = $leaveStart->copy()->addDays(rand(1, 3));

                $leaveStatusRand = rand(1, 100);
                if ($leaveStatusRand <= 70) {
                    $leaveStatus = 'approved';
                    $leaveApprovedBy = $approver?->id;
                    $leaveApprovedAt = $leaveStart->copy()->subDays(rand(1, 3));
                } elseif ($leaveStatusRand <= 90) {
                    $leaveStatus = 'pending';
                    $leaveApprovedBy = null;
                    $leaveApprovedAt = null;
                } else {
                    $leaveStatus = 'rejected';
                    $leaveApprovedBy = $approver?->id;
                    $leaveApprovedAt = $leaveStart->copy()->subDays(rand(1, 3));
                }

                Leave::create([
                    'intern_id' => $intern->id,
                    'type' => 'leave',
                    'reason_title' => 'Personal Leave',
                    'status' => $leaveStatus,
                    'start_date' => $leaveStart->toDateString(),
                    'end_date' => $leaveEnd->toDateString(),
                    'notes' => 'Requested personal leave',
                    'rejection_reason' => $leaveStatus === 'rejected' ? 'Insufficient documentation' : null,
                    'approved_by' => $leaveApprovedBy,
                    'approved_at' => $leaveApprovedAt,
                ]);
            }
        }

        $this->command->info('Successfully seeded ' . count($internsData) . ' interns with complete data!');
    }

    /**
     * Get attendance scenario based on intern index for variety
     */
    private function getAttendanceScenario(int $index): string
    {
        $scenarios = ['on_time', 'late', 'early', 'undertime', 'overtime'];
        $scenarioIndex = $index % count($scenarios);
        return $scenarios[$scenarioIndex];
    }
}
