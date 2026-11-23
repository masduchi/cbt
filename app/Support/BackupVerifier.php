<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Memastikan berkas backup benar-benar dapat dipulihkan, bukan sekadar ada.
 * Dump diperiksa bisa didekompresi dan memuat definisi tabel; arsip publik
 * diperiksa bisa dibuka sebagai ZIP.
 */
class BackupVerifier
{
    /**
     * @return array{database: string, bytes: int, public_archive: ?string, verified_at: string}
     */
    public static function verify(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            throw new RuntimeException('Folder backup tidak ditemukan.');
        }

        $gzip = $directory.DIRECTORY_SEPARATOR.'database.sql.gz';
        $plain = $directory.DIRECTORY_SEPARATOR.'database.sql';
        $database = null;
        $bytes = 0;

        if (File::exists($gzip)) {
            $raw = @gzdecode((string) File::get($gzip));

            if ($raw === false || trim($raw) === '') {
                throw new RuntimeException('Dump database tidak dapat didekompresi atau kosong.');
            }

            if (! str_contains($raw, 'CREATE TABLE')) {
                throw new RuntimeException('Dump database tidak memuat definisi tabel.');
            }

            $database = 'database.sql.gz';
            $bytes = strlen($raw);
        } elseif (File::exists($plain)) {
            $raw = (string) File::get($plain);

            if (trim($raw) === '' || ! str_contains($raw, 'CREATE TABLE')) {
                throw new RuntimeException('Dump database kosong atau tidak memuat definisi tabel.');
            }

            $database = 'database.sql';
            $bytes = strlen($raw);
        } else {
            throw new RuntimeException('Dump database tidak ditemukan pada folder backup.');
        }

        $archive = null;
        $zipPath = $directory.DIRECTORY_SEPARATOR.'storage-public.zip';

        if (File::exists($zipPath)) {
            $zip = new ZipArchive;

            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Arsip berkas publik tidak dapat dibuka.');
            }

            $zip->close();
            $archive = 'storage-public.zip';
        }

        return [
            'database' => $database,
            'bytes' => $bytes,
            'public_archive' => $archive,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
