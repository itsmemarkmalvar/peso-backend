# Invitation email – verification that the user receives it properly

This document verifies that the invited user will receive the invitation email correctly (recipient, content, and link).

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

## 5. Summary

| Question | Answer |
|----------|--------|
| Will the **correct user** get the email? | Yes – it’s sent to `$user->email` (the address they registered with). |
| Is the **content** correct (name, role, link, expiry)? | Yes – view and mailable are wired correctly; queue restores the user so data is up to date. |
| Will the **link work** when they click? | Yes – URL format and frontend route/API match; set `FRONTEND_URL` in production. |
| What can still go wrong? | (1) SMTP/queue not running or failing, (2) `FRONTEND_URL` wrong in production, (3) spam filtering. |

So **yes – the user will receive the email properly** as long as the queue worker runs, the mailer (e.g. SMTP) works, and `FRONTEND_URL` is set correctly for the environment they use.
