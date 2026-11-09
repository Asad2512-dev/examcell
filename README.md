# Run ExamCell on Windows

ExamCell is an examination management application with administrator, teacher and student accounts. Follow these instructions to run it on Windows using PowerShell and a local MySQL server. Run commands from the project folder unless stated otherwise.

## 1. Requirements

- A local server package such as XAMPP, WampServer or MAMP for Windows, with MySQL running. Choose a package that includes PHP 8.2 or newer.
- PHP 8.2 or newer, with PDO MySQL, BCMath, GD, ZIP, Mbstring, DOM/XML and Fileinfo enabled.
- Composer to install PHP dependencies.
- Node.js and npm if rebuilding frontend assets. The installed Vite release requires Node 20.19+ on the Node 20 branch, or 22.12+. Compiled assets are included in `public/build`, so Node is not needed just to serve those files.

## 2. Open the project and select PHP

Extract the project into a folder such as `C:\xampp\htdocs\ExamcellP`. Open **PowerShell** and enter:

```powershell
Set-Location "C:\xampp\htdocs\ExamcellP"
```

Replace this path if your project is elsewhere. Select your installed PHP folder. For XAMPP installed at `C:\xampp`, use:

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
php -v
php -m
composer --version
```

For WampServer or MAMP, replace `C:\xampp\php` with the folder containing your PHP 8.2+ `php.exe`. Use the same PHP installation for Composer and Artisan. If Composer is missing, install Composer for Windows and select that PHP executable during installation. Reopen PowerShell afterward.

Repeat the PHP path command in each new PowerShell window, or add the PHP folder to your Windows user **Path** environment variable. After changing Windows environment variables, reopen PowerShell.

Install Node.js if you need frontend builds. Reopen PowerShell after installation and check:

```powershell
node --version
npm --version
```

If PowerShell blocks `npm.ps1`, use `npm.cmd` in place of `npm` in the commands below, for example `npm.cmd ci`.

## 3. Install dependencies and prepare settings

```powershell
composer install
```

Create `.env` only if it does not already exist:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

`.env` holds your private connection settings. Keep an existing file when updating the project, and do not share or commit it.

## 4. Create and connect the database

Open your server package's control panel and start MySQL. To use phpMyAdmin, start Apache too, then open the package's phpMyAdmin page and create an empty database called `examcell`. For a default XAMPP setup, phpMyAdmin is usually at `http://localhost/phpmyadmin`.

Edit `.env` using a text editor. Set the following values, replacing the database port, username and password with the values from your local MySQL installation:

```dotenv
APP_NAME=ExamCell
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=examcell
DB_USERNAME=YOUR_MYSQL_USERNAME
DB_PASSWORD=YOUR_MYSQL_PASSWORD
DB_SOCKET=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
MAIL_MAILER=log
OPENAI_ENABLED=false
```

`3306` is the usual MySQL port; check the actual port in your control panel. Some local XAMPP installations use username `root` with an empty password; use that only if it matches your local configuration. For an empty password, set `DB_PASSWORD=`. Keep `DB_SOCKET` empty on Windows.

Prepare the application and check the connection:

```powershell
php artisan config:clear
php artisan examcell:setup-key
php artisan examcell:preflight
```

The setup-key command creates an application key only when one is missing. Keep the original key for an existing installation. Resolve any failed preflight checks before continuing.

Create the database tables:

```powershell
php artisan migrate
```

For an existing installation, back up its database first. Do not use `migrate:fresh`: it deletes existing tables.

## 5. Choose how to sign in

For your own installation, create an administrator:

```powershell
php artisan examcell:admin you@your-institution.edu --name="Examination Administrator"
```

Replace the email with your own. The command privately asks for a password of at least 12 characters. It does not overwrite an existing account.

For a populated local demonstration, optionally run:

```powershell
php artisan db:seed
```

The demo seeder is restricted to local/testing environments. Use it on a separate local demonstration database: it creates sample academic records and links the following login addresses to demo identities. On its initial run, matching existing login addresses can be reassigned to those identities. Subsequent runs retain already-linked passwords.

| Role | Email | Initial password |
| --- | --- | --- |
| Administrator | `admin@gmail.com` | `12345678` |
| Teacher | `teacher@gmail.com` | `12345678` |
| Student | `student@gmail.com` | `12345678` |

These credentials are for local demonstrations only.

## 6. Start the application

In the first terminal:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000/sign-in** and sign in. Keep this terminal open; press **Ctrl+C** to stop the server. Your MySQL server must also remain running. Apache is not required when using `php artisan serve`, except when opening phpMyAdmin.

In a second terminal, set the same PHP path, open the project folder and start the worker for bulk PDF jobs:

```powershell
Set-Location "C:\xampp\htdocs\ExamcellP"
php artisan queue:work --tries=1 --timeout=120
```

In a third terminal, do the same and start scheduled tasks:

