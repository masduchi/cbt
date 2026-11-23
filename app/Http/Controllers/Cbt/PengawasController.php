<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Pengawas;
use App\Models\Ruang;
use App\Models\Sesi;
use App\Support\AuditLog;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PengawasController extends Controller
{
    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;

        $jadwalList = $tp && $smt
            ? Jadwal::with([
                'bank:id_bank,bank_nama,bank_kode,bank_kelas,bank_mapel_id',
                'bank.mapel:id_mapel,nama_mapel',
                'jenis:id_jenis,nama_jenis',
            ])
                ->where('id_tp', $tp)
                ->where('id_smt', $smt)
                ->orderBy('tgl_mulai')
                ->orderBy('id_jadwal')
                ->get()
            : collect();

        $selected = (int) $request->query('jadwal');
        $jadwal = $jadwalList->firstWhere('id_jadwal', $selected) ?? $jadwalList->first();
        $selected = $jadwal?->id_jadwal;

        $combos = $jadwal ? $this->combos($jadwal, (int) $tp, (int) $smt) : [];

        $assignments = $selected
            ? Pengawas::where('id_jadwal', (string) $selected)
                ->where('id_tp', $tp)
                ->where('id_smt', $smt)
                ->get()
            : collect();

        $saved = [];
        foreach ($assignments as $row) {
            $saved[$row->id_ruang.'|'.$row->id_sesi] = array_values(array_filter(
                array_map('intval', explode(',', (string) $row->id_guru))
            ));
        }

        foreach ($combos as &$combo) {
            $combo['id_guru'] = $saved[$combo['id_ruang'].'|'.$combo['id_sesi']] ?? [];
        }
        unset($combo);

        return Inertia::render('Cbt/Pengawas', [
            'options' => $jadwalList->map(fn (Jadwal $item) => [
                'id_jadwal' => $item->id_jadwal,
                'label' => $this->label($item),
            ]),
            'selected' => $selected,
            'combos' => $combos,
            'guru' => Guru::orderBy('nama_guru')->get(['id_guru', 'nip', 'nama_guru']),
            'periode' => $tahun && $semester ? $tahun->tahun.' · '.$semester->smt : '-',
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        [$tahun, $semester] = Period::current();

        if (! $tahun || ! $semester) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_jadwal' => ['required', 'integer', 'exists:cbt_jadwal,id_jadwal'],
            'rows.*.id_ruang' => ['required', 'integer', 'exists:cbt_ruang,id_ruang'],
            'rows.*.id_sesi' => ['required', 'integer', 'exists:cbt_sesi,id_sesi'],
            'rows.*.id_guru' => ['array'],
            'rows.*.id_guru.*' => ['integer', 'exists:master_guru,id_guru'],
        ]);

        $validJadwal = Jadwal::where('id_tp', $tahun->id_tp)
            ->where('id_smt', $semester->id_smt)
            ->pluck('id_jadwal')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($data['rows'] as $row) {
            if (! in_array((int) $row['id_jadwal'], $validJadwal, true)) {
                throw ValidationException::withMessages([
                    'rows' => 'Jadwal yang dipilih tidak termasuk periode aktif.',
                ]);
            }

            $ids = array_values(array_unique(array_map('intval', $row['id_guru'] ?? [])));

            if (strlen(implode(',', $ids)) > 50) {
                throw ValidationException::withMessages([
                    'rows' => 'Terlalu banyak pengawas pada satu ruang dan sesi.',
                ]);
            }
        }

        $rencana = collect($data['rows'])->map(function (array $row) use ($tahun, $semester) {
            $ids = array_values(array_unique(array_map('intval', $row['id_guru'] ?? [])));

            return [
                'id_pengawas' => $tahun->id_tp.$semester->id_smt.$row['id_jadwal'].$row['id_ruang'].$row['id_sesi'],
                'id_jadwal' => (string) $row['id_jadwal'],
                'id_tp' => $tahun->id_tp,
                'id_smt' => $semester->id_smt,
                'id_ruang' => (string) $row['id_ruang'],
                'id_sesi' => (string) $row['id_sesi'],
                'id_guru' => implode(',', $ids),
            ];
        });

        $sebelum = DB::table('cbt_pengawas')
            ->whereIn('id_pengawas', $rencana->pluck('id_pengawas')->all())
            ->pluck('id_guru', 'id_pengawas');

        $berubah = $rencana
            ->filter(fn (array $row) => (string) ($sebelum[$row['id_pengawas']] ?? '') !== $row['id_guru'])
            ->count();

        DB::transaction(function () use ($rencana) {
            foreach ($rencana as $row) {
                if ($row['id_guru'] === '') {
                    DB::table('cbt_pengawas')->where('id_pengawas', $row['id_pengawas'])->delete();

                    continue;
                }

                DB::table('cbt_pengawas')->updateOrInsert(
                    ['id_pengawas' => $row['id_pengawas']],
                    [
                        'id_jadwal' => $row['id_jadwal'],
                        'id_tp' => $row['id_tp'],
                        'id_smt' => $row['id_smt'],
                        'id_ruang' => $row['id_ruang'],
                        'id_sesi' => $row['id_sesi'],
                        'id_guru' => $row['id_guru'],
                    ]
                );
            }
        });

        AuditLog::record('cbt.pengawas', sprintf(
            'Pengawas jadwal #%s: %d penugasan disimpan (%d berubah)',
            implode(', #', $rencana->pluck('id_jadwal')->unique()->all()),
            $rencana->count(),
            $berubah
        ));

        return back()->with('success', 'Pengawas ujian berhasil disimpan.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function combos(Jadwal $jadwal, int $tp, int $smt): array
    {
        $kelasIds = array_values(array_filter(
            array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas))
        ));

        if (! $kelasIds) {
            return [];
        }

        $rows = DB::table('cbt_kelas_ruang')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $kelasIds)
            ->get(['id_kelas', 'id_ruang', 'id_sesi']);

        if ($rows->isEmpty()) {
            return [];
        }

        $perKelas = DB::table('kelas_siswa')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $kelasIds)
            ->selectRaw('id_kelas, COUNT(*) as jumlah')
            ->groupBy('id_kelas')
            ->pluck('jumlah', 'id_kelas');

        $ruang = Ruang::pluck('nama_ruang', 'id_ruang');
        $sesi = Sesi::get(['id_sesi', 'nama_sesi', 'waktu_mulai', 'waktu_akhir'])->keyBy('id_sesi');

        $combos = [];

        foreach ($rows as $row) {
            $key = $row->id_ruang.'|'.$row->id_sesi;
            $sesiRow = $sesi->get($row->id_sesi);

            $combos[$key] ??= [
                'id_ruang' => (string) $row->id_ruang,
                'nama_ruang' => $ruang[$row->id_ruang] ?? '-',
                'id_sesi' => (string) $row->id_sesi,
                'nama_sesi' => $sesiRow?->nama_sesi ?? '-',
                'waktu' => $sesiRow
                    ? substr((string) $sesiRow->waktu_mulai, 0, 5).'-'.substr((string) $sesiRow->waktu_akhir, 0, 5)
                    : '',
                'peserta' => 0,
            ];

            $combos[$key]['peserta'] += (int) ($perKelas[$row->id_kelas] ?? 0);
        }

        usort($combos, fn ($a, $b) => [$a['nama_ruang'], $a['waktu']] <=> [$b['nama_ruang'], $b['waktu']]);

        return array_values($combos);
    }

    private function label(Jadwal $jadwal): string
    {
        $tanggal = $jadwal->tgl_mulai
            ? Carbon::parse($jadwal->tgl_mulai)->translatedFormat('d M Y')
            : '-';
        $mapel = $jadwal->bank?->mapel?->nama_mapel ?? $jadwal->bank?->bank_nama ?? 'Tanpa mapel';
        $jenis = $jadwal->jenis?->nama_jenis;

        return trim($tanggal.' · '.$mapel.($jenis ? ' · '.$jenis : ''));
    }
}
