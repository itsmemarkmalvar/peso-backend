# Role-Based Access Control (RBAC) Design Document
## PESO OJT Attendance System

---

## 🎭 Role Hierarchy & Overview

### Current Roles (4 roles):
1. **ADMIN** - Full system access, user management, system configuration
2. **SUPERVISOR** - Oversees interns, manages schedules, approves/rejects attendance, can be assigned to specific interns
3. **GIP** - Government Internship Program participant - Same restrictions as Intern
4. **INTERN** - Clock in/out, view own attendance, submit timesheets

**Note:** GIP has the same restrictions and capabilities as INTERN. Both roles can only access their own data and cannot view other users' information.

---

## 📊 Permission Matrix

### Legend:
- ✅ **Full Access** - Can create, read, update, delete
- 👁️ **Read Only** - Can view but not modify
- 🔒 **No Access** - Cannot access this feature
- ⚠️ **Conditional** - Access depends on ownership/assignment

| Feature | ADMIN | SUPERVISOR | GIP | INTERN |
|---------|-------|------------|-----|-------|
| **User Management** |
| Create users | ✅ | ⚠️ (interns/gip only) | 🔒 | 🔒 |
| View all users | ✅ | ✅ | 🔒 | 🔒 |
| Edit users | ✅ | ⚠️ (interns/gip only) | 👁️ (own profile) | 👁️ (own profile) |
| Delete users | ✅ | ⚠️ (interns/gip only) | 🔒 | 🔒 |
| Suspend/Activate users | ✅ | ✅ | 🔒 | 🔒 |
| **Intern Management** |
| Create intern profiles | ✅ | ✅ | 🔒 | 🔒 |
| View all interns | ✅ | ✅ | 🔒 | 🔒 |
| Edit intern profiles | ✅ | ✅ | 👁️ (own profile) | 👁️ (own profile) |
| Delete intern profiles | ✅ | ✅ | 🔒 | 🔒 |
| **Attendance** |
| Clock in/out | 🔒 | 🔒 | ✅ | ✅ |
| View own attendance | ✅ | ✅ | ✅ | ✅ |
| View all attendance | ✅ | ✅ | 🔒 | 🔒 |
| Edit attendance | ✅ | ⚠️ (manual override) | 🔒 | 🔒 |
| Delete attendance | ✅ | ⚠️ (with reason) | 🔒 | 🔒 |
| **Approvals** |
| Approve attendance | ✅ | ✅ | 🔒 | 🔒 |
| Reject attendance | ✅ | ✅ | 🔒 | 🔒 |
| View approval history | ✅ | ✅ | 👁️ (own records) | 👁️ (own records) |
| **Schedules** |
| Create schedules | ✅ | ✅ | 🔒 | 🔒 |
| View all schedules | ✅ | ✅ | 👁️ (own schedule) | 👁️ (own schedule) |
| Edit schedules | ✅ | ✅ | 🔒 | 🔒 |
| Delete schedules | ✅ | ✅ | 🔒 | 🔒 |
| **Reports** |
| View all reports | ✅ | ✅ | 👁️ (own reports) | 👁️ (own reports) |
| Export reports | ✅ | ✅ | 👁️ (own DTR) | 👁️ (own DTR) |
| **Geofence Locations** |
| Create locations | ✅ | ✅ | 🔒 | 🔒 |
| View locations | ✅ | ✅ | 👁️ (active only) | 👁️ (active only) |
| Edit locations | ✅ | ✅ | 🔒 | 🔒 |
| Delete locations | ✅ | ✅ | 🔒 | 🔒 |
| **System Settings** |
| Configure system | ✅ | 🔒 | 🔒 | 🔒 |
| View activity logs | ✅ | ✅ | 👁️ (own actions) | 👁️ (own actions) |
| Manage notifications | ✅ | ✅ | 👁️ (own notifications) | 👁️ (own notifications) |

---

## 🔐 Detailed Role Definitions

### 1. ADMIN
**Purpose:** Full system administrator with unrestricted access

**Capabilities:**
- ✅ Create/edit/delete all user accounts (any role)
- ✅ Manage system configuration (geofence, holidays, settings)
- ✅ View all attendance records across all interns
- ✅ Override any attendance record (manual entry)
- ✅ Approve/reject any attendance
- ✅ Generate system-wide reports
- ✅ Access activity logs for all users
- ✅ Manage notifications
- ✅ Suspend/activate any user account

