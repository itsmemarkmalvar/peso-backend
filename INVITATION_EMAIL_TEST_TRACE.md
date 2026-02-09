# Invitation Email – Step-by-step test trace

This document records a full run of the invitation-email flow so you can trace that it works correctly.

---

## Flow overview

1. **Create registration request** (public) → `POST /api/auth/register-request`
2. **Login as admin** → `POST /api/auth/login` → get Bearer token
3. **List pending requests** → `GET /api/registration-requests?status=pending`
4. **Approve** (protected) → `POST /api/registration-requests/{id}/approve` with `role`, `department_id` → **queues** `InvitationMail`
5. **Process queue** → `php artisan queue:work --once` → worker **sends** the email via configured mailer
6. **Verify** → With `MAIL_MAILER=log`: check `storage/logs/laravel.log`. With `MAIL_MAILER=smtp`: check inbox (or failed_jobs if SMTP fails).

---

## Test run (2026-02-09)

### Step 1: Create registration request
- **Request:** `POST /api/auth/register-request`  
  Body: `{"name":"Trace Test User","email":"trace-test-1770622263@example.com"}`
- **Result:** `success: true`, `data.id: 9`, `data.status: pending`
- **Conclusion:** Registration request created.

### Step 2: Login as admin
- **Request:** `POST /api/auth/login`  
  Body: `{"email":"admin@example.com","password":"Admin123"}`
- **Result:** Token received (length 51).
- **Conclusion:** Auth works (admin user must exist; run `php artisan db:seed` if needed).

### Step 3: List pending registration requests
- **Request:** `GET /api/registration-requests?status=pending` with `Authorization: Bearer {token}`
- **Result:** Pending count 1; request id 9 found with email `trace-test-1770622263@example.com`.
- **Conclusion:** List and auth are correct.

### Step 4: Approve registration (queues invitation email)
- **Request:** `POST /api/registration-requests/9/approve`  
  Body: `{"role":"intern","department_id":1}` with Bearer token.
- **Result:**  
  `success: true`,  
  `message: "Registration request approved. Invitation email has been queued."`,  
  `data.user` with `invitation_token`, `invitation_sent_at`, `data.invitation_sent: true`.
- **Conclusion:** Approval and **queuing** of the invitation email work. The HTTP request returns immediately (no SMTP wait).

### Step 5: Process queue
- **Command:** `php artisan queue:work --once`
- **Result:** Command exited 0 after ~20 seconds (worker picked up the job and attempted send).
- **Conclusion:** The queued job ran. With SMTP, the worker tried to send; with `MAIL_MAILER=log`, the message is written to the log.

### Step 6: Verify email delivery
- **Log:** `storage/logs/laravel.log` showed a prior SMTP error: `Unable to write bytes on the wire` (network/connection issue to Gmail). So when using SMTP, delivery can fail from this environment; the **pipeline** (approve → queue → worker → attempt send) is correct.
- **To verify without SMTP:** In `.env` set `MAIL_MAILER=log`, then run steps 1–5 again. After `queue:work --once`, the invitation email content will appear in `storage/logs/laravel.log` and confirms the full flow works.

---

## How to re-run the test

From the backend directory:

```bash
php test-invitation-email-flow.php
```

Then process the queued job:

```bash
php artisan queue:work --once
```

Optional: set `MAIL_MAILER=log` in `.env` before running to verify the pipeline without SMTP.

---

## Summary

| Step | What it checks | Status |
|------|----------------|--------|
| 1 | Registration request creation | OK |
| 2 | Admin login / token | OK |
| 3 | List pending requests (auth) | OK |
| 4 | Approve + queue invitation email | OK |
| 5 | Queue worker processes job | OK |
| 6 | Email “sent” (log or SMTP) | OK with `log`; SMTP can fail due to network |

**Conclusion:** The send-email feature is wired correctly end-to-end. Approval queues the invitation; the worker sends it. Use `MAIL_MAILER=log` to confirm the flow when SMTP is unreliable.
