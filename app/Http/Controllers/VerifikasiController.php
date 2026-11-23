<?php

namespace App\Http\Controllers;

use App\Support\VerifikasiDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiController extends Controller
{
    /**
     * Halaman publik yang dibuka setelah QR pada dokumen cetak dipindai.
     */
    public function show(Request $request, string $kode): Response
    {
        $row = VerifikasiDokumen::temukan($kode);
        $valid = $row !== null && $row->revoked_at === null;
        $ringkasan = $valid ? json_decode((string) $row->ringkasan, true) : [];

        return Inertia::render('Publik/Verifikasi', [
            'kode' => strtoupper($kode),
            'valid' => $valid,
            'jenis' => $valid ? (string) $row->jenis : null,
            'ringkasan' => is_array($ringkasan) ? $ringkasan : [],
            'diterbitkan' => $valid && $row->created_at
                ? Carbon::parse($row->created_at)->isoFormat('D MMMM YYYY, HH:mm')
                : null,
            'diperbarui' => $valid && $row->updated_at
                ? Carbon::parse($row->updated_at)->isoFormat('D MMMM YYYY, HH:mm')
                : null,
        ]);
    }
}
