<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesPerPage
{
    /**
     * Jumlah baris per halaman yang konsisten untuk semua daftar: 25, 50, atau 100.
     * Nilai lain diabaikan agar tautan pagination tetap aman.
     */
    protected function perPage(Request $request, int $default = 25): int
    {
        $requested = (int) $request->query('per_page', $default);

        return in_array($requested, [25, 50, 100], true) ? $requested : $default;
    }
}
