# CampusFind — PHP + SQLite starter project

CampusFind is a campus lost-and-found system starter built with PHP, PDO, SQLite, HTML, CSS, and vanilla JavaScript.

## Run locally (XAMPP)
1. Copy the `CampusFind_Project` folder into `C:\xampp\htdocs\campusfind`.
2. Check `config/app.php`: set `BASE_URL` to `/campusfind`.
3. Ensure PHP has the `pdo_sqlite` and `fileinfo` extensions enabled.
4. Visit `http://localhost/campusfind/setup-database.php` once to create the tables.
5. Delete `setup-database.php` after setup.
6. Visit `http://localhost/campusfind/`.

Alternative: from the project folder run `php -S localhost:8000`, set `BASE_URL` to an empty string, then visit `http://localhost:8000/setup-database.php` once and delete that setup script afterward.

## Important
- This is a project scaffold with a working database schema, authentication, role checks, reporting form, item browsing, and basic dashboards. Some advanced screens/actions are starter pages and need completion/testing before production or final defense.
- First registration is a student account. Do not expose role selection on public registration.
- Change the default admin password immediately if you add a seeded administrator. No default admin account is created by this starter.
- Keep `database/` and private uploads outside the public web root in a real deployment. For a school local demo, use server rules to deny direct access to database files and private claim evidence.
- Never leave `setup-database.php` available after initialization.
