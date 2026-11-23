<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Ekstra;
use App\Models\Kelas;
use App\Models\KelasEkstra;
use App\Models\Mapel;
use App\Models\RaporCatatanWali;
use App\Models\RaporNaik;
use App\Models\RaporNilaiAkhir;
use App\Models\RaporNilaiEkstra;
use App\Models\RaporNilaiSikap;
use App\Models\RaporPrestasi;
use App\Models\Setting;
use App\Support\Rapor;
use App\Support\SchoolLogo;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DknController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        $classes = Kelas::with('waliKelas:id_guru,nama_guru')
            ->withCount(['siswa as siswa_count' => fn ($q) => $q->where('kelas_siswa.id_tp', $tp->id_tp)->where('kelas_siswa.id_smt', $smt->id_smt)])
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas')
            ->get()
            ->filter(fn (Kelas $kelas) => Rapor::hasKelasAccess($user, $guruId, $kelas->id_kelas, $tp->id_tp, $smt->id_smt))
            ->map(fn (Kelas $kelas) => [
                'id_kelas' => $kelas->id_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'wali' => $kelas->waliKelas?->nama_guru,
                'siswa_count' => $kelas->siswa_count,
            ])
            ->values();

        return Inertia::render('Rapor/DknIndex', [
            'classes' => $classes,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'settingAda' => (bool) Rapor::setting($tp->id_tp, $smt->id_smt),
        ]);
    }

    public function show(Request $request, int $kelas): Response
    {
        return Inertia::render('Rapor/Dkn', $this->data($request, $kelas));
    }

    public function pdf(Request $request, int $kelas)
    {
        $setting = Setting::first();
        $data = $this->data($request, $kelas) + [
            'logoKiriData' => SchoolLogo::dataUrl($setting?->logo_kiri),
            'logoKananData' => SchoolLogo::dataUrl($setting?->logo_kanan),
        ];

        $html = view('pdf.dkn', $data)->render();
        $nama = preg_replace('/[^A-Za-z0-9._-]+/', '-', 'dkn-'.$data['kelas']['nama_kelas']);

        $pdf = new Dompdf(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'.pdf"',
        ]);
    }

    private function data(Request $request, int $kelas): array
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();

        abort_unless(Rapor::hasKelasAccess($user, Rapor::guruId($user), $kelas, $tp->id_tp, $smt->id_smt), 403);

        $kelasModel = Kelas::with('waliKelas:id_guru,nama_guru')->findOrFail($kelas);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);

        $akhir = RaporNilaiAkhir::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get();

        $nilaiBySiswa = $akhir->groupBy('id_siswa');
        $mapelIds = $akhir->pluck('id_mapel')->map(fn ($id) => (int) $id)->unique()->all();

        $mapelRows = Mapel::whereIn('id_mapel', $mapelIds)
            ->orderBy('urutan')
            ->orderBy('nama_mapel')
            ->get(['id_mapel', 'nama_mapel', 'kelompok'])
            ->map(fn (Mapel $mapel) => [
                'id_mapel' => (int) $mapel->id_mapel,
                'nama_mapel' => $mapel->nama_mapel,
                'kkm' => Rapor::kkm((int) $mapel->id_mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting)['kkm'],
                'mulok' => strtoupper((string) $mapel->kelompok) === 'MULOK',
            ]);

        $ekstraRows = $this->ekstra($kelas, $tp->id_tp, $smt->id_smt);
        $nilaiEkstra = RaporNilaiEkstra::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->groupBy('id_siswa');

        $sikap = [];

        foreach (RaporNilaiSikap::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $sikap[$row->id_siswa][(int) $row->jenis] = (string) ($nilai['predikat'] ?? '');
        }

        $absensi = [];

        foreach (RaporCatatanWali::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $absensi[$row->id_siswa] = [
                's' => (int) ($nilai['s'] ?? 0),
                'i' => (int) ($nilai['i'] ?? 0),
                'a' => (int) ($nilai['a'] ?? 0),
            ];
        }

        $rankings = RaporPrestasi::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('ranking', 'id_siswa');

        $naikMap = RaporNaik::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereIn('id_siswa', collect($siswas)->pluck('id_siswa'))
            ->pluck('naik', 'id_siswa');

        $rows = collect($siswas)->map(function (array $siswa) use ($nilaiBySiswa, $mapelRows, $ekstraRows, $nilaiEkstra, $sikap, $absensi, $rankings, $naikMap) {
            $perMapel = collect($nilaiBySiswa[$siswa['id_siswa']] ?? [])->keyBy('id_mapel');
            $nilai = [];
            $jumlah = 0;
            $terisi = 0;

            foreach ($mapelRows as $mapel) {
                $row = $perMapel[$mapel['id_mapel']] ?? null;
                $angka = $row?->akhir;

                if ($angka !== null && $angka !== '') {
                    $jumlah += (int) $angka;
                    $terisi++;
                }

                $nilai[$mapel['id_mapel']] = [
                    'nilai' => $angka,
                    'predikat' => $row?->predikat ?: Rapor::predikat($angka, $mapel['kkm']),
                ];
            }

            $perEkstra = collect($nilaiEkstra[$siswa['id_siswa']] ?? [])->keyBy('id_ekstra');
            $ekstra = [];

            foreach ($ekstraRows as $item) {
                $row = $perEkstra[$item['id_ekstra']] ?? null;
                $ekstra[$item['id_ekstra']] = [
                    'nilai' => $row?->nilai,
                    'predikat' => $row?->predikat ?? '',
                ];
            }

            return [
                'id_siswa' => $siswa['id_siswa'],
                'nis' => $siswa['nis'],
                'nisn' => $siswa['nisn'],
                'nama' => $siswa['nama'],
                'jenis_kelamin' => $siswa['jenis_kelamin'],
                'nilai' => $nilai,
                'ekstra' => $ekstra,
                'sikap_spiritual' => $sikap[$siswa['id_siswa']][1] ?? '',
                'sikap_sosial' => $sikap[$siswa['id_siswa']][2] ?? '',
                'absensi' => $absensi[$siswa['id_siswa']] ?? ['s' => 0, 'i' => 0, 'a' => 0],
                'jumlah' => $jumlah,
                'rata_rata' => $terisi ? round($jumlah / $terisi, 2) : null,
                'ranking' => (int) ($rankings[$siswa['id_siswa']] ?? 0),
                'naik' => isset($naikMap[$siswa['id_siswa']]) ? (int) $naikMap[$siswa['id_siswa']] : null,
            ];
        });

        $rows = $this->fillRankings($rows);

        return [
            'kelas' => [
                'id_kelas' => $kelasModel->id_kelas,
                'nama_kelas' => $kelasModel->nama_kelas,
                'wali' => $kelasModel->waliKelas?->nama_guru,
            ],
            'mapels' => [
                'reguler' => $mapelRows->where('mulok', false)->values()->all(),
                'mulok' => $mapelRows->where('mulok', true)->values()->all(),
            ],
            'ekstra' => $ekstraRows,
            'rows' => $rows,
            'tahun' => $tp->tahun,
            'semester' => $smt->nama_smt ?: $smt->smt,
            'sekolah' => ($school = Setting::first())?->sekolah,
            'kepsek' => $school?->kepsek,
            'kota' => $school?->kota,
            'tanggal' => $this->tanggal($setting?->tgl_rapor_akhir),
            'siap' => (bool) $setting,
            'logoKiri' => SchoolLogo::url($school?->logo_kiri),
            'logoKanan' => SchoolLogo::url($school?->logo_kanan),
        ];
    }

    private function fillRankings($rows)
    {
        if ($rows->contains(fn (array $row) => $row['ranking'] > 0)) {
            return $rows->values();
        }

        $sorted = $rows->filter(fn (array $row) => $row['rata_rata'] !== null)
            ->sortByDesc('rata_rata')
            ->values();

        $rank = [];

        foreach ($sorted as $index => $row) {
            $rank[$row['id_siswa']] = $index + 1;
        }

        return $rows->map(function (array $row) use ($rank) {
            $row['ranking'] = $rank[$row['id_siswa']] ?? 0;

            return $row;
        })->values();
    }

    private function ekstra(int $kelas, int $tp, int $smt): array
    {
        $ids = [];

        foreach (KelasEkstra::where('id_kelas', $kelas)->where('id_tp', $tp)->where('id_smt', $smt)->get() as $row) {
            foreach (Rapor::ekstraKelas($row->ekstra) as $item) {
                if (in_array($kelas, $item['kelas'], true)) {
                    $ids[] = $item['id_ekstra'];
                }
            }
        }

        if (! $ids) {
            $ids = RaporNilaiEkstra::where('id_kelas', $kelas)
                ->where('id_tp', $tp)
                ->where('id_smt', $smt)
                ->pluck('id_ekstra')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
        }

        if (! $ids) {
            return [];
        }

        return Ekstra::whereIn('id_ekstra', array_values(array_unique($ids)))
            ->orderBy('nama_ekstra')
            ->get(['id_ekstra', 'nama_ekstra'])
            ->map(fn (Ekstra $row) => [
                'id_ekstra' => (int) $row->id_ekstra,
                'nama_ekstra' => $row->nama_ekstra,
            ])
            ->all();
    }

    private function students(int $kelas, int $tp, int $smt): array
    {
        return DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_kelas', $kelas)
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->orderBy('master_siswa.nama')
            ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nisn', 'master_siswa.nama', 'master_siswa.jenis_kelamin'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function tanggal($value): string
    {
        if (! $value || (string) $value === '0000-00-00') {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function period(): array
    {
        [$tp, $smt] = Rapor::period();
        abort_if(! $tp || ! $smt, 409, 'Tahun pelajaran / semester aktif belum diatur.');

        return [$tp, $smt];
    }
}