**Restrictions:**
- ❌ Cannot clock in/out (not an intern)
- ❌ Should not approve own attendance (if admin is also an intern - edge case)

**Use Cases:**
- System setup and maintenance
- Emergency overrides
- User account management
- System-wide reporting

---

### 2. SUPERVISOR
**Purpose:** Supervisor who oversees interns in the program.

**Capabilities:**
- ✅ Create/edit intern profiles
- ✅ View all interns and their attendance (full access)
- ✅ Approve/reject attendance for any intern
- ✅ Create/edit schedules for any intern
- ✅ Generate reports for all interns
- ✅ Manage geofence locations
- ✅ View activity logs (all interns)
- ✅ Send notifications to interns
- ✅ Suspend/activate intern accounts
- ✅ Can optionally be assigned to specific interns (for workflow/organization purposes, but access is not restricted)

**Restrictions:**
- ❌ Cannot create admin/coordinator accounts
- ❌ Cannot modify system settings
- ❌ Cannot clock in/out (not an intern)
- ❌ Cannot delete attendance records (only approve/reject, unless manual override with reason)

**Use Cases:**
- Onboarding new interns
- Managing schedules across all interns
- Reviewing attendance patterns
- Generating program-wide reports
- Daily attendance approval (for any intern)
- Monitoring intern performance

**Data Access:**
- **Full access** - Can see ALL interns (not filtered by assignment)
- Can see ALL attendance records
- Can see ALL schedules
- Assignment to specific interns is optional (for organizational purposes, notifications, etc.) but does NOT restrict access

**Note:** Even if a coordinator is assigned to specific interns (via `supervisor_user_id` or similar), they still have full access to all interns. Assignment is for workflow/organization purposes only.

---

### 3. GIP
**Purpose:** Government Internship Program participant who clocks in/out and tracks attendance

**Capabilities:**
- ✅ Clock in/out (with geofence verification)
- ✅ View own attendance history
- ✅ View own schedule
- ✅ View own timesheets
- ✅ View own approval status
- ✅ View own notifications
- ✅ Export own DTR (Daily Time Record)

**Restrictions:**
- ❌ Cannot view other users' data
- ❌ Cannot approve/reject attendance
- ❌ Cannot create/edit schedules
- ❌ Cannot modify attendance records after clock in/out
- ❌ Cannot access admin/supervisor features

