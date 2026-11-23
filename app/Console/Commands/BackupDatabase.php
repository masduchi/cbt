<?php

namespace App\Console\Commands;

use App\Support\BackupOffsite;
use App\Support\BackupVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run {--keep=14 : Jumlah hari backup yang disimpan}';

    protected $description = 'Membuat backup database dan berkas publik, lalu menghapus backup lama';

    public function handle(): int
    {
        $root = storage_path('app/backups');
        $stamp = now()->format('Ymd-His');
        $target = $root.DIRECTORY_SEPARATOR.$stamp;

        File::ensureDirectoryExists($target);

        try {
            $this->dumpDatabase($target.DIRECTORY_SEPARATOR.'database.sql');
            $this->archivePublicStorage($target.DIRECTORY_SEPARATOR.'storage-public.zip');

            $hasil = BackupVerifier::verify($target);
            File::put(
                $target.DIRECTORY_SEPARATOR.'verify.json',
                json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );
        } catch (Throwable $e) {
            Log::error('Backup gagal', ['message' => $e->getMessage()]);
            $this->error('Backup gagal: '.$e->getMessage());

            File::deleteDirectory($target);

            return self::FAILURE;
        }

        if (BackupOffsite::configured()) {
            try {
                $copy = BackupOffsite::copy($target);
                $this->info('Salinan off-site: '.$copy);
            } catch (Throwable $e) {
                Log::error('Salinan backup off-site gagal', ['message' => $e->getMessage()]);
                $this->error('Backup lokal dibuat, tetapi salinan off-site gagal: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->prune($root, max(1, (int) $this->option('keep')));

        Log::info('Backup selesai', ['path' => $target]);
        $this->info('Backup tersimpan di '.$target);

        return self::SUCCESS;
    }

    private function dumpDatabase(string $path): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $binary = $this->findDumpBinary();

        $args = [
            $binary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $connection['database'],
        ];

        $process = new Process($args);
        $process->setTimeout(600);

        $env = [];

        if (PHP_OS_FAMILY === 'Windows') {
            // Proses web tidak selalu mewarisi variabel ini; tanpa SystemRoot mysqldump gagal membuka socket.
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

        if (! $process->isSuccessful() || ! File::exists($path) || File::size($path) === 0) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump tidak menghasilkan berkas.');
        }

        $compressed = $path.'.gz';

        if (function_exists('gzencode')) {
            File::put($compressed, gzencode(File::get($path), 6));
            File::delete($path);
        }
    }

    private function findDumpBinary(): string
    {
        $configured = (string) config('backup.mysqldump_path', 'mysqldump');

        if ($configured !== '' && $configured !== 'mysqldump') {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['C:/MAMP/bin/mysql/bin/mysqldump.exe', 'C:/xampp/mysql/bin/mysqldump.exe', 'C:/laragon/bin/mysql/mysql/bin/mysqldump.exe']
            : ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $configured;
    }

    private function archivePublicStorage(string $zipPath): void
    {
        $source = storage_path('app/public');

        if (! File::isDirectory($source)) {
            return;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new \RuntimeException('Tidak bisa membuat arsip berkas publik.');
        }

        $files = File::allFiles($source);

        foreach ($files as $file) {
            // Separator arsip wajib '/', bukan '\' bawaan Windows, agar berkas tetap
            // terbuka sebagai struktur folder saat diekstrak di sistem lain.
            $entry = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), $entry);
        }

        $zip->close();
    }

    private function prune(string $root, int $keepDays): void
    {
        $limit = now()->subDays($keepDays)->getTimestamp();

        foreach (File::directories($root) as $directory) {
            if (File::lastModified($directory) < $limit) {
                File::deleteDirectory($directory);
            }
        }
    }
}