```powershell
Set-Location "C:\xampp\htdocs\ExamcellP"
php artisan schedule:work
```

The scheduler runs daily backups at **02:00 Asia/Karachi** and periodically clears expired password-reset tokens. It must remain running for scheduled tasks to execute. The queue worker must remain running for queued bulk jobs.

To serve the application through Apache instead of `artisan serve`, configure an Apache virtual host whose document root is `C:/xampp/htdocs/ExamcellP/public` (adjust to your project path), enable `mod_rewrite` and allow `.htaccess` overrides for that directory. Set `APP_URL` to the actual virtual-host address. Run `php artisan config:clear` after changing it. Only the `public` folder should be exposed as the web document root.

## 7. Build or edit frontend assets

Skip this step when the included compiled assets work and you have not changed frontend source files.

```powershell
npm ci
npm run build
```

After editing files in `resources/css`, `resources/js` or Blade templates, rebuild with `npm run build`. Do not edit hashed files inside `public/build` directly.

For live frontend development, keep this running in another terminal alongside the application server:

```powershell
npm run dev
```

## 8. Set up academic records

For an empty installation:

1. Sign in as administrator and open **Academic Setup**.
2. Create a session, departments, semesters/classes and courses.
3. Add teacher and student accounts, teaching assignments and enrollments.
4. Configure and approve your institution's grading scheme and assessment rules.
5. Create an examination and its papers, rooms, timetable, seating and invigilation duties.
6. Review and publish the timetable.
7. Teachers enter and submit marks; administrators review, approve and publish results.
8. Students can then view their own published records and available admit cards.

An empty page can simply mean no records have been created for the selected session or account.

## 9. Optional email and AI settings

Local `MAIL_MAILER=log` writes email content, including password-reset links, to `storage/logs/laravel.log`. It does not deliver email. To deliver messages, configure your SMTP provider in `.env` or use the administrator email settings. Example environment fields:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_ADDRESS=YOUR_SENDER_ADDRESS
```

Set the port and `MAIL_SCHEME` according to your provider's requirements.

The application can run without an OpenAI key. To enable provider-backed features, privately configure:

```dotenv
OPENAI_ENABLED=true
OPENAI_API_KEY=YOUR_PRIVATE_KEY
OPENAI_MODEL=YOUR_ALLOWED_MODEL
```

Choose a model available to your account and supported by the feature. Never place API keys in browser scripts or templates. After changing settings:

```powershell
php artisan config:clear
php artisan queue:restart
```

If you run a worker manually, start it again after it exits.

## 10. Backups

Use the administrator Backups page or run:

```powershell
php artisan examcell:backup
```

Keep a separate secure copy of `.env` and its original `APP_KEY`. Downloaded backups contain private academic records.

To restore into a separate empty database whose name ends in `_restore`:

```powershell
php artisan examcell:restore "C:\Backups\backup.zip" examcell_recovery_restore
```

This verifies and stages the recovery; it does not automatically switch the running application. Review the restored database and staged private files before changing the database configuration.

## 11. Troubleshooting

| Problem | What to check |
| --- | --- |
| `php` or `composer` is not found | Add the folder containing `php.exe` to `Path`; install Composer for Windows if missing. |
| Missing PHP extension | Run `php -m` using the same PHP binary used by Composer and Artisan. Run `php --ini` to find the loaded `php.ini`, enable the required extension there and restart affected PHP/Apache processes. |
| Database connection refused | Start MySQL in your control panel; check host, port or socket in `.env`. Run `php artisan config:clear` and `php artisan examcell:db-check`. |
| Database access denied | Check the MySQL username, password and permissions. |
| Missing table | Confirm the intended database is selected, then run `php artisan migrate`. |
| Missing application key | Run `php artisan examcell:setup-key`. |
| Missing styles or Vite manifest | Run `npm ci` and `npm run build`. If a stopped dev server left `public/hot`, remove that stale file with `Remove-Item public/hot`. |
| Port 8000 is already used | Stop the other server, or use `--port=8001` and update `APP_URL`. |
| Form returns 419 | Reload the form, allow cookies, use one consistent hostname and check that `storage` is writable. |
| Bulk PDFs stay queued | Start the queue worker and inspect `php artisan queue:failed`. |
| Password-reset email does not arrive | Check whether `MAIL_MAILER=log`; configure SMTP for delivery. |
| Permission error | In Windows folder Properties → Security, grant your PHP/web-server user Modify access to `storage` and `bootstrap/cache`. |
| Unexpected application error | Inspect `storage/logs/laravel.log`; keep passwords and reset links private. |

## 12. Optional checks

Frontend checks and content validation:

```powershell
npm run test:frontend
php scripts/validate-content.php
```

PHP feature tests require a separate disposable MySQL database ending in `_test`. Create `examcell_test`, configure the test connection, and inspect `phpunit.xml` before running:

```powershell
php vendor/bin/phpunit
```

Tests can reset database tables. Never point them at your working or production database.
# examcell