**Data Access:**
- **Strictly filtered** - Only sees own data (`user_id` = authenticated user's ID)
- Can only clock in/out for themselves

**Use Cases:**
- Daily time tracking
- Viewing attendance history
- Checking approval status
- Exporting DTR for submission

**Note:** GIP has the same restrictions and capabilities as INTERN.

---

### 4. INTERN
**Purpose:** OJT intern who clocks in/out and tracks attendance

**Capabilities:**
- ✅ Clock in/out (with geofence verification)
- ✅ View own attendance history
- ✅ View own schedule
- ✅ View own timesheets
- ✅ View own approval status
- ✅ View own notifications
- ✅ Export own DTR (Daily Time Record)

**Restrictions:**
- ❌ Cannot view other interns' data
- ❌ Cannot approve/reject attendance
- ❌ Cannot create/edit schedules
- ❌ Cannot modify attendance records after clock in/out
- ❌ Cannot access admin/supervisor features

**Data Access:**
- **Strictly filtered** - Only sees own data (`user_id` = authenticated user's ID)
- Can only clock in/out for themselves

**Use Cases:**
- Daily time tracking
- Viewing attendance history
- Checking approval status
- Exporting DTR for submission

---

## 🔒 Data Access Rules

### Intern Data Filtering:

1. **ADMIN & SUPERVISOR:**
   ```sql
   -- Can see ALL interns
   SELECT * FROM interns;
   ```

2. **GIP & INTERN:**
   ```sql
   -- Can only see own profile
   SELECT * FROM interns WHERE user_id = :user_id;
   ```

### Attendance Data Filtering:

1. **ADMIN & SUPERVISOR:**
   ```sql
   -- Can see ALL attendance
   SELECT * FROM attendance;
   ```

2. **GIP & INTERN:**
   ```sql
   -- Can only see own attendance
   SELECT * FROM attendance 
   WHERE intern_id = (
     SELECT id FROM interns WHERE user_id = :user_id
   );
   ```

### Supervisor Assignment (Optional):
- Supervisors can be assigned to specific interns via `supervisor_user_id` in `interns` table
- This assignment is for **organizational/workflow purposes only** (notifications, reports grouping, etc.)
- **Assignment does NOT restrict access** - supervisors still see all interns
- Useful for: "Show me interns assigned to supervisor X" (filtering view, not access control)

---

## ⚠️ Security Considerations

### 1. **Role Escalation Prevention**
- ❌ Users cannot change their own role
- ❌ Only ADMIN can create ADMIN/SUPERVISOR accounts
- ❌ Only ADMIN can modify role assignments
- ❌ Interns and GIP cannot become supervisors/admins through registration

### 2. **Data Isolation**
- ✅ Strict filtering at database query level (not just UI)
- ✅ Middleware checks role before allowing access
- ✅ API endpoints validate ownership before returning data
- ✅ Interns and GIP can only access their own data

### 3. **Approval Workflow**
- ✅ Supervisors can approve any intern/gip
- ✅ Admins can approve any intern/gip
- ✅ Self-approval should be prevented (edge case: admin/supervisor who is also intern/gip)

### 4. **Activity Logging**
- ✅ All role changes logged
- ✅ All approval actions logged
- ✅ All data access logged (for sensitive operations)
- ✅ Failed permission checks logged

---

## 🤔 Questions to Discuss

### 1. **Coordinator Assignment Model**
- **Current:** Interns have `supervisor_name` and `supervisor_email`
- **Question:** Should we add `coordinator_user_id` to `interns` table for explicit assignment?
  - Option A: Keep `supervisor_name`/`supervisor_email` (string matching)
  - Option B: Add `coordinator_user_id` foreign key (explicit relationship)
  - Option C: Create `intern_coordinator` pivot table (many-to-many)

**Recommendation:** Option B - Add `coordinator_user_id` for explicit relationship (even though coordinators have full access, assignment helps with workflow/organization)

### 2. **Registration Restrictions**
- ✅ Currently: Registration creates INTERN role only
- **Question:** Should registration be disabled entirely, or allow with approval?
  - Option A: Registration disabled - only admins create accounts
  - Option B: Registration open but requires coordinator approval
  - Option C: Registration open, auto-approved (current)

**Recommendation:** Option B - Registration open but requires approval (add `status = 'pending'` for new registrations)

### 3. **Data Export Permissions**
- **Question:** Who can export what?
  - Interns: Own DTR only
  - Coordinators: All interns' reports
  - Admins: All data exports

**Recommendation:** Role-based export limits as shown in matrix

---

## 📝 Implementation Checklist

### Backend (Laravel):
- [x] Remove SUPERVISOR from UserRole enum
- [x] Update HasRoles trait (remove isSupervisor)
- [ ] Create middleware: `EnsureUserHasRole`
- [ ] Add `coordinator_user_id` to `interns` table migration (optional, for assignment)
- [ ] Update `InternController` with role-based filtering
- [ ] Update `AttendanceController` with role-based filtering
- [ ] Update `ApprovalController` with role-based checks
- [ ] Add role checks to all API endpoints
- [ ] Implement activity logging for sensitive operations

### Frontend (Next.js):
- [ ] Create role-based route guards
- [ ] Hide/show UI elements based on role
- [ ] Implement role-based data filtering in API calls
- [ ] Add role badges to user profile
- [ ] Create role-specific dashboard views
- [ ] Update role references (remove supervisor mentions)

### Database:
- [ ] Consider adding `coordinator_user_id` foreign key to `interns` table (optional)
- [ ] Update migration to remove supervisor-specific fields if not needed
- [ ] Add indexes for role-based queries

---

## 🎯 Next Steps

1. ✅ **Combine COORDINATOR and SUPERVISOR roles** - DONE
2. **Clarify coordinator assignment model** - Add `coordinator_user_id` for explicit relationship?
3. **Define registration workflow** - Open with approval, or admin-only?
4. **Review permission matrix** - Adjust based on your actual needs
5. **Implement role-based middleware** - Start with backend security
6. **Test role boundaries** - Ensure data isolation works correctly

---

## 📚 References

- Current roles: `app/Enums/UserRole.php`
- Role trait: `app/Traits/HasRoles.php`
- Database schema: `DATABASE_SCHEMA.md`
- Project plan: `PROJECT_PLAN.md`
