# PESO OJT Attendance System - Database Schema Design

## Overview

This document details the complete database schema for the PESO OJT Interns Attendance System, designed to support time tracking, approvals, schedules, and comprehensive reporting.

---

## Entity Relationship Diagram (Conceptual)

```
users (1) ──── (1) interns
  │               │
  │               │ (1)
  │               │   │
  │               │   └── (N) schedules
  │               │
  │               │ (1)
  │               │   │
  │               └───┴── (N) attendance
  │                           │
  │                           │ (1)
  │                           │   │
  │                           └───┴── (N) approvals
  │                                       │
  │                                       │ (N)
  │                                       │   │
  │                                       └───┴── (1) users (approver)
  │
  │ (1)
  │   │
  └───┴── (N) notifications

users (1) ──── (N) activity_logs
```

---

## Tables

### 1. `users`

Core authentication and user management table.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | User ID |
| `username` | VARCHAR(255) | UNIQUE, NOT NULL | Username for login |
| `email` | VARCHAR(255) | UNIQUE, NOT NULL | Email address |
| `email_verified_at` | TIMESTAMP | NULLABLE | Email verification timestamp |
| `password` | VARCHAR(255) | NOT NULL | Hashed password (bcrypt) |
| `role` | ENUM | NOT NULL, DEFAULT 'intern' | User role: 'admin', 'intern', 'coordinator' |
| `status` | ENUM | NOT NULL, DEFAULT 'active' | Account status: 'active', 'inactive', 'suspended' |
| `device_fingerprint` | VARCHAR(255) | NULLABLE, INDEX | Device fingerprint for security |
| `last_login_at` | TIMESTAMP | NULLABLE | Last login timestamp |
| `last_login_ip` | VARCHAR(45) | NULLABLE | Last login IP address |
| `remember_token` | VARCHAR(100) | NULLABLE | Remember me token |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- UNIQUE: `username`, `email`
- INDEX: `role`, `status`, `device_fingerprint`

---

### 2. `interns`

Intern profile information linked to users.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Intern ID |
| `user_id` | BIGINT UNSIGNED | FOREIGN KEY → users(id), UNIQUE, NOT NULL | Associated user account |
| `student_id` | VARCHAR(50) | UNIQUE, NOT NULL | Student ID number |
| `full_name` | VARCHAR(255) | NOT NULL | Full name |
| `course` | VARCHAR(255) | NOT NULL | Course/program name |
| `year_level` | VARCHAR(50) | NULLABLE | Year level (e.g., "3rd Year", "4th Year") |
| `company_name` | VARCHAR(255) | NOT NULL | Company/organization name |
| `supervisor_name` | VARCHAR(255) | NOT NULL | Supervisor full name |
| `supervisor_email` | VARCHAR(255) | NULLABLE | Supervisor email |
| `supervisor_contact` | VARCHAR(50) | NULLABLE | Supervisor contact number |
| `start_date` | DATE | NOT NULL | OJT start date |
| `end_date` | DATE | NOT NULL | OJT end date |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE CASCADE
- UNIQUE: `user_id`, `student_id`
- INDEX: `company_name`, `supervisor_name`, `is_active`, `start_date`, `end_date`

---

### 3. `schedules`

Work schedules for interns (can have multiple schedules per intern).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Schedule ID |
| `intern_id` | BIGINT UNSIGNED | FOREIGN KEY → interns(id), NOT NULL | Associated intern |
| `day_of_week` | TINYINT | NOT NULL, CHECK (0-6) | Day: 0=Sunday, 1=Monday, ..., 6=Saturday |
| `start_time` | TIME | NOT NULL | Shift start time (HH:MM:SS) |
| `end_time` | TIME | NOT NULL | Shift end time (HH:MM:SS) |
| `break_duration` | INTEGER | DEFAULT 0 | Break duration in minutes |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `intern_id` → `interns(id)` ON DELETE CASCADE
- INDEX: `intern_id`, `day_of_week`, `is_active`
- UNIQUE: `intern_id`, `day_of_week` (composite) - one schedule per day per intern

**Constraints:**
- `end_time` must be after `start_time`
- `break_duration` must be >= 0

---

### 4. `attendance`

