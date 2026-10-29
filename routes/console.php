<?php

use App\Models\User;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('examcell:db-check', function () {
    try {
        $row = DB::selectOne('select database() as db, version() as version');
        $this->info('Connected: '.$row->db.'; server '.$row->version);
    } catch (Throwable $e) {
        $this->error('Connection failed. Check the MAMP host, port/socket, database and credentials.');

        return 1;
    }
});
Artisan::command('examcell:admin {email} {--name=Examination Administrator}', function () {
    if (User::where('email', $this->argument('email'))->exists()) {
        $this->error('Email already exists; no changes made.');

        return 1;
    }$password = $this->secret('New password (minimum 12 characters)');
    if (strlen($password ?? '') < 12) {
        $this->error('Password too short.');

        return 1;
    }$u = User::create(['name' => $this->option('name'), 'email' => $this->argument('email'), 'role' => 'admin', 'password' => $password, 'active' => true]);
    app(AuditService::class)->record('admin.provisioned', $u);
    $this->info('Administrator created.');
});

Artisan::command('examcell:setup-key', function () {
    if (config('app.key')) {
        $this->info('Existing application key retained.');

        return 0;
    }

    return $this->call('key:generate');
});

Artisan::command('examcell:backup', function () {
    try {
        $run = app(BackupService::class)->create();
        $this->info('Verified backup #'.$run->id.' stored privately.');
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
});
Artisan::command('examcell:restore {archive} {target}', function () {
    if (! $this->confirm('Restore to a separate empty database ending in _restore? Current database will be retained.')) {
        return 1;
    }try {
        app(BackupService::class)->restore($this->argument('archive'), $this->argument('target'));
        $this->info('Restore verified. Review the staged database and private files before switching configuration.');
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
});
Artisan::command('examcell:preflight', function () {
    $checks = ['PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='), 'PDO MySQL' => extension_loaded('pdo_mysql'), 'BCMath' => extension_loaded('bcmath'), 'ZIP' => extension_loaded('zip'), 'APP_KEY' => ! empty(config('app.key')), 'Private storage writable' => is_writable(storage_path('app')), 'Queue driver configured' => in_array(config('queue.default'), ['database', 'sync'])];
    foreach ($checks as $name => $pass) {
        $this->line(($pass ? 'PASS ' : 'FAIL ').$name);
    }try {
        DB::connection()->getPdo();
        $this->info('PASS MySQL connection');
    } catch (Throwable $e) {
        $this->error('FAIL MySQL connection; check local MAMP settings.');

        return 1;
    }$this->line(config('services.openai.key') && config('services.openai.enabled') ? 'OpenAI configured; check live access from assistant.' : 'OpenAI optional explanation disabled/unconfigured; exact database answers work.');
    $this->line('Mail transport: '.config('mail.default').'; run queue:work and schedule:work in separate terminals.');

    return in_array(false, $checks, true) ? 1 : 0;
});
Schedule::command('examcell:backup')->dailyAt('02:00')->withoutOverlapping();
