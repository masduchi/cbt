<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Cbt\Concerns\AuthorizesJadwal;
use App\Http\Controllers\Controller;
use App\Models\DurasiSiswa;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KelasSiswa;
use App\Models\LogUjian;
use App\Models\Nilai;
use App\Models\RekapNilai;
use App\Models\RemedialPeserta;
use App\Models\Siswa;
use App\Models\SoalSiswa;
use App\Support\AuditLog;
use App\Support\PercobaanArsip;
use App\Support\Period;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RemedialController extends Controller
{
    use AuthorizesJadwal;

    private const SCORE_SQL = '(COALESCE(pg_nilai, 0) + COALESCE(kompleks_nilai, 0) + COALESCE(jodohkan_nilai, 0) + COALESCE(isian_nilai, 0) + COALESCE(essai_nilai, 0))';

    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;
        $user = $request->user();

        $jadwals = Jadwal::with(['bank:id_bank,bank_nama,bank_kelas,kkm,bank_guru_id', 'jenis:id_jenis,nama_jenis'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('remedial', 0)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'bank',
                fn ($bank) => $bank->where('bank_guru_id', $this->guruId($user))
            ))
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();
        $sesi = $selected ? $this->sesiRemedial($selected) : [];

        return Inertia::render('Cbt/Remedial/Index', [
            'jadwalOptions' => $jadwals->map(fn (Jadwal $jadwal) => [
                'id_jadwal' => $jadwal->id_jadwal,
                'label' => trim(($jadwal->bank?->bank_nama ?? '-').' · '.($jadwal->jenis?->nama_jenis ?? '')),
                'tgl_mulai' => $this->dateLabel($jadwal->tgl_mulai),
            ]),
            'selected' => $selected?->id_jadwal,
            'jadwal' => $selected ? [
                'id_jadwal' => $selected->id_jadwal,
                'bank_nama' => $selected->bank?->bank_nama,
                'jenis' => $selected->jenis?->nama_jenis,
                'kkm' => (int) ($selected->bank?->kkm ?? 0),
                'tgl_mulai' => $this->dateLabel($selected->tgl_mulai),
                'tgl_selesai' => $this->dateLabel($selected->tgl_selesai),
                'durasi_ujian' => (int) $selected->durasi_ujian,
            ] : null,
            'peserta' => $selected ? $this->peserta($selected) : [],
            'sesiRemedial' => $sesi,
            'efektivitas' => $selected ? $this->efektivitas($selected) : null,
            'periode' => trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-')),
        ]);
    }

    /**
     * Laporan efektivitas remedial dalam bentuk Excel: satu baris per sesi.
     */
    public function export(Request $request): StreamedResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), (int) $request->query('jadwal'), false);

        $headers = ['No', 'Mulai', 'Selesai', 'Durasi (menit)', 'Status', 'Peserta', 'Selesai Ujian', 'Dinilai', 'Dibandingkan', 'Rata Sebelum', 'Rata Sesudah', 'Naik', 'Tuntas Sebelum', 'Tuntas Sesudah'];
        $rows = [];

        foreach ($this->sesiRemedial($jadwal) as $index => $sesi) {
            $rows[] = [
                $index + 1,
                $sesi['tgl_mulai'],
                $sesi['tgl_selesai'],
                $sesi['durasi_ujian'],
                $sesi['status'] ? 'Aktif' : 'Nonaktif',
                $sesi['peserta'],
                $sesi['selesai'],
                $sesi['dinilai'],
                $sesi['dibandingkan'],
                $sesi['rata_sebelum'],
                $sesi['rata_sesudah'],
                $sesi['naik'],
                $sesi['tuntas_sebelum'],
                $sesi['tuntas_sesudah'],
            ];
        }

        $kode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($jadwal->bank?->bank_kode ?? 'ujian'));

        return XlsxExport::download('efektivitas-remedial-'.trim((string) $kode, '-').'.xlsx', $headers, $rows, 'Efektivitas Remedial');
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id, false);

        $data = $request->validate([
            'tgl_mulai' => ['required', 'date_format:Y-m-d\TH:i'],
            'tgl_selesai' => ['required', 'date_format:Y-m-d\TH:i', 'after:tgl_mulai'],
            'durasi_ujian' => ['required', 'integer', 'min:5', 'max:300'],
            'max_percobaan' => ['required', 'integer', 'min:1', 'max:5'],
            'siswa' => ['required', 'array', 'min:1'],
            'siswa.*' => ['integer'],
        ]);

        $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));

        $pesertaSah = KelasSiswa::query()
            ->where('id_tp', $jadwal->id_tp)
            ->where('id_smt', $jadwal->id_smt)
            ->when($bankKelas, fn ($query) => $query->whereIn('id_kelas', $bankKelas))
            ->whereIn('id_siswa', $data['siswa'])
            ->pluck('id_siswa')
            ->map(fn ($value) => (int) $value)
            ->unique();

        if ($pesertaSah->count() !== count(array_unique($data['siswa']))) {
            return back()->with('error', 'Ada peserta yang tidak terdaftar pada kelas bank soal ini.');
        }

        $sudah = RemedialPeserta::query()
            ->join('cbt_jadwal', 'cbt_jadwal.id_jadwal', '=', 'cbt_remedial_peserta.id_jadwal')
            ->where('cbt_remedial_peserta.id_jadwal_induk', $jadwal->id_jadwal)
            ->whereIn('cbt_remedial_peserta.id_siswa', $data['siswa'])
            ->where('cbt_jadwal.status', 1)
            ->where('cbt_jadwal.tgl_selesai', '>=', now()->format('Y-m-d\TH:i'))
            ->pluck('cbt_remedial_peserta.id_siswa')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($sudah->isNotEmpty()) {
            $nama = Siswa::whereIn('id_siswa', $sudah)->pluck('nama')->implode(', ');

            return back()->with('error', 'Peserta berikut masih punya jadwal remedial aktif: '.$nama.'.');
        }

        $remedial = DB::transaction(function () use ($jadwal, $data) {
            $baru = Jadwal::create([
                'id_tp' => $jadwal->id_tp,
                'id_smt' => $jadwal->id_smt,
                'id_bank' => $jadwal->id_bank,
                'id_jenis' => $jadwal->id_jenis,
                'tgl_mulai' => $data['tgl_mulai'],
                'tgl_selesai' => $data['tgl_selesai'],
                'durasi_ujian' => $data['durasi_ujian'],
                'pengawas' => $jadwal->pengawas,
                'acak_soal' => $jadwal->acak_soal,
                'acak_opsi' => $jadwal->acak_opsi,
                'hasil_tampil' => $jadwal->hasil_tampil,
                'token' => 0,
                'status' => 1,
                'remedial' => 1,
                'id_jadwal_induk' => $jadwal->id_jadwal,
                'ulang' => 0,
                'reset_login' => 0,
                'jam_ke' => 0,
                'time_create' => now(),
            ]);

            foreach (array_values(array_unique($data['siswa'])) as $siswaId) {
                RemedialPeserta::create([
                    'id_jadwal' => $baru->id_jadwal,
                    'id_siswa' => $siswaId,
                    'id_jadwal_induk' => $jadwal->id_jadwal,
                    'percobaan' => 0,
                    'max_percobaan' => $data['max_percobaan'],
                    'time_create' => now(),
                ]);
            }

            return $baru;
        });

        AuditLog::record('cbt.remedial', sprintf(
            'Jadwal #%d untuk %d peserta, induk #%d',
            $remedial->id_jadwal,
            count(array_unique($data['siswa'])),
            $jadwal->id_jadwal
        ));

        return back()->with('success', 'Jadwal remedial dibuat untuk '.count(array_unique($data['siswa'])).' peserta.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id, true);

        DB::transaction(function () use ($jadwal) {
            $ids = Nilai::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa')
                ->merge(SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa'))
                ->merge(DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa'))
                ->unique()
                ->all();

            PercobaanArsip::simpan($jadwal, $ids, 'jadwal_remedial_dihapus');

            SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->delete();
            DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->delete();
            Nilai::where('id_jadwal', $jadwal->id_jadwal)->delete();
            RekapNilai::where('id_jadwal', $jadwal->id_jadwal)->delete();
            LogUjian::where('id_jadwal', $jadwal->id_jadwal)->delete();
            RemedialPeserta::where('id_jadwal', $jadwal->id_jadwal)->delete();
            $jadwal->delete();
        });

        AuditLog::record('cbt.remedial.delete', 'Jadwal remedial #'.$jadwal->id_jadwal.' dihapus');

        return back()->with('success', 'Jadwal remedial dihapus.');
    }

    private function peserta(Jadwal $jadwal): array
    {
        $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));
        $kkm = (int) ($jadwal->bank?->kkm ?? 0);

        $kelasSiswa = KelasSiswa::query()
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $jadwal->id_tp)
            ->where('kelas_siswa.id_smt', $jadwal->id_smt)
            ->when($bankKelas, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $bankKelas))
            ->get(['kelas_siswa.id_siswa', 'master_kelas.nama_kelas'])
            ->keyBy('id_siswa');

        $sesiIds = Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)
            ->pluck('id_jadwal')
            ->map(fn ($id) => (int) $id)
            ->push((int) $jadwal->id_jadwal)
            ->unique()
            ->values()
            ->all();

        $nilaiInduk = DB::table('cbt_nilai')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->selectRaw('id_siswa, '.self::SCORE_SQL.' as total')
            ->get()
            ->keyBy('id_siswa');

        // Status remedial ditentukan dari nilai terbaik antara ujian induk dan
        // sesi remedialnya, sama seperti Rekap dan Hasil. Nilai remedial yang
        // sudah tuntas tidak boleh muncul lagi sebagai peserta di bawah KKM.
        $nilai = DB::table('cbt_nilai')
            ->whereIn('id_jadwal', $sesiIds)
            ->selectRaw('id_siswa, MAX('.self::SCORE_SQL.') as total')
            ->groupBy('id_siswa')
            ->get()
            ->keyBy('id_siswa');

        $remedial = RemedialPeserta::query()
            ->join('cbt_jadwal', 'cbt_jadwal.id_jadwal', '=', 'cbt_remedial_peserta.id_jadwal')
            ->where('cbt_remedial_peserta.id_jadwal_induk', $jadwal->id_jadwal)
            ->orderBy('cbt_remedial_peserta.id')
            ->get([
                'cbt_remedial_peserta.id_siswa',
                'cbt_remedial_peserta.id_jadwal',
                'cbt_remedial_peserta.percobaan',
                'cbt_remedial_peserta.max_percobaan',
                'cbt_jadwal.tgl_mulai',
                'cbt_jadwal.tgl_selesai',
                'cbt_jadwal.status as jadwal_status',
            ]);

        $remedialNilai = $remedial->isEmpty()
            ? collect()
            : DB::table('cbt_nilai')
                ->whereIn('id_jadwal', $remedial->pluck('id_jadwal')->unique())
                ->selectRaw('id_jadwal, id_siswa, '.self::SCORE_SQL.' as total')
                ->get()
                ->keyBy(fn ($row) => $row->id_jadwal.':'.$row->id_siswa);

        $siswa = Siswa::whereIn('id_siswa', $kelasSiswa->keys())->orderBy('nama')->get();

        return $siswa->map(function (Siswa $item) use ($kelasSiswa, $nilai, $nilaiInduk, $remedial, $remedialNilai, $kkm) {
            $skor = $nilai->get($item->id_siswa);
            $total = $skor ? round((float) $skor->total, 2) : null;
            $indukSiswa = $nilaiInduk->get($item->id_siswa);
            $totalInduk = $indukSiswa ? round((float) $indukSiswa->total, 2) : null;
            $sesi = $remedial->where('id_siswa', $item->id_siswa);
            $terbaik = $sesi
                ->map(fn ($row) => $remedialNilai->get($row->id_jadwal.':'.$row->id_siswa)?->total)
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => round((float) $value, 2))
                ->max();

            return [
                'id_siswa' => $item->id_siswa,
                'nama' => $item->nama,
                'nis' => $item->nis,
                'kelas' => $kelasSiswa->get($item->id_siswa)?->nama_kelas ?? '',
                'nilai' => $total,
                'nilai_induk' => $totalInduk,
                'dari_remedial' => $total !== null && ($totalInduk === null || $total > $totalInduk),
                'tuntas' => $total !== null && $kkm > 0 && $total >= $kkm,
                'perlu_remedial' => $kkm > 0 && $total !== null && $total < $kkm,
                'remedial' => [
                    'sesi' => $sesi->count(),
                    'percobaan' => (int) $sesi->max('percobaan'),
                    'max_percobaan' => (int) ($sesi->last()?->max_percobaan ?? 0),
                    'nilai_terbaik' => $terbaik,
                    'aktif' => $sesi->contains(fn ($row) => (int) $row->jadwal_status === 1
                        && (string) $row->tgl_selesai >= now()->format('Y-m-d\TH:i')),
                ],
            ];
        })->values()->all();
    }

    private function sesiRemedial(Jadwal $jadwal): array
    {
        $kkm = (int) ($jadwal->bank?->kkm ?? 0);
        $sebelum = $this->skorPerSiswa(
            (int) $jadwal->id_jadwal,
            RemedialPeserta::whereIn('id_jadwal', Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)->pluck('id_jadwal'))
                ->pluck('id_siswa')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );

        return Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)
            ->withCount('remedialPeserta')
            ->orderByDesc('tgl_mulai')
            ->get()
            ->map(function (Jadwal $row) use ($sebelum, $kkm) {
                $peserta = RemedialPeserta::where('id_jadwal', $row->id_jadwal)
                    ->pluck('id_siswa')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $sesudah = $this->skorPerSiswa((int) $row->id_jadwal, $peserta);
                $ringkasan = $this->ringkasEfektivitas($sebelum, $sesudah, $kkm, (int) $row->remedial_peserta_count);

                return array_merge([
                    'id_jadwal' => $row->id_jadwal,
                    'tgl_mulai' => $this->dateLabel($row->tgl_mulai),
                    'tgl_selesai' => $this->dateLabel($row->tgl_selesai),
                    'durasi_ujian' => (int) $row->durasi_ujian,
                    'status' => (int) $row->status,
                    'selesai' => DurasiSiswa::where('id_jadwal', $row->id_jadwal)->where('status', 2)->count(),
                ], $ringkasan);
            })
            ->values()
            ->all();
    }

    /**
     * Ringkasan efektivitas seluruh sesi remedial pada satu jadwal induk.
     *
     * @return array<string, int|float|null>|null
     */
    private function efektivitas(Jadwal $jadwal): ?array
    {
        $anak = Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)->pluck('id_jadwal');

        if ($anak->isEmpty()) {
            return null;
        }

        $peserta = RemedialPeserta::whereIn('id_jadwal', $anak)
            ->pluck('id_siswa')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sebelum = $this->skorPerSiswa((int) $jadwal->id_jadwal, $peserta);
        $sesudah = $this->skorPerSiswaSemua($anak->all(), $peserta);

        return $this->ringkasEfektivitas($sebelum, $sesudah, (int) ($jadwal->bank?->kkm ?? 0), count($peserta));
    }

    /**
     * Nilai tertinggi per siswa dari beberapa jadwal remedial sekaligus.
     *
     * @param  array<int, int>  $jadwalIds
     * @param  array<int, int>  $siswaIds
     * @return array<int, float>
     */
    private function skorPerSiswaSemua(array $jadwalIds, array $siswaIds): array
    {
        if ($jadwalIds === [] || $siswaIds === []) {
            return [];
        }

        $hasil = [];

        foreach (DB::table('cbt_nilai')
            ->whereIn('id_jadwal', $jadwalIds)
            ->whereIn('id_siswa', $siswaIds)
            ->selectRaw('id_siswa, '.self::SCORE_SQL.' as total')
            ->get() as $row) {
            $idSiswa = (int) $row->id_siswa;
            $total = round((float) $row->total, 2);

            if (! isset($hasil[$idSiswa]) || $total > $hasil[$idSiswa]) {
                $hasil[$idSiswa] = $total;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $siswaIds
     * @return array<int, float>
     */
    private function skorPerSiswa(int $jadwalId, array $siswaIds): array
    {
        if ($siswaIds === []) {
            return [];
        }

        return DB::table('cbt_nilai')
            ->where('id_jadwal', $jadwalId)
            ->whereIn('id_siswa', $siswaIds)
            ->selectRaw('id_siswa, '.self::SCORE_SQL.' as total')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id_siswa => round((float) $row->total, 2)])
            ->all();
    }

    /**
     * Rata-rata dan jumlah tuntas dihitung pada himpunan peserta yang sama, yaitu
     * peserta yang punya nilai sebelum (ujian induk) sekaligus nilai sesudah
     * (remedial). Peserta yang hanya punya salah satunya tidak bisa dibandingkan.
     *
     * @param  array<int, float>  $sebelum
     * @param  array<int, float>  $sesudah
     * @return array<string, int|float|null>
     */
    private function ringkasEfektivitas(array $sebelum, array $sesudah, int $kkm, int $peserta): array
    {
        $dibandingkan = array_values(array_filter(
            array_keys($sesudah),
            fn ($idSiswa) => array_key_exists($idSiswa, $sebelum)
        ));

        $naik = 0;
        $nilaiSebelum = [];
        $nilaiSesudah = [];
        $nilaiTerbaik = [];

        foreach ($dibandingkan as $idSiswa) {
            $awal = $sebelum[$idSiswa];
            $akhir = $sesudah[$idSiswa];

            if ($akhir > $awal) {
                $naik++;
            }

            $nilaiSebelum[] = $awal;
            $nilaiSesudah[] = $akhir;
            $nilaiTerbaik[] = max($awal, $akhir);
        }

        return [
            'peserta' => $peserta,
            'dinilai' => count($sesudah),
            'dibandingkan' => count($dibandingkan),
            'naik' => $naik,
            'rata_sebelum' => $nilaiSebelum === [] ? null : round(array_sum($nilaiSebelum) / count($nilaiSebelum), 1),
            'rata_sesudah' => $nilaiSesudah === [] ? null : round(array_sum($nilaiSesudah) / count($nilaiSesudah), 1),
            'tuntas_sebelum' => $kkm > 0 ? count(array_filter($nilaiSebelum, fn ($nilai) => $nilai >= $kkm)) : null,
            'tuntas_sesudah' => $kkm > 0 ? count(array_filter($nilaiTerbaik, fn ($nilai) => $nilai >= $kkm)) : null,
        ];
    }

    private function guruId($user): int
    {
        return (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
    }

    private function dateLabel(?string $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->isoFormat('D MMM YYYY, HH:mm');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
