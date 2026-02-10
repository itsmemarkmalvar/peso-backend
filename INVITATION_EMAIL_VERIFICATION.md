# Invitation email – verification that the user receives it properly

This document verifies that the invited user will receive the invitation email correctly (recipient, content, and link).

**Current behaviour:** The invitation email is **sent immediately** when the admin approves (no queue worker required). If sending fails, the API still returns success but with `invitation_sent: false` and a message to check logs and MAIL settings.

---

## 1. Recipient

| Check | How it works | Status |
|-------|----------------|--------|
| **To address** | `Mail::to($user->email)->queue(...)` – the email is sent to the **new user’s email** (the one from the registration request). | OK |
| **When job runs** | The queued job uses the same recipient; Laravel’s `SendQueuedMailable` sends to the address given at queue time. | OK |

So the **correct person** (the email they signed up with) receives the email.

---

## 2. Email content

| Element | Source | Status |
|--------|--------|--------|
| **Subject** | `InvitationMail::envelope()` → `'Welcome to PESO OJT Attendance System - Account Invitation'` | OK |
| **From** | `config/mail.php` → `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (e.g. “PESO OJT Attendance System” &lt;peso.cabuyao19@gmail.com&gt;) | OK |
| **Greeting** | View: `Dear {{ $user->name }}` – uses the new user’s full name from the registration request. | OK |
| **Role** | View: `{{ ucfirst($role) }}` – shows the role assigned by the admin (Intern, Supervisor, etc.). | OK |
| **CTA button** | View: “Accept Invitation & Set Password” → `href="{{ $invitationUrl }}"`. | OK |
| **Plain link** | View: “If the button doesn’t work, copy and paste this link” + `{{ $invitationUrl }}`. | OK |
| **Expiry note** | View: “This link expires in 7 days.” (matches backend logic in `InvitationController`). | OK |

When the **queue worker** runs, `$user` is re-loaded from the DB (via `SerializesModels`), so name/email are still correct. The body is HTML, with fallback link and clear instructions, so the user can **receive and use** the email properly.

---

## 3. Invitation link (will it work when the user clicks?)

| Check | Backend | Frontend | Status |
|-------|---------|----------|--------|
| **URL format** | `$invitationUrl = "{$frontendUrl}/invitation/accept?token={$invitationToken}"` | `searchParams.get("token")` and `invitationVerify?token=${token}` | OK |
| **Route** | N/A | `/invitation/accept` page exists | OK |
| **Token** | 64-char token stored on `users.invitation_token` | Sent to API `GET /api/invitation/verify?token=...` then `POST /api/invitation/accept` | OK |

So the **link in the email** matches what the frontend expects. As long as `FRONTEND_URL` points to the same app the user will open (see below), the user will land on the right page and the token will validate.

---

## 4. What you must ensure for the user to receive and use it

1. **SMTP / mailer**  
   The invitation is **queued**; a worker must run to actually send it:
   - Run `php artisan queue:work` (or a process manager that runs it).
   - If `MAIL_MAILER=smtp`, the server must be able to connect (e.g. Gmail: correct credentials, network, and any “less secure app” / App Password settings).  
   If SMTP fails, the job may end up in `failed_jobs`; the user won’t receive the email until the job is fixed and retried (or resent).

2. **FRONTEND_URL**  
   The link in the email is built from `FRONTEND_URL` (default `http://localhost:3000`):
   - **Local:** Default is fine; user must open the app at `http://localhost:3000`.
   - **Production:** Set in `.env`, e.g. `FRONTEND_URL=https://your-domain.com`, so the link points to your real frontend. Otherwise the user would get a localhost link and the invitation would not work when they click.

3. **Logo (optional)**  
   The template uses `$logoBase64` or `$logoPath`. If the logo file is missing, the email still sends; only the logo is omitted.

---

## 5. How to know if the email is being sent properly

### A. Send happens when admin approves

- After approval, the invitation is **sent immediately** (no queue). If it succeeds, the API returns `invitation_sent: true` and the email appears in the sender's Sent folder and the recipient's inbox.
- If the send fails (e.g. SMTP error), the API returns `invitation_sent: false` and a message to check logs. Check `storage/logs/laravel.log` for the exact error.

### B. When using `MAIL_MAILER=log` (local/dev)

- No real email is sent; Laravel writes the message to the log.
- **Check:** Open `storage/logs/laravel.log` and look for a log entry that contains the full email (To, Subject, and HTML body).
- You should see the recipient address, subject “Welcome to PESO OJT Attendance System - Account Invitation”, and the invitation link. That confirms the mailable and template ran correctly.

### C. When using `MAIL_MAILER=smtp` (real email)

- The message is sent through SMTP (e.g. Gmail).
- **Check 1 – Recipient inbox:** Use the **email address the user registered with** (e.g. the one from the registration request). The invitation is sent to that address. Check inbox and spam.
- **Check 2 – Sender “Sent” folder:** If sending from Gmail (`MAIL_USERNAME`), open that account → Sent. You should see the invitation email there after the worker runs.
- **Check 3 – No failure:** If the job shows **DONE** and does not appear in `php artisan queue:failed`, and the recipient/sender folders show the message, the email is being sent properly.

### D. Quick checklist

