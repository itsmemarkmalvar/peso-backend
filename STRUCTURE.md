# Backend Folder Structure

```
peso-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── Auth/
│   │   │   │   │   └── AuthController.php
│   │   │   │   ├── Attendance/
│   │   │   │   │   └── AttendanceController.php
│   │   │   │   ├── Interns/
│   │   │   │   │   └── InternController.php
│   │   │   │   ├── Schedules/
│   │   │   │   │   └── ScheduleController.php
│   │   │   │   ├── Approvals/
│   │   │   │   │   └── ApprovalController.php
│   │   │   │   ├── Reports/
│   │   │   │   │   └── ReportController.php
│   │   │   │   ├── Dashboard/
│   │   │   │   │   └── DashboardController.php
│   │   │   │   └── BaseController.php
│   │   ├── Middleware/
│   │   │   └── EnsureUserHasRole.php
│   │   ├── Requests/
│   │   │   ├── Auth/
│   │   │   ├── Attendance/
│   │   │   ├── Interns/
│   │   │   └── ...
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       ├── InternResource.php
│   │       ├── AttendanceResource.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Intern.php
│   │   ├── Attendance.php
│   │   ├── Schedule.php
│   │   ├── Approval.php
│   │   └── ...
│   ├── Services/
│   │   ├── AttendanceService.php
│   │   ├── ApprovalService.php
│   │   ├── ReportService.php
│   │   └── ...
│   ├── Repositories/
│   │   ├── AttendanceRepository.php
│   │   ├── InternRepository.php
│   │   └── ...
│   ├── Enums/
│   │   ├── UserRole.php
│   │   └── AttendanceStatus.php
│   ├── Helpers/
│   │   └── ResponseHelper.php
│   ├── Traits/
│   │   └── HasRoles.php
│   └── Providers/
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php          # API routes
│   ├── web.php
│   └── console.php
│
├── config/
│   ├── sanctum.php      # Laravel Sanctum config
│   ├── cors.php         # CORS config
│   └── ...
│
├── storage/
│   └── app/
│       └── public/      # Public storage (for selfies, etc.)
│
└── public/
```

## Key Directories

### `/app/Http/Controllers/Api`
API controllers organized by feature (Auth, Attendance, Interns, etc.). All controllers extend `BaseController` for consistent responses.

### `/app/Http/Requests`
Form request classes for validation. Organized by feature.

### `/app/Http/Resources`
API resources for transforming models to JSON responses.

### `/app/Models`
Eloquent models representing database tables.

### `/app/Services`
Business logic layer. Handles complex operations like attendance calculations, approval workflows, etc.

### `/app/Repositories`
Data access layer. Abstracts database queries.

### `/app/Enums`
PHP enums for type-safe constants (UserRole, AttendanceStatus, etc.).

### `/app/Helpers`
Utility functions (ResponseHelper, etc.).

### `/app/Traits`
Reusable traits (HasRoles, etc.).

### `/routes/api.php`
All API routes defined here. Uses Laravel Sanctum for authentication.

### `/database/migrations`
Database schema migrations.

### `/database/seeders`
Database seeders for initial data.

## API Structure

- **Base URL**: `http://localhost:8000/api`
- **Authentication**: Laravel Sanctum (Bearer tokens)
- **Response Format**: JSON with consistent structure:
  ```json
  {
    "success": true,
    "message": "Success message",
    "data": { ... }
  }
  ```

## Next Steps

1. Install Laravel Sanctum: `composer require laravel/sanctum`
2. Configure CORS for frontend
3. Create database migrations
4. Implement controllers, services, and repositories
5. Add validation requests
6. Create API resources
