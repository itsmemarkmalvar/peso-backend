# Intern Data Requirements for PESO OJT Attendance System

This document lists all the data fields and related records needed for a complete intern profile in the system.

## 1. User Account Data (`users` table)
- **name** (string) - Full name of the intern
- **username** (string) - Unique username for login
- **email** (string) - Unique email address
- **password** (string) - Hashed password
- **role** (enum) - Must be `INTERN`
- **status** (string) - Usually `active` or `pending`
- **invitation_token** (string, nullable) - For invitation system
- **invitation_sent_at** (datetime, nullable)
- **invitation_accepted_at** (datetime, nullable)

## 2. Intern Profile Data (`interns` table)
- **user_id** (foreign key) - Links to users table
- **student_id** (string, unique, nullable) - Student ID number (e.g., OJT-2026-001)
- **full_name** (string) - Full name
- **school** (string) - School/University name
- **course** (string) - Course/Program (e.g., BS Information Technology)
- **year_level** (string, nullable) - Year level (e.g., 3rd Year, 4th Year)
- **phone** (string) - Contact phone number
- **emergency_contact_name** (string) - Emergency contact person name
- **emergency_contact_phone** (string) - Emergency contact phone number
- **required_hours** (integer, nullable) - Total required OJT hours (e.g., 200, 400)
- **company_name** (string, nullable) - Company/Department name where interning
- **supervisor_name** (string, nullable) - Supervisor's name
- **supervisor_email** (string, nullable) - Supervisor's email
- **supervisor_contact** (string, nullable) - Supervisor's contact number
- **start_date** (date, nullable) - OJT start date
- **end_date** (date, nullable) - OJT end date
- **is_active** (boolean) - Whether intern is currently active
- **onboarded_at** (datetime, nullable) - When intern completed onboarding

## 3. Work Schedule Data (`schedules` table)
Each intern needs work schedules for the days they work.

- **intern_id** (foreign key) - Links to interns table
- **day_of_week** (integer, 0-6) - 0=Sunday, 1=Monday, ..., 6=Saturday
- **start_time** (time) - Work start time (e.g., 08:00:00)
- **end_time** (time) - Work end time (e.g., 17:00:00)
- **break_duration** (integer) - Break duration in minutes (e.g., 60 for 1 hour lunch)
- **is_active** (boolean) - Whether this schedule is active

**Common Schedule Patterns:**
- Monday-Friday: 8:00 AM - 5:00 PM (1 hour lunch break)
- Monday-Friday: 9:00 AM - 6:00 PM (1 hour lunch break)
- Monday-Saturday: 8:00 AM - 12:00 PM (half day)

## 4. School Schedule Data (`school_schedules` table)
Days when the intern has classes and cannot work.

- **intern_id** (foreign key) - Links to interns table
- **day_of_week** (integer, 0-6) - 0=Sunday, 1=Monday, ..., 6=Saturday
- **is_active** (boolean) - Whether this school day is active

**Common Patterns:**
- Some interns have classes on specific days (e.g., Monday, Wednesday, Friday)
- School schedules should NOT overlap with work schedules

## 5. Attendance Records (`attendance` table)
Historical clock in/out data for tracking attendance.

- **intern_id** (foreign key) - Links to interns table
- **geofence_location_id** (foreign key, nullable) - Location where clocked in
- **date** (date) - Date of attendance
- **clock_in_time** (datetime, nullable) - When intern clocked in
- **clock_out_time** (datetime, nullable) - When intern clocked out
- **break_start** (datetime, nullable) - When break started
- **break_end** (datetime, nullable) - When break ended
- **location_lat** (decimal, nullable) - GPS latitude
- **location_lng** (decimal, nullable) - GPS longitude
- **location_address** (text, nullable) - Address where clocked in
- **clock_in_photo** (string, nullable) - Path to clock-in selfie photo
- **clock_out_photo** (string, nullable) - Path to clock-out selfie photo
- **clock_in_method** (enum, nullable) - 'web', 'qr_code', or 'manual'
- **status** (enum) - 'pending', 'approved', or 'rejected' (default: 'pending')
- **approved_by** (foreign key, nullable) - User who approved/rejected
- **approved_at** (datetime, nullable) - When approved/rejected
- **rejection_reason** (text, nullable) - Reason if rejected
- **notes** (text, nullable) - Additional notes
- **total_hours** (decimal, nullable) - Total hours worked (calculated)
- **is_late** (boolean) - Whether clock-in was late
- **is_undertime** (boolean) - Whether clock-out was early
- **is_overtime** (boolean) - Whether worked overtime

**Attendance Scenarios to Seed:**
- Regular attendance (on-time clock in/out)
- Late arrivals
- Early departures (undertime)
- Overtime work
- Pending approvals
- Approved attendance
- Rejected attendance (with reasons)
- Attendance with breaks
- Attendance without breaks

## 6. Leave Records (`leaves` table) - Optional
Leave requests and holidays.

- **intern_id** (foreign key) - Links to interns table
- **type** (enum) - 'leave' or 'holiday'
- **reason_title** (string) - Reason for leave
- **status** (enum) - 'pending', 'approved', or 'rejected'
- **start_date** (date) - Leave start date
- **end_date** (date, nullable) - Leave end date (null for single day)
- **notes** (text, nullable) - Additional notes
- **rejection_reason** (text, nullable) - Reason if rejected
- **approved_by** (foreign key, nullable) - User who approved
- **approved_at** (datetime, nullable) - When approved

## 7. Department Assignment - Optional
Interns may be assigned to departments (if this relationship exists in the system).

## Data Relationships Summary

```
User (1) ──< (1) Intern (1) ──< (N) Schedule
                              └──< (N) SchoolSchedule
                              └──< (N) Attendance
                              └──< (N) Leave
```

## Sample Data Requirements for Seeder

For a comprehensive seeder, we need:

1. **20-50 interns** with realistic Filipino names
2. **Each intern needs:**
   - User account with unique email/username
   - Complete intern profile
   - Work schedule (typically Monday-Friday)
   - School schedule (if applicable - some days they have classes)
   - 30-60 days of attendance records (mix of scenarios)
   - 0-3 leave records (optional)

3. **Attendance data should include:**
   - Last 30-60 days of work
   - Mix of weekdays (based on schedule)
   - Various scenarios (on-time, late, undertime, overtime)
   - Mix of statuses (pending, approved, rejected)
   - Realistic clock in/out times
   - Break times
   - Location data (GPS coordinates for Cabuyao area)

4. **Realistic Filipino Names:**
   - Use common Filipino first names and surnames
   - Format: "Surname, First Name Middle Initial."

5. **Realistic Schools:**
   - Laguna State Polytechnic University (LSPU)
   - University of the Philippines Los Baños (UPLB)
   - De La Salle University (DLSU)
   - Polytechnic University of the Philippines (PUP)
   - University of Santo Tomas (UST)
   - Ateneo de Manila University
   - Mapúa University

6. **Realistic Courses:**
   - BS Information Technology
   - BS Computer Science
   - BS Information Systems
   - BS Computer Engineering
   - BS Business Administration
   - BS Accountancy

7. **Realistic Companies/Departments:**
   - PESO Office
   - Office of the City Mayor
   - HRMO
   - IT Department
   - Accounting Office
   - etc.