| What to check | How |
|---------------|-----|
| Job ran successfully | Worker output shows `InvitationMail ... DONE` (not FAIL). |
| Job did not fail | `php artisan queue:failed` has no new row for that send. |
| Log driver | `MAIL_MAILER=log` → see full email in `storage/logs/laravel.log`. |
| SMTP driver | `MAIL_MAILER=smtp` → see email in recipient inbox (and/or sender Sent folder). |
| Link works | Open the invitation link in the email; it should load the frontend and accept the token. |

---

## 6. Internal verification: database and logs

Use these checks internally (no UI needed) to confirm that invitation emails are being sent properly after admin approval.

### Database

1. **Approved users and “invitation sent”**
   - Table: `users`
   - Columns: `invitation_sent_at`, `invitation_accepted_at`, `status`, `email`, `name`
   - Check: After an admin approves a registration, the new user row should have `invitation_sent_at` set (non-NULL). That means the system queued (and, once the worker runs, sent) the invitation.
   - Example (MySQL/phpMyAdmin):
     ```sql
     SELECT id, name, email, status, invitation_sent_at, invitation_accepted_at
     FROM users
     WHERE invitation_sent_at IS NOT NULL
     ORDER BY invitation_sent_at DESC;
     ```
   - Use this list of emails to verify in your mail client or logs that each received the invitation.

2. **Queue: job was processed**
   - Table: `jobs`
   - After approval there is a new row; after the worker runs, that row is removed. So a growing `jobs` count with no worker running means emails are waiting; after `queue:work`, the count for that invitation should go down.
   - Example:
     ```sql
     SELECT id, queue, created_at FROM jobs ORDER BY id DESC LIMIT 20;
     ```

3. **Failures**
   - Table: `failed_jobs`
   - If an invitation send fails, a row appears here with `exception` containing the error.
   - Example:
     ```sql
     SELECT id, failed_at, LEFT(exception, 500) AS exception_preview FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
     ```
   - Or run: `php artisan queue:failed`

### Logs

1. **Laravel log** (`storage/logs/laravel.log`)
   - With `MAIL_MAILER=log`, the full email (To, Subject, body) is written here when the worker runs. Search for the recipient email or “Account Invitation” / “invitation” to confirm the send.
   - With `MAIL_MAILER=smtp`, errors (e.g. connection or auth) often appear here as well.

2. **Failed job exception**
   - The `failed_jobs.exception` column (or `php artisan queue:failed` output) gives the exact error (e.g. SMTP connection refused, authentication failed). Use it to fix send issues.

### Why approved users show "pending" in the database

In the `users` table, **status = `pending`** after admin approval is correct. It means:

- The registration was **approved** and the user account was created.
- The invitation email was **queued** (and, once the worker runs, sent).
- **Pending** = "waiting for the user to accept the invitation and set their password," not "waiting for admin approval."

When the user clicks the link and sets their password, `status` becomes `active` and `invitation_accepted_at` is set.

### When "I approved 4 users but don't see 4 emails in Sent"

1. **List the 4 approved users' emails from the DB** (so you can match to Gmail Sent):
   ```sql
   SELECT id, name, email, status, invitation_sent_at
   FROM users
   WHERE invitation_sent_at IS NOT NULL
   ORDER BY invitation_sent_at DESC
   LIMIT 10;
   ```
2. **In Gmail Sent**, search for each email (e.g. `to:garciaadrianjohn75@gmail.com`, `to:blancachristianivan54@gmail.com`, etc.). If one address has no matching "Account Invitation" email, that send may be missing.
3. **If one or more are missing:**
   - **Still queued:** `SELECT * FROM jobs;` — if rows exist, run the queue worker (`php artisan queue:work` or `queue:work --once` per job). No worker = no send.
   - **Send failed:** `php artisan queue:failed` or check the `failed_jobs` table. The `exception` column (or CLI output) will say why (e.g. SMTP error, user not found).
4. **If all 4 have `invitation_sent_at` set** but only 3 appear in Sent, the missing one was likely never processed (worker not run) or failed (check `failed_jobs`).

### Quick internal checklist

| Goal | Where to check |
|------|----------------|
| List approved accounts that had an invitation sent | `users` where `invitation_sent_at IS NOT NULL`; use `email` to verify in inbox/logs. |
| See if invitation was accepted | `users.invitation_accepted_at` non-NULL and `status = 'active'`. |
| See if send is stuck | `jobs` table: rows present and no worker running. |
| See if send failed | `failed_jobs` table or `php artisan queue:failed`; read `exception`. |
| See email content (log driver) | `storage/logs/laravel.log` for the recipient or “Account Invitation”. |

---

## 7. Summary

| Question | Answer |
|----------|--------|
| Will the **correct user** get the email? | Yes – it’s sent to `$user->email` (the address they registered with). |
| Is the **content** correct (name, role, link, expiry)? | Yes – view and mailable are wired correctly; queue restores the user so data is up to date. |
| Will the **link work** when they click? | Yes – URL format and frontend route/API match; set `FRONTEND_URL` in production. |
| How do I **know** the email was sent? | See **section 5** (UI/worker) and **section 6** (internal: database + logs). |
| What can still go wrong? | (1) SMTP/queue not running or failing, (2) `FRONTEND_URL` wrong in production, (3) spam filtering. |

So **yes – the user will receive the email properly** as long as the queue worker runs, the mailer (e.g. SMTP) works, and `FRONTEND_URL` is set correctly for the environment they use.
