<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\LogUjian;
use App\Models\Nilai;
use App\Models\NomorPeserta;
use App\Models\RekapNilai;
use App\Models\RemedialPeserta;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Models\Token;
use App\Support\AuditLog;
use App\Support\CbtScoring;
use App\Support\HtmlSanitizer;
use App\Support\PercobaanArsip;
use App\Support\SnapshotSoal;
use App\Support\SoalMedia;
use App\Support\UjianSessionGuard;
use App\Support\UjianWaktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UjianController extends Controller
{
    public function __construct(
        private readonly UjianWaktu $waktu,
        private readonly SnapshotSoal $snapshot,
    ) {}

    public function index(Request $request): Response
    {
        $siswa = $this->student($request);
        [$tp, $smt] = $this->activePeriod();

        $exams = collect();

        if ($tp && $smt) {
            $classIds = $this->classIds($siswa, (int) $tp, (int) $smt);

            $exams = Jadwal::with(['bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis'])
                ->where('id_tp', $tp)
                ->where('id_smt', $smt)
                ->where('status', 1)
                ->orderBy('tgl_mulai')
                ->orderBy('jam_ke')
                ->get()
                ->filter(fn (Jadwal $jadwal) => $this->matchesStudent($jadwal, $siswa, $classIds)
                    && $this->remedialAkses($siswa, $jadwal) !== false)
                ->map(function (Jadwal $jadwal) use ($siswa) {
                    $durasi = DurasiSiswa::where('id_siswa', $siswa->id_siswa)
                        ->where('id_jadwal', $jadwal->id_jadwal)
                        ->first();

                    $status = (int) ($durasi?->status ?? 0);
                    $remaining = $status === 1 ? $this->waktu->sisaDetik($jadwal, $durasi) : null;
                    $lock = $status === 0 ? $this->jamKeLock($siswa, $jadwal) : null;

                    return [
                        'id_jadwal' => $jadwal->id_jadwal,
                        'bank_nama' => $jadwal->bank?->bank_nama,
                        'bank_kode' => $jadwal->bank?->bank_kode,
                        'mapel' => $jadwal->bank?->mapel?->nama_mapel,
                        'jenis' => $jadwal->jenis?->nama_jenis,
                        'jenis_kode' => $jadwal->jenis?->kode_jenis,
                        'tgl_mulai' => $this->dateLabel($jadwal->tgl_mulai),
                        'tgl_selesai' => $this->dateLabel($jadwal->tgl_selesai),
                        'durasi_ujian' => (int) $jadwal->durasi_ujian,
                        'jam_ke' => (int) $jadwal->jam_ke,
                        'token' => (int) $jadwal->token,
                        'hasil_tampil' => (int) $jadwal->hasil_tampil,
                        'jumlah_soal' => $this->plannedQuestions($jadwal),
                        'remedial' => $jadwal->isRemedial(),
                        'status' => $status,
                        'remaining' => $remaining,
                        'selesai' => $durasi?->selesai,
                        'locked' => $lock !== null,
                        'lock_reason' => $lock !== null
                            ? 'Selesaikan ujian jam ke-'.max(1, (int) $lock->jam_ke).' terlebih dahulu.'
                            : null,
                    ];
                })
                ->values();
        }

        return Inertia::render('Siswa/Ujian/Index', [
            'exams' => $exams,
            'siswa' => $this->studentPayload($siswa, $tp, $smt),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function show(Request $request, int $id): Response|RedirectResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if ((! $durasi || (int) $durasi->status === 0) && ($lock = $this->jamKeLock($siswa, $jadwal))) {
            return redirect()->route('ujian.index')->with('error', $this->jamKeMessage($lock));
        }

        if ($durasi && (int) $durasi->status === 1 && $this->waktu->sisaDetik($jadwal, $durasi) <= 0) {
            $this->finalize($siswa, $jadwal, $durasi);

            return redirect()->route('ujian.hasil')->with('success', 'Waktu ujian telah habis.');
        }

        $tokenAktif = Token::orderBy('id_token')->first();

        $tokenReady = (int) $jadwal->token === 0
            || ($tokenAktif && $tokenAktif->token && ! $tokenAktif->kedaluwarsa());

        return Inertia::render('Siswa/Ujian/Show', [
            'jadwal' => $this->jadwalPayload($jadwal),
            'durasi' => $durasi ? [
                'status' => (int) $durasi->status,
                'mulai' => $durasi->mulai,
                'selesai' => $durasi->selesai,
                'lama_ujian' => $durasi->lama_ujian,
                'remaining' => (int) $durasi->status === 1 ? $this->waktu->sisaDetik($jadwal, $durasi) : null,
            ] : null,
            'tokenReady' => (bool) $tokenReady,
            'siswa' => $this->studentPayload($siswa, (int) $jadwal->id_tp, (int) $jadwal->id_smt),
            'remedial' => $jadwal->isRemedial() ? [
                'percobaan' => (int) ($this->remedialPeserta($siswa, $jadwal)?->percobaan ?? 0),
                'max_percobaan' => (int) ($this->remedialPeserta($siswa, $jadwal)?->max_percobaan ?? 0),
            ] : null,
        ]);
    }

    public function start(Request $request, int $id): RedirectResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:10'],
        ]);

        $durasi = $this->durasi($siswa, $jadwal);

        if ((! $durasi || (int) $durasi->status === 0) && ($lock = $this->jamKeLock($siswa, $jadwal))) {
            return redirect()->route('ujian.index')->with('error', $this->jamKeMessage($lock));
        }

        if ($jadwal->isRemedial()) {
            $peserta = $this->remedialPeserta($siswa, $jadwal);

            if (! $peserta) {
                abort(404);
            }

            $percobaan = (int) $peserta->percobaan;
            $batas = (int) $peserta->max_percobaan;

            if ($durasi && (int) $durasi->status === 2) {
                if ($percobaan < $batas) {
                    $this->resetPercobaan($siswa, $jadwal, $durasi);
                    $durasi = $this->durasi($siswa, $jadwal);
                } else {
                    return redirect()->route('ujian.index')
                        ->with('error', 'Batas percobaan remedial sudah habis. Hubungi guru atau pengawas.');
                }
            } elseif ((! $durasi || (int) $durasi->status === 0) && $percobaan >= $batas) {
                return redirect()->route('ujian.index')
                    ->with('error', 'Batas percobaan remedial sudah habis. Hubungi guru atau pengawas.');
            }
        }

        if ($durasi && (int) $durasi->status === 2) {
            return redirect()->route('ujian.show', $jadwal->id_jadwal)
                ->with('error', 'Ujian ini sudah Anda selesaikan.');
        }

        if ((int) $jadwal->token === 1) {
            $token = Token::orderBy('id_token')->first();

            if (! $token || ! $token->token) {
                return back()->with('error', 'Token ujian belum digenerate pengawas.');
            }

            if ($token->kedaluwarsa()) {
                return back()->with('error', 'Token sudah kedaluwarsa. Minta pengawas membuat token baru.');
            }

            if (strtoupper(trim((string) $data['token'])) !== strtoupper((string) $token->token)) {
                return back()->with('error', 'Token ujian salah.');
            }
        }

        try {
            DB::transaction(function () use ($request, $siswa, $jadwal, &$durasi) {
                if (! $durasi) {
                    $durasi = DurasiSiswa::create([
                        'id_durasi' => CbtScoring::durasiId($siswa->id_siswa, (int) $jadwal->id_jadwal),
                        'id_siswa' => $siswa->id_siswa,
                        'id_jadwal' => $jadwal->id_jadwal,
                        'status' => 1,
                        'lama_ujian' => '00:00:00',
                        'mulai' => now(),
                        'reset' => 0,
                        'time_create' => now(),
                    ]);
                } elseif ((int) $durasi->status === 0) {
                    $durasi->update(['status' => 1, 'mulai' => $durasi->mulai ?: now(), 'reset' => 0]);
                }

                if (! SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->where('id_siswa', $siswa->id_siswa)->exists()) {
                    $this->snapshot->buat($jadwal, $siswa, (int) $durasi->id_durasi);
                }

                $mulaiBaru = (int) $durasi->status === 1 && ! $durasi->session_id;

                if ($mulaiBaru) {
                    $durasi->session_id = $request->session()->getId();
                    $durasi->ip_address = substr((string) $request->ip(), 0, 45);
                }

                if ($mulaiBaru || (int) $durasi->status === 1) {
                    $durasi->save();
                }

                if ($mulaiBaru && $jadwal->isRemedial()) {
                    RemedialPeserta::where('id_jadwal', $jadwal->id_jadwal)
                        ->where('id_siswa', $siswa->id_siswa)
                        ->increment('percobaan');
                }

                $this->writeLog($siswa->id_siswa, (int) $jadwal->id_jadwal, 1, 'Memulai Ujian');
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('ujian.exam', $jadwal->id_jadwal);
    }

    public function exam(Request $request, int $id): Response|RedirectResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if (! $durasi || (int) $durasi->status === 0) {
            return redirect()->route('ujian.show', $jadwal->id_jadwal)
                ->with('error', 'Mulai ujian terlebih dahulu.');
        }

        if ((int) $durasi->status === 2) {
            return redirect()->route('ujian.hasil')->with('error', 'Ujian sudah selesai.');
        }

        $this->waktu->sentuh($durasi);
        $remaining = $this->waktu->sisaDetik($jadwal, $durasi);

        if ($remaining <= 0) {
            $this->finalize($siswa, $jadwal, $durasi);

            return redirect()->route('ujian.hasil')->with('success', 'Waktu ujian telah habis.');
        }

        $this->guardDevice($request, $siswa, $jadwal, $durasi);

        $questions = $this->questions($jadwal, $siswa);

        return Inertia::render('Siswa/Ujian/Kerjakan', [
            'jadwal' => $this->jadwalPayload($jadwal),
            'siswa' => $this->studentPayload($siswa, (int) $jadwal->id_tp, (int) $jadwal->id_smt),
            'questions' => $questions,
            'remaining' => $remaining,
            'serverNow' => now()->getTimestampMs(),
            'hasilTampil' => (int) $jadwal->hasil_tampil,
        ]);
    }

    public function saveAnswer(Request $request, int $id): JsonResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if (! $durasi || (int) $durasi->status !== 1) {
            return response()->json(['message' => 'Ujian tidak aktif.'], 409);
        }

        $this->guardDevice($request, $siswa, $jadwal, $durasi);

        $data = $request->validate([
            'id_soal_siswa' => ['required', 'string', 'max:15'],
            'jenis' => ['required', 'integer', 'in:1,2,3,4,5'],
            'jawaban' => ['nullable'],
            'jawaban_alias' => ['nullable', 'string', 'max:10'],
        ]);

        $row = SoalSiswa::where('id_soal_siswa', $data['id_soal_siswa'])
            ->where('id_siswa', $siswa->id_siswa)
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->firstOrFail();

        if ((int) $row->jenis_soal !== (int) $data['jenis']) {
            return response()->json(['message' => 'Jenis soal tidak sesuai.'], 422);
        }

        $jenis = (int) $row->jenis_soal;
        $jawaban = $data['jawaban'] ?? null;

        if ($jenis === 1) {
            $letter = strtoupper(substr(trim((string) $jawaban), 0, 1));
            $row->jawaban_siswa = in_array($letter, ['A', 'B', 'C', 'D', 'E'], true) ? $letter : null;
            $row->jawaban_alias = strtoupper(substr(trim((string) ($data['jawaban_alias'] ?? '')), 0, 1)) ?: '';
        } elseif ($jenis === 2) {
            $letters = is_array($jawaban) ? $jawaban : [];
            $letters = array_values(array_unique(array_filter(array_map(
                fn ($value) => strtoupper(trim((string) $value)),
                $letters
            ), fn ($value) => in_array($value, ['A', 'B', 'C', 'D', 'E'], true))));
            $row->jawaban_siswa = $letters ? json_encode($letters) : null;
        } elseif ($jenis === 3) {
            $pairs = CbtScoring::pairs($row->jawaban_benar);
            $values = is_array($jawaban) ? $jawaban : [];
            $answers = [];
            foreach ($pairs as $index => $_) {
                $value = $values[$index] ?? null;
                $answers[$index] = is_numeric($value) ? (int) $value : null;
            }
            $row->jawaban_siswa = json_encode($answers);
        } elseif ($jenis === 4) {
            $text = trim((string) $jawaban);
            $row->jawaban_siswa = $text === '' ? null : mb_substr($text, 0, 1000);
        } else {
            $text = trim((string) $jawaban);
            $row->jawaban_siswa = $text === '' ? null : mb_substr($text, 0, 20000);
        }

        $row->save();
        $this->waktu->sentuh($durasi);

        $remaining = $this->waktu->sisaDetik($jadwal, $durasi);
        $answered = $this->answeredCount($siswa, $jadwal);
        $expired = $remaining <= 0;

        if ($expired) {
            $this->finalize($siswa, $jadwal, $durasi);
        }

        return response()->json([
            'saved' => true,
            'answered' => $answered,
            'remaining' => $remaining,
            'expired' => $expired,
        ]);
    }

    public function checkpoint(Request $request, int $id): JsonResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if (! $durasi || (int) $durasi->status !== 1) {
            return response()->json(['message' => 'Ujian tidak aktif.'], 409);
        }

        $this->waktu->sentuh($durasi);
        $remaining = $this->waktu->sisaDetik($jadwal, $durasi);

        if ($remaining <= 0) {
            $this->finalize($siswa, $jadwal, $durasi);
        }

        return response()->json([
            'remaining' => $remaining,
            'answered' => $this->answeredCount($siswa, $jadwal),
            'expired' => $remaining <= 0,
        ]);
    }

    public function violation(Request $request, int $id): JsonResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if (! $durasi || (int) $durasi->status !== 1) {
            return response()->json(['message' => 'Ujian tidak aktif.'], 409);
        }

        $data = $request->validate([
            'jenis' => ['required', 'string', 'in:pindah_tab,salin,tempel,klik_kanan,keluar_halaman,fokus_hilang,keluar_fullscreen'],
            'detail' => ['nullable', 'string', 'max:200'],
        ]);

        $labels = [
            'pindah_tab' => 'Berpindah tab atau jendela',
            'salin' => 'Mencoba menyalin soal',
            'tempel' => 'Mencoba menempel ke jawaban',
            'klik_kanan' => 'Membuka menu klik kanan',
            'keluar_halaman' => 'Mencoba meninggalkan halaman ujian',
            'fokus_hilang' => 'Jendela ujian kehilangan fokus',
            'keluar_fullscreen' => 'Keluar dari mode layar penuh',
        ];

        $description = $labels[$data['jenis']];

        if (! empty($data['detail'])) {
            $description .= ': '.trim(strip_tags($data['detail']));
        }

        $this->writeLog($siswa->id_siswa, $jadwal->id_jadwal, 3, $description, $data['jenis']);

        $count = LogUjian::where('id_siswa', $siswa->id_siswa)
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->where('log_type', 3)
            ->count();

        return response()->json(['count' => $count], 201);
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $siswa = $this->student($request);
        $jadwal = $this->eligibleJadwal($siswa, $id);
        $durasi = $this->durasi($siswa, $jadwal);

        if ($durasi && (int) $durasi->status !== 2) {
            $this->finalize($siswa, $jadwal, $durasi);
        }

        return response()->json([
            'redirect' => route('ujian.hasil'),
        ]);
    }

    public function hasil(Request $request): Response
    {
        $siswa = $this->student($request);
        [$tp, $smt] = $this->activePeriod();

        $riwayat = DurasiSiswa::with(['jadwal.bank.mapel:id_mapel,nama_mapel', 'jadwal.jenis:id_jenis,nama_jenis'])
            ->where('id_siswa', $siswa->id_siswa)
            ->where('status', 2)
            ->orderByDesc('selesai')
            ->get()
            ->filter(fn (DurasiSiswa $row) => $row->jadwal !== null)
            ->map(function (DurasiSiswa $row) {
                $jadwal = $row->jadwal;
                $nilai = Nilai::find(CbtScoring::nilaiId((int) $row->id_siswa, (int) $jadwal->id_jadwal));
                $tampil = (int) $jadwal->hasil_tampil === 1;

                $components = null;
                $total = null;

                if ($nilai && $tampil) {
                    $components = [
                        'pg' => (float) $nilai->pg_nilai,
                        'kompleks' => (float) $nilai->kompleks_nilai,
                        'jodohkan' => (float) $nilai->jodohkan_nilai,
                        'isian' => (float) $nilai->isian_nilai,
                        'esai' => (float) $nilai->essai_nilai,
                    ];
                    $total = round(array_sum($components), 2);
                }

                return [
                    'id_jadwal' => $jadwal->id_jadwal,
                    'bank_nama' => $jadwal->bank?->bank_nama,
                    'mapel' => $jadwal->bank?->mapel?->nama_mapel,
                    'jenis' => $jadwal->jenis?->nama_jenis,
                    'selesai' => $row->selesai,
                    'lama_ujian' => $row->lama_ujian,
                    'hasil_tampil' => $tampil,
                    'dikoreksi' => (bool) ($nilai?->dikoreksi ?? false),
                    'komponen' => $components,
                    'total' => $total,
                    'kkm' => (int) ($jadwal->bank?->kkm ?? 0),
                    'remedial' => $jadwal->isRemedial(),
                    'id_jadwal_induk' => $jadwal->id_jadwal_induk ? (int) $jadwal->id_jadwal_induk : null,
                ];
            });

        // Nilai akhir memakai hasil terbaik antara ujian utama dan remedialnya.
        $terbaik = $riwayat
            ->groupBy(fn (array $row) => $row['id_jadwal_induk'] ?? $row['id_jadwal'])
            ->map(fn ($group) => $group->pluck('total')->filter(fn ($value) => $value !== null)->max());

        $riwayat = $riwayat
            ->map(fn (array $row) => $row + [
                'nilai_akhir' => $terbaik->get($row['id_jadwal_induk'] ?? $row['id_jadwal']),
            ])
            ->values();

        return Inertia::render('Siswa/Ujian/Hasil', [
            'riwayat' => $riwayat,
            'siswa' => $this->studentPayload($siswa, $tp, $smt),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    private function student(Request $request): Siswa
    {
        $siswa = Siswa::where('username', $request->user()->username)->first();

        abort_unless($siswa, 403, 'Akun ini tidak terhubung dengan data siswa.');

        return $siswa;
    }

    private function durasi(Siswa $siswa, Jadwal $jadwal): ?DurasiSiswa
    {
        return DurasiSiswa::where('id_siswa', $siswa->id_siswa)
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->first();
    }

    private function eligibleJadwal(Siswa $siswa, int $id): Jadwal
    {
        [$tp, $smt] = $this->activePeriod();

        $jadwal = Jadwal::with(['bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('status', 1)
            ->findOrFail($id);

        $classIds = $this->classIds($siswa, (int) $tp, (int) $smt);

        if (! $this->matchesStudent($jadwal, $siswa, $classIds)) {
            abort(404);
        }

        if ($this->remedialAkses($siswa, $jadwal) === false) {
            abort(404);
        }

        return $jadwal;
    }

    /**
     * Jadwal remedial hanya boleh diakses peserta yang dipetakan guru.
     * Null berarti jadwal bukan remedial (akses mengikuti kelas).
     */
    private function remedialAkses(Siswa $siswa, Jadwal $jadwal): ?bool
    {
        if (! $jadwal->isRemedial()) {
            return null;
        }

        return RemedialPeserta::where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->exists();
    }

    /**
     * Membuka percobaan remedial berikutnya: jawaban dan nilai lama dibersihkan
     * agar peserta mengerjakan ulang dari awal di jadwal yang sama.
     */
    private function resetPercobaan(Siswa $siswa, Jadwal $jadwal, DurasiSiswa $durasi): void
    {
        DB::transaction(function () use ($siswa, $jadwal, $durasi) {
            PercobaanArsip::simpan($jadwal, [$siswa->id_siswa], 'reset_otomatis');

            SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->where('id_siswa', $siswa->id_siswa)->delete();
            Nilai::where('id_jadwal', $jadwal->id_jadwal)->where('id_siswa', $siswa->id_siswa)->delete();
            RekapNilai::where('id_jadwal', $jadwal->id_jadwal)->where('id_siswa', $siswa->id_siswa)->delete();

            $durasi->status = 0;
            $durasi->mulai = null;
            $durasi->selesai = null;
            $durasi->lama_ujian = '00:00:00';
            $durasi->session_id = null;
            $durasi->ip_address = null;
            $durasi->reset = 0;
            $durasi->save();

            UjianSessionGuard::forget((int) $jadwal->id_jadwal, (int) $siswa->id_siswa);
        });
    }

    private function remedialPeserta(Siswa $siswa, Jadwal $jadwal): ?RemedialPeserta
    {
        return RemedialPeserta::where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->first();
    }

    /**
     * Catat penggunaan perangkat/sesi lain saat ujian berlangsung.
     *
     * Pelanggaran hanya dicatat bila dua sesi benar-benar aktif bersamaan.
     * Perpindahan perangkat biasa (browser restart, sesi kedaluwarsa, atau
     * jaringan lab berganti) hanya memindahkan sesi tercatat tanpa menuduh
     * siswa. Ujian tetap berjalan agar siswa tidak terhenti total.
     */
    private function guardDevice(Request $request, Siswa $siswa, Jadwal $jadwal, ?DurasiSiswa $durasi): void
    {
        if (! $durasi || (int) $durasi->status !== 1) {
            return;
        }

        $sessionId = $request->session()->getId();
        $ip = substr((string) $request->ip(), 0, 45);

        $lain = UjianSessionGuard::record(
            (int) $jadwal->id_jadwal,
            (int) $siswa->id_siswa,
            $sessionId,
            $ip,
        );

        // Sesi tercatat mengikuti perangkat yang terakhir dipakai agar perangkat
        // lama yang sudah tidak aktif tidak terus dianggap sebagai pelanggaran.
        if ($durasi->session_id !== $sessionId || $durasi->ip_address !== $ip) {
            $durasi->session_id = $sessionId;
            $durasi->ip_address = $ip;
            $durasi->save();
        }

        if ($lain === null) {
            return;
        }

        $this->logOnce($siswa, $jadwal, 'sesi_ganda', sprintf(
            'Dua sesi aktif bersamaan: perangkat lain aktif %d detik lalu dari %s, perangkat ini dari %s',
            $lain['detik'],
            $lain['ip'] !== '' ? $lain['ip'] : 'IP tidak diketahui',
            $ip !== '' ? $ip : 'IP tidak diketahui',
        ));
    }

    private function logOnce(Siswa $siswa, Jadwal $jadwal, string $jenis, string $description): void
    {
        $key = sprintf('ujian-log:%s:%s:%s', $jenis, $siswa->id_siswa, $jadwal->id_jadwal);

        if (! Cache::add($key, true, now()->addMinutes(3))) {
            return;
        }

        $this->writeLog($siswa->id_siswa, (int) $jadwal->id_jadwal, 3, $description);
    }

    private function jamKeLock(Siswa $siswa, Jadwal $jadwal): ?Jadwal
    {
        $jam = (int) $jadwal->jam_ke;

        if ($jam <= 1) {
            return null;
        }

        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return null;
        }

        $classIds = $this->classIds($siswa, (int) $tp, (int) $smt);
        $previous = Jadwal::with('bank')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('status', 1)
            ->where('tgl_mulai', $jadwal->tgl_mulai)
            ->where('jam_ke', $jam - 1)
            ->get();

        foreach ($previous as $candidate) {
            if (! $this->matchesStudent($candidate, $siswa, $classIds)) {
                continue;
            }

            $finished = DurasiSiswa::where('id_siswa', $siswa->id_siswa)
                ->where('id_jadwal', $candidate->id_jadwal)
                ->where('status', 2)
                ->exists();

            if (! $finished) {
                return $candidate;
            }
        }

        return null;
    }

    private function jamKeMessage(Jadwal $blocking): string
    {
        return 'Ujian jam ke-'.max(1, (int) $blocking->jam_ke).' belum diselesaikan. Selesaikan ujian tersebut terlebih dahulu.';
    }

    private function matchesStudent(Jadwal $jadwal, Siswa $siswa, array $classIds): bool
    {
        $bank = $jadwal->bank;

        if (! $bank) {
            return false;
        }

        $bankKelas = array_filter(array_map('intval', explode(',', (string) $bank->bank_kelas)));

        if ($bankKelas && ! array_intersect($bankKelas, $classIds)) {
            return false;
        }

        if (! $bankKelas) {
            $kelas = $classIds ? Kelas::find($classIds[0]) : null;
            $level = (int) ($kelas?->level_id ?? $siswa->kelas_awal ?? 0);
            if ($bank->bank_level && $level && (int) $bank->bank_level !== $level) {
                return false;
            }
        }

        $mulai = Carbon::parse($jadwal->tgl_mulai)->startOfDay();
        $selesai = Carbon::parse($jadwal->tgl_selesai)->endOfDay();

        if (now()->lt($mulai) || now()->gt($selesai)) {
            return false;
        }

        $agama = trim((string) ($bank->soal_agama ?? ''));

        if ($agama !== '' && ! in_array($agama, ['-', '0'], true) && $siswa->agama && $agama !== $siswa->agama) {
            return false;
        }

        return true;
    }

    private function classIds(Siswa $siswa, int $tp, int $smt): array
    {
        $ids = KelasSiswa::where('id_siswa', $siswa->id_siswa)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->pluck('id_kelas')
            ->all();

        $sesiKelas = DB::table('cbt_sesi_siswa')
            ->where('siswa_id', $siswa->id_siswa)
            ->where('tp_id', $tp)
            ->where('smt_id', $smt)
            ->value('kelas_id');

        if ($sesiKelas) {
            $ids[] = (int) $sesiKelas;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function questions(Jadwal $jadwal, Siswa $siswa): array
    {
        return SoalSiswa::with('soal')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->orderBy('no_soal_alias')
            ->get()
            ->map(function (SoalSiswa $row) {
                $soal = $row->soal;
                $jenis = (int) $row->jenis_soal;

                $item = [
                    'id_soal_siswa' => $row->id_soal_siswa,
                    'jenis' => $jenis,
                    'nomor' => (int) $row->no_soal_alias,
                    'soal' => HtmlSanitizer::clean((string) ($soal?->soal ?? '')),
                    'deskripsi' => HtmlSanitizer::clean((string) ($soal?->deskripsi ?? '')),
                    'file' => SoalMedia::url($soal?->file),
                    'point' => (float) $row->point_soal,
                ];

                if ($jenis === 1) {
                    $answer = strtoupper((string) $row->jawaban_siswa);
                    $opsi = [];
                    foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                        $text = HtmlSanitizer::clean((string) ($soal?->{'opsi_'.$letter} ?? ''));
                        $file = $soal?->{'file_'.$letter};
                        if ($text === '' && ! $file) {
                            continue;
                        }
                        $opsi[] = [
                            'value' => strtoupper($letter),
                            'alias' => strtoupper((string) ($row->{'opsi_alias_'.$letter} ?: strtoupper($letter))),
                            'text' => $text,
                            'file' => SoalMedia::url($file),
                            'checked' => $answer === strtoupper($letter),
                        ];
                    }
                    usort($opsi, fn ($a, $b) => strcmp($a['alias'], $b['alias']));
                    $item['opsi'] = $opsi;
                } elseif ($jenis === 2) {
                    $answer = CbtScoring::letters($row->jawaban_siswa);
                    $opsi = [];
                    foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                        $text = HtmlSanitizer::clean((string) ($soal?->{'opsi_'.$letter} ?? ''));
                        $file = $soal?->{'file_'.$letter};
                        if ($text === '' && ! $file) {
                            continue;
                        }
                        $opsi[] = [
                            'value' => strtoupper($letter),
                            'text' => $text,
                            'file' => SoalMedia::url($file),
                            'checked' => in_array(strtoupper($letter), $answer, true),
                        ];
                    }
                    $item['opsi'] = $opsi;
                } elseif ($jenis === 3) {
                    $pairs = CbtScoring::pairs($row->jawaban_benar);
                    $order = json_decode((string) $row->jawaban_alias, true);
                    $order = is_array($order) ? array_values(array_filter($order, fn ($value) => is_numeric($value) && isset($pairs[(int) $value]))) : [];
                    if (! $order) {
                        $order = range(0, max(0, count($pairs) - 1));
                    }
                    $answer = json_decode((string) $row->jawaban_siswa, true);
                    $item['pairs'] = collect($pairs)->map(fn ($pair, $index) => [
                        'index' => $index,
                        'kiri' => HtmlSanitizer::clean((string) ($pair['kiri'] ?? '')),
                    ])->values();
                    $item['rightOptions'] = collect($order)->map(fn ($index) => [
                        'index' => (int) $index,
                        'text' => HtmlSanitizer::clean((string) ($pairs[(int) $index]['kanan'] ?? '')),
                    ])->values();
                    $item['answer'] = collect($pairs)->map(fn ($_, $index) => isset($answer[$index]) && $answer[$index] !== null ? (int) $answer[$index] : null)->values();
                } elseif ($jenis === 4) {
                    $item['answer'] = (string) ($row->jawaban_siswa ?? '');
                } elseif ($jenis === 5) {
                    $item['answer'] = (string) ($row->jawaban_siswa ?? '');
                }

                return $item;
            })
            ->values()
            ->all();
    }

    private function finalize(Siswa $siswa, Jadwal $jadwal, DurasiSiswa $durasi): void
    {
        if ((int) $durasi->status === 2) {
            return;
        }

        DB::transaction(function () use ($siswa, $jadwal, $durasi) {
            $durasi->lama_ujian = gmdate('H:i:s', min($this->waktu->terlampaui($durasi), 359999));
            $durasi->selesai = now();
            $durasi->status = 2;
            $durasi->reset = 0;
            $durasi->save();

            CbtScoring::simpan($jadwal, (int) $siswa->id_siswa);
            $this->writeLog($siswa->id_siswa, (int) $jadwal->id_jadwal, 2, 'Menyelesaikan Ujian');
        });
    }

    private function answeredCount(Siswa $siswa, Jadwal $jadwal): int
    {
        $rows = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->get();

        return $rows->filter(function (SoalSiswa $row) {
            $value = (string) $row->jawaban_siswa;

            if (trim($value) === '' || $value === 'null') {
                return false;
            }

            if ((int) $row->jenis_soal === 3) {
                $decoded = json_decode($value, true);

                return is_array($decoded) && collect($decoded)->contains(fn ($item) => $item !== null);
            }

            return true;
        })->count();
    }

    private function writeLog(int $siswaId, int $jadwalId, int $type, string $description, ?string $kode = null): void
    {
        $action = match ($type) {
            1 => 'ujian.mulai',
            2 => 'ujian.selesai',
            default => 'ujian.pelanggaran',
        };

        AuditLog::record($action, 'Siswa #'.$siswaId.' jadwal #'.$jadwalId.': '.$description);

        LogUjian::create([
            'log_time' => now(),
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwalId,
            'log_type' => $type,
            'log_desc' => $description,
            'kode' => $kode,
            'address' => substr((string) request()->ip(), 0, 45),
            'agent' => substr((string) request()->userAgent(), 0, 255),
            'device' => 'web',
            'reset' => 0,
        ]);
    }

    private function studentPayload(Siswa $siswa, $tp, $smt): array
    {
        $kelas = $tp && $smt
            ? KelasSiswa::where('id_siswa', $siswa->id_siswa)
                ->where('id_tp', $tp)->where('id_smt', $smt)
                ->first()
            : null;

        return [
            'id_siswa' => $siswa->id_siswa,
            'nama' => $siswa->nama,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'kelas' => $kelas ? Kelas::find($kelas->id_kelas)?->nama_kelas : null,
            'nomor_peserta' => $tp && $smt
                ? NomorPeserta::where('id_siswa', $siswa->id_siswa)
                    ->where('id_tp', $tp)->where('id_smt', $smt)
                    ->value('nomor_peserta')
                : null,
        ];
    }

    private function jadwalPayload(Jadwal $jadwal): array
    {
        return [
            'id_jadwal' => $jadwal->id_jadwal,
            'bank_nama' => $jadwal->bank?->bank_nama,
            'bank_kode' => $jadwal->bank?->bank_kode,
            'mapel' => $jadwal->bank?->mapel?->nama_mapel,
            'jenis' => $jadwal->jenis?->nama_jenis,
            'jenis_kode' => $jadwal->jenis?->kode_jenis,
            'tgl_mulai' => $this->dateLabel($jadwal->tgl_mulai),
            'tgl_selesai' => $this->dateLabel($jadwal->tgl_selesai),
            'durasi_ujian' => (int) $jadwal->durasi_ujian,
            'token' => (int) $jadwal->token,
            'acak_soal' => (int) $jadwal->acak_soal,
            'acak_opsi' => (int) $jadwal->acak_opsi,
            'hasil_tampil' => (int) $jadwal->hasil_tampil,
            'jumlah_soal' => $this->plannedQuestions($jadwal),
            'kkm' => (int) ($jadwal->bank?->kkm ?? 0),
        ];
    }

    private function plannedQuestions(Jadwal $jadwal): int
    {
        $bank = $jadwal->bank;

        if (! $bank) {
            return 0;
        }

        return (int) $bank->tampil_pg + (int) $bank->tampil_kompleks
            + (int) $bank->tampil_jodohkan + (int) $bank->tampil_isian
            + (int) $bank->tampil_esai;
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
