<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NomorPesertaController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $kelasId = (int) $request->query('kelas', 0);

        $kelasOptions = $tp && $smt
            ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
            : collect();

        $siswa = collect();

        if ($kelasId && $tp && $smt) {
            $nomor = DB::table('cbt_nomor_peserta')
                ->where('id_tp', $tp)
                ->pluck('nomor_peserta', 'id_siswa');

            $siswa = DB::table('kelas_siswa')
                ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
                ->where('kelas_siswa.id_kelas', $kelasId)
                ->where('kelas_siswa.id_tp', $tp)->where('kelas_siswa.id_smt', $smt)
                ->orderBy('master_siswa.nama')
                ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nisn', 'master_siswa.nama'])
                ->map(fn ($row) => [
                    'id_siswa' => $row->id_siswa,
                    'nis' => $row->nis,
                    'nisn' => $row->nisn,
                    'nama' => $row->nama,
                    'nomor_peserta' => $nomor[$row->id_siswa] ?? '',
                ]);
        }

        return Inertia::render('Cbt/NomorPeserta', [
            'kelasOptions' => $kelasOptions,
            'selectedKelas' => $kelasId ?: null,
            'siswa' => $siswa,
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp) {
            return back()->with('error', 'Aktifkan tahun pelajaran terlebih dahulu.');
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id_siswa' => ['required', 'integer', 'exists:master_siswa,id_siswa'],
            'items.*.nomor_peserta' => ['nullable', 'string', 'max:20'],
        ]);

        $nomorList = collect($data['items'])
            ->pluck('nomor_peserta')
            ->map(fn ($nomor) => trim((string) $nomor))
            ->filter(fn ($nomor) => $nomor !== '');

        $duplicate = $nomorList->duplicates();

        if ($duplicate->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Nomor peserta tidak boleh sama: '.$duplicate->implode(', '),
            ]);
        }

        $siswaIds = collect($data['items'])->pluck('id_siswa')->map(fn ($id) => (int) $id)->all();

        $dipakai = DB::table('cbt_nomor_peserta')
            ->where('id_tp', $tp)
            ->whereIn('nomor_peserta', $nomorList->values()->all())
            ->whereNotIn('id_siswa', $siswaIds)
            ->pluck('nomor_peserta');

        if ($dipakai->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Nomor peserta sudah dipakai siswa lain: '.$dipakai->unique()->implode(', ').'.',
            ]);
        }

        $sebelum = DB::table('cbt_nomor_peserta')
            ->where('id_tp', $tp)
            ->whereIn('id_siswa', $siswaIds)
            ->pluck('nomor_peserta', 'id_siswa');

        $berubah = 0;

        foreach ($data['items'] as $item) {
            if (trim((string) ($sebelum[$item['id_siswa']] ?? '')) !== trim((string) ($item['nomor_peserta'] ?? ''))) {
                $berubah++;
            }

            DB::table('cbt_nomor_peserta')->updateOrInsert(
                ['id_nomor' => $item['id_siswa'].$tp],
                [
                    'id_siswa' => $item['id_siswa'],
                    'id_tp' => $tp,
                    'id_smt' => $smt ?? 1,
                    'nomor_peserta' => trim((string) ($item['nomor_peserta'] ?? '')),
                ]
            );
        }

        AuditLog::record('cbt.nomor', sprintf(
            'Nomor peserta disimpan: %d siswa (%d berubah)',
            count($data['items']),
            $berubah
        ));

        return back()->with('success', 'Nomor peserta berhasil disimpan.');
    }

    public function generate(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'id_kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
        ]);

        $siswa = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_kelas', $data['id_kelas'])
            ->where('kelas_siswa.id_tp', $tp)->where('kelas_siswa.id_smt', $smt)
            ->orderBy('master_siswa.nama')
            ->pluck('master_siswa.id_siswa');

        $maxNomor = (int) DB::table('cbt_nomor_peserta')
            ->where('id_tp', $tp)
            ->selectRaw('MAX(CAST(NULLIF(nomor_peserta, \'\') AS UNSIGNED)) as max_nomor')
            ->value('max_nomor');

        $next = $maxNomor + 1;

        foreach ($siswa as $idSiswa) {
            DB::table('cbt_nomor_peserta')->updateOrInsert(
                ['id_nomor' => $idSiswa.$tp],
                [
                    'id_siswa' => $idSiswa,
                    'id_tp' => $tp,
                    'id_smt' => $smt ?? 1,
                    'nomor_peserta' => str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                ]
            );
            $next++;
        }

        AuditLog::record('cbt.nomor', sprintf(
            'Nomor peserta digenerate untuk %d siswa kelas #%d',
            $siswa->count(),
            $data['id_kelas']
        ));

        return back()->with('success', 'Nomor peserta berhasil digenerate untuk '.$siswa->count().' siswa.');
    }

    public function reset(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        $data = $request->validate([
            'id_kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
        ]);

        $siswaIds = DB::table('kelas_siswa')
            ->where('id_kelas', $data['id_kelas'])
            ->where('id_tp', $tp)->where('id_smt', $smt)
            ->pluck('id_siswa');

        DB::table('cbt_nomor_peserta')
            ->where('id_tp', $tp)
            ->whereIn('id_siswa', $siswaIds)
            ->update(['nomor_peserta' => '']);

        AuditLog::record('cbt.nomor', sprintf(
            'Nomor peserta kelas #%d dikosongkan (%d siswa)',
            $data['id_kelas'],
            $siswaIds->count()
        ));

        return back()->with('success', 'Nomor peserta kelas dikosongkan.');
    }

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel(?int $tp, ?int $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
