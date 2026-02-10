# Email sending – why it can be slow and what to do

This document explains how invitation emails are sent in this system and lists **possible causes** of slow email delivery, with **concrete checks and fixes**.

---

## How email sending works here

1. **Admin approves a registration request** → `RegistrationRequestsController::approve()` creates the user and **queues** the invitation email (it does **not** send it in the same HTTP request).
2. **Queue**: The job is stored in the `jobs` table (database queue). The HTTP response returns right away with a message like “Invitation email has been queued.”
3. **Actual send**: A **queue worker** must run (`php artisan queue:work` or `queue:listen`). When the worker processes the job, it connects to the mail server (e.g. Gmail SMTP) and sends the email.

So “email takes too long” can mean either:

- **The API/approval feels slow** (rare if you’re really using the queue).
- **The email arrives late or never** (usually because the worker isn’t running or something is slow when the worker runs).

---

## Possible causes and what to do

### 1. Queue worker not running (most common)

**Cause:** If no process is running `php artisan queue:work` (or `queue:listen`), jobs stay in the `jobs` table and are never processed. The email is only “sent” when a worker runs.

**Check:**

- In MySQL/phpMyAdmin: `SELECT * FROM jobs ORDER BY id DESC LIMIT 10;`  
  If you see rows there and no worker is running, those emails are waiting.
- Or: `php artisan queue:monitor` (if available) / check your process list for `queue:work` or `queue:listen`.

**Fix:**

- On the **server** (the machine that runs the Laravel app), start a worker and keep it running:
  ```bash
  php artisan queue:work
  ```
  Or use the project’s dev command that includes the queue (see `composer.json` scripts).
- For production, run the worker via Supervisor, systemd, or your hosting’s process manager so it restarts if it stops.

---

### 2. Queue worker runs only sometimes (e.g. manual “run once”)

**Cause:** If you only run `php artisan queue:work --once` manually, emails are sent only when someone runs it. That can feel like “emails take forever” or “sometimes they don’t arrive.”

**Fix:**

- Run a **long-lived** worker: `php artisan queue:work` (or `queue:listen`) and leave it running, or run it under a process manager so it’s always on.

---

### 3. SMTP / Gmail is slow or timing out

**Cause:** When the worker runs, it connects to `MAIL_HOST` (e.g. `smtp.gmail.com:587`). Connection + TLS + auth + server processing can take several seconds per email. If the network is slow or the mail server is unreachable, Laravel will wait up to the configured **timeout** (default 10 seconds in `config/mail.php`).

**Check:**

- `.env`: Is `MAIL_TIMEOUT` set? If not, Laravel uses the default from `config/mail.php` (10 seconds). A long timeout can make each failed/slow attempt feel “stuck.”
- Try sending one email and watch the worker: if it hangs for several seconds then succeeds or fails, SMTP latency or timeout is involved.

**Fix:**

- Set an explicit, reasonable timeout in `.env`, e.g.:
  ```env
  MAIL_TIMEOUT=10
  ```
  (10 seconds is usually enough; lower values fail faster if the server is unreachable.)
- Use a fast, reliable SMTP provider and ensure the server has a good network path to it (no firewall blocking outbound 587).
- For **local dev** without real SMTP, you can set `MAIL_MAILER=log` so the worker “sends” to the log file instantly (no network).

---

### 4. Queue connection is `sync` by mistake

**Cause:** If `QUEUE_CONNECTION=sync`, then `Mail::queue(...)` runs the job **synchronously** in the same HTTP request. The approval request will wait for SMTP to complete (or timeout), so the API will feel slow and timeouts (e.g. 30s) can occur.

**Check:**

- In `.env`: `QUEUE_CONNECTION` should be `database` (or `redis`, etc.), **not** `sync`.

**Fix:**

- Set in `.env`:
  ```env
  QUEUE_CONNECTION=database
  ```
  Then run `php artisan config:clear` and ensure a queue worker is running (see cause 1).

---

### 5. Large job payload (logo in the mailable)

**Cause:** `InvitationMail`’s constructor reads the logo file and base64-encodes it. That string is stored in the queued job payload. A large logo makes the `jobs.payload` column bigger, which can slightly slow down serialization, DB writes, and worker deserialization.

**Check:**

- Inspect `jobs.payload` size in the database for recent rows (e.g. after approving a registration).

**Fix:**

- Keep the logo file small (e.g. optimized PNG).
- Optionally, refactor so the logo is read **when building the email** in the worker (e.g. in `build()` or in the view) instead of in the constructor, so the payload doesn’t contain the full base64. This is an optimization, not required for correctness.

---

### 6. Database queue under load

**Cause:** The default queue driver is `database`. The worker polls the `jobs` table. Under high concurrency or many jobs, DB I/O and lock contention can add delay.

**Fix:**

- For production with more traffic, consider **Redis** for the queue: set `QUEUE_CONNECTION=redis` and run a Redis instance. Then run the worker as usual. This is optional and only needed if you see the DB as a bottleneck.

---

### 7. Network or firewall blocking SMTP

**Cause:** The server cannot reach `MAIL_HOST:MAIL_PORT` (e.g. 587), or the path is very slow. The worker will hang until the connection times out.

**Check:**

- From the server: `telnet smtp.gmail.com 587` (or use PowerShell `Test-NetConnection -ComputerName smtp.gmail.com -Port 587`). If it doesn’t connect, something is blocking.
- Check Windows Firewall / antivirus / corporate proxy for outbound SMTP.

**Fix:**

- Open outbound port 587 (and 465 if you switch to SSL) for the server.
- If you must use a relay or different port, update `MAIL_HOST`, `MAIL_PORT`, and `MAIL_ENCRYPTION` in `.env`.

---

### 8. Gmail throttling or “less secure app” / App Password

**Cause:** Gmail may throttle many sends in a short time, or block login if you’re not using an App Password for 2FA accounts. That can cause retries and delay or failed jobs.

**Check:**

- Check the `failed_jobs` table and `storage/logs/laravel.log` for SMTP auth or rate-limit errors.

**Fix:**

- Use a **Gmail App Password** for `MAIL_PASSWORD` (not the main account password) if 2FA is on.
- Don’t send high volume through a single Gmail account; use a transactional provider (e.g. Mailgun, SES, Postmark) if you need many emails.

---

## Quick checklist

| Check | What to do |
|-------|------------|
| Worker running? | Run `php artisan queue:work` (or equivalent) and keep it running. |
| `QUEUE_CONNECTION` | Must be `database` (or `redis`), not `sync`. |
| `MAIL_MAILER` | `smtp` for real email; `log` for local dev to avoid SMTP wait. |
| `MAIL_TIMEOUT` | Set in `.env` (e.g. `10`) so slow SMTP doesn’t hang too long. |
| Pending jobs? | `SELECT * FROM jobs;` – if rows exist and no worker, start the worker. |
| Failed jobs? | Check `failed_jobs` table and logs for SMTP/network errors. |
| Network to SMTP? | Test connectivity to `MAIL_HOST:MAIL_PORT` from the server. |

---

## Summary

- **Emails are sent by a queue worker**, not during the approval HTTP request. So “sending” is delayed until the worker runs.
- The **main reason** emails “take too long” or never arrive is usually: **queue worker not running**.
- Secondary reasons: **SMTP slow or timeout**, **queue set to sync**, **network/firewall**, or **Gmail throttling/auth**. Use the checks and fixes above to narrow it down.

For full invitation flow details, see `INVITATION_EMAIL_VERIFICATION.md` and `INVITATION_EMAIL_TEST_TRACE.md`.