Daily attendance records with clock-in/out times, location, and photos.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Attendance ID |
| `intern_id` | BIGINT UNSIGNED | FOREIGN KEY → interns(id), NOT NULL | Associated intern |
| `date` | DATE | NOT NULL | Attendance date |
| `clock_in_time` | TIMESTAMP | NULLABLE | Clock-in timestamp |
| `clock_out_time` | TIMESTAMP | NULLABLE | Clock-out timestamp |
| `break_start` | TIMESTAMP | NULLABLE | Break start timestamp |
| `break_end` | TIMESTAMP | NULLABLE | Break end timestamp |
| `location_lat` | DECIMAL(10, 8) | NULLABLE | GPS latitude |
| `location_lng` | DECIMAL(11, 8) | NULLABLE | GPS longitude |
| `location_address` | TEXT | NULLABLE | Resolved address from GPS |
| `clock_in_photo` | VARCHAR(255) | NULLABLE | Clock-in selfie file path |
| `clock_out_photo` | VARCHAR(255) | NULLABLE | Clock-out selfie file path |
| `clock_in_method` | ENUM | NULLABLE | Method: 'web', 'qr_code', 'manual' |
| `status` | ENUM | DEFAULT 'pending' | Status: 'pending', 'approved', 'rejected' |
| `approved_by` | BIGINT UNSIGNED | FOREIGN KEY → users(id), NULLABLE | Approver user ID |
| `approved_at` | TIMESTAMP | NULLABLE | Approval timestamp |
| `rejection_reason` | TEXT | NULLABLE | Reason for rejection |
| `notes` | TEXT | NULLABLE | Additional notes/comments |
| `total_hours` | DECIMAL(5, 2) | NULLABLE | Calculated total hours |
| `is_late` | BOOLEAN | DEFAULT FALSE | Late arrival flag |
| `is_undertime` | BOOLEAN | DEFAULT FALSE | Early departure flag |
| `is_overtime` | BOOLEAN | DEFAULT FALSE | Overtime flag |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `intern_id` → `interns(id)` ON DELETE CASCADE
- FOREIGN KEY: `approved_by` → `users(id)` ON DELETE SET NULL
- INDEX: `intern_id`, `date`, `status`, `approved_by`
- UNIQUE: `intern_id`, `date` (composite) - one attendance record per day per intern

**Constraints:**
- `clock_out_time` must be after `clock_in_time` (if both exist)
- `break_end` must be after `break_start` (if both exist)
- `location_lat` range: -90 to 90
- `location_lng` range: -180 to 180

---

### 5. `approvals`

Approval history and workflow tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Approval ID |
| `attendance_id` | BIGINT UNSIGNED | FOREIGN KEY → attendance(id), NOT NULL | Associated attendance record |
| `approver_id` | BIGINT UNSIGNED | FOREIGN KEY → users(id), NOT NULL | Approver user ID |
| `status` | ENUM | NOT NULL | Status: 'pending', 'approved', 'rejected' |
| `comments` | TEXT | NULLABLE | Approval comments/notes |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `attendance_id` → `attendance(id)` ON DELETE CASCADE
- FOREIGN KEY: `approver_id` → `users(id)` ON DELETE RESTRICT
- INDEX: `attendance_id`, `approver_id`, `status`, `created_at`

**Note:** This table tracks approval history. The current approval status is also stored in `attendance.status` for quick queries.

---

### 6. `notifications`

User notifications system.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Notification ID |
| `user_id` | BIGINT UNSIGNED | FOREIGN KEY → users(id), NOT NULL | Recipient user ID |
| `type` | VARCHAR(50) | NOT NULL | Notification type: 'attendance_pending', 'approval_request', 'attendance_approved', 'attendance_rejected', 'system' |
| `title` | VARCHAR(255) | NOT NULL | Notification title |
| `message` | TEXT | NOT NULL | Notification message |
| `data` | JSON | NULLABLE | Additional data (e.g., attendance_id, links) |
| `is_read` | BOOLEAN | DEFAULT FALSE | Read status |
| `read_at` | TIMESTAMP | NULLABLE | Read timestamp |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE CASCADE
- INDEX: `user_id`, `type`, `is_read`, `created_at`

---

### 7. `activity_logs`

