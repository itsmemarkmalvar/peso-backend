# Intern & GIP — Database Schema Summary

**Purpose:** Reference for seeding and understanding intern/GIP data flow.

---

## 1. Users (intern / GIP)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| name | string | Display name |
| email | string | Unique, used for login |
| username | string | Nullable, unique |
| password | string | Hashed |
| role | enum | `'admin'`, `'supervisor'`, `'gip'`, `'intern'` |
| status | enum | `'active'`, `'inactive'`, `'suspended'` |
| ... | | device_fingerprint, last_login_at, invitation_* |

**Intern/GIP:** `role` = `'intern'` or `'gip'`. One user → one `interns` row (`user_id` unique).

---

## 2. Interns (profile per intern/GIP)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| user_id | FK users | Unique, required |
| supervisor_user_id | FK users | Nullable |
| department_id | FK departments | Nullable |
| student_id | string(50) | Nullable, unique |
| full_name | string | Required |
| school | string | Required |
| course | string | Required |
| year_level | string(50) | Nullable |
| phone | string(50) | Required |
| emergency_contact_name | string | Required |
| emergency_contact_phone | string(50) | Required |
| required_hours | unsigned int | Nullable (e.g. 200) |
| weekly_availability | json | Nullable |
| company_name | string | Nullable (often department name) |
| supervisor_name | string | Nullable |
| supervisor_email | string | Nullable |
| supervisor_contact | string(50) | Nullable |
| start_date | date | Nullable |
| end_date | date | Nullable |
| is_active | boolean | Default true |
| onboarded_at | timestamp | Nullable |

**Flow:** User (role intern/gip) signs in → Intern profile linked by `user_id`. Schedules, attendance, leaves use `intern_id`.

---

## 3. Schedules (work days/times per intern)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| intern_id | FK interns | Required |
| day_of_week | 0–6 | 0=Sunday … 6=Saturday |
| start_time | time | e.g. 08:00 |
| end_time | time | e.g. 17:00 |
| break_duration | smallint | Minutes, default 0 |
| is_active | boolean | Default true |

**Unique:** `(intern_id, day_of_week)`. Default schedule in UI comes from one active intern’s schedules (Mon–Fri typical).

---

## 4. School schedules (excused days per intern)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| intern_id | FK interns | Required |
| day_of_week | 0–6 | Day intern has class (excused) |
| is_active | boolean | Default true |

**Unique:** `(intern_id, day_of_week)`. Used for “excused due to school schedule” (e.g. Wednesday = 3).

---

## 5. Attendance (daily clock-in/out)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| intern_id | FK interns | Required |
| geofence_location_id | FK geofence_locations | Nullable |
| date | date | Required |
| clock_in_time | timestamp | Nullable |
| clock_out_time | timestamp | Nullable |
| break_start | timestamp | Nullable |
| break_end | timestamp | Nullable |
| break_start_photo | string | Nullable |
| break_end_photo | string | Nullable |
| location_lat | decimal(10,8) | Nullable |
| location_lng | decimal(11,8) | Nullable |
| location_address | text | Nullable |
| clock_in_photo | string | Nullable |
| clock_out_photo | string | Nullable |
| clock_in_method | enum | 'web', 'qr_code', 'manual' |
| status | enum | 'pending', 'approved', 'rejected' |
| approved_by | FK users | Nullable |
| approved_at | timestamp | Nullable |
| rejection_reason | text | Nullable |
| notes | text | Nullable |
| total_hours | decimal(5,2) | Nullable |
| is_late | boolean | Default false |
| is_undertime | boolean | Default false |
| is_overtime | boolean | Default false |

**Unique:** `(intern_id, date)`. One row per intern per day. “Approvals” in admin = attendance records with status pending/approved/rejected.

---

## 6. Leaves (leave requests)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| intern_id | FK interns | Required |
| type | enum | 'leave', 'holiday' |
| reason_title | string | Required |
| status | enum | 'pending', 'approved', 'rejected' |
| start_date | date | Required |
| end_date | date | Nullable |
| notes | text | Nullable |
| rejection_reason | text | Nullable |
| approved_by | FK users | Nullable |
| approved_at | timestamp | Nullable |

---

## 7. Geofence locations (allowed clock-in areas)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| name | string | Required |
| address | text | Nullable |
| latitude | decimal(10,8) | Required |
| longitude | decimal(11,8) | Required |
| radius_meters | int | Default 100 |
| is_active | boolean | Default true |

---

## 8. Departments

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| name | string | Unique |
| code | string | Unique, nullable |
| description | text | Nullable |
| is_active | boolean | Default true |

**Seeder:** DepartmentSeeder already seeds PESO departments (PESO, HRMO, etc.).

---

## 9. System settings (singleton)

Relevant for flow: `grace_period_minutes`, `verification_gps`, `verification_selfie`, `default_lunch_break_start`, `default_lunch_break_end`, `default_schedule_name`, `default_admin_notes`. Default schedule “days” are read from an active intern’s `schedules` rows.

---

## Seeder order (recommended)

1. Departments (existing DepartmentSeeder)
2. Admin (existing AdminSeeder)
3. Geofence (GeofenceSeeder — one location so clock-in can validate)
4. Supervisor user + Intern/GIP users + Intern rows + Schedules + SchoolSchedules + Attendance + Leaves (InternAndGipSeeder)

**Run all seeders (fresh + seed):**
```bash
php artisan migrate:fresh --seed
```

**Or run only intern/GIP seeders (after migrations and Department + Admin + Geofence):**
```bash
php artisan db:seed --class=GeofenceSeeder
php artisan db:seed --class=InternAndGipSeeder
```

### Demo credentials (after seeding)

| Role        | Email                  | Password   |
|------------|------------------------|------------|
| Admin      | admin@example.com      | Admin123   |
| Supervisor | supervisor@example.com| Password123|
| Intern 1   | intern1@example.com   | Password123|
| Intern 2   | intern2@example.com   | Password123|
| …          | intern3@example.com … | Password123|
| GIP 1      | gip1@example.com      | Password123|

- **15 intern** and **5 GIP** users (intern1@example.com … intern15@example.com, gip1@example.com … gip5@example.com).
- Each has an **Intern** profile, **Mon–Fri 08:00–17:00** schedules, and **~8 days of attendance** (including today; some clocked in only today for Live Locations).
- Some interns have a **school schedule on Wednesday** (excused list).
- A few **leave requests** (pending, approved, rejected).
- One **geofence**: Cabuyao City Hall (14.2486, 121.1258, 100 m).
