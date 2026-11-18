<?php

namespace App\Services;

use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    private function tables(): array
    {
        return array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));
    }

    public function create(?int $actor = null): BackupRun
    {
        $dir = Storage::path('backups');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
            throw new \RuntimeException('Private backup directory is not writable.');
        }
        $lock = fopen($dir.'/mutex-'.sha1(config('app.env').':'.DB::connection()->getDatabaseName()).'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }throw new \RuntimeException('Another backup is running.');
        }
        $originalLevel = DB::transactionLevel();
        $run = null;
        $zip = null;
        $tmp = null;
        $path = null;
        try {
            $run = BackupRun::create(['requested_by' => $actor, 'status' => 'running']);
            $dir = Storage::path('backups');
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }$path = 'backups/'.Str::uuid().'.zip';
            $tmp = $dir.'/'.Str::uuid().'.sql';
            $zip = null;

            $f = fopen($tmp, 'wb');
            fwrite($f, "-- ExamCell MySQL logical backup v1\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $pdo = DB::connection()->getPdo();
            if (DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }DB::beginTransaction();
            foreach ($this->tables() as $table) {
                if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    throw new \RuntimeException('Unexpected table name.');
                }$definition = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
                fwrite($f, 'DROP TABLE IF EXISTS `'.$table."`;\n".array_values($definition)[1].";\n");
                $query = $pdo->query('SELECT * FROM `'.$table.'`');
                while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                    $fields = implode(',', array_map(fn ($k) => '`'.$k.'`', array_keys($row)));
                    $values = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row)));
                    fwrite($f, 'INSERT INTO `'.$table.'` ('.$fields.') VALUES ('.$values.");\n");
                }
            }
            DB::commit();
            fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($f);
            $zip = new ZipArchive;
            if ($zip->open(Storage::path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Archive could not be opened.');
            }$zip->addFile($tmp, 'database.sql');
            $manifest = ['format' => 'examcell-backup-v1', 'created_at' => now()->toIso8601String(), 'database_sha256' => hash_file('sha256', $tmp), 'app_key_fingerprint' => hash('sha256', (string) config('app.key')), 'note' => 'Keep the existing APP_KEY to decrypt protected application data. Credentials are not included.'];
            $manifest['private_files'] = [];
            $private = rtrim(Storage::path(''), DIRECTORY_SEPARATOR);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($private, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }$relative = substr($file->getPathname(), strlen($private) + 1);
                if (str_starts_with($relative, 'backups/') || str_starts_with($relative, 'restore/')) {
                    continue;
                }$zip->addFile($file->getPathname(), 'private/'.$relative);
                $manifest['private_files']['private/'.$relative] = hash_file('sha256', $file->getPathname());
            }$manifest['signature'] = hash_hmac('sha256', $manifest['database_sha256'].'|'.$manifest['app_key_fingerprint'].'|'.$manifest['format'].'|'.json_encode($manifest['private_files'] ?? []), (string) config('app.key'));
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            $zip->close();
            $zip = null;
            $this->verify(Storage::path($path));
            $run->update(['status' => 'completed', 'path' => $path, 'checksum' => hash_file('sha256', Storage::path($path)), 'bytes' => Storage::size($path), 'verified_at' => now()]);
            app(AuditService::class)->record('backup.verified', $run, ['sha256' => $run->checksum, 'bytes' => $run->bytes]);

            return $run;
        } catch (\Throwable $e) {
            while (DB::transactionLevel() > $originalLevel) {
                DB::rollBack();
            }if (is_resource($f ?? null)) {
                fclose($f);
            }if ($zip) {
                $zip->close();
            }if ($path) {
                Storage::delete($path);
            }
            $run?->update(['status' => 'failed', 'failure' => 'Backup failed. Check database read access and private storage permissions.']);
            throw new \RuntimeException($run?->failure ?? 'Backup failed before initialization. Check database and storage permissions.');
        } finally {
            if ($tmp && is_file($tmp)) {
                unlink($tmp);
            }flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function verify(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Invalid backup archive.');
        }try {
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true);
            if (($manifest['format'] ?? null) !== 'examcell-backup-v1') {
                throw new \RuntimeException('Unrecognized backup format.');
            }$expected = hash_hmac('sha256', ($manifest['database_sha256'] ?? '').'|'.($manifest['app_key_fingerprint'] ?? '').'|'.($manifest['format'] ?? '').'|'.json_encode($manifest['private_files'] ?? []), (string) config('app.key'));
            if (! hash_equals($expected, $manifest['signature'] ?? '')) {
                throw new \RuntimeException('Backup signature mismatch. Use the original APP_KEY; do not restore modified archives.');
            }$sql = $zip->getFromName('database.sql');
            if ($sql === false || ! hash_equals($manifest['database_sha256'], hash('sha256', $sql))) {
                throw new \RuntimeException('Database backup checksum mismatch.');
            }foreach ($manifest['private_files'] ?? [] as $name => $hash) {
                $bytes = $zip->getFromName($name);
                if ($bytes === false || ! hash_equals($hash, hash('sha256', $bytes))) {
                    throw new \RuntimeException('Private document checksum mismatch.');
                }
            }for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! in_array($name, ['database.sql', 'manifest.json']) && ! array_key_exists($name, $manifest['private_files'] ?? [])) {
                    throw new \RuntimeException('Unrecognized file in signed backup.');
                }
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw new \RuntimeException('Unsafe archive path.');
                }
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    public function restore(string $archive, string $target): void
    {
        // Restores only to a new empty database; never destroys the current academic database.
        if (! preg_match('/^[a-zA-Z0-9_]+_restore$/', $target) || $target === DB::connection()->getDatabaseName()) {
            throw new \RuntimeException('Use a separate database name ending in _restore.');
        }$this->verify($archive);
        $exists = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? LIMIT 1', [$target]);
        if ($exists) {
            throw new \RuntimeException('Restore target is not empty. Choose a new database.');
        }config(['database.connections.restore_admin' => config('database.connections.mysql')]);
        DB::connection('restore_admin')->statement('CREATE DATABASE IF NOT EXISTS `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $cfg = config('database.connections.mysql');
        $cfg['database'] = $target;
        config(['database.connections.restore' => $cfg]);
        DB::purge('restore');
        $pdo = DB::connection('restore')->getPdo();
        $zip = new ZipArchive;
        $zip->open($archive);
        $sql = $zip->getFromName('database.sql');
        $pdo->exec($sql);
        $directory = Storage::path('restore/'.$target);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, 'private/')) {
                continue;
            }$relative = substr($name, 8);
            $dest = $directory.'/'.$relative;
            if (! is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0700, true);
            }file_put_contents($dest, $zip->getFromIndex($i));
        }$zip->close();
        if (! DB::connection('restore')->getSchemaBuilder()->hasTable('users')) {
            throw new \RuntimeException('Restored database verification failed.');
        }app(AuditService::class)->record('backup.restored', 'database', ['target' => $target, 'private_files_staged' => $directory]);
    }
}