Audit trail for system activities.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Log ID |
| `user_id` | BIGINT UNSIGNED | FOREIGN KEY → users(id), NULLABLE | User who performed action |
| `action` | VARCHAR(100) | NOT NULL | Action type (e.g., 'clock_in', 'clock_out', 'approve_attendance', 'login', 'logout') |
| `description` | TEXT | NOT NULL | Action description |
| `model_type` | VARCHAR(255) | NULLABLE | Related model type (e.g., 'App\Models\Attendance') |
| `model_id` | BIGINT UNSIGNED | NULLABLE | Related model ID |
| `changes` | JSON | NULLABLE | Changed attributes (before/after) |
| `ip_address` | VARCHAR(45) | NULLABLE | IP address |
| `user_agent` | TEXT | NULLABLE | User agent string |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE SET NULL
- INDEX: `user_id`, `action`, `model_type`, `model_id`, `created_at`

---

### 8. `holidays` (Optional - for holiday management)

Holiday calendar management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Holiday ID |
| `name` | VARCHAR(255) | NOT NULL | Holiday name |
| `date` | DATE | NOT NULL | Holiday date |
| `is_recurring` | BOOLEAN | DEFAULT FALSE | Recurring holiday flag |
| `year` | YEAR | NULLABLE | Specific year (if not recurring) |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- INDEX: `date`, `is_active`, `year`

---

### 9. `geofence_locations` (Optional - for geofencing)

Allowed clock-in locations for geofencing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Location ID |
| `name` | VARCHAR(255) | NOT NULL | Location name |
| `address` | TEXT | NOT NULL | Location address |
| `latitude` | DECIMAL(10, 8) | NOT NULL | Center latitude |
| `longitude` | DECIMAL(11, 8) | NOT NULL | Center longitude |
| `radius_meters` | INTEGER | DEFAULT 100 | Allowed radius in meters |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active status |
| `created_at` | TIMESTAMP | NULLABLE | Creation timestamp |
| `updated_at` | TIMESTAMP | NULLABLE | Last update timestamp |

**Indexes:**
- PRIMARY KEY: `id`
- INDEX: `is_active`

---

## Relationships Summary

1. **users → interns** (1:1)
   - One user account = one intern profile (for interns role)

2. **interns → schedules** (1:N)
   - One intern can have multiple schedules (e.g., different schedules for different days)

3. **interns → attendance** (1:N)
   - One intern can have multiple attendance records (one per day)

4. **attendance → approvals** (1:N)
   - One attendance record can have multiple approval entries (history)

5. **users → approvals** (1:N as approver)
   - One user can approve multiple attendance records

6. **users → notifications** (1:N)
   - One user can receive multiple notifications

7. **users → activity_logs** (1:N)
   - One user can have multiple activity log entries

---

## Data Types & Constraints Best Practices

- **Timestamps**: Use `TIMESTAMP` for created_at, updated_at, and datetime fields
- **Dates**: Use `DATE` for date-only fields
- **Times**: Use `TIME` for time-only fields
- **Decimals**: Use `DECIMAL(10, 8)` for latitude, `DECIMAL(11, 8)` for longitude
- **Enums**: Use `ENUM` for fixed value sets (role, status, etc.)
- **JSON**: Use `JSON` for flexible data structures (notifications.data, activity_logs.changes)
- **Foreign Keys**: Always define CASCADE or RESTRICT behavior
- **Indexes**: Index foreign keys, frequently queried columns, and composite unique constraints

---

## Migration Order

1. `users` (base table)
2. `interns` (depends on users)
3. `schedules` (depends on interns)
4. `attendance` (depends on interns)
5. `approvals` (depends on attendance and users)
6. `notifications` (depends on users)
7. `activity_logs` (depends on users)
8. `holidays` (optional, standalone)
9. `geofence_locations` (optional, standalone)

---

## Notes

- All tables include `created_at` and `updated_at` timestamps (Laravel default)
- Soft deletes can be added later if needed (using `deleted_at` column)
- Photo file paths stored in `attendance` table point to Laravel storage (`storage/app/public/attendance`)
- GPS coordinates use DECIMAL for precision
- Status fields use ENUM for data integrity
- JSON columns provide flexibility for extensible data
