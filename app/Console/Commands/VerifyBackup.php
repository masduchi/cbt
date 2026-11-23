<?php

namespace App\Console\Commands;

use App\Support\BackupVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {path? : Folder backup yang diperiksa; default backup terbaru}';

    protected $description = 'Memeriksa bahwa backup terbaru dapat dipulihkan dan menulis manifest verifikasi';

    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?: $this->latestBackup());

        if ($path === '' || ! File::isDirectory($path)) {
            $this->error('Folder backup tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $hasil = BackupVerifier::verify($path);
        } catch (Throwable $e) {
            $this->error('Backup tidak valid: '.$e->getMessage());

            return self::FAILURE;
        }

        File::put(
            $path.DIRECTORY_SEPARATOR.'verify.json',
            json_encode($hasil, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        $this->info(sprintf(
            'Backup %s valid (%s, %s byte).',
            basename($path),
            $hasil['database'],
            number_format($hasil['bytes'], 0, ',', '.')
        ));

        return self::SUCCESS;
    }

    private function latestBackup(): ?string
    {
        $root = storage_path('app/backups');

        if (! File::isDirectory($root)) {
            return null;
        }

        $directories = collect(File::directories($root))
            ->sortByDesc(fn (string $directory) => basename($directory))
            ->values();

        return $directories->first();
    }
}
