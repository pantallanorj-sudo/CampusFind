# Authentication & session tests

**How these were verified.** "Auto" = covered by the automated end-to-end suite that was run against the real PHP files on a
PHP-subset interpreter + SQLite (see README, *Test results*). That is **not** a run on the real PHP engine, so please repeat the
cases marked *Repeat* on your own XAMPP / `php -S` setup and tick the last column.

| # | Case | Steps | Expected | Auto | Your run |
|---|------|-------|----------|------|----------|
| A1 | Register a student | `register.php`, valid name/email/password | Row in `users` with `role='student'`, `is_active=1`, `password_hash` starts with `$2y$` | ✔ | ☐ |
| A2 | Duplicate email | Register `ONE@x.com` after `one@x.com` | Rejected: "already registered"; no new row (case-insensitive) | ✔ | ☐ |
| A3 | Invalid input | Bad email / password < 8 / mismatch / empty name | Each rejected with a readable message; no row | ✔ | ☐ |
| A4 | No role choice | POST extra field `role=admin` to `register.php` | Account is still a `student` | ✔ | ☐ |
| A5 | Wrong password | Log in with a wrong password | Generic "Incorrect email or password" | ✔ | ☐ |
| A6 | Unknown email | Log in with an unknown email | Same generic message (no account details) | ✔ | ☐ |
| A7 | Login redirect by role | Log in as student / staff / admin | Student, Staff and Admin dashboards respectively (staff never lands on the admin dashboard) | ✔ | ☐ |
| A8 | Session fixation | Compare `CAMPUSFIND_SESSION` cookie before/after login | Session id changes | ✔ | ☐ Repeat |
| A9 | Cookie flags | Inspect `Set-Cookie` | `HttpOnly; SameSite=Lax; path=<app path>` (+`Secure` over HTTPS) | ✔ | ☐ Repeat |
| A10 | Inactive account | Admin deactivates a user; user logs in | Refused with "account is inactive" (only shown when the password was correct) | ✔ | ☐ |
| A11 | Deactivation mid-session | Deactivate a user who is logged in | Their next request is signed out | ✔ | ☐ |
| A12 | Role change mid-session | Promote/demote a logged-in user | New permissions apply on the next request | ✔ | ☐ |
| A13 | Throttle | 5 wrong passwords in one session | 6th attempt blocked for up to 10 min, even with the right password | ✔ | ☐ |
| A14 | Idle timeout | Leave a session idle > 2 h (`SESSION_IDLE_SECONDS`) | Signed out with an explanatory message | ✔ | ☐ |
| A15 | Logout | Press *Log out*; also open `logout.php` by URL | Button (POST + CSRF) logs out; a plain GET does nothing; POST without token → 419 | ✔ | ☐ |
| A16 | Protected pages | Open any student/staff/admin URL logged out | Redirect to `login.php` (and back to the page after login) | ✔ | ☐ |
| A17 | First administrator | `php tools/create-admin.php` | Admin created from the CLI only; web access to `tools/` → 403; no default password exists | ✔ | ☐ |
| A18 | Admin password reset | Admin sets a new password for a user | Old password stops working, new one works; weak password refused | ✔ | ☐ |
