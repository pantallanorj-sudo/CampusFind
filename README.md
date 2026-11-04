# CampusFind — Campus Lost and Found Information System

A web-based lost-and-found system for **La Verdad Christian College**. Students report lost/found items, browse listings, submit
ownership claims with proof, and follow every step through in-system notifications. Staff verify claims, confirm the physical
handover and manage unclaimed items. Administrators manage users, categories, settings and the audit trail.

**Stack:** HTML5 · CSS3 · vanilla JavaScript · PHP · SQLite via PDO. No framework, no Composer, no build step.

---

## 1. Features (all 18 required features are implemented)

| Area | Features |
|------|----------|
| Student | 1 Registration & login · 2 Report lost/found items (photo, duplicate warning) · 3 Browse/search/filter · 4 Manage my reports · 5 Claim with proof of ownership · 6 My claims & status tracking · 7 In-system notifications |
| Staff | 8 Dashboard (live numbers) · 9 Item report management · 10 Claim verification & review · 11 Handover confirmation · 12 Unclaimed items & archive · 13 Reports & CSV export |
| Administrator | 14 Users & roles · 15 Categories · 16 Status history / audit trail · 17 System settings |
| Public | 18 Returned-items history (sanitised; no login, as in the original project) |

Business rules in one paragraph: a report starts `open`; the first claim makes it `claim_pending`; staff move a claim through
`pending → under_review ⇄ needs_info → approved`; **only the confirmed handover** makes the claim and the item `returned`. Rejecting or
cancelling the last active claim re-opens the item. Students can only touch their own reports/claims; staff cannot change roles;
status history is append-only.

## 2. Requirements

* **PHP 7.4 or newer** (PHP 8.x recommended) with: `pdo_sqlite`, `sqlite3`, `session`, `pcre`, `filter` (all default in XAMPP).
* Recommended: `mbstring`, `fileinfo` (used when present; the app has fallbacks).
* XAMPP for Windows **or** PHP's built-in server. No MySQL needed. No internet access needed at runtime.

## 3. Install and run

### Option A — XAMPP (Windows)
1. Copy the folder to `C:\xampp\htdocs\CampusFind_Project`.
2. In the XAMPP Control Panel start **Apache** only.
3. In `C:\xampp\php\php.ini` make sure these lines are **not** commented out (no leading `;`), then restart Apache:
   `extension=pdo_sqlite`, `extension=sqlite3`, `extension=mbstring`, `extension=fileinfo`.
4. Create the database (step 4 below), then open **http://localhost/CampusFind_Project/**.

### Option B — PHP built-in server
```
cd CampusFind_Project
php -S localhost:8000 router.php
```
Open **http://localhost:8000/**. `router.php` is recommended: the built-in server ignores `.htaccess`, so it is what blocks direct
downloads of the database, source folders and private evidence. (`php -S localhost:8000` without it also runs the app.)

### BASE_URL — nothing to configure
The URL path is detected automatically (`/CampusFind_Project` under XAMPP, empty under `php -S`). To force a value edit
`BASE_URL_OVERRIDE` in `config/app.php`, or set the environment variable `CAMPUSFIND_BASE_URL` (for example `/CampusFind_Project`).
CSS, JavaScript, links, forms, redirects, cookies and upload URLs all use this one value.

### 4. Initialise the database
Pick one (both are safe to repeat — existing data is never erased or overwritten):
* **Browser, from the same computer:** open `…/setup-database.php` and press **Create database**.
* **Command line:** `php setup-database.php` (XAMPP: `C:\xampp\php\php.exe setup-database.php`).
  `--check` lists tables and row counts; `--upgrade` first copies the database to `database/backups/` and then applies `schema.sql`
  again (adds anything missing, e.g. after updating CampusFind).

The file is created at `database/campusfind.sqlite` (override with the `CAMPUSFIND_DB_PATH` environment variable). Seven default
categories and the default settings are inserted once. Every page, action and tool uses the same single connection (`config/database.php`).

### 5. Create the first administrator (no default account exists)
```
php tools/create-admin.php
```
It asks for name, e-mail and password (typed at the prompt, never on the command line; for scripts use the environment variable
`CAMPUSFIND_ADMIN_PASSWORD`). Staff accounts can be created later from **Admin → Users**, or with
`php tools/create-admin.php --name="…" --email=… --role=staff`, or by promoting a registered student:
`php tools/create-admin.php --promote=person@school.edu --role=staff`. The tool refuses to run from a browser.

