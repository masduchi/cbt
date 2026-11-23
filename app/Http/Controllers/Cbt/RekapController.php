<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Nilai;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\Period;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        [$columns, $rows] = $this->matrix($request->user(), $tp, $smt);

        return Inertia::render('Cbt/Rekap/Index', [
            'columns' => $columns,
            'rows' => $rows,
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$tp, $smt] = $this->activePeriod();
        [$columns, $rows] = $this->matrix($request->user(), $tp, $smt);

        $filename = 'rekap-nilai-'.str_replace(['/', ' '], '-', (string) $tp).'-'.$smt.'.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $header = ['No', 'NIS', 'Nama', 'Kelas'];
            foreach ($columns as $column) {
                $header[] = $column['label'];
            }
            $header[] = 'Rata-rata';
            fputcsv($handle, $header);

            foreach ($rows as $index => $row) {
                $line = [$index + 1, $row['nis'], $row['nama'], $row['kelas']];
                foreach ($columns as $column) {
                    $line[] = $row['nilai'][$column['id_jadwal']] ?? '';
                }
                $line[] = $row['rata_rata'] ?? '';
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        [$tp, $smt] = $this->activePeriod();
        [$columns, $rows] = $this->matrix($request->user(), $tp, $smt);

        $headers = ['No', 'NIS', 'Nama', 'Kelas'];
        foreach ($columns as $column) {
            $headers[] = $column['label'];
        }
        $headers[] = 'Rata-rata';

        $lines = [];

        foreach ($rows as $index => $row) {
            $line = [$index + 1, $row['nis'], $row['nama'], $row['kelas']];
            foreach ($columns as $column) {
                $line[] = $row['nilai'][$column['id_jadwal']] ?? null;
            }
            $line[] = $row['rata_rata'];
            $lines[] = $line;
        }

        $filename = 'rekap-nilai-'.str_replace(['/', ' '], '-', (string) $tp).'-'.$smt.'.xlsx';

        return XlsxExport::download($filename, $headers, $lines, 'Rekap Nilai');
    }

    private function matrix($user, $tp, $smt): array
    {
        $jadwals = $this->jadwalQuery($user, $tp, $smt)
            ->with(['bank:id_bank,bank_nama,bank_kode,bank_kelas,kkm', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->orderBy('tgl_mulai')
            ->get();

        $columns = $jadwals->map(fn (Jadwal $jadwal) => [
            'id_jadwal' => $jadwal->id_jadwal,
            'label' => trim(($jadwal->bank?->bank_nama ?? '-').' ('.($jadwal->jenis?->kode_jenis ?? '-').')'),
            'short' => (string) ($jadwal->bank?->bank_kode ?? '-'),
            'kkm' => (int) ($jadwal->bank?->kkm ?? 0),
        ])->values()->all();

        if ($jadwals->isEmpty()) {
            return [$columns, []];
        }

        // Sesi remedial tidak menjadi kolom sendiri. Nilainya dipakai sebagai
        // nilai terbaik pada kolom jadwal induk agar rekap tidak menggandakan
        // ujian dan rata-rata tidak bias oleh peserta yang mengulang.
        $anakPerInduk = [];

        foreach (Jadwal::whereIn('id_jadwal_induk', $jadwals->pluck('id_jadwal'))->get(['id_jadwal', 'id_jadwal_induk']) as $anak) {
            $anakPerInduk[(int) $anak->id_jadwal_induk][] = (int) $anak->id_jadwal;
        }

        $nilai = Nilai::whereIn('id_jadwal', $jadwals->pluck('id_jadwal')->merge(collect($anakPerInduk)->flatten()))
            ->get()
            ->groupBy('id_siswa')
            ->map(fn ($items) => $items->mapWithKeys(fn (Nilai $row) => [
                (int) $row->id_jadwal => round(
                    (float) $row->pg_nilai + (float) $row->kompleks_nilai + (float) $row->jodohkan_nilai
                    + (float) $row->isian_nilai + (float) $row->essai_nilai,
                    2
                ),
            ])->all());

        $kelasIds = $jadwals
            ->flatMap(fn (Jadwal $jadwal) => array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas))))
            ->unique()
            ->values()
            ->all();

        $kelasSiswa = DB::table('kelas_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($kelasIds, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $kelasIds))
            ->get(['kelas_siswa.id_siswa', 'master_kelas.nama_kelas'])
            ->keyBy('id_siswa');

        $ids = $kelasSiswa->keys()->merge($nilai->keys())->unique();

        $rows = Siswa::whereIn('id_siswa', $ids)
            ->orderBy('nama')
            ->get()
            ->map(function (Siswa $siswa) use ($nilai, $kelasSiswa, $columns, $anakPerInduk) {
                $scores = $nilai->get($siswa->id_siswa, []);
                $perJadwal = [];

                foreach ($columns as $column) {
                    $sesi = array_merge([(int) $column['id_jadwal']], $anakPerInduk[$column['id_jadwal']] ?? []);
                    $skor = array_values(array_filter(
                        array_map(fn ($id) => $scores[$id] ?? null, $sesi),
                        fn ($value) => $value !== null
                    ));

                    $perJadwal[$column['id_jadwal']] = $skor === [] ? null : max($skor);
                }

                $filled = collect($perJadwal)->filter(fn ($value) => $value !== null);

                return [
                    'id_siswa' => $siswa->id_siswa,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                    'kelas' => $kelasSiswa->get($siswa->id_siswa)?->nama_kelas ?? '',
                    'nilai' => $perJadwal,
                    'rata_rata' => $filled->isNotEmpty() ? round($filled->avg(), 2) : null,
                ];
            })
            ->values()
            ->all();

        return [$columns, $rows];
    }

    private function jadwalQuery($user, $tp, $smt)
    {
        $query = Jadwal::where('id_tp', $tp)->where('id_smt', $smt)->induk();

        if (! $user->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
            $query->whereHas('bank', fn ($bank) => $bank->where('bank_guru_id', $guruId));
        }

        return $query;
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
