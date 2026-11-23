<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Semester;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Support\CbtScoring;
use App\Support\Period;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalisisController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();

        $jadwals = $this->jadwalQuery($user, $tp, $smt)
            ->with(['bank', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();
        $lintas = $request->boolean('lintas');

        $analisis = ! $lintas && $selected
            ? $this->analisis($selected)
            : ['butir' => [], 'ringkasan' => null];

        return Inertia::render('Cbt/Analisis/Index', [
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
                'tgl_mulai' => $this->dateLabel($selected->tgl_mulai),
            ] : null,
            'butir' => $analisis['butir'],
            'ringkasan' => $analisis['ringkasan'],
            'lintas' => $lintas,
            'lintasButir' => $lintas ? $this->analisisLintas($jadwals) : [],
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$tp, $smt] = $this->activePeriod();

        $jadwals = $this->jadwalQuery($request->user(), $tp, $smt)
            ->with('bank')
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();
        abort_if(! $selected, 404, 'Jadwal ujian tidak ditemukan.');

        $analisis = $this->analisis($selected);
        $flagLabel = [
            'kunci_curiga' => 'Kunci perlu dicek',
            'revisi' => 'Perlu revisi',
            'pengecoh_kuat' => 'Pengecoh kuat',
        ];

        $headers = ['No', 'Soal', 'Jenis', 'Poin', 'Peserta', 'Menjawab', 'Benar', 'Ketuntasan %', 'Daya beda', 'Korelasi', 'Atas %', 'Bawah %', 'Catatan'];
        $rows = [];

        foreach ($analisis['butir'] as $item) {
            $rows[] = [
                $item['nomor'] ?? null,
                trim(preg_replace('/\s+/', ' ', strip_tags((string) ($item['soal'] ?? '')))),
                $this->jenisLabel((int) ($item['jenis'] ?? 0)),
                $item['point'] ?? null,
                $item['peserta'] ?? null,
                $item['menjawab'] ?? null,
                $item['benar'] ?? null,
                $item['persentase'] ?? null,
                $item['daya_beda'] ?? null,
                $item['korelasi'] ?? null,
                $item['kelompok_atas'] ?? null,
                $item['kelompok_bawah'] ?? null,
                $flagLabel[$item['flag'] ?? ''] ?? ($item['catatan'] ?? ''),
            ];
        }

        $kode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($selected->bank?->bank_kode ?? 'ujian'));

        return XlsxExport::download('analisis-'.trim((string) $kode, '-').'.xlsx', $headers, $rows, 'Analisis Butir');
    }

    private function jenisLabel(int $jenis): string
    {
        return match ($jenis) {
            1 => 'Pilihan Ganda',
            2 => 'PG Kompleks',
            3 => 'Menjodohkan',
            4 => 'Isian Singkat',
            5 => 'Uraian',
            default => 'Lainnya',
        };
    }

    private function butir(Jadwal $jadwal): array
    {
        $rows = SoalSiswa::with('soal:id_soal,soal,jenis')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->get();

        $groups = $rows->groupBy('id_soal')->sortBy(fn ($items) => $items->min('no_soal_alias'));

        return $groups->map(function ($items) {
            $first = $items->first();
            $jenis = (int) $first->jenis_soal;
            $total = $items->count();
            $answered = $items->filter(fn (SoalSiswa $row) => $this->hasAnswer($row))->count();

            $data = [
                'id_soal' => (int) $first->id_soal,
                'nomor' => (int) $items->min('no_soal_alias'),
                'jenis' => $jenis,
                'soal' => (string) ($first->soal?->soal ?? ''),
                'point' => (float) $first->point_soal,
                'peserta' => $total,
                'menjawab' => $answered,
                'benar' => 0,
                'persentase' => 0.0,
                'kesulitan' => 'Sulit',
                'opsi' => [],
            ];

            if ($jenis === 1) {
                $benar = $items->filter(fn (SoalSiswa $row) => CbtScoring::pgBenar($row))->count();
                $distribusi = [];

                foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
                    $distribusi[$letter] = 0;
                }

                foreach ($items as $row) {
                    $pilihan = strtoupper(trim((string) ($row->jawaban_alias ?: $row->jawaban_siswa)));
                    if ($pilihan !== '' && isset($distribusi[$pilihan])) {
                        $distribusi[$pilihan]++;
                    }
                }

                $data['benar'] = $benar;
                $data['opsi'] = collect($distribusi)->map(fn ($count, $letter) => [
                    'label' => $letter,
                    'jumlah' => $count,
                ])->values()->all();
            } elseif ($jenis === 2) {
                $exact = 0;
                $partial = 0.0;

                foreach ($items as $row) {
                    $correct = CbtScoring::letters($row->jawaban_benar);
                    $answer = CbtScoring::letters($row->jawaban_siswa);

                    if (! $correct) {
                        continue;
                    }

                    $intersection = count(array_intersect($answer, $correct));
                    $partial += $intersection / count($correct);

                    if ($intersection === count($correct) && count($answer) === count($correct)) {
                        $exact++;
                    }
                }

                $data['benar'] = $exact;
                $data['partial'] = $total > 0 ? round($partial / $total * 100, 1) : 0;
            } elseif ($jenis === 3) {
                $exact = 0;

                foreach ($items as $row) {
                    $pairs = CbtScoring::pairs($row->jawaban_benar);
                    $answer = json_decode((string) $row->jawaban_siswa, true);
                    $answer = is_array($answer) ? $answer : [];

                    if (! $pairs) {
                        continue;
                    }

                    $correct = 0;
                    foreach ($pairs as $index => $_) {
                        if (isset($answer[$index]) && $answer[$index] !== null && (int) $answer[$index] === $index) {
                            $correct++;
                        }
                    }

                    if ($correct === count($pairs)) {
                        $exact++;
                    }
                }

                $data['benar'] = $exact;
            } elseif ($jenis === 4) {
                $benar = $items->filter(function (SoalSiswa $row) {
                    $correct = mb_strtolower(trim((string) $row->jawaban_benar));
                    $answer = mb_strtolower(trim((string) $row->jawaban_siswa));

                    return $correct !== '' && $answer === $correct;
                })->count();

                $data['benar'] = $benar;
            } else {
                $terkoreksi = $items->filter(fn (SoalSiswa $row) => (int) $row->nilai_otomatis === 1);
                $nilai = $terkoreksi->sum(fn (SoalSiswa $row) => (float) $row->nilai_koreksi);

                $data['terkoreksi'] = $terkoreksi->count();
                $data['rata_nilai'] = $terkoreksi->isNotEmpty() ? round($nilai / $terkoreksi->count(), 2) : null;
                $data['benar'] = null;
            }

            if ($data['benar'] !== null) {
                $persentase = $total > 0 ? round($data['benar'] / $total * 100, 1) : 0;
                $data['persentase'] = $persentase;
                $data['kesulitan'] = $persentase >= 70 ? 'Mudah' : ($persentase >= 30 ? 'Sedang' : 'Sulit');
            } else {
                $data['kesulitan'] = null;
            }

            return $data;
        })->values()->all();
    }

    private function hasAnswer(SoalSiswa $row): bool
    {
        $value = (string) $row->jawaban_siswa;

        if (trim($value) === '' || $value === 'null') {
            return false;
        }

        if ((int) $row->jenis_soal === 3) {
            $decoded = json_decode($value, true);

            return is_array($decoded) && collect($decoded)->contains(fn ($item) => $item !== null);
        }

        return true;
    }

    /**
     * Statistik butir lanjutan: daya beda, korelasi butir-total, dan reliabilitas KR-20.
     *
     * @return array{butir: array<int, array<string, mixed>>, ringkasan: ?array<string, mixed>}
     */
    private function analisis(Jadwal $jadwal): array
    {
        $butir = collect($this->butir($jadwal))->keyBy('id_soal');

        $rows = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->get(['id_soal', 'id_siswa', 'jenis_soal', 'jawaban_siswa', 'jawaban_benar', 'nilai_koreksi', 'nilai_otomatis', 'point_soal']);

        if ($rows->isEmpty()) {
            return ['butir' => $butir->values()->all(), 'ringkasan' => null];
        }

        // Skor mentah tiap peserta dipakai untuk mengurutkan kelompok atas/bawah.
        $total = $rows->groupBy('id_siswa')->map(
            fn ($items) => $items->sum(fn (SoalSiswa $row) => $this->benar($row) ? (float) $row->point_soal : 0.0)
        );

        $sorted = $total->sortDesc();
        $peserta = $sorted->count();
        $potong = max(1, (int) ceil($peserta * 0.27));
        $atas = $sorted->keys()->take($potong)->all();
        $bawah = $sorted->keys()->slice(-$potong)->all();

        $nilai = $sorted->values()->all();
        // Dengan peserta sangat sedikit, kelompok atas/bawah dan pengecoh tidak
        // bermakna; butir jangan langsung ditandai "perlu revisi".
        $cukupPeserta = $peserta >= 6;
        $rata = $this->mean($nilai);
        $sd = $this->stdDev($nilai);

        foreach ($rows->groupBy('id_soal') as $idSoal => $items) {
            $item = $butir->get((int) $idSoal);

            if (! $item) {
                continue;
            }

            if ((int) $item['jenis'] === 5) {
                $butir->put((int) $idSoal, $item + [
                    'daya_beda' => null,
                    'korelasi' => null,
                    'kelompok_atas' => null,
                    'kelompok_bawah' => null,
                    'flag' => null,
                    'catatan' => 'Soal uraian tidak dihitung daya beda otomatis.',
                ]);

                continue;
            }

            $benar = $items->filter(fn (SoalSiswa $row) => $this->benar($row));
            $salah = $items->reject(fn (SoalSiswa $row) => $this->benar($row));

            $pAtas = $this->proporsi($benar, $atas);
            $pBawah = $this->proporsi($benar, $bawah);
            $dayaBeda = round($pAtas - $pBawah, 2);

            $p = $items->count() > 0 ? $benar->count() / $items->count() : 0.0;
            $korelasi = null;

            if ($sd > 0 && $benar->isNotEmpty() && $salah->isNotEmpty()) {
                $m1 = $this->mean($total->only($benar->pluck('id_siswa')->all())->values()->all());
                $m0 = $this->mean($total->only($salah->pluck('id_siswa')->all())->values()->all());
                $korelasi = round((($m1 - $m0) / $sd) * sqrt(max(0.0, $p * (1 - $p))), 3);
            }

            $flag = null;
            $catatan = $cukupPeserta
                ? null
                : 'Peserta belum cukup (minimal 6) untuk menilai daya beda dan pengecoh.';

            if (! $cukupPeserta) {
                // Nilai statistik tetap ditampilkan, tetapi tanpa tuduhan butir.
            } elseif ($dayaBeda < 0) {
                $flag = 'kunci_curiga';
                $catatan = 'Daya beda negatif: kelompok bawah lebih sering benar. Periksa kunci jawaban.';
            } elseif ($dayaBeda < 0.2) {
                $flag = 'revisi';
                $catatan = 'Daya beda rendah: soal belum membedakan peserta mampu dan kurang mampu.';
            }

            if ($flag === null && $cukupPeserta && (int) $item['jenis'] === 1) {
                $kunci = strtoupper(trim((string) ($items->first()->jawaban_benar ?? '')));
                $pilihan = $items->countBy(fn (SoalSiswa $row) => strtoupper(trim((string) ($row->jawaban_siswa ?? ''))));
                $pengecoh = collect(['A', 'B', 'C', 'D', 'E'])
                    ->reject(fn ($letter) => $letter === $kunci || $letter === '')
                    ->map(fn ($letter) => (int) ($pilihan[$letter] ?? 0))
                    ->max();

                if ($pengecoh > (int) ($pilihan[$kunci] ?? 0) && $pengecoh > 0) {
                    $flag = 'pengecoh_kuat';
                    $catatan = 'Ada pengecoh yang dipilih lebih sering daripada kunci jawaban.';
                }
            }

            $butir->put((int) $idSoal, $item + [
                'daya_beda' => $dayaBeda,
                'korelasi' => $korelasi,
                'kelompok_atas' => round($pAtas * 100, 1),
                'kelompok_bawah' => round($pBawah * 100, 1),
                'flag' => $flag,
                'catatan' => $catatan,
            ]);
        }

        return [
            'butir' => $butir->values()->all(),
            'ringkasan' => [
                'peserta' => $peserta,
                'rata' => round($rata, 2),
                'simpangan' => round($sd, 2),
                'kr20' => $this->kr20($rows, $total, $butir),
                'butir_bermasalah' => $butir->filter(fn ($item) => in_array($item['flag'] ?? null, ['kunci_curiga', 'revisi', 'pengecoh_kuat'], true))->count(),
                'butir_total' => $butir->count(),
                'daya_beda_rata' => $this->rataDayaBeda($butir),
            ],
        ];
    }

    /**
     * Menggabungkan statistik butir yang sama (id_soal) dari seluruh ujian pada
     * periode aktif, sehingga soal yang dipakai berulang dapat dievaluasi lintas ujian.
     *
     * Jadwal remedial dilewati: pesertanya mengulang soal yang sama dari jadwal
     * induk, sehingga ikut dihitung akan menggandakan siswa dan butir tersebut.
     *
     * @return array<int, array<string, mixed>>
     */
    private function analisisLintas($jadwals): array
    {
        $agregat = [];

        foreach ($jadwals as $jadwal) {
            if ($jadwal->isRemedial()) {
                continue;
            }

            foreach ($this->analisis($jadwal)['butir'] as $item) {
                $id = (int) $item['id_soal'];

                $baris = $agregat[$id] ?? [
                    'id_soal' => $id,
                    'nomor' => (int) ($item['nomor'] ?? 0),
                    'jenis' => (int) ($item['jenis'] ?? 0),
                    'soal' => (string) ($item['soal'] ?? ''),
                    'point' => (float) ($item['point'] ?? 0),
                    'jumlah_ujian' => 0,
                    'peserta' => 0,
                    'benar' => 0,
                    'daya_beda' => [],
                    'catatan' => [],
                    'flag' => null,
                ];

                $baris['jumlah_ujian']++;
                $baris['peserta'] += (int) ($item['peserta'] ?? 0);

                if (($item['benar'] ?? null) !== null) {
                    $baris['benar'] += (int) $item['benar'];
                }

                if (($item['daya_beda'] ?? null) !== null) {
                    $baris['daya_beda'][] = (float) $item['daya_beda'];
                }

                if (! empty($item['flag'])) {
                    $baris['flag'] = $this->prioritasFlag($baris['flag'], (string) $item['flag']);
                }

                if (! empty($item['catatan'])) {
                    $baris['catatan'][] = (string) $item['catatan'];
                }

                $agregat[$id] = $baris;
            }
        }

        return collect($agregat)
            ->map(function (array $baris) {
                $daya = $baris['daya_beda'];
                $baris['daya_beda'] = $daya === [] ? null : round(array_sum($daya) / count($daya), 2);
                $baris['catatan'] = implode(' ', array_unique($baris['catatan'])) ?: null;
                $baris['persentase'] = $baris['peserta'] > 0
                    ? round($baris['benar'] / $baris['peserta'] * 100, 1)
                    : null;
                $baris['kesulitan'] = $baris['persentase'] === null
                    ? null
                    : ($baris['persentase'] >= 70 ? 'Mudah' : ($baris['persentase'] >= 30 ? 'Sedang' : 'Sulit'));

                return $baris;
            })
            ->sortBy('persentase')
            ->sortByDesc(fn (array $baris) => $baris['flag'] !== null)
            ->values()
            ->all();
    }

    private function prioritasFlag(?string $sekarang, string $baru): ?string
    {
        $prioritas = ['kunci_curiga' => 3, 'revisi' => 2, 'pengecoh_kuat' => 1];

        return ($prioritas[$baru] ?? 0) > ($prioritas[$sekarang ?? ''] ?? 0) ? $baru : $sekarang;
    }

    private function benar(SoalSiswa $row): bool
    {
        return match ((int) $row->jenis_soal) {
            1 => CbtScoring::pgBenar($row),
            2 => count(array_diff(CbtScoring::letters($row->jawaban_siswa), CbtScoring::letters($row->jawaban_benar))) === 0
                && count(CbtScoring::letters($row->jawaban_siswa)) > 0,
            3 => $this->pairsBenar($row),
            4 => mb_strtolower(trim((string) $row->jawaban_siswa)) !== ''
                && mb_strtolower(trim((string) $row->jawaban_siswa)) === mb_strtolower(trim((string) $row->jawaban_benar)),
            default => (float) $row->nilai_koreksi > 0,
        };
    }

    private function pairsBenar(SoalSiswa $row): bool
    {
        $pairs = CbtScoring::pairs($row->jawaban_benar);
        $answer = json_decode((string) $row->jawaban_siswa, true);

        if (! $pairs || ! is_array($answer)) {
            return false;
        }

        foreach ($pairs as $index => $_) {
            if (! isset($answer[$index]) || $answer[$index] === null || (int) $answer[$index] !== (int) $index) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, int|string>  $siswaIds
     */
    private function proporsi($benar, array $siswaIds): float
    {
        if (! $siswaIds) {
            return 0.0;
        }

        $jumlah = $benar->whereIn('id_siswa', $siswaIds)->count();

        return $jumlah / count($siswaIds);
    }

    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private function stdDev(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn ($value) => ($value - $mean) ** 2, $values)) / ($count - 1);

        return sqrt($variance);
    }

    /**
     * Reliabilitas KR-20 untuk butir objektif (pilihan ganda).
     */
    private function kr20($rows, $total, $butir): ?float
    {
        $objektif = $rows->filter(fn (SoalSiswa $row) => (int) $row->jenis_soal === 1);
        $k = $objektif->pluck('id_soal')->unique()->count();
        $varians = $this->stdDev($total->values()->all()) ** 2;

        if ($k < 2 || $varians <= 0) {
            return null;
        }

        $pq = 0.0;

        foreach ($objektif->groupBy('id_soal') as $items) {
            $p = $items->count() > 0 ? $items->filter(fn (SoalSiswa $row) => $this->benar($row))->count() / $items->count() : 0.0;
            $pq += $p * (1 - $p);
        }

        $nilai = (1 - $pq / $varians) * ($k / ($k - 1));

        return round(max(-1.0, min(1.0, $nilai)), 2);
    }

    private function rataDayaBeda($butir): ?float
    {
        $values = $butir->pluck('daya_beda')->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : round($values->avg(), 2);
    }

    private function jadwalQuery($user, $tp, $smt)
    {
        // Remedial memakai soal yang sama dengan induknya; bila ikut menjadi
        // opsi, pengguna bisa menganalisis statistik yang tergandakan.
        $query = Jadwal::where('id_tp', $tp)->where('id_smt', $smt)->induk();

        if (! $user->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
            $query->whereHas('bank', fn ($bank) => $bank->where('bank_guru_id', $guruId));
        }

        return $query;
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
        [$tahun, $semester] = Period::current();

        return [$tahun?->id_tp, $semester?->id_smt];
    }

    private function periodeLabel($tp, $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
