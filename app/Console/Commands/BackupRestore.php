<?php

namespace App\Console\Commands;

use App\Support\AuditLog;
use App\Support\BackupVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore
                            {name : Nama folder backup di storage/app/backups}
                            {--force : Pulihkan tanpa konfirmasi interaktif}';

    protected $description = 'Memulihkan database dari berkas backup yang sudah diverifikasi';

    public function handle(): int
    {
        $name = basename((string) $this->argument('name'));
        $directory = storage_path('app/backups/'.$name);

        if ($name === '' || $name === '.' || $name === '..' || ! File::isDirectory($directory)) {
            $this->error('Folder backup tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            BackupVerifier::verify($directory);
        } catch (Throwable $e) {
            $this->error('Backup tidak layak dipulihkan: '.$e->getMessage());

            return self::FAILURE;
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->error('Pemulihan hanya didukung untuk database MySQL.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Seluruh data saat ini akan diganti dengan isi backup '.$name.'. Lanjutkan?')) {
            $this->warn('Pemulihan dibatalkan.');

            return self::FAILURE;
        }

        // Backup pengaman sebelum menimpa database. Bila gagal, pemulihan tetap
        // boleh lanjut karena data lama mungkin justru sedang rusak.
        if ($this->call('backup:run') !== self::SUCCESS) {
            $this->warn('Backup pengaman gagal dibuat; pemulihan dilanjutkan.');
        }

        $sql = $this->dumpContents($directory);

        if ($sql === null) {
            $this->error('Berkas dump tidak dapat dibaca.');

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));

        $process = new Process([
            $this->findMysqlBinary(),
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            $connection['database'],
        ]);

        $process->setInput($sql);
        $process->setTimeout(1200);

        $env = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $env['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
            $env['windir'] = $env['SystemRoot'];
        }

        if (! empty($connection['password'])) {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        if ($env !== []) {
            $process->setEnv($env);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Pemulihan gagal: '.(trim($process->getErrorOutput()) ?: 'mysql menolak dump.'));

            return self::FAILURE;
        }

        DB::purge();

        // Saat dijalankan lewat web, controller mencatat audit dengan pengguna
        // yang sebenarnya; jalur CLI tidak punya pengguna sehingga dicatat tamu.
        if (app()->runningInConsole()) {
            AuditLog::recordAttempt('database.restore', 'Backup '.$name.' dipulihkan');
        }

        $this->info('Database berhasil dipulihkan dari backup '.$name.'.');

        return self::SUCCESS;
    }

    private function dumpContents(string $directory): ?string
    {
        $gzip = $directory.DIRECTORY_SEPARATOR.'database.sql.gz';
        $plain = $directory.DIRECTORY_SEPARATOR.'database.sql';

        if (File::exists($gzip)) {
            $raw = @gzdecode((string) File::get($gzip));

            return $raw === false ? null : $raw;
        }

        if (File::exists($plain)) {
            return (string) File::get($plain);
        }

        return null;
    }

    private function findMysqlBinary(): string
    {
        $configured = (string) config('backup.mysql_path', 'mysql');

        if ($configured !== '' && $configured !== 'mysql') {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['C:/MAMP/bin/mysql/bin/mysql.exe', 'C:/xampp/mysql/bin/mysql.exe', 'C:/laragon/bin/mysql/mysql/bin/mysql.exe']
            : ['/usr/bin/mysql', '/usr/local/bin/mysql'];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $configured;
    }
}
