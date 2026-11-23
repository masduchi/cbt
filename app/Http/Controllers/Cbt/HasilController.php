<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Jobs\KirimNotifikasiHasilUjian;
use App\Models\DurasiSiswa;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Nilai;
use App\Models\NomorPeserta;
use App\Models\PercobaanRiwayat;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\Period;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HasilController extends Controller
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
        $peserta = [];
        $summary = null;

        if ($selected) {
            $peserta = $this->peserta($selected, $tp, $smt);
            $finished = collect($peserta)->where('status', 2)->pluck('total')->filter(fn ($value) => $value !== null);
            $kkm = (int) ($selected->bank?->kkm ?? 0);

            $summary = [
                'peserta' => count($peserta),
                'selesai' => collect($peserta)->where('status', 2)->count(),
                'rata_rata' => $finished->isNotEmpty() ? round($finished->avg(), 2) : null,
                'tertinggi' => $finished->isNotEmpty() ? round($finished->max(), 2) : null,
                'terendah' => $finished->isNotEmpty() ? round($finished->min(), 2) : null,
                'tuntas' => $kkm > 0 ? $finished->filter(fn ($value) => $value >= $kkm)->count() : null,
                'kkm' => $kkm,
            ];
        }

        $riwayat = collect();

        if ($selected) {
            // Percobaan pada sesi remedial juga milik jadwal induk, jadi riwayatnya
            // ikut ditampilkan dengan penanda sumber agar tidak tertukar.
            $sesiIds = Jadwal::where('id_jadwal_induk', $selected->id_jadwal)
                ->pluck('id_jadwal')
                ->map(fn ($id) => (int) $id)
                ->push((int) $selected->id_jadwal)
                ->unique()
                ->values();

            $rows = PercobaanRiwayat::whereIn('id_jadwal', $sesiIds)
                ->orderByDesc('id')
                ->get();

            $nama = Siswa::whereIn('id_siswa', $rows->pluck('id_siswa')->unique())
                ->pluck('nama', 'id_siswa');

            $indukId = (int) $selected->id_jadwal;

            $riwayat = $rows->map(fn (PercobaanRiwayat $row) => [
                'id' => $row->id,
                'nama' => $nama[$row->id_siswa] ?? '-',
                'percobaan' => (int) $row->percobaan,
                'total' => $row->total,
                'alasan' => $row->alasan,
                'sumber' => (int) $row->id_jadwal === $indukId ? 'Ujian' : 'Remedial',
                'waktu' => ($row->selesai ?? $row->time_create)?->isoFormat('D MMM YYYY, HH:mm'),
            ])->values();
        }

        return Inertia::render('Cbt/Hasil/Index', [
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
                'kkm' => (int) ($selected->bank?->kkm ?? 0),
                'bobot' => [
                    'pg' => (int) ($selected->bank?->bobot_pg ?? 0),
                    'kompleks' => (int) ($selected->bank?->bobot_kompleks ?? 0),
                    'jodohkan' => (int) ($selected->bank?->bobot_jodohkan ?? 0),
                    'isian' => (int) ($selected->bank?->bobot_isian ?? 0),
                    'esai' => (int) ($selected->bank?->bobot_esai ?? 0),
                ],
            ] : null,
            'peserta' => $peserta,
            'summary' => $summary,
            'periode' => $this->periodeLabel($tp, $smt),
            'riwayat' => $riwayat,
            'notifikasi_gagal' => $selected ? (int) Nilai::where('id_jadwal', $selected->id_jadwal)
                ->whereNotNull('hasil_notify_failed_at')
                ->count() : 0,
        ]);
    }

    /**
     * Antre ulang email/SMS hasil yang sebelumnya gagal permanen untuk satu
     * jadwal. Notifikasi dalam aplikasi tidak digandakan karena job bersifat
     * idempoten.
     */
    public function ulangNotifikasi(Request $request, int $jadwal): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        $selected = $this->jadwalQuery($request->user(), $tp, $smt)->find($jadwal);
        abort_if(! $selected, 404, 'Jadwal ujian tidak ditemukan.');

        $jumlah = KirimNotifikasiHasilUjian::ulangGagal((int) $selected->id_jadwal);

        if ($jumlah === 0) {
            return back()->with('error', 'Tidak ada notifikasi gagal pada jadwal ini.');
        }

        AuditLog::record('cbt.notifikasi.ulang', $jumlah.' notifikasi pada jadwal #'.$selected->id_jadwal.' diantrekan ulang');

        return back()->with('success', $jumlah.' notifikasi hasil ujian diantrekan ulang.');
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

        $peserta = $this->peserta($selected, $tp, $smt);
        $headers = ['Rank', 'NIS', 'Nama', 'Kelas', 'No Peserta', 'PG', 'Kompleks', 'Jodohkan', 'Isian', 'Esai', 'Total', 'Sumber', 'Status', 'Terkoreksi'];
        $rows = [];

        foreach ($peserta as $row) {
            $status = match ((int) $row['status']) {
                1 => 'Mengerjakan',
                2 => 'Selesai',
                default => 'Belum mulai',
            };

            $rows[] = [
                $row['rank'],
                $row['nis'],
                $row['nama'],
                $row['kelas'],
                $row['no_peserta'],
                $row['komponen']['pg'] ?? null,
                $row['komponen']['kompleks'] ?? null,
                $row['komponen']['jodohkan'] ?? null,
                $row['komponen']['isian'] ?? null,
                $row['komponen']['esai'] ?? null,
                $row['total'],
                $row['remedial'] ? 'Remedial' : 'Ujian',
                $status,
                $row['dikoreksi'] ? 'Ya' : 'Tidak',
            ];
        }

        $kode = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($selected->bank?->bank_kode ?? 'ujian'));
        $filename = 'hasil-'.trim((string) $kode, '-').'.xlsx';

        return XlsxExport::download($filename, $headers, $rows, 'Hasil Ujian');
    }

    private function peserta(Jadwal $jadwal, $tp, $smt): array
    {
        $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));
        $indukId = (int) $jadwal->id_jadwal;

        // Sesi remedial mengulang soal yang sama dari jadwal induk, jadi nilai yang
        // ditampilkan adalah yang terbaik antara ujian utama dan sesi remedialnya.
        $sesiIds = Jadwal::where('id_jadwal_induk', $indukId)
            ->pluck('id_jadwal')
            ->map(fn ($id) => (int) $id)
            ->push($indukId)
            ->unique()
            ->values()
            ->all();

        $nilai = Nilai::whereIn('id_jadwal', $sesiIds)
            ->get()
            ->groupBy('id_siswa')
            ->map(fn ($items) => $this->nilaiTerbaik($items, $indukId));

        $durasi = DurasiSiswa::whereIn('id_jadwal', $sesiIds)
            ->get()
            ->groupBy('id_siswa')
            ->map(fn ($items) => $items->firstWhere('id_jadwal', $indukId) ?? $items->first());

        $nomor = NomorPeserta::where('id_tp', $tp)->where('id_smt', $smt)->get()->keyBy('id_siswa');

        $kelasSiswa = DB::table('kelas_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($bankKelas, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $bankKelas))
            ->get(['kelas_siswa.id_siswa', 'master_kelas.nama_kelas'])
            ->keyBy('id_siswa');

        $ids = $kelasSiswa->keys()->merge($nilai->keys())->merge($durasi->keys())->unique();

        $rows = Siswa::whereIn('id_siswa', $ids)
            ->orderBy('nama')
            ->get()
            ->map(function (Siswa $siswa) use ($nilai, $durasi, $nomor, $kelasSiswa, $indukId) {
                $skor = $nilai->get($siswa->id_siswa);
                $row = $durasi->get($siswa->id_siswa);
                $komponen = $skor ? [
                    'pg' => (float) $skor->pg_nilai,
                    'kompleks' => (float) $skor->kompleks_nilai,
                    'jodohkan' => (float) $skor->jodohkan_nilai,
                    'isian' => (float) $skor->isian_nilai,
                    'esai' => (float) $skor->essai_nilai,
                ] : null;

                return [
                    'id_siswa' => $siswa->id_siswa,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                    'kelas' => $kelasSiswa->get($siswa->id_siswa)?->nama_kelas ?? '',
                    'no_peserta' => $nomor->get($siswa->id_siswa)?->nomor_peserta,
                    'status' => (int) ($row?->status ?? 0),
                    'lama_ujian' => $row?->lama_ujian,
                    'selesai' => $row?->selesai,
                    'komponen' => $komponen,
                    'total' => $skor ? $this->totalNilai($skor) : null,
                    'remedial' => $skor !== null && (int) $skor->id_jadwal !== $indukId,
                    'dikoreksi' => (bool) ($skor?->dikoreksi ?? false),
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($b['total'] ?? -1) <=> ($a['total'] ?? -1),
                fn ($a, $b) => strcmp((string) $a['nama'], (string) $b['nama']),
            ])
            ->values()
            ->map(function ($row, $index) {
                $row['rank'] = $row['total'] !== null ? $index + 1 : null;

                return $row;
            })
            ->all();

        return $rows;
    }

    /**
     * Nilai terbaik satu siswa dari ujian induk dan sesi remedialnya. Bila nilainya
     * sama, nilai dari jadwal induk yang dipakai agar tidak ditandai remedial.
     *
     * @param  Collection<int, Nilai>  $items
     */
    private function nilaiTerbaik($items, int $indukId): Nilai
    {
        return $items->reduce(function (?Nilai $terbaik, Nilai $row) use ($indukId) {
            if ($terbaik === null) {
                return $row;
            }

            $total = $this->totalNilai($row);
            $totalTerbaik = $this->totalNilai($terbaik);

            if ($total > $totalTerbaik) {
                return $row;
            }

            if ($total === $totalTerbaik
                && (int) $row->id_jadwal === $indukId
                && (int) $terbaik->id_jadwal !== $indukId) {
                return $row;
            }

            return $terbaik;
        });
    }

    private function totalNilai(Nilai $skor): float
    {
        return round(
            (float) $skor->pg_nilai + (float) $skor->kompleks_nilai + (float) $skor->jodohkan_nilai
            + (float) $skor->isian_nilai + (float) $skor->essai_nilai,
            2
        );
    }

    private function jadwalQuery($user, $tp, $smt)
    {
        // Hasil remedial bukan ujian terpisah: nilainya sudah diperhitungkan
        // sebagai nilai terbaik pada jadwal induk, jadi dropdown hanya memuat induk.
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
