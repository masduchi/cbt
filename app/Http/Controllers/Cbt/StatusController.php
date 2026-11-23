<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Cbt\Concerns\AuthorizesJadwal;
use App\Http\Controllers\Controller;
use App\Models\DurasiSiswa;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\LogUjian;
use App\Models\Nilai;
use App\Models\NomorPeserta;
use App\Models\RekapNilai;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\CbtScoring;
use App\Support\PercobaanArsip;
use App\Support\UjianSessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatusController extends Controller
{
    use AuthorizesJadwal;

    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();

        $jadwals = $this->jadwalQuery($user, $tp, $smt)
            ->with(['bank:id_bank,bank_nama,bank_kode,bank_kelas,bank_guru_id', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();

        return Inertia::render('Cbt/Status', [
            'jadwalOptions' => $jadwals->map(fn (Jadwal $jadwal) => [
                'id_jadwal' => $jadwal->id_jadwal,
                'label' => trim(($jadwal->bank?->bank_nama ?? '-').' · '.($jadwal->jenis?->nama_jenis ?? '')),
                'tgl_mulai' => $this->dateLabel($jadwal->tgl_mulai),
            ]),
            'selected' => $selected ? $selected->id_jadwal : null,
            'jadwal' => $selected ? [
                'id_jadwal' => $selected->id_jadwal,
                'bank_nama' => $selected->bank?->bank_nama,
                'jenis' => $selected->jenis?->nama_jenis,
                'tgl_mulai' => $this->dateLabel($selected->tgl_mulai),
                'tgl_selesai' => $this->dateLabel($selected->tgl_selesai),
                'durasi_ujian' => (int) $selected->durasi_ujian,
            ] : null,
            'peserta' => $selected ? $this->peserta($selected, $tp, $smt) : [],
            'periode' => $this->periodeLabel($tp, $smt),
            // Klien hanya membuka SSE bila server memang sanggup melayaninya.
            'realtime' => $this->realtimeEnabled(),
        ]);
    }

    /**
     * Kirim status peserta sebagai Server-Sent Events agar pengawas melihat
     * perubahan tanpa menunggu interval polling. Koneksi ditutup setelah
     * beberapa kiriman; browser menyambung ulang sendiri.
     */
    public function stream(Request $request, int $id): HttpResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id);
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt || ! $this->realtimeEnabled()) {
            // Klien akan memakai polling biasa bila endpoint ini tidak tersedia.
            return response()->noContent();
        }

        $interval = max(0, (int) config('cbt.realtime.interval', 3));
        $ticks = max(1, (int) config('cbt.realtime.ticks', 20));

        return response()->eventStream(function () use ($jadwal, $tp, $smt, $interval, $ticks) {
            for ($tick = 0; $tick < $ticks; $tick++) {
                if (connection_aborted()) {
                    return;
                }

                yield new StreamedEvent('snapshot', [
                    'at' => now()->toIso8601String(),
                    'rows' => $this->peserta($jadwal, $tp, $smt),
                ]);

                if ($interval > 0 && $tick < $ticks - 1) {
                    sleep($interval);
                }
            }
        }, [], null);
    }

    public function reset(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id);
        $data = $request->validate([
            'siswa' => ['required', 'array'],
            'siswa.*' => ['integer'],
            'mode' => ['required', 'integer', 'in:1,2'],
        ]);

        $count = 0;

        foreach (DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $data['siswa'])->get() as $durasi) {
            if ((int) $durasi->status === 2) {
                continue;
            }

            $elapsed = $this->elapsedSeconds($durasi);
            $durasi->lama_ujian = gmdate('H:i:s', min($elapsed, 359999));
            $durasi->reset = (int) $data['mode'];

            if ((int) $data['mode'] === 1) {
                $durasi->lama_ujian = '00:00:00';
            }

            $durasi->save();

            UjianSessionGuard::forget((int) $jadwal->id_jadwal, (int) $durasi->id_siswa);
            $count++;
        }

        if ($count === 0) {
            return back()->with('error', 'Tidak ada peserta yang sedang mengerjakan ujian pada pilihan tersebut.');
        }

        AuditLog::record('cbt.reset', $count.' peserta pada jadwal #'.$jadwal->id_jadwal.' (mode '.$data['mode'].')');

        return back()->with('success', "Permintaan reset waktu dikirim untuk {$count} peserta.");
    }

    public function force(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id);
        $data = $request->validate([
            'siswa' => ['required', 'array'],
            'siswa.*' => ['integer'],
        ]);

        $count = 0;

        DB::transaction(function () use ($jadwal, $data, &$count) {
            $durasies = DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)
                ->whereIn('id_siswa', $data['siswa'])
                ->get();

            foreach ($durasies as $durasi) {
                if ((int) $durasi->status === 2) {
                    continue;
                }

                $durasi->lama_ujian = gmdate('H:i:s', min($this->elapsedSeconds($durasi), 359999));
                $durasi->selesai = now();
                $durasi->status = 2;
                $durasi->reset = 0;
                $durasi->save();

                CbtScoring::simpan($jadwal, (int) $durasi->id_siswa);
                $this->writeLog((int) $durasi->id_siswa, (int) $jadwal->id_jadwal, 'Ujian diselesaikan pengawas');
                $count++;
            }
        });

        AuditLog::record('cbt.force', $count.' peserta pada jadwal #'.$jadwal->id_jadwal.' diselesaikan paksa');

        return back()->with('success', "{$count} peserta diselesaikan paksa.");
    }

    public function repeat(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->authorizeJadwal($request->user(), $id);
        $data = $request->validate([
            'siswa' => ['required', 'array'],
            'siswa.*' => ['integer'],
        ]);

        $ids = $data['siswa'];

        DB::transaction(function () use ($jadwal, $ids) {
            PercobaanArsip::simpan($jadwal, $ids, 'pengawas_mengulang');

            SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $ids)->delete();
            DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $ids)->delete();

            foreach ($ids as $idSiswa) {
                UjianSessionGuard::forget((int) $jadwal->id_jadwal, (int) $idSiswa);
            }
            Nilai::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $ids)->delete();
            RekapNilai::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $ids)->delete();
            LogUjian::where('id_jadwal', $jadwal->id_jadwal)->whereIn('id_siswa', $ids)->delete();
        });

        AuditLog::record('cbt.repeat', count($ids).' peserta pada jadwal #'.$jadwal->id_jadwal.' diizinkan mengulang');

        return back()->with('success', count($ids).' peserta dapat mengulang ujian.');
    }

    private function peserta(Jadwal $jadwal, $tp, $smt): array
    {
        $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));
        $durasi = DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->get()->keyBy('id_siswa');
        $nilai = Nilai::where('id_jadwal', $jadwal->id_jadwal)->get()->keyBy('id_siswa');
        $nomor = NomorPeserta::where('id_tp', $tp)->where('id_smt', $smt)->get()->keyBy('id_siswa');

        $kelasSiswa = DB::table('kelas_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($bankKelas, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $bankKelas))
            ->get(['kelas_siswa.id_siswa', 'master_kelas.nama_kelas'])
            ->keyBy('id_siswa');

        $ids = $kelasSiswa->keys()->merge($durasi->keys())->unique();
        $siswa = Siswa::whereIn('id_siswa', $ids)->orderBy('nama')->get()->keyBy('id_siswa');
        $violations = LogUjian::where('id_jadwal', $jadwal->id_jadwal)
            ->where('log_type', 3)
            ->orderByDesc('log_time')
            ->get()
            ->groupBy('id_siswa');

        return $siswa->map(function (Siswa $item) use ($durasi, $nilai, $nomor, $kelasSiswa, $jadwal, $violations) {
            $row = $durasi->get($item->id_siswa);
            $skor = $nilai->get($item->id_siswa);
            $logs = $violations->get($item->id_siswa);
            $status = $row ? (int) $row->status : 0;
            $remaining = $row && $status === 1
                ? max(0, (int) $jadwal->durasi_ujian * 60 - $this->elapsedSeconds($row))
                : null;

            $rincian = $logs
                ? $logs->groupBy(fn (LogUjian $log) => $log->kode ?: 'desc:'.trim((string) $log->log_desc))
                    ->map(function ($group, $kunci) {
                        // Pelanggaran lama belum punya kode; pakai deskripsinya agar
                        // pengawas tetap tahu jenis kejadiannya.
                        $kode = str_starts_with((string) $kunci, 'desc:') ? 'lainnya' : (string) $kunci;

                        return [
                            'kode' => $kode,
                            'label' => $kode === 'lainnya'
                                ? Str::limit((string) $group->first()->log_desc, 60)
                                : $this->violationLabel($kode),
                            'jumlah' => $group->count(),
                            'terakhir' => $group->first()->log_time,
                        ];
                    })
                    ->sortByDesc('jumlah')
                    ->values()
                    ->all()
                : [];

            return [
                'id_siswa' => $item->id_siswa,
                'nama' => $item->nama,
                'nis' => $item->nis,
                'kelas' => $kelasSiswa->get($item->id_siswa)?->nama_kelas ?? '',
                'no_peserta' => $nomor->get($item->id_siswa)?->nomor_peserta,
                'id_durasi' => $row?->id_durasi,
                'status' => $status,
                'reset' => (int) ($row?->reset ?? 0),
                'mulai' => $row?->mulai,
                'lama_ujian' => $row?->lama_ujian,
                'selesai' => $row?->selesai,
                'remaining' => $remaining,
                'nilai' => $skor ? round((float) $skor->pg_nilai + (float) $skor->kompleks_nilai + (float) $skor->jodohkan_nilai + (float) $skor->isian_nilai + (float) $skor->essai_nilai, 2) : null,
                'dikoreksi' => (bool) ($skor?->dikoreksi ?? false),
                'violations' => $logs ? $logs->count() : 0,
                'violations_rincian' => $rincian,
                'last_violation' => $logs?->first()?->log_desc,
                'last_violation_at' => $logs?->first()?->log_time,
            ];
        })->values()->all();
    }

    private function violationLabel(string $kode): string
    {
        return [
            'sesi_ganda' => 'Dua sesi aktif bersamaan',
            'pindah_tab' => 'Berpindah tab atau jendela',
            'salin' => 'Mencoba menyalin soal',
            'tempel' => 'Mencoba menempel teks',
            'klik_kanan' => 'Membuka menu klik kanan',
            'keluar_halaman' => 'Meninggalkan halaman ujian',
            'fokus_hilang' => 'Jendela ujian kehilangan fokus',
            'keluar_fullscreen' => 'Keluar dari mode layar penuh',
            'lainnya' => 'Catatan lain',
        ][$kode] ?? 'Catatan lain';
    }

    private function jadwalQuery($user, $tp, $smt)
    {
        $query = Jadwal::where('id_tp', $tp)->where('id_smt', $smt);

        if (! $user->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
            $query->whereHas('bank', fn ($bank) => $bank->where('bank_guru_id', $guruId));
        }

        return $query;
    }

    private function writeLog(int $siswaId, int $jadwalId, string $description): void
    {
        AuditLog::record('ujian.paksa', 'Siswa #'.$siswaId.' jadwal #'.$jadwalId.': '.$description);

        LogUjian::create([
            'log_time' => now(),
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwalId,
            'log_type' => 2,
            'log_desc' => $description,
            'address' => substr((string) request()->ip(), 0, 45),
            'agent' => substr((string) request()->userAgent(), 0, 255),
            'device' => 'web',
            'reset' => 0,
        ]);
    }

    /**
     * Server bawaan PHP (`php artisan serve`) melayani satu permintaan
     * sekaligus, sehingga koneksi SSE akan memblokir halaman lain saat
     * pengembangan. Di lingkungan itu monitor memakai polling; server
     * produksi (php-fpm/Apache) tetap realtime.
     */
    private function realtimeEnabled(): bool
    {
        $configured = config('cbt.realtime.enabled');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return PHP_SAPI !== 'cli-server';
    }

    private function elapsedSeconds(DurasiSiswa $durasi): int
    {
        if (! $durasi->mulai) {
            return 0;
        }

        return max(0, Carbon::parse($durasi->mulai)->diffInSeconds(now()));
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

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel($tp, $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
