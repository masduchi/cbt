<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\JenisUjian;
use App\Models\NomorPeserta;
use App\Models\Setting;
use App\Support\QrCode;
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
    public function kartu(Request $request, int $jadwal): Response
    {
        return Inertia::render('Cbt/Cetak/Kartu', $this->kartuData($request, $jadwal));
    }

    public function kartuPdf(Request $request, int $jadwal)
    {
        $data = $this->kartuData($request, $jadwal) + $this->logoFiles();

        $html = view('pdf.kartu', $data)->render();

        return $this->pdf($html, 'kartu-peserta-'.$jadwal.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function kartuData(Request $request, int $jadwal): array
    {
        [$tp, $smt] = $this->period();
        $model = $this->jadwal($request, $jadwal, $tp->id_tp, $smt->id_smt);
        $peserta = $this->peserta($model, $tp->id_tp, $smt->id_smt);
        $school = Setting::first();

        $cari = trim((string) $request->query('q'));
        $pilih = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->query('pilih', [])
        ))));

        if ($cari !== '') {
            $needle = mb_strtolower($cari);

            $peserta = array_values(array_filter($peserta, fn (array $row) => str_contains(mb_strtolower((string) $row['nama']), $needle)
                || str_contains(mb_strtolower((string) $row['nis']), $needle)
                || str_contains(mb_strtolower((string) $row['nisn']), $needle)));
        }

        if ($pilih !== []) {
            $peserta = array_values(array_filter($peserta, fn (array $row) => in_array((int) $row['id_siswa'], $pilih, true)));
        }

        $ujian = $this->ujian($model);

        // Satu kartu per siswa memakai kode verifikasi sendiri agar QR di kartu
        // bisa dipindai di pintu ruang dan langsung dikenali siswanya.
        $peserta = array_map(function (array $row) use ($model, $ujian): array {
            $kode = VerifikasiDokumen::terbitkan('kartu-peserta-siswa', implode('-', [
                (string) $model->id_jadwal,
                (string) $row['id_siswa'],
            ]), [
                'siswa' => (string) $row['nama'],
                'nis' => (string) $row['nis'],
                'kelas' => (string) $row['kelas'],
                'ujian' => (string) ($ujian['mapel'] ?? ''),
                'jenis' => (string) $ujian['jenis'],
                'tanggal' => (string) $ujian['tanggal'],
            ]);

            $row['verifikasi'] = VerifikasiDokumen::qr($kode);

            return $row;
        }, $peserta);

        return [
            'ujian' => $ujian,
            'peserta' => $peserta,
            'kop' => $this->kop('cbt_kop_kartu', 'id_set_kartu', ['header_1', 'header_2', 'header_3', 'header_4', 'tanggal']),
            'sekolah' => $school?->sekolah,
            'alamat' => trim(($school?->alamat ?? '').' '.($school?->kota ?? '')),
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'filters' => ['q' => $cari, 'pilih' => $pilih],
        ];
    }

    /**
     * Satu kartu per siswa untuk seluruh mapel pada periode (dan jenis) yang
     * dipilih, agar siswa tidak membawa satu kartu terpisah per mata pelajaran.
     */
    public function kartuGlobal(Request $request): Response
    {
        return Inertia::render('Cbt/Cetak/KartuGlobal', $this->kartuGlobalData($request));
    }

    public function kartuGlobalPdf(Request $request)
    {
        $data = $this->kartuGlobalData($request);
        $data['kartu'] = collect($data['kartu'])->map(function (array $siswa): array {
            if (! empty($siswa['verifikasi']['url'])) {
                $siswa['verifikasi']['png'] = 'data:image/png;base64,'.base64_encode(QrCode::png($siswa['verifikasi']['url']));
            }

            return $siswa;
        })->all();
        $data += $this->logoFiles();

        $html = view('pdf.kartu-global', $data)->render();

        return $this->pdf($html, 'kartu-ujian-global.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function kartuGlobalData(Request $request): array
    {
        // Kartu lintas kelas memuat data seluruh siswa, jadi hanya admin.
        abort_unless($request->user()->isAdmin(), 403);

        [$tp, $smt] = $this->period();
        $school = Setting::first();

        $kelasId = (int) $request->query('kelas');
        $jenisId = (int) $request->query('jenis');

        $jadwal = Jadwal::with([
            'bank:id_bank,bank_nama,bank_kelas,bank_mapel_id',
            'bank.mapel:id_mapel,nama_mapel',
            'jenis:id_jenis,nama_jenis',
        ])
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->when($jenisId > 0, fn ($query) => $query->where('id_jenis', $jenisId))
            ->orderBy('tgl_mulai')
            ->orderBy('id_jadwal')
            ->get();

        $perKelas = [];

        foreach ($jadwal as $row) {
            $kelasBank = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) $row->bank?->bank_kelas)
            )));

            foreach ($kelasBank as $idKelas) {
                $perKelas[$idKelas][] = [
                    'mapel' => (string) ($row->bank?->mapel?->nama_mapel ?? $row->bank?->bank_nama ?? 'Ujian'),
                    'jenis' => (string) ($row->jenis?->nama_jenis ?? ''),
                    'tanggal' => $this->tanggal($row->tgl_mulai),
                    'mulai' => substr((string) $row->tgl_mulai, 11, 5),
                    'selesai' => substr((string) $row->tgl_selesai, 11, 5),
                    'sort' => (string) $row->tgl_mulai,
                ];
            }
        }

        $ruang = DB::table('cbt_kelas_ruang')
            ->leftJoin('cbt_ruang', 'cbt_ruang.id_ruang', '=', 'cbt_kelas_ruang.id_ruang')
            ->leftJoin('cbt_sesi', 'cbt_sesi.id_sesi', '=', 'cbt_kelas_ruang.id_sesi')
            ->where('cbt_kelas_ruang.id_tp', $tp->id_tp)
            ->where('cbt_kelas_ruang.id_smt', $smt->id_smt)
            ->get(['cbt_kelas_ruang.id_kelas', 'cbt_ruang.nama_ruang', 'cbt_sesi.nama_sesi'])
            ->keyBy('id_kelas');

        $nomor = NomorPeserta::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('nomor_peserta', 'id_siswa');

        $siswa = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp->id_tp)
            ->where('kelas_siswa.id_smt', $smt->id_smt)
            ->when($kelasId > 0, fn ($query) => $query->where('kelas_siswa.id_kelas', $kelasId))
            ->orderBy('master_kelas.nama_kelas')
            ->orderBy('master_siswa.nama')
            ->get([
                'master_siswa.id_siswa',
                'master_siswa.nis',
                'master_siswa.nisn',
                'master_siswa.nama',
                'kelas_siswa.id_kelas',
                'master_kelas.nama_kelas',
            ]);

        $kartu = $siswa->groupBy('id_siswa')->map(function ($rows) use ($perKelas, $ruang, $nomor, $tp, $smt) {
            $first = $rows->first();
            $kelasIds = $rows->pluck('id_kelas')->map(fn ($id) => (int) $id)->unique();
            $namaKelas = (string) $rows->pluck('nama_kelas')->unique()->implode(', ');

            $ujian = $kelasIds
                ->flatMap(fn (int $id) => $perKelas[$id] ?? [])
                ->sortBy('sort')
                ->values()
                ->map(fn (array $row) => [
                    'mapel' => $row['mapel'],
                    'jenis' => $row['jenis'],
                    'tanggal' => $row['tanggal'],
                    'mulai' => $row['mulai'],
                    'selesai' => $row['selesai'],
                ]);

            $kode = VerifikasiDokumen::terbitkan('kartu-global', implode('-', [
                (string) $first->id_siswa,
                (string) $tp->id_tp,
                (string) $smt->id_smt,
            ]), [
                'siswa' => (string) $first->nama,
                'nis' => (string) $first->nis,
                'kelas' => $namaKelas,
                'tahun' => (string) $tp->tahun,
                'semester' => (string) ($smt->nama_smt ?: $smt->smt),
            ]);

            $lokasi = $ruang->get((int) $first->id_kelas);

            return [
                'id_siswa' => (int) $first->id_siswa,
                'nama' => (string) $first->nama,
                'nis' => (string) $first->nis,
                'nisn' => (string) $first->nisn,
                'kelas' => $namaKelas,
                'nomor_peserta' => $nomor->get((int) $first->id_siswa),
                'ruang' => $lokasi?->nama_ruang,
                'sesi' => $lokasi?->nama_sesi,
                'ujian' => $ujian->all(),
                'jumlah_mapel' => $ujian->count(),
                'verifikasi' => VerifikasiDokumen::qr($kode, 60),
            ];
        })->filter(fn (array $row) => $row['jumlah_mapel'] > 0)->values();

        return [
            'kartu' => $kartu,
            'filters' => ['kelas' => $kelasId ?: null, 'jenis' => $jenisId ?: null],
            'kelasOptions' => $this->kelasOptions((int) $tp->id_tp, (int) $smt->id_smt),
            'jenisOptions' => JenisUjian::orderBy('nama_jenis')
                ->get(['id_jenis', 'nama_jenis'])
                ->map(fn ($row) => ['id_jenis' => (int) $row->id_jenis, 'nama_jenis' => (string) $row->nama_jenis])
                ->values()
                ->all(),
            'kop' => $this->kop('cbt_kop_kartu', 'id_set_kartu', ['header_1', 'header_2', 'header_3', 'header_4', 'tanggal']),
            'logo' => SchoolLogo::url($school?->logo_kiri),
            'sekolah' => $school?->sekolah,
            'alamat' => trim(($school?->alamat ?? '').' '.($school?->kota ?? '')),
            'tahun' => $tp->tahun,
            'semester' => $smt->nama_smt ?: $smt->smt,
            'ringkasan' => [
                'siswa' => $kartu->count(),
                'mapel' => $jadwal->count(),
                // Nomor peserta hasil impor bisa tersimpan sebagai string kosong,
                // jadi string kosong dianggap sama dengan belum punya nomor.
                'tanpa_nomor' => $kartu->filter(fn (array $row) => trim((string) ($row['nomor_peserta'] ?? '')) === '')->count(),
                'tanpa_ruang' => $kartu->filter(fn (array $row) => ! $row['ruang'] || ! $row['sesi'])->count(),
            ],
        ];
    }

    public function hadir(Request $request, int $jadwal): Response
    {
        [$tp, $smt] = $this->period();
        $model = $this->jadwal($request, $jadwal, $tp->id_tp, $smt->id_smt);
        $peserta = $this->peserta($model, $tp->id_tp, $smt->id_smt);
        $school = Setting::first();

        return Inertia::render('Cbt/Cetak/Hadir', [
            'ujian' => $this->ujian($model),
            'peserta' => $peserta,
            'kop' => $this->kop('cbt_kop_absensi', 'id_kop', ['header_1', 'header_2', 'header_3', 'header_4', 'proktor', 'pengawas_1', 'pengawas_2']),
            'sekolah' => $school?->sekolah,
            'kota' => $school?->kota,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
        ]);
    }

    public function beritaAcara(Request $request, int $jadwal): Response
    {
        [$tp, $smt] = $this->period();
        $model = $this->jadwal($request, $jadwal, $tp->id_tp, $smt->id_smt);
        $peserta = $this->peserta($model, $tp->id_tp, $smt->id_smt);
        $school = Setting::first();

        $pengawasRows = DB::table('cbt_pengawas')
            ->leftJoin('cbt_ruang', 'cbt_ruang.id_ruang', '=', 'cbt_pengawas.id_ruang')
            ->leftJoin('cbt_sesi', 'cbt_sesi.id_sesi', '=', 'cbt_pengawas.id_sesi')
            ->where('cbt_pengawas.id_jadwal', (string) $model->id_jadwal)
            ->get(['cbt_pengawas.id_guru', 'cbt_ruang.nama_ruang', 'cbt_sesi.nama_sesi']);

        $guruNames = Guru::whereIn('id_guru', $pengawasRows
            ->flatMap(fn ($row) => array_filter(array_map('intval', explode(',', (string) $row->id_guru))))
            ->unique()
            ->values())
            ->pluck('nama_guru', 'id_guru');

        $pengawas = $pengawasRows
            ->flatMap(fn ($row) => collect(explode(',', (string) $row->id_guru))
                ->filter()
                ->map(fn ($id) => [
                    'nama' => $guruNames[(int) $id] ?? null,
                    'ruang' => $row->nama_ruang,
                    'sesi' => $row->nama_sesi,
                ]))
            ->filter(fn ($row) => $row['nama'])
            ->values()
            ->all();

        return Inertia::render('Cbt/Cetak/BeritaAcara', [
            'ujian' => $this->ujian($model),
            'peserta' => $peserta,
            'pengawas' => $pengawas,
            'kop' => $this->kop('cbt_kop_berita', 'id_kop', ['header_1', 'header_2', 'header_3', 'header_4']),
            'sekolah' => $school?->sekolah,
            'kota' => $school?->kota,
            'kepala_sekolah' => $school?->kepsek,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'tanggal' => $this->tanggal($model->tgl_mulai),
        ]);
    }

    private function jadwal(Request $request, int $id, int $tp, int $smt): Jadwal
    {
        $model = Jadwal::with(['bank:id_bank,bank_nama,bank_kode,bank_mapel_id,bank_kelas,bank_guru_id', 'bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->findOrFail($id);

        if (! $request->user()->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $request->user()->id)->value('id_guru') ?? 0);
            abort_unless((int) $model->bank?->bank_guru_id === $guruId, 403);
        }

        return $model;
    }

    private function peserta(Jadwal $model, int $tp, int $smt): array
    {
        $kelasIds = array_values(array_filter(array_map('intval', explode(',', (string) $model->bank?->bank_kelas))));

        $rows = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->leftJoin('cbt_nomor_peserta', function ($join) use ($tp, $smt) {
                $join->on('cbt_nomor_peserta.id_siswa', '=', 'master_siswa.id_siswa')
                    ->where('cbt_nomor_peserta.id_tp', $tp)
                    ->where('cbt_nomor_peserta.id_smt', $smt);
            })
            ->leftJoin('cbt_kelas_ruang', function ($join) use ($tp, $smt) {
                $join->on('cbt_kelas_ruang.id_kelas', '=', 'master_kelas.id_kelas')
                    ->where('cbt_kelas_ruang.id_tp', $tp)
                    ->where('cbt_kelas_ruang.id_smt', $smt);
            })
            ->leftJoin('cbt_ruang', 'cbt_ruang.id_ruang', '=', 'cbt_kelas_ruang.id_ruang')
            ->leftJoin('cbt_sesi', 'cbt_sesi.id_sesi', '=', 'cbt_kelas_ruang.id_sesi')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($kelasIds, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $kelasIds))
            ->orderBy('master_kelas.nama_kelas')
            ->orderBy('master_siswa.nama')
            ->get([
                'master_siswa.id_siswa',
                'master_siswa.nis',
                'master_siswa.nisn',
                'master_siswa.nama',
                'master_siswa.tempat_lahir',
                'master_siswa.tanggal_lahir',
                'master_kelas.nama_kelas',
                'cbt_nomor_peserta.nomor_peserta',
                'cbt_ruang.nama_ruang',
                'cbt_sesi.nama_sesi',
            ]);

        $nilai = DB::table('cbt_nilai')->where('id_jadwal', $model->id_jadwal)->pluck('id_siswa')->flip();
        $durasi = DB::table('cbt_durasi_siswa')->where('id_jadwal', $model->id_jadwal)->get()->keyBy('id_siswa');

        return $rows->map(fn ($row) => [
            'id_siswa' => $row->id_siswa,
            'nis' => $row->nis,
            'nisn' => $row->nisn,
            'nama' => $row->nama,
            'kelas' => $row->nama_kelas,
            'tempat_lahir' => $row->tempat_lahir,
            'tanggal_lahir' => $this->tanggal($row->tanggal_lahir),
            'nomor_peserta' => $row->nomor_peserta,
            'ruang' => $row->nama_ruang,
            'sesi' => $row->nama_sesi,
            'hadir' => $nilai->has($row->id_siswa) || (($durasi[$row->id_siswa] ?? null)?->status ?? 0) > 0,
        ])->values()->all();
    }

    private function ujian(Jadwal $model): array
    {
        return [
            'id_jadwal' => $model->id_jadwal,
            'mapel' => $model->bank?->mapel?->nama_mapel,
            'bank' => $model->bank?->bank_nama,
            'bank_kode' => $model->bank?->bank_kode,
            'jenis' => $model->jenis?->nama_jenis,
            'tanggal' => $this->tanggal($model->tgl_mulai),
            'mulai' => substr((string) $model->tgl_mulai, 11, 5),
            'selesai' => substr((string) $model->tgl_selesai, 11, 5),
            'durasi' => (int) $model->durasi_ujian,
        ];
    }

    /**
     * @return array<int, array{id_kelas: int, nama_kelas: string}>
     */
    private function kelasOptions(int $tp, int $smt): array
    {
        return DB::table('master_kelas')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas'])
            ->map(fn ($row) => ['id_kelas' => (int) $row->id_kelas, 'nama_kelas' => (string) $row->nama_kelas])
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, string>|null
     */
    private function kop(string $table, string $key, array $columns): ?array
    {
        $row = DB::table($table)->where($key, 1)->first();

        if (! $row) {
            return null;
        }

        $data = [];

        foreach ($columns as $column) {
            $value = trim((string) ($row->{$column} ?? ''));

            if ($value !== '') {
                $data[$column] = $value;
            }
        }

        return $data === [] ? null : $data;
    }

    /**
     * Logo sebagai data URI agar dapat disematkan langsung pada PDF Dompdf,
     * sama seperti rapor.
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

    private function pdf(string $html, string $filename)
    {
        $pdf = new Dompdf(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function tanggal(?string $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse(substr($value, 0, 10))->locale('id')->translatedFormat('d F Y');
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
