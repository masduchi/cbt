<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Salinan backup di luar host aplikasi. Path tujuan biasanya berupa mount
 * jaringan, disk eksternal, atau folder sinkronisasi; bila kosong, fitur ini
 * tidak aktif dan health tidak menuntutnya.
 */
class BackupOffsite
{
    public static function configured(): bool
    {
        return self::root() !== '';
    }

    /**
     * Salin seluruh isi satu folder backup ke tujuan off-site.
     *
     * @return string folder tujuan
     */
    public static function copy(string $source): string
    {
        $root = self::root();

        if ($root === '') {
            throw new RuntimeException('BACKUP_OFFSITE_PATH belum diatur.');
        }

        if (! File::isDirectory($source)) {
            throw new RuntimeException('Folder backup sumber tidak ditemukan: '.$source);
        }

        $target = $root.DIRECTORY_SEPARATOR.basename($source);

        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $relative = $file->getRelativePathname();
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            File::ensureDirectoryExists(dirname($destination));
            File::copy($file->getPathname(), $destination);
        }

        File::put(
            $target.DIRECTORY_SEPARATOR.'offsite.json',
            json_encode([
                'copied_at' => now()->toIso8601String(),
                'source' => basename($source),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $target;
    }

    public static function exists(string $name): bool
    {
        $root = self::root();

        return $root !== '' && File::isDirectory($root.DIRECTORY_SEPARATOR.$name);
    }

    private static function root(): string
    {
        return rtrim(trim((string) config('backup.offsite_path')), '/\\');
    }
}
