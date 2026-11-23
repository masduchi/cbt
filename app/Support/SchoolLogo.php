<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class SchoolLogo
{
    /**
     * URL publik logo sekolah untuk halaman Inertia.
     */
    public static function url(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Logo sebagai data URI agar dapat disematkan langsung pada PDF Dompdf.
     */
    public static function dataUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}
