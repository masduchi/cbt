<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Media soal tidak lagi ditautkan langsung ke disk publik. Berkas di bawah
 * folder soal/ disajikan lewat rute yang membutuhkan login, sedangkan berkas
 * lain (foto profil, logo) tetap memakai URL publik biasa.
 */
class SoalMedia
{
    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'soal/')) {
            return url('/media/'.$path);
        }

        return Storage::disk('public')->url($path);
    }
}
