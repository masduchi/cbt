<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\VerifikasiDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiAdminController extends Controller
{
    /**
     * Label jenis dokumen yang menerbitkan kode verifikasi.
     *
     * @var array<string, string>
     */
    private const JENIS = [
        'kartu-peserta' => 'Kartu peserta ujian',
        'kartu-peserta-siswa' => 'Kartu peserta ujian (per siswa)',
        'rapor' => 'Rapor',
        'buku-induk' => 'Buku induk',
    ];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->query('per_page', 40);
        $perPage = in_array($perPage, [25, 40, 50, 100], true) ? $perPage : 40;

        $status = (string) $request->query('status');
        $filters = [
            'q' => trim((string) $request->query('q')),
            'jenis' => trim((string) $request->query('jenis')),
            'status' => in_array($status, ['aktif', 'dicabut'], true) ? $status : '',
            'per_page' => $perPage,
        ];

        $dokumen = DB::table('dokumen_verifikasi')
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($inner) use ($filters) {
                $inner->where('kode', 'like', '%'.$filters['q'].'%')
                    ->orWhere('ref', 'like', '%'.$filters['q'].'%')
                    ->orWhere('ringkasan', 'like', '%'.$filters['q'].'%');
            }))
            ->when($filters['jenis'] !== '', fn ($query) => $query->where('jenis', $filters['jenis']))
            ->when($filters['status'] === 'aktif', fn ($query) => $query->whereNull('revoked_at'))
            ->when($filters['status'] === 'dicabut', fn ($query) => $query->whereNotNull('revoked_at'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'kode' => (string) $row->kode,
                'jenis' => (string) $row->jenis,
                'jenis_label' => self::JENIS[$row->jenis] ?? (string) $row->jenis,
                'ref' => (string) $row->ref,
                'ringkasan' => json_decode((string) $row->ringkasan, true) ?: [],
                'aktif' => $row->revoked_at === null,
                'diterbitkan' => $row->created_at
                    ? Carbon::parse($row->created_at)->isoFormat('D MMM YYYY, HH:mm')
                    : '-',
                'diperbarui' => $row->updated_at
                    ? Carbon::parse($row->updated_at)->isoFormat('D MMM YYYY, HH:mm')
                    : '-',
                'dicabut' => $row->revoked_at
                    ? Carbon::parse($row->revoked_at)->isoFormat('D MMM YYYY, HH:mm')
                    : null,
                'url' => VerifikasiDokumen::url((string) $row->kode),
            ]);

        $jenis = DB::table('dokumen_verifikasi')
            ->select('jenis')
            ->distinct()
            ->orderBy('jenis')
            ->pluck('jenis')
            ->map(fn ($value) => [
                'value' => (string) $value,
                'label' => self::JENIS[$value] ?? (string) $value,
            ]);

        return Inertia::render('Setting/Verifikasi', [
            'dokumen' => $dokumen,
            'jenis' => $jenis,
            'filters' => $filters,
            'statistik' => [
                'total' => DB::table('dokumen_verifikasi')->count(),
                'aktif' => DB::table('dokumen_verifikasi')->whereNull('revoked_at')->count(),
                'dicabut' => DB::table('dokumen_verifikasi')->whereNotNull('revoked_at')->count(),
            ],
        ]);
    }

    /**
     * Mencabut kode yang bocor agar dokumen cetak lama tidak lagi terverifikasi.
     * Nilai lain (kode, ringkasan) tetap disimpan sebagai jejak.
     */
    public function cabut(Request $request, string $kode): RedirectResponse
    {
        $row = VerifikasiDokumen::temukan($kode);

        abort_if($row === null, 404);

        $tercabut = DB::table('dokumen_verifikasi')
            ->where('id', $row->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'kunci_aktif' => null, 'updated_at' => now()]);

        if ($tercabut === 0) {
            return redirect()->route('setting.verifikasi.index')
                ->with('info', 'Kode '.$row->kode.' sudah dicabut.');
        }

        AuditLog::record(
            'verifikasi.cabut',
            'Kode '.$row->kode.' ('.$row->jenis.': '.$row->ref.') dicabut',
        );

        return redirect()->route('setting.verifikasi.index')
            ->with('success', 'Kode '.$row->kode.' dicabut. Dokumen lama dengan kode ini tidak lagi terverifikasi.');
    }
}
