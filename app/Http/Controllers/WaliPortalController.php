<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\KelasJadwalMateri;
use App\Models\KelasSiswa;
use App\Models\LogMateri;
use App\Models\Siswa;
use App\Models\WaliSiswa;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WaliPortalController extends Controller
{
    private const LEVELS = [
        1 => 'Saran',
        2 => 'Teguran',
        3 => 'Peringatan',
        4 => 'Sangsi',
    ];

    private const SCORE_SQL = '(COALESCE(n.pg_nilai, 0) + COALESCE(n.kompleks_nilai, 0) + COALESCE(n.jodohkan_nilai, 0) + COALESCE(n.isian_nilai, 0) + COALESCE(n.essai_nilai, 0))';

    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;

        $anak = $this->children($request, $tp, $smt);

        return Inertia::render('Wali/Dashboard', [
            'anak' => $anak,
            'periode' => trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-')),
        ]);
    }

    public function show(Request $request, int $siswa): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;

        abort_unless($this->linked($request, $siswa), 403);

        $data = Siswa::findOrFail($siswa);
        $classes = $this->classes($data, $tp, $smt);
        $rangking = $this->nilai($data, $tp, $smt);

        return Inertia::render('Wali/Anak', [
            'siswa' => [
                'id_siswa' => $data->id_siswa,
                'nama' => $data->nama,
                'nis' => $data->nis,
                'nisn' => $data->nisn,
                'kelas' => $classes->pluck('nama_kelas')->implode(', ') ?: null,
            ],
            'nilai' => $rangking,
            'kehadiran' => $this->kehadiran($data, $classes, $tp, $smt),
            'catatan' => $this->catatan($data, $classes, $tp, $smt),
            'jadwal' => $this->jadwal($classes, $tp, $smt),
            'periode' => trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-')),
        ]);
    }

    private function children(Request $request, $tp, $smt): array
    {
        $ids = WaliSiswa::where('id_user', $request->user()->id)->pluck('id_siswa');

        $siswa = Siswa::whereIn('id_siswa', $ids)->orderBy('nama')->get();

        return $siswa->map(function (Siswa $item) use ($tp, $smt) {
            $classes = $this->classes($item, $tp, $smt);
            $nilai = $this->nilai($item, $tp, $smt);
            $kehadiran = $this->kehadiran($item, $classes, $tp, $smt);
            $rata = collect($nilai)->pluck('total')->filter(fn ($value) => $value !== null);

            return [
                'id_siswa' => $item->id_siswa,
                'nama' => $item->nama,
                'nis' => $item->nis,
                'kelas' => $classes->pluck('nama_kelas')->implode(', ') ?: null,
                'ujian' => count($nilai),
                'rata_nilai' => $rata->isEmpty() ? null : round($rata->avg(), 1),
                'kehadiran' => $kehadiran['persen'],
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nilai(Siswa $siswa, $tp, $smt): array
    {
        // Satu baris per ujian induk; nilai remedial dipakai bila lebih tinggi
        // agar daftar nilai tidak menampilkan ujian yang sama dua kali.
        return DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->leftJoin('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->leftJoin('master_mapel as m', 'm.id_mapel', '=', 'b.bank_mapel_id')
            ->where('n.id_siswa', $siswa->id_siswa)
            ->when($tp, fn ($query) => $query->where('j.id_tp', $tp))
            ->when($smt, fn ($query) => $query->where('j.id_smt', $smt))
            ->orderByDesc('j.tgl_mulai')
            ->select(['n.id_jadwal', 'j.id_jadwal_induk', 'b.bank_nama', 'm.nama_mapel', 'b.kkm', 'j.remedial', 'j.tgl_mulai'])
            ->selectRaw(self::SCORE_SQL.' as total')
            ->get()
            ->groupBy(fn ($row) => (int) ($row->id_jadwal_induk ?: $row->id_jadwal))
            ->map(function ($rows, $indukId) {
                $rows = $rows->sortByDesc(fn ($row) => (float) $row->total)->values();
                $terbaik = $rows->first();
                $induk = $rows->first(fn ($row) => (int) $row->id_jadwal === (int) $indukId);
                $kkm = (int) ($induk->kkm ?? $terbaik->kkm ?? 0);
                $total = round((float) $terbaik->total, 1);

                return [
                    'id_jadwal' => (int) $indukId,
                    'bank_nama' => (string) (($induk->bank_nama ?? $terbaik->bank_nama) ?? 'Ujian'),
                    'mapel' => (string) (($induk->nama_mapel ?? $terbaik->nama_mapel) ?? '-'),
                    'kkm' => $kkm,
                    'remedial' => (int) $terbaik->remedial === 1,
                    'tgl' => ($induk->tgl_mulai ?? $terbaik->tgl_mulai)
                        ? Carbon::parse($induk->tgl_mulai ?? $terbaik->tgl_mulai)->isoFormat('D MMM YYYY')
                        : '-',
                    'total' => $total,
                    'tuntas' => $kkm > 0 && $total >= $kkm,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{hadir: int, alpa: int, total: int, persen: ?float}
     */
    private function kehadiran(Siswa $siswa, $classes, $tp, $smt): array
    {
        $classIds = $classes->pluck('id_kelas')->all();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        if (! $tp || ! $smt || $classIds === []) {
            return ['hadir' => 0, 'alpa' => 0, 'total' => 0, 'persen' => null];
        }

        $items = KelasJadwalMateri::query()
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $classIds)
            ->whereBetween('jadwal_materi', [$start->toDateString(), $end->toDateString().' 23:59:59'])
            ->whereHas('materi', fn ($query) => $query->where('status', 1))
            ->pluck('id_kjm');

        if ($items->isEmpty()) {
            return ['hadir' => 0, 'alpa' => 0, 'total' => 0, 'persen' => null];
        }

        $logs = LogMateri::where('id_siswa', $siswa->id_siswa)
            ->whereIn('id_materi', $items)
            ->get()
            ->keyBy('id_materi');

        $hadir = $logs->filter(fn ($log) => $log->finish_time !== null)->count();
        $total = $items->count();

        return [
            'hadir' => $hadir,
            'alpa' => max(0, $total - $logs->count()),
            'total' => $total,
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catatan(Siswa $siswa, $classes, $tp, $smt): array
    {
        $classIds = $classes->pluck('id_kelas')->all();

        if (! $tp || ! $smt || $classIds === []) {
            return [];
        }

        return DB::table('kelas_catatan_wali')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $classIds)
            ->where(fn ($query) => $query
                ->where('type', 1)
                ->orWhere(fn ($inner) => $inner->where('type', 2)->where('id_siswa', $siswa->id_siswa)))
            ->orderByDesc('tgl')
            ->limit(20)
            ->get(['id_catatan', 'type', 'level', 'tgl', 'text'])
            ->map(fn ($row) => [
                'id_catatan' => (int) $row->id_catatan,
                'level' => self::LEVELS[(int) $row->level] ?? '-',
                'untuk' => (int) $row->type === 1 ? 'Kelas' : 'Siswa',
                'tanggal' => $row->tgl ? Carbon::parse($row->tgl)->isoFormat('D MMM YYYY') : '-',
                'text' => (string) $row->text,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jadwal($classes, $tp, $smt): array
    {
        $classIds = $classes->pluck('id_kelas')->all();

        if (! $tp || ! $smt || $classIds === []) {
            return [];
        }

        return Jadwal::with(['bank:id_bank,bank_nama,bank_kelas', 'jenis:id_jenis,nama_jenis'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('status', 1)
            ->where('tgl_selesai', '>=', now()->format('Y-m-d\TH:i'))
            ->orderBy('tgl_mulai')
            ->get()
            ->filter(function (Jadwal $jadwal) use ($classIds) {
                $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));

                return $bankKelas === [] || array_intersect($bankKelas, $classIds) !== [];
            })
            ->take(8)
            ->map(fn (Jadwal $jadwal) => [
                'id_jadwal' => $jadwal->id_jadwal,
                'bank_nama' => $jadwal->bank?->bank_nama,
                'jenis' => $jadwal->jenis?->nama_jenis,
                'mulai' => Carbon::parse($jadwal->tgl_mulai)->isoFormat('D MMM YYYY, HH:mm'),
                'selesai' => Carbon::parse($jadwal->tgl_selesai)->isoFormat('D MMM YYYY, HH:mm'),
                'remedial' => (int) $jadwal->remedial === 1,
            ])
            ->values()
            ->all();
    }

    private function linked(Request $request, int $siswaId): bool
    {
        return WaliSiswa::where('id_user', $request->user()->id)
            ->where('id_siswa', $siswaId)
            ->exists();
    }

    private function classes(Siswa $siswa, $tp, $smt)
    {
        if (! $tp || ! $smt) {
            return collect();
        }

        $ids = KelasSiswa::where('id_siswa', $siswa->id_siswa)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->pluck('id_kelas')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return Kelas::whereIn('id_kelas', $ids)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas']);
    }
}
