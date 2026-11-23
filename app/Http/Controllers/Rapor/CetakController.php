<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Ekstra;
use App\Models\Kelas;
use App\Models\KelasEkstra;
use App\Models\Mapel;
use App\Models\RaporCatatanWali;
use App\Models\RaporFisik;
use App\Models\RaporNaik;
use App\Models\RaporNilaiAkhir;
use App\Models\RaporNilaiEkstra;
use App\Models\RaporNilaiHarian;
use App\Models\RaporNilaiPts;
use App\Models\RaporNilaiSikap;
use App\Models\RaporPrestasi;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\Rapor;
use App\Support\SchoolLogo;
use App\Support\VerifikasiDokumen;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CetakController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        $query = Kelas::with('waliKelas:id_guru,nama_guru')
            ->withCount(['siswa as siswa_count' => fn ($q) => $q->where('kelas_siswa.id_tp', $tp->id_tp)->where('kelas_siswa.id_smt', $smt->id_smt)])
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas');

        if (! $user->isAdmin()) {
            $query->where('guru_id', $guruId ?? 0);
        }

        $kelas = $query->get()->map(fn (Kelas $row) => [
            'id_kelas' => $row->id_kelas,
            'nama_kelas' => $row->nama_kelas,
            'wali' => $row->waliKelas?->nama_guru,
            'siswa_count' => $row->siswa_count,
        ]);

        return Inertia::render('Rapor/Cetak', [
            'kelas' => $kelas,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'isAdmin' => $user->isAdmin(),
            'settingAda' => (bool) Rapor::setting($tp->id_tp, $smt->id_smt),
        ]);
    }

    public function siswa(Request $request, int $kelas): Response
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);

        $kelasModel = Kelas::with('waliKelas:id_guru,nama_guru')->findOrFail($kelas);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $counts = RaporNilaiAkhir::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereNotNull('akhir')
            ->selectRaw('id_siswa, COUNT(*) as total')
            ->groupBy('id_siswa')
            ->pluck('total', 'id_siswa');

        $rankings = RaporPrestasi::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('ranking', 'id_siswa');

        $rows = collect($siswas)->map(fn (array $siswa) => [
            'id_siswa' => $siswa['id_siswa'],
            'nis' => $siswa['nis'],
            'nisn' => $siswa['nisn'],
            'nama' => $siswa['nama'],
            'jumlah_nilai' => (int) ($counts[$siswa['id_siswa']] ?? 0),
            'ranking' => (int) ($rankings[$siswa['id_siswa']] ?? 0),
        ]);

        return Inertia::render('Rapor/CetakSiswa', [
            'kelas' => [
                'id_kelas' => $kelasModel->id_kelas,
                'nama_kelas' => $kelasModel->nama_kelas,
                'wali' => $kelasModel->waliKelas?->nama_guru,
            ],
            'siswas' => $rows,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'settingAda' => (bool) Rapor::setting($tp->id_tp, $smt->id_smt),
        ]);
    }

    public function rapor(Request $request, int $kelas, int $siswa): Response
    {
        return Inertia::render('Rapor/PrintRapor', $this->raporData($request, $kelas, $siswa));
    }

    public function raporPdf(Request $request, int $kelas, int $siswa)
    {
        $data = $this->raporData($request, $kelas, $siswa) + $this->logoFiles();

        $html = view('pdf.rapor', $data)->render();
        $suffix = $data['jenis'] === 'pts' ? '-pts' : '';
        $nama = preg_replace('/[^A-Za-z0-9._-]+/', '-', 'rapor'.$suffix.'-'.$data['siswa']['nis'].'-'.$data['siswa']['nama']);

        return $this->pdf($html, $nama.'.pdf', 'portrait');
    }

    private function raporData(Request $request, int $kelas, int $siswa): array
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);

        $siswaModel = Siswa::findOrFail($siswa);
        $this->authorizeSiswa($kelas, $siswa, $tp->id_tp, $smt->id_smt);

        $kelasModel = Kelas::with(['waliKelas:id_guru,nama_guru,nip', 'level:id_level,level'])->findOrFail($kelas);
        $school = Setting::first();
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $isFinalLevel = in_array((string) $kelasModel->level?->level, ['6', '9', '12'], true);

        $tanggal = $this->tanggal($setting?->tgl_rapor_akhir);
        if ($isFinalLevel) {
            $tanggal = $this->tanggal($setting?->tgl_rapor_kelas_akhir) ?: $tanggal;
        }

        $wali = $kelasModel->waliKelas;
        $waliNip = $setting && (string) $setting->nip_walikelas === '1' ? $wali?->nip : '';
        $kepsekNip = $setting && (string) $setting->nip_kepsek === '1' ? $school?->nip : '';

        $mode = $request->query('jenis') === 'pts' ? 'pts' : 'akhir';

        $harian = RaporNilaiHarian::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_mapel');

        $akhir = RaporNilaiAkhir::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_mapel');

        $pts = RaporNilaiPts::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_mapel');

        $mapels = Mapel::orderBy('urutan')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel']);

        $nilai = [];

        foreach ($mapels as $mapel) {
            $h = $harian[$mapel->id_mapel] ?? null;
            $a = $akhir[$mapel->id_mapel] ?? null;
            $p = $pts[$mapel->id_mapel] ?? null;

            if ($mode === 'pts') {
                if (! $p) {
                    continue;
                }

                $config = Rapor::kkm((int) $mapel->id_mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

                $nilai[] = [
                    'id_mapel' => (int) $mapel->id_mapel,
                    'nama_mapel' => $mapel->nama_mapel,
                    'kkm' => $config['kkm'],
                    'nilai' => $p->nilai,
                    'predikat' => $p->predikat ?? '',
                ];

                continue;
            }

            if (! $h && ! $a) {
                continue;
            }

            $config = Rapor::kkm((int) $mapel->id_mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

            $nilai[] = [
                'id_mapel' => (int) $mapel->id_mapel,
                'nama_mapel' => $mapel->nama_mapel,
                'kkm' => $config['kkm'],
                'p_nilai' => $h?->p_rata_rata,
                'p_predikat' => $h?->p_predikat ?? '',
                'p_deskripsi' => $h?->p_deskripsi ?? '',
                'k_nilai' => $h?->k_rata_rata,
                'k_predikat' => $h?->k_predikat ?? '',
                'k_deskripsi' => $h?->k_deskripsi ?? '',
                'akhir' => $a?->akhir,
                'predikat' => $a?->predikat ?? '',
            ];
        }

        $sikap = [];

        foreach (RaporNilaiSikap::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get() as $row) {
            $nilaiSikap = Rapor::unserialize($row->nilai);
            $sikap[(int) $row->jenis] = [
                'predikat' => $nilaiSikap['predikat'] ?? '',
                'deskripsi' => $row->deskripsi ?? '',
            ];
        }

        $absensi = ['s' => 0, 'i' => 0, 'a' => 0];
        $catatan = '';
        $catatanRow = RaporCatatanWali::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->first();

        if ($catatanRow) {
            $nilaiCatatan = Rapor::unserialize($catatanRow->nilai);
            $absensi = [
                's' => (int) ($nilaiCatatan['s'] ?? 0),
                'i' => (int) ($nilaiCatatan['i'] ?? 0),
                'a' => (int) ($nilaiCatatan['a'] ?? 0),
            ];
            $catatan = (string) ($catatanRow->deskripsi ?? '');
        }

        $prestasiRow = RaporPrestasi::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->first();

        $prestasi = [
            'ranking' => $prestasiRow?->ranking ? (int) $prestasiRow->ranking : null,
            'items' => [],
        ];

        if ($prestasiRow) {
            foreach ([1, 2, 3] as $index) {
                $jenis = $prestasiRow->{'p'.$index};
                $desk = $prestasiRow->{'p'.$index.'_desk'};

                if (trim((string) $jenis) !== '' || trim((string) $desk) !== '') {
                    $prestasi['items'][] = ['jenis' => (string) $jenis, 'deskripsi' => (string) $desk];
                }
            }
        }

        $ekstraIds = [];

        foreach (KelasEkstra::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            foreach (Rapor::unserialize($row->ekstra) as $item) {
                $item = (array) $item;
                if (isset($item['ekstra'])) {
                    $ekstraIds[] = (int) $item['ekstra'];
                }
            }
        }

        $nilaiEkstra = RaporNilaiEkstra::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_ekstra');

        $ekstraQuery = $ekstraIds
            ? Ekstra::whereIn('id_ekstra', array_unique($ekstraIds))
            : Ekstra::query();

        $ekstra = $ekstraQuery->orderBy('nama_ekstra')->get(['id_ekstra', 'nama_ekstra'])->map(function (Ekstra $row) use ($nilaiEkstra) {
            $val = $nilaiEkstra[$row->id_ekstra] ?? null;

            return [
                'id_ekstra' => (int) $row->id_ekstra,
                'nama_ekstra' => $row->nama_ekstra,
                'nilai' => $val?->nilai,
                'predikat' => $val?->predikat ?? '',
                'deskripsi' => $val?->deskripsi ?? '',
            ];
        });

        $fisikRow = RaporFisik::where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->first();

        $fisik = null;

        if ($fisikRow) {
            $kondisi = Rapor::unserialize($fisikRow->kondisi);
            $fisik = [
                'tinggi' => (int) ($fisikRow->tinggi ?? 0),
                'berat' => (int) ($fisikRow->berat ?? 0),
                'telinga' => $kondisi['telinga'] ?? '',
                'mata' => $kondisi['mata'] ?? '',
                'gigi' => $kondisi['gigi'] ?? '',
                'lain' => $kondisi['lain'] ?? '',
            ];
        }

        $naik = RaporNaik::where('id_siswa', $siswa)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->value('naik');

        $kode = VerifikasiDokumen::terbitkan('rapor', implode('-', [
            (string) $siswa,
            (string) $kelas,
            (string) $tp->id_tp,
            (string) $smt->id_smt,
            $mode,
        ]), [
            'siswa' => (string) $siswaModel->nama,
            'nis' => (string) $siswaModel->nis,
            'kelas' => (string) $kelasModel->nama_kelas,
            'tahun' => (string) $tp->tahun,
            'semester' => (string) ($smt->nama_smt ?: $smt->smt),
            'jenis' => $mode === 'pts' ? 'Penilaian Tengah Semester' : 'Akhir Semester',
        ]);

        return [
            'siswa' => [
                'id_siswa' => $siswaModel->id_siswa,
                'nis' => $siswaModel->nis,
                'nisn' => $siswaModel->nisn,
                'nama' => $siswaModel->nama,
                'jenis_kelamin' => $siswaModel->jenis_kelamin,
                'tempat_lahir' => $siswaModel->tempat_lahir,
                'tanggal_lahir' => $this->tanggal($siswaModel->tanggal_lahir),
                'agama' => $siswaModel->agama,
                'alamat' => $siswaModel->alamat,
                'foto' => $siswaModel->foto ? '/storage/'.$siswaModel->foto : null,
                'nama_ayah' => $siswaModel->nama_ayah,
                'nama_ibu' => $siswaModel->nama_ibu,
                'nama_wali' => $siswaModel->nama_wali,
            ],
            'kelas' => [
                'id_kelas' => $kelasModel->id_kelas,
                'nama_kelas' => $kelasModel->nama_kelas,
                'wali' => $wali?->nama_guru,
                'wali_nip' => $waliNip,
                'akhir' => $isFinalLevel,
            ],
            'sekolah' => [
                'nama' => $school?->sekolah,
                'npsn' => $school?->npsn,
                'nss' => $school?->nss,
                'alamat' => $school?->alamat,
                'desa' => $school?->desa,
                'kecamatan' => $school?->kecamatan,
                'kota' => $school?->kota,
                'provinsi' => $school?->provinsi,
                'kepsek' => $school?->kepsek,
                'nip' => $kepsekNip,
                'jenjang' => $this->jenjangLabel($school?->jenjang, $school?->satuan_pendidikan),
            ],
            'tahun' => $tp->tahun,
            'semester' => $smt->nama_smt ?: $smt->smt,
            'tanggal' => $tanggal,
            'jenis' => $mode,
            'judul' => $mode === 'pts' ? 'Laporan Hasil Penilaian Tengah Semester' : 'Laporan Hasil Belajar',
            'nilai' => $nilai,
            'p_nilai' => $setting && (int) $setting->kkm_tunggal === 1 ? (int) $setting->kkm : null,
            'sikap' => $sikap,
            'absensi' => $absensi,
            'catatan' => $catatan,
            'prestasi' => $prestasi,
            'ekstra' => $ekstra,
            'fisik' => $fisik,
            'naik' => $naik === null ? null : (int) $naik,
            'siap' => (bool) $setting,
            'verifikasi' => VerifikasiDokumen::qr($kode, 80),
            'logoKiri' => SchoolLogo::url($school?->logo_kiri),
            'logoKanan' => SchoolLogo::url($school?->logo_kanan),
        ];
    }

    public function leger(Request $request, int $kelas): Response
    {
        return Inertia::render('Rapor/PrintLeger', $this->legerData($request, $kelas));
    }

    public function legerPdf(Request $request, int $kelas)
    {
        $data = $this->legerData($request, $kelas) + $this->logoFiles();

        $html = view('pdf.leger', $data)->render();
        $nama = preg_replace('/[^A-Za-z0-9._-]+/', '-', 'leger-'.($data['kelas']['nama_kelas'] ?? ''));

        return $this->pdf($html, $nama.'.pdf', 'landscape');
    }

    private function legerData(Request $request, int $kelas): array
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);

        $kelasModel = Kelas::with('waliKelas:id_guru,nama_guru')->findOrFail($kelas);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);

        $mapels = Mapel::orderBy('urutan')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel']);

        $akhir = RaporNilaiAkhir::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get();

        $bySiswa = $akhir->groupBy('id_siswa');
        $mapelIds = $akhir->pluck('id_mapel')->unique()->all();

        $mapelRows = $mapels->filter(fn (Mapel $mapel) => in_array($mapel->id_mapel, $mapelIds, true))
            ->map(fn (Mapel $mapel) => [
                'id_mapel' => (int) $mapel->id_mapel,
                'nama_mapel' => $mapel->nama_mapel,
                'kkm' => Rapor::kkm((int) $mapel->id_mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting)['kkm'],
            ])
            ->values();

        $sikap = [];

        foreach (RaporNilaiSikap::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $sikap[$row->id_siswa][(int) $row->jenis] = $nilai['predikat'] ?? '';
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

        $naikMap = RaporNaik::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereIn('id_siswa', collect($siswas)->pluck('id_siswa'))
            ->pluck('naik', 'id_siswa')
            ->all();

        $rankings = RaporPrestasi::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('ranking', 'id_siswa');

        $rows = collect($siswas)->map(function (array $siswa) use ($bySiswa, $mapelRows, $sikap, $absensi, $naikMap, $rankings) {
            $nilaiSiswa = collect($bySiswa[$siswa['id_siswa']] ?? [])->pluck('akhir', 'id_mapel');
            $nilai = [];

            foreach ($mapelRows as $mapel) {
                $nilai[$mapel['id_mapel']] = $nilaiSiswa[$mapel['id_mapel']] ?? null;
            }

            $terisi = collect($nilai)->filter(fn ($value) => $value !== null && $value !== '');
            $naik = $naikMap[$siswa['id_siswa']] ?? null;

            return [
                'id_siswa' => $siswa['id_siswa'],
                'nis' => $siswa['nis'],
                'nisn' => $siswa['nisn'],
                'nama' => $siswa['nama'],
                'nilai' => $nilai,
                'rata_rata' => $terisi->count() ? round($terisi->avg(), 2) : null,
                'ranking' => (int) ($rankings[$siswa['id_siswa']] ?? 0),
                'sikap_spiritual' => $sikap[$siswa['id_siswa']][1] ?? '',
                'sikap_sosial' => $sikap[$siswa['id_siswa']][2] ?? '',
                'absensi' => $absensi[$siswa['id_siswa']] ?? ['s' => 0, 'i' => 0, 'a' => 0],
                'naik' => $naik === null ? null : (int) $naik,
            ];
        });

        return [
            'kelas' => [
                'id_kelas' => $kelasModel->id_kelas,
                'nama_kelas' => $kelasModel->nama_kelas,
                'wali' => $kelasModel->waliKelas?->nama_guru,
            ],
            'mapels' => $mapelRows,
            'rows' => $rows,
            'tahun' => $tp->tahun,
            'semester' => $smt->nama_smt ?: $smt->smt,
            'sekolah' => ($school = Setting::first())?->sekolah,
            'tanggal' => $this->tanggal($setting?->tgl_rapor_akhir),
            'kota' => $school?->kota,
            'logoKiri' => SchoolLogo::url($school?->logo_kiri),
            'logoKanan' => SchoolLogo::url($school?->logo_kanan),
        ];
    }

    /**
     * Logo sebagai data URI, hanya untuk dokumen PDF.
     *
     * @return array{logoKiriData: string|null, logoKananData: string|null}
     */
    private function logoFiles(): array
    {
        $setting = Setting::first();

        return [
            'logoKiriData' => SchoolLogo::dataUrl($setting?->logo_kiri),
            'logoKananData' => SchoolLogo::dataUrl($setting?->logo_kanan),
        ];
    }

    private function pdf(string $html, string $filename, string $orientation = 'portrait')
    {
        $pdf = new Dompdf(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', $orientation);
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function authorizeKelas(Request $request, int $kelas, int $tp, int $smt): void
    {
        $guruId = Rapor::guruId($request->user());

        abort_unless(Rapor::hasKelasAccess($request->user(), $guruId, $kelas, $tp, $smt), 403);
    }

    private function authorizeSiswa(int $kelas, int $siswa, int $tp, int $smt): void
    {
        $exists = DB::table('kelas_siswa')
            ->where('id_kelas', $kelas)
            ->where('id_siswa', $siswa)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->exists();

        abort_unless($exists, 404);
    }

    private function students(int $kelas, int $tp, int $smt): array
    {
        return DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_kelas', $kelas)
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->orderBy('master_siswa.nama')
            ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nisn', 'master_siswa.nama'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function jenjangLabel($jenjang, $satuan): string
    {
        $madrasah = (string) $satuan === '2';

        return match ((int) $jenjang) {
            1 => $madrasah ? 'MADRASAH IBTIDAIYAH (MI)' : 'SEKOLAH DASAR (SD)',
            2 => $madrasah ? 'MADRASAH TSANAWIYAH (MTS)' : 'SEKOLAH MENENGAH PERTAMA (SMP)',
            default => $madrasah ? 'MADRASAH ALIYAH (MA)' : 'SEKOLAH MENENGAH ATAS (SMA)',
        };
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