### 6. Optional development data (local use only)
`php tools/seed-dev-data.php` creates four demo accounts (admin, staff, two students) with **random passwords printed once**
(nothing is hardcoded) plus a few reports, claims and one completed handover. Running it again duplicates nothing, and it
refuses to run if real accounts exist unless you add `--allow-existing`.

### 7. Afterwards
Delete `setup-database.php` (and, on a public server, the `tools/` and `tests/` folders). Nothing else depends on them.

## 4. Verify the database with DB Browser for SQLite
1. Open **DB Browser for SQLite** → *Open Database* → `database/campusfind.sqlite`.
2. *Database Structure* shows 11 tables: `users, categories, item_reports, claim_requests, claim_evidence, item_images, handover_records,
   status_history, claim_status_history, notifications, system_settings` (plus indexes and 4 append-only triggers).
3. *Browse Data* → check rows appear after you register, report, claim, approve and hand over. Useful queries:
```sql
SELECT id, full_name, email, role, is_active FROM users;
SELECT id, report_type, item_name, status FROM item_reports ORDER BY id DESC;
SELECT id, item_report_id, claimant_id, status FROM claim_requests;
SELECT * FROM handover_records;
SELECT * FROM claim_status_history ORDER BY id;
PRAGMA foreign_key_check;   -- should return no rows
```
Close DB Browser (or don't *Write Changes*) while the app is in use, otherwise SQLite may report "database is locked".
Timestamps in the database are **UTC**; screens show **Asia/Manila** time.

## 5. Try each role
| Role | How | What to look at |
|------|-----|-----------------|
| Student | Register at `register.php` | Report an item (with photo) · Browse/search · Claim a found item reported by *another* student · My claims · Notifications |
| Staff | Created by an admin | Dashboard · Claim queue → review → request info/approve · Handover · Unclaimed & archive · Reports & CSV |
| Admin | `tools/create-admin.php` | Users & roles · Categories · System settings · Status history · everything staff can do |
| Public | No login | `public/claimed-items-history.php` |

**Five-minute demo:** (1) Student A reports a found umbrella with a photo. (2) Student B searches for it and files a claim with proof.
(3) Staff open the claim → *Start review* → *Request info*; B answers. (4) Staff accept the evidence and *Approve* — note the item is still
**not** returned. (5) Staff press *Confirm handover* → claim and item become **Returned**; both students get notifications.
(6) Open the public history page (no private data shown). (7) Admin: change the retention days in *System settings*, view *Status
history*, add a category, deactivate a user. (8) Staff: *Reports & export* → download a CSV.

## 6. Uploads, storage and backups
* Item photos → `uploads/items/` (public; random names). Claim evidence → `uploads/claim-evidence/` (**private**: blocked from direct
  access and served only by `actions/serve-evidence.php` to the claimant, staff and admins). JPG/PNG/WebP, max 5 MB each, real image content checked.
  One photo per report; up to 3 evidence photos per submission.
* If large uploads fail, raise `upload_max_filesize` and `post_max_size` (≥ 16M) in `php.ini`; the web server user needs write access to `uploads/` and `logs/`.
* Logs: `logs/error.log` (PHP errors), `logs/audit.log` (admin actions: roles, activation, passwords, settings, categories).
* **Backup:** stop Apache (or make sure nobody is saving) and copy `database/campusfind.sqlite` plus the `uploads/` folder. Restore by putting them back.
  `php setup-database.php --upgrade` makes a timestamped copy in `database/backups/` automatically.

## 7. Settings (Admin → System settings)
`unclaimed_retention_days` (default 30; archiving reads the saved value) · `app_name` · `items_per_page` · `notify_staff_new_claims`.
Invalid or missing values fall back to safe defaults. Authentication and role checks cannot be configured away.
Archive rules: an *open found* item whose age (from the day it was reported) reaches the retention period can be archived by staff;
*closed* reports can be tidied away too; items with a claim in progress and **returned** items cannot (they stay in the public history).

## 8. Project layout
```
index.php login.php register.php logout.php setup-database.php router.php .htaccess
config/    app.php (settings, BASE_URL, session) · database.php (one PDO connection, transactions)
includes/  auth · csrf · permissions · validators · functions · components · header · footer · views/ (shared screens)
services/  Claim · Handover · DuplicateDetection · ImageUpload · Notification · ReportArchive · Export
           + Report · History · Settings · Category · User   (added to keep rules in one place)
student/ staff/ admin/ public/   pages        actions/   POST-only request handlers (+ GET csv export / evidence gatekeeper)
database/schema.sql   assets/css|js   uploads/   logs/   tools/ (CLI)   tests/ (documents + run-tests.php)
```

## 9. Changes to the original project (summary)
* `database/schema.sql`: **additive only** (no table or column changed): 7 supporting indexes, a unique "one approved/returned claim per report"
  index, 4 triggers making the two history tables append-only, 3 extra default settings. All statements are idempotent.
* Rewritten/completed: every page, all 13 actions that returned HTTP 501, all 7 services, CSS/JS, setup script, README, tests.
* Intentional deviations: PHP ≥ 7.4 compatibility (removed `never` return type and `strict_types`); logout is a POST button; the owner
  "Delete report" is a **controlled closure** (history cannot be erased); staff and admins may also *report* items (they often receive found items).

## 10. Test results — read this honestly
The development environment that produced this version had **no PHP interpreter and no network**, so `php -l` and the real PHP engine
were **not** available. What was done instead:

| Check | Result |
|-------|--------|
| Syntax check of all 80 PHP files with a purpose-built parser (not Zend) | 0 errors; deliberately broken code is rejected |
| Static analysis: undefined functions/classes/methods, call arity, include paths, link targets, CSRF on every POST form, auth on every action, unescaped output, placeholders | 0 problems; every builtin PHP function used was checked against the real PHP function list |
| End-to-end suite: the real PHP files run on a PHP-subset interpreter with SQLite and a simulated browser (cookies, sessions, uploads, redirects) | **603 checks passed in each of 4 configurations** (built-in-server URL / XAMPP-subdirectory URL × PDO returning native types / strings as PHP < 8.1) |
| PHP warnings or notices raised during all of that | **0** |
| PHP statement coverage of that suite | **96.1 %** (3,087 of 3,211 statements) |
| Browser JavaScript (upload checks, confirm dialogs, select-all) in Chromium | 9 checks passed |
| Layout in Chromium at 1280 / 820 / 390 px | no horizontal overflow on any checked page |

**Not done:** a run on real PHP/Apache/XAMPP and a human clicking through in a browser. Please run these once on your machine:
```
php -l on every file:   Windows PowerShell:  Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
                        Linux/macOS:         find . -name '*.php' -print0 | xargs -0 -n1 php -l
Self-test (real PHP):   php tests/run-tests.php        (temporary database, 56 checks, exit code 0 = pass)
```
(`tests/run-tests.php` passed on the interpreter described above; it has not yet run on real PHP.) Manual test sheets with a column for your own results are in `tests/*.md`.

## 11. Known limitations
* No e-mail/SMS: notifications are in-system and appear on the next page load. No "forgot password" and no self-service password change (admins reset passwords).
* Login throttling is per browser session only; use web-server rate limiting in production. No two-factor sign-in, no CAPTCHA.
* A rejected or cancelled claim cannot be filed again by the same student (the unique `(report, claimant)` rule was kept as required).
* Evidence and photos must be images (no PDFs); EXIF metadata is not stripped; one photo per report.
* Archiving is manual by design (staff review the eligible list); there is no scheduled job. CSV exports are capped at 5,000 rows.
* Lost-item reports are not matched to found items automatically. The public history hides the name/photo of items in a category named "ID / Cards".
* SQLite serves a school-sized load well; heavy concurrent writing may wait up to 5 s (busy timeout).

## 12. Before using it publicly
Serve over **HTTPS** (secure cookies switch on automatically) · move the database outside the web root and point `CAMPUSFIND_DB_PATH` at it ·
delete `setup-database.php`, `tools/`, `tests/` and `README.md` from the server · keep `display_errors` off (default; `CAMPUSFIND_DEBUG` must not be set) ·
confirm Apache honours the `.htaccess` files (`AllowOverride All`) and that `uploads/claim-evidence/` is not reachable by URL ·
restrict write permission to `uploads/`, `logs/` and the database folder only · schedule backups · keep PHP updated ·
adjust `SESSION_IDLE_SECONDS` / the CSP header in `config/app.php` if your hosting needs it.
