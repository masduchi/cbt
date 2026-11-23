<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KopCetakController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setting/Kop', [
            'kartu' => DB::table('cbt_kop_kartu')->where('id_set_kartu', 1)->first(),
            'berita' => DB::table('cbt_kop_berita')->where('id_kop', 1)->first(),
            'absensi' => DB::table('cbt_kop_absensi')->where('id_kop', 1)->first(),
            'sekolah' => Setting::first()?->sekolah,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kartu.header_1' => ['nullable', 'string', 'max:100'],
            'kartu.header_2' => ['nullable', 'string', 'max:100'],
            'kartu.header_3' => ['nullable', 'string', 'max:100'],
            'kartu.header_4' => ['nullable', 'string', 'max:100'],
            'kartu.tanggal' => ['nullable', 'string', 'max:100'],
            'berita.header_1' => ['nullable', 'string', 'max:100'],
            'berita.header_2' => ['nullable', 'string', 'max:100'],
            'berita.header_3' => ['nullable', 'string', 'max:100'],
            'berita.header_4' => ['nullable', 'string', 'max:100'],
            'absensi.header_1' => ['nullable', 'string', 'max:100'],
            'absensi.header_2' => ['nullable', 'string', 'max:100'],
            'absensi.header_3' => ['nullable', 'string', 'max:100'],
            'absensi.header_4' => ['nullable', 'string', 'max:100'],
            'absensi.proktor' => ['nullable', 'string', 'max:100'],
            'absensi.pengawas_1' => ['nullable', 'string', 'max:100'],
            'absensi.pengawas_2' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data) {
            DB::table('cbt_kop_kartu')->updateOrInsert(['id_set_kartu' => 1], $this->clean($data['kartu'] ?? []));
            DB::table('cbt_kop_berita')->updateOrInsert(['id_kop' => 1], $this->clean($data['berita'] ?? []));
            DB::table('cbt_kop_absensi')->updateOrInsert(['id_kop' => 1], $this->clean($data['absensi'] ?? []));
        });

        AuditLog::record('setting.kop', 'Kop kartu, berita acara, dan absensi disimpan');

        return back()->with('success', 'Kop cetak berhasil disimpan.');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }
}
