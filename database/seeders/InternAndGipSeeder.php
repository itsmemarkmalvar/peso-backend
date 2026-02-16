<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\GeofenceLocation;
use App\Models\Intern;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\SchoolSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class InternAndGipSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Password123';

    /** Number of intern users to create */
    private const INTERN_COUNT = 15;

    /** Number of GIP users to create */
    private const GIP_COUNT = 5;

    /** Past days of attendance to seed (including today) */
    private const ATTENDANCE_DAYS = 8;

    private ?int $supervisorId = null;
    private ?int $geofenceId = null;
    /** @var array<int, int> intern_id => department_id */
    private array $internDepartments = [];

    public function run(): void
    {
        $this->geofenceId = GeofenceLocation::where('is_active', true)->first()?->id;
        if (!$this->geofenceId) {
            Log::warning('InternAndGipSeeder: Run GeofenceSeeder first so attendance can use geofence_location_id.');
        }

        $this->seedSupervisor();
        $departments = Department::where('is_active', true)->pluck('id')->all();
        if (empty($departments)) {
            Log::warning('InternAndGipSeeder: Run DepartmentSeeder first.');
        }

        $internUsers = $this->seedUsers(UserRole::INTERN, self::INTERN_COUNT);
        $gipUsers = $this->seedUsers(UserRole::GIP, self::GIP_COUNT);
        $allUsers = array_merge($internUsers, $gipUsers);

        foreach ($allUsers as $user) {
            $this->seedInternProfile($user, $departments);
        }

        foreach (Intern::all() as $intern) {
            $this->seedSchedules($intern);
            $this->maybeSeedSchoolSchedule($intern);
        }

        $this->seedAttendance($departments);
        $this->seedLeaves();
    }

    private function seedSupervisor(): void
    {
        $deptId = Department::where('is_active', true)->first()?->id;
        $user = User::updateOrCreate(
            ['email' => 'supervisor@example.com'],
            [
                'name' => 'Supervisor One',
                'username' => 'supervisor',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => UserRole::SUPERVISOR,
                'status' => 'active',
                'department_id' => $deptId,
            ]
        );
        $this->supervisorId = $user->id;
    }

    /**
     * @return User[]
     */
    private function seedUsers(UserRole $role, int $count): array
    {
        $created = [];
        $prefix = $role === UserRole::GIP ? 'gip' : 'intern';
        for ($i = 1; $i <= $count; $i++) {
            $email = "{$prefix}{$i}@example.com";
            $name = $role === UserRole::GIP ? "GIP User {$i}" : "Intern User {$i}";
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $email,
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'role' => $role,
                    'status' => 'active',
                ]
            );
            $created[] = $user;
        }
        return $created;
    }

    /**
     * @param array<int> $departmentIds
     */
    private function seedInternProfile(User $user, array $departmentIds): void
    {
        $deptId = $departmentIds[array_rand($departmentIds)] ?? null;
        $studentId = 'STU-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);

        $intern = Intern::updateOrCreate(
            ['user_id' => $user->id],
            [
                'supervisor_user_id' => $this->supervisorId,
                'department_id' => $deptId,
                'student_id' => $studentId,
                'full_name' => $user->name,
                'school' => 'Cabuyao State University',
                'course' => 'BS Information Technology',
                'phone' => '09' . str_pad((string) rand(0, 999999999), 9, '0'),
                'emergency_contact_name' => 'Emergency Contact ' . $user->name,
                'emergency_contact_phone' => '09' . str_pad((string) rand(0, 999999999), 9, '0'),
                'required_hours' => 200,
                'supervisor_name' => 'Supervisor One',
                'supervisor_email' => 'supervisor@example.com',
                'start_date' => now()->subMonths(2)->startOfMonth(),
                'end_date' => now()->addMonths(2)->endOfMonth(),
                'is_active' => true,
                'onboarded_at' => now()->subMonths(2),
            ]
        );
        $this->internDepartments[$intern->id] = $deptId;
    }

    private function seedSchedules(Intern $intern): void
    {
        $days = [1 => '08:00', 2 => '08:00', 3 => '08:00', 4 => '08:00', 5 => '08:00'];
        $endTimes = [1 => '17:00', 2 => '17:00', 3 => '17:00', 4 => '17:00', 5 => '17:00'];
        foreach ($days as $dayOfWeek => $start) {
            Schedule::updateOrCreate(
                ['intern_id' => $intern->id, 'day_of_week' => $dayOfWeek],
                [
                    'start_time' => $start,
                    'end_time' => $endTimes[$dayOfWeek],
                    'break_duration' => 60,
                    'is_active' => true,
                ]
            );
        }
    }

    private function maybeSeedSchoolSchedule(Intern $intern): void
    {
        if (rand(1, 3) !== 1) {
            return;
        }
        SchoolSchedule::updateOrCreate(
            ['intern_id' => $intern->id, 'day_of_week' => 3],
            ['is_active' => true]
        );
    }

    /**
     * @param array<int> $departmentIds
     */
    private function seedAttendance(array $departmentIds): void
    {
        $manila = Carbon::now('Asia/Manila');
        $interns = Intern::where('is_active', true)->get();
        $adminId = User::where('role', UserRole::ADMIN)->first()?->id ?? $this->supervisorId;

        for ($d = 0; $d < self::ATTENDANCE_DAYS; $d++) {
            $date = $manila->copy()->subDays($d);
            $dateStart = $date->copy()->startOfDay();
            $isToday = $date->isToday();

            foreach ($interns as $intern) {
                $dayOfWeek = $date->dayOfWeek;
                if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                    continue;
                }

                $existing = Attendance::where('intern_id', $intern->id)->where('date', $dateStart->toDateString())->first();
                if ($existing) {
                    continue;
                }

                $clockIn = $dateStart->copy()->setTime(8, rand(0, 20), 0);
                $clockOut = null;
                $breakStart = null;
                $breakEnd = null;
                $status = AttendanceStatus::PENDING;
                $approvedBy = null;
                $approvedAt = null;
                $isLate = $clockIn->format('i') > 10;
                $totalHours = null;

                if (!$isToday || rand(1, 2) === 1) {
                    $clockOut = $dateStart->copy()->setTime(17, rand(0, 30), 0);
                    if (rand(1, 3) === 1) {
                        $breakStart = $dateStart->copy()->setTime(12, 0, 0);
                        $breakEnd = $dateStart->copy()->setTime(13, 0, 0);
                    }
                    $status = match (rand(1, 3)) {
                        1 => AttendanceStatus::APPROVED,
                        2 => AttendanceStatus::REJECTED,
                        default => AttendanceStatus::PENDING,
                    };
                    if ($status === AttendanceStatus::APPROVED || $status === AttendanceStatus::REJECTED) {
                        $approvedBy = $adminId;
                        $approvedAt = $date->copy()->setTime(18, 0, 0);
                    }
                    $totalHours = 8.0 + (rand(-10, 20) / 60.0);
                }

                Attendance::create([
                    'intern_id' => $intern->id,
                    'geofence_location_id' => $this->geofenceId,
                    'date' => $dateStart->toDateString(),
                    'clock_in_time' => $clockIn,
                    'clock_out_time' => $clockOut,
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    'location_lat' => 14.2486 + (rand(-100, 100) / 10000.0),
                    'location_lng' => 121.1258 + (rand(-100, 100) / 10000.0),
                    'location_address' => 'Cabuyao City Hall, Laguna',
                    'clock_in_photo' => 'seeded/clock-in.jpg',
                    'clock_out_photo' => $clockOut ? 'seeded/clock-out.jpg' : null,
                    'clock_in_method' => 'web',
                    'status' => $status,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'rejection_reason' => $status === AttendanceStatus::REJECTED ? 'Seeded rejection.' : null,
                    'notes' => null,
                    'total_hours' => $totalHours,
                    'is_late' => $isLate,
                    'is_undertime' => false,
                    'is_overtime' => $totalHours !== null && $totalHours > 8.5,
                ]);
            }
        }
    }

    private function seedLeaves(): void
    {
        $interns = Intern::where('is_active', true)->get();
        if ($interns->isEmpty()) {
            return;
        }
        $n = min(5, $interns->count());
        $toSeed = $n === 1 ? [$interns->random()] : $interns->random($n)->all();
        $adminId = User::where('role', UserRole::ADMIN)->first()?->id ?? $this->supervisorId;
        $manila = Carbon::now('Asia/Manila');

        foreach ($toSeed as $intern) {
            $start = $manila->copy()->addDays(rand(5, 20))->startOfDay();
            $end = $start->copy()->addDays(rand(1, 2));

            $status = match (rand(1, 3)) {
                1 => 'approved',
                2 => 'rejected',
                default => 'pending',
            };

            $leave = Leave::create([
                'intern_id' => $intern->id,
                'type' => rand(1, 2) === 1 ? 'leave' : 'holiday',
                'reason_title' => 'Seeded leave request',
                'status' => $status,
                'start_date' => $start,
                'end_date' => $end,
                'notes' => null,
                'rejection_reason' => $status === 'rejected' ? 'Seeded rejection.' : null,
                'approved_by' => $status !== 'pending' ? $adminId : null,
                'approved_at' => $status !== 'pending' ? $manila->copy() : null,
            ]);
        }
    }
}
