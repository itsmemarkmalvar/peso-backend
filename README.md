# PESO - OJT Interns Attendance System (Backend)

Laravel REST API backend for the OJT Interns Attendance System.

## 🚀 Technology Stack

- **Framework**: Laravel 12
- **Database**: MySQL
- **Authentication**: Laravel Sanctum (SPA)
- **PHP Version**: 8.2+

## 📋 Features

- ✅ User Authentication (Login/Logout)
- ✅ Role-Based Access Control (Admin, Intern, Supervisor, Coordinator)
- ✅ Attendance Management (Clock In/Out)
- ✅ Schedule Management
- ✅ Approval Workflow
- ✅ Reports & Analytics
- ✅ Dashboard Statistics

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd peso-backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database**
   - Update `.env` with your database credentials:
     ```
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=peso_attendance
     DB_USERNAME=root
     DB_PASSWORD=
     ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start development server**
   ```bash
   php artisan serve
   ```

   API will be available at: `http://localhost:8000/api`

## 📁 Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/    # API Controllers
│   ├── Middleware/         # Custom Middleware
│   ├── Requests/           # Form Validation
│   └── Resources/          # API Resources
├── Models/                 # Eloquent Models
├── Services/               # Business Logic
├── Repositories/           # Data Access Layer
├── Enums/                  # PHP Enums
├── Helpers/                # Utility Functions
└── Traits/                 # Reusable Traits
```

See `STRUCTURE.md` for detailed documentation.

## 🔐 API Authentication

This API uses Laravel Sanctum for SPA authentication. Include the Bearer token in requests:

```
Authorization: Bearer {token}
```

## 📝 API Endpoints

### Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get authenticated user

### Attendance
- `POST /api/attendance/clock-in` - Clock in
- `POST /api/attendance/clock-out` - Clock out
- `GET /api/attendance` - List attendance
- `GET /api/attendance/today` - Today's attendance
- `GET /api/attendance/history` - Attendance history

### Interns
- `GET /api/interns` - List interns
- `POST /api/interns` - Create intern
- `GET /api/interns/{id}` - Get intern
- `PUT /api/interns/{id}` - Update intern
- `DELETE /api/interns/{id}` - Delete intern

### Schedules
- `GET /api/schedules` - List schedules
- `POST /api/schedules` - Create schedule
- `POST /api/schedules/assign` - Assign schedule

### Approvals
- `GET /api/approvals` - List approvals
- `GET /api/approvals/pending` - Pending approvals
- `POST /api/approvals/{id}/approve` - Approve attendance
- `POST /api/approvals/{id}/reject` - Reject attendance

### Reports
- `GET /api/reports/dtr` - Daily Time Record
- `GET /api/reports/attendance` - Attendance report
- `GET /api/reports/hours` - Hours report

### Dashboard
- `GET /api/dashboard/stats` - Dashboard statistics
- `GET /api/dashboard/recent-activity` - Recent activity

## 🧪 Testing

```bash
php artisan test
```

## 📄 License

Proprietary - All rights reserved

## 👥 Contributors

- Development Team

---

**Note**: This is the backend API. The frontend is in a separate repository.
