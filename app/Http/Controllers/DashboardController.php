<?php

namespace App\Http\Controllers;

use App\Models\BankSoal;
use App\Models\DurasiSiswa;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const SCORE_SQL = '(COALESCE(n.pg_nilai, 0) + COALESCE(n.kompleks_nilai, 0) + COALESCE(n.jodohkan_nilai, 0) + COALESCE(n.isian_nilai, 0) + COALESCE(n.essai_nilai, 0))';

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOrangtua()) {
            return redirect()->route('wali-murid.index');
        }
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;
        $guruId = $user->isGuru() ? (int) ($user->guru?->id_guru ?? 0) : null;
        $now = now()->format('Y-m-d\TH:i');

        $stats = match ($user->role) {
            'admin' => [
                'siswa' => $this->activeStudentCount($tp, $smt),
                'guru' => Guru::count(),
                'kelas' => $this->forPeriod(Kelas::query(), $tp, $smt)->count(),
                'mapel' => Mapel::count(),
                'bank_soal' => $this->forPeriod(BankSoal::query(), $tp, $smt)->count(),
                'jadwal' => $this->jadwalQuery($tp, $smt, null)->count(),
            ],
            'guru' => $this->guruStats($tp, $smt, $guruId),
            'siswa' => [
                'kelas' => $tp && $smt
                    ? KelasSiswa::where('id_tp', $tp)->where('id_smt', $smt)->where('id_siswa', $this->siswaId($user))->count()
                    : 0,
            ],
            default => [],
        };

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'tahunAktif' => $tahun?->tahun,
            // Ringkasan satu menit basi cukup untuk panel baca-saja; agregat berat tidak dihitung tiap muat.
            'analytics' => in_array($user->role, ['admin', 'guru'], true)
                ? Cache::remember(
                    sprintf('dashboard:analytics:%s:%s:%s:%s', $user->role, $tp ?? '-', $smt ?? '-', $guruId ?? '-'),
                    now()->addMinute(),
                    fn () => $this->analytics($tp, $smt, $guruId)
                )
                : null,
            'reminders' => in_array($user->role, ['admin', 'guru'], true) ? $this->reminders($tp, $smt, $user->isAdmin(), $guruId) : [],
            'recentJadwal' => $this->recentJadwal($tp, $smt, $guruId, $now),
        ]);
    }

    private function forPeriod(Builder $query, ?string $tp, ?string $smt): Builder
    {
        return $query
            ->when($tp, fn (Builder $q) => $q->where('id_tp', $tp))
            ->when($smt, fn (Builder $q) => $q->where('id_smt', $smt));
    }

    private function activeStudentCount(?string $tp, ?string $smt): int
    {
        if (! $tp || ! $smt) {
            return Siswa::count();
        }

        return KelasSiswa::where('id_tp', $tp)->where('id_smt', $smt)->distinct()->count('id_siswa');
    }

    private function guruStats(?string $tp, ?string $smt, int $guruId): array
    {
        $kelasIds = $this->forPeriod(Kelas::query(), $tp, $smt)->where('guru_id', $guruId)->pluck('id_kelas');

        return [
            'siswa' => $kelasIds->isEmpty() ? 0 : KelasSiswa::whereIn('id_kelas', $kelasIds)->distinct()->count('id_siswa'),
            'kelas' => $kelasIds->count(),
            'bank_soal' => $this->forPeriod(BankSoal::query(), $tp, $smt)->where('bank_guru_id', $guruId)->count(),
            'jadwal' => $this->jadwalQuery($tp, $smt, $guruId)->count(),
        ];
    }

    private function jadwalQuery(?string $tp, ?string $smt, ?int $guruId): Builder
    {
        return $this->forPeriod(Jadwal::query(), $tp, $smt)
            // Jadwal remedial adalah percobaan ulang dari jadwal induk. Bila ikut
            // dihitung, satu siswa tercatat dua kali pada statistik.
            ->where('remedial', 0)
            ->when($guruId !== null, fn (Builder $q) => $q->whereHas('bank', fn ($bank) => $bank->where('bank_guru_id', $guruId)));
    }

    private function analytics(?string $tp, ?string $smt, ?int $guruId): array
    {
        $jadwalIds = $this->jadwalQuery($tp, $smt, $guruId)->pluck('id_jadwal');

        if ($jadwalIds->isEmpty()) {
            return [
                'nilai_rata' => 0,
                'dinilai' => 0,
                'tuntas' => 0,
                'dinilai_kkm' => 0,
                'pass_rate' => null,
                'selesai' => 0,
                'berjalan' => 0,
                'jadwal_aktif' => 0,
                'chart' => [],
                'mapel' => [],
                'kelas' => [],
                'partisipasi' => ['siswa' => 0, 'dinilai' => 0, 'persen' => null],
            ];
        }

        $aggregate = DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->leftJoin('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->whereIn('n.id_jadwal', $jadwalIds)
            ->selectRaw('COUNT(*) as dinilai')
            ->selectRaw('AVG('.self::SCORE_SQL.') as rata')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 THEN 1 ELSE 0 END) as dinilai_kkm')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 AND '.self::SCORE_SQL.' >= b.kkm THEN 1 ELSE 0 END) as tuntas')
            ->first();

        $dinilaiKkm = (int) ($aggregate->dinilai_kkm ?? 0);
        $tuntas = (int) ($aggregate->tuntas ?? 0);

        $chart = DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->leftJoin('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->whereIn('n.id_jadwal', $jadwalIds)
            ->groupBy('n.id_jadwal', 'b.bank_nama', 'b.kkm')
            ->selectRaw('n.id_jadwal as id_jadwal, COALESCE(b.bank_nama, ?) as nama, COALESCE(b.kkm, 0) as kkm, COUNT(*) as peserta, AVG('.self::SCORE_SQL.') as rata, MAX(j.tgl_mulai) as tgl_mulai', ['Ujian'])
            ->orderByDesc('n.id_jadwal')
            ->limit(6)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'id_jadwal' => (int) $row->id_jadwal,
                'nama' => (string) $row->nama,
                'kkm' => (int) $row->kkm,
                'peserta' => (int) $row->peserta,
                'rata' => round((float) $row->rata, 1),
                'tgl_mulai' => $row->tgl_mulai,
            ]);

        return [
            'nilai_rata' => round((float) ($aggregate->rata ?? 0), 1),
            'dinilai' => (int) ($aggregate->dinilai ?? 0),
            'tuntas' => $tuntas,
            'dinilai_kkm' => $dinilaiKkm,
            'pass_rate' => $dinilaiKkm > 0 ? round($tuntas / $dinilaiKkm * 100, 1) : null,
            'selesai' => DurasiSiswa::whereIn('id_jadwal', $jadwalIds)->where('status', 2)->count(),
            'berjalan' => DurasiSiswa::whereIn('id_jadwal', $jadwalIds)->where('status', 1)->count(),
            'jadwal_aktif' => Jadwal::whereIn('id_jadwal', $jadwalIds)
                ->where('status', 1)
                ->where('tgl_mulai', '<=', now()->format('Y-m-d\TH:i'))
                ->where('tgl_selesai', '>=', now()->format('Y-m-d\TH:i'))
                ->count(),
            'chart' => $chart,
            'mapel' => $this->perMapel($jadwalIds),
            'kelas' => $this->perKelas($jadwalIds),
            'partisipasi' => $this->partisipasi($jadwalIds, $tp, $smt),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $jadwalIds
     * @return array<int, array<string, mixed>>
     */
    private function perMapel($jadwalIds): array
    {
        return DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->leftJoin('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->leftJoin('master_mapel as m', 'm.id_mapel', '=', 'b.bank_mapel_id')
            ->whereIn('n.id_jadwal', $jadwalIds)
            ->groupBy('b.bank_mapel_id', 'm.nama_mapel')
            ->selectRaw("COALESCE(m.nama_mapel, 'Tanpa mapel') as nama")
            ->selectRaw('COUNT(*) as peserta')
            ->selectRaw('AVG('.self::SCORE_SQL.') as rata')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 THEN 1 ELSE 0 END) as dinilai_kkm')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 AND '.self::SCORE_SQL.' >= b.kkm THEN 1 ELSE 0 END) as tuntas')
            ->orderBy('rata')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'nama' => (string) $row->nama,
                'peserta' => (int) $row->peserta,
                'rata' => round((float) $row->rata, 1),
                'tuntas' => (int) $row->tuntas,
                'dinilai_kkm' => (int) $row->dinilai_kkm,
                'pass_rate' => (int) $row->dinilai_kkm > 0
                    ? round((int) $row->tuntas / (int) $row->dinilai_kkm * 100, 1)
                    : null,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $jadwalIds
     * @return array<int, array<string, mixed>>
     */
    private function perKelas($jadwalIds): array
    {
        return DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->leftJoin('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->leftJoin('kelas_siswa as ks', function ($join) {
                $join->on('ks.id_siswa', '=', 'n.id_siswa')
                    ->on('ks.id_tp', '=', 'j.id_tp')
                    ->on('ks.id_smt', '=', 'j.id_smt');
            })
            ->leftJoin('master_kelas as k', 'k.id_kelas', '=', 'ks.id_kelas')
            ->whereIn('n.id_jadwal', $jadwalIds)
            ->whereNotNull('k.id_kelas')
            ->groupBy('k.id_kelas', 'k.nama_kelas')
            ->selectRaw('k.id_kelas as id_kelas, k.nama_kelas as nama')
            ->selectRaw('COUNT(*) as peserta')
            ->selectRaw('AVG('.self::SCORE_SQL.') as rata')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 THEN 1 ELSE 0 END) as dinilai_kkm')
            ->selectRaw('SUM(CASE WHEN COALESCE(b.kkm, 0) > 0 AND '.self::SCORE_SQL.' >= b.kkm THEN 1 ELSE 0 END) as tuntas')
            ->orderBy('rata')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id_kelas' => (int) $row->id_kelas,
                'nama' => (string) $row->nama,
                'peserta' => (int) $row->peserta,
                'rata' => round((float) $row->rata, 1),
                'pass_rate' => (int) $row->dinilai_kkm > 0
                    ? round((int) $row->tuntas / (int) $row->dinilai_kkm * 100, 1)
                    : null,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $jadwalIds
     * @return array{siswa: int, dinilai: int, persen: ?float}
     */
    private function partisipasi($jadwalIds, ?string $tp, ?string $smt): array
    {
        $bankKelas = DB::table('cbt_jadwal as j')
            ->join('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->whereIn('j.id_jadwal', $jadwalIds)
            ->pluck('b.bank_kelas')
            ->flatMap(fn ($value) => array_filter(array_map('intval', explode(',', (string) $value))))
            ->unique()
            ->values();

        $siswa = $bankKelas->isEmpty()
            ? 0
            : KelasSiswa::query()
                ->whereIn('id_kelas', $bankKelas)
                ->when($tp, fn ($query) => $query->where('id_tp', $tp))
                ->when($smt, fn ($query) => $query->where('id_smt', $smt))
                ->distinct()
                ->count('id_siswa');

        $dinilai = DB::table('cbt_nilai')
            ->whereIn('id_jadwal', $jadwalIds)
            ->distinct()
            ->count('id_siswa');

        return [
            'siswa' => (int) $siswa,
            'dinilai' => (int) $dinilai,
            'persen' => $siswa > 0 ? round(min(100, $dinilai / $siswa * 100), 1) : null,
        ];
    }

    private function reminders(?string $tp, ?string $smt, bool $isAdmin, ?int $guruId): array
    {
        $items = [];
        $start = now()->startOfDay()->format('Y-m-d\TH:i');
        $end = now()->endOfDay()->format('Y-m-d\TH:i');

        $hariIni = $this->jadwalQuery($tp, $smt, $guruId)
            ->where('status', 1)
            ->whereBetween('tgl_mulai', [$start, $end])
            ->count();

        if ($hariIni > 0) {
            $items[] = [
                'label' => 'Jadwal ujian hari ini',
                'count' => $hariIni,
                'href' => '/cbt/jadwal',
                'tone' => 'amber',
            ];
        }

        $koreksi = DB::table('cbt_nilai as n')
            ->join('cbt_jadwal as j', 'j.id_jadwal', '=', 'n.id_jadwal')
            ->join('cbt_bank_soal as b', 'b.id_bank', '=', 'j.id_bank')
            ->when($tp, fn ($q) => $q->where('j.id_tp', $tp))
            ->when($smt, fn ($q) => $q->where('j.id_smt', $smt))
            ->when($guruId !== null, fn ($q) => $q->where('b.bank_guru_id', $guruId))
            ->where('b.jml_esai', '>', 0)
            ->where('n.dikoreksi', 0)
            ->count();

        if ($koreksi > 0) {
            $items[] = [
                'label' => 'Jawaban esai belum dikoreksi',
                'count' => $koreksi,
                'href' => '/cbt/koreksi',
                'tone' => 'rose',
            ];
        }

        $bankKosong = $this->forPeriod(BankSoal::query(), $tp, $smt)
            ->when($guruId !== null, fn (Builder $q) => $q->where('bank_guru_id', $guruId))
            ->whereDoesntHave('soal')
            ->count();

        if ($bankKosong > 0) {
            $items[] = [
                'label' => 'Bank soal belum berisi soal',
                'count' => $bankKosong,
                'href' => '/cbt/bank-soal',
                'tone' => 'amber',
            ];
        }

        if ($isAdmin) {
            $tanpaWali = $this->forPeriod(Kelas::query(), $tp, $smt)->whereNull('guru_id')->count();

            if ($tanpaWali > 0) {
                $items[] = [
                    'label' => 'Kelas belum punya wali kelas',
                    'count' => $tanpaWali,
                    'href' => '/master/kelas',
                    'tone' => 'slate',
                ];
            }
        }

        return $items;
    }

    private function recentJadwal(?string $tp, ?string $smt, ?int $guruId, string $now): array
    {
        return $this->jadwalQuery($tp, $smt, $guruId)
            ->with(['bank:id_bank,bank_nama', 'jenis:id_jenis,nama_jenis'])
            ->orderByDesc('tgl_mulai')
            ->limit(5)
            ->get()
            ->map(function (Jadwal $jadwal) use ($now) {
                $state = (int) $jadwal->status !== 1
                    ? 'Nonaktif'
                    : ($jadwal->tgl_selesai < $now ? 'Selesai' : ($jadwal->tgl_mulai <= $now ? 'Berlangsung' : 'Mendatang'));

                return [
                    'id_jadwal' => $jadwal->id_jadwal,
                    'bank' => $jadwal->bank?->bank_nama,
                    'jenis' => $jadwal->jenis?->nama_jenis,
                    'tgl_mulai' => $jadwal->tgl_mulai,
                    'tgl_selesai' => $jadwal->tgl_selesai,
                    'state' => $state,
                ];
            })
            ->all();
    }

    private function siswaId($user): ?int
    {
        return Siswa::where('username', $user->username)->value('id_siswa');
    }
}
