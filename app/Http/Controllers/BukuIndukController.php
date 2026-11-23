<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Models\BukuInduk;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\AuditLog;
use App\Support\Period;
use App\Support\SchoolLogo;
use App\Support\VerifikasiDokumen;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BukuIndukController extends Controller
{
    use ResolvesPerPage;

    private const STATUS = [
        1 => 'Aktif',
        2 => 'Lulus',
        3 => 'Pindah',
        4 => 'Keluar',
    ];

    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $search = trim((string) $request->query('search'));
        $kelasId = (int) $request->query('kelas');

        $query = Siswa::query()
            ->with(['kelas' => fn ($q) => $tahun && $semester
                ? $q->wherePivot('id_tp', $tahun->id_tp)->wherePivot('id_smt', $semester->id_smt)
                : $q]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($kelasId > 0 && $tahun && $semester) {
            $query->whereHas('kelas', fn ($q) => $q->where('master_kelas.id_kelas', $kelasId)
                ->where('kelas_siswa.id_tp', $tahun->id_tp)
                ->where('kelas_siswa.id_smt', $semester->id_smt));
        }

        $paginated = $query->orderBy('nama')->paginate($this->perPage($request))->withQueryString();
        $records = BukuInduk::whereIn('id_siswa', $paginated->pluck('id_siswa'))->get()->keyBy('id_siswa');

        $paginated->through(function (Siswa $siswa) use ($records) {
            $record = $records->get($siswa->id_siswa);

            return [
                'id_siswa' => (int) $siswa->id_siswa,
                'nama' => (string) $siswa->nama,
                'nis' => (string) $siswa->nis,
                'nisn' => (string) $siswa->nisn,
                'jenis_kelamin' => (string) ($siswa->jenis_kelamin ?? ''),
                'kelas' => $siswa->kelas->pluck('nama_kelas')->join(', '),
                'status' => $record?->status !== null ? (int) $record->status : null,
                'status_label' => $record?->status !== null ? (self::STATUS[(int) $record->status] ?? '-') : 'Belum diisi',
                'tahun_lulus' => $record?->tahun_lulus ? (int) $record->tahun_lulus : null,
                'terisi' => $record !== null,
            ];
        });

        return Inertia::render('BukuInduk/Index', [
            'siswa' => $paginated,
            'filters' => ['search' => $search, 'kelas' => $kelasId ?: null],
            'kelasOptions' => $this->classes(),
            'stats' => [
                'total' => Siswa::count(),
                'terisi' => BukuInduk::whereIn('status', array_keys(self::STATUS))->count(),
                'kosong' => max(0, Siswa::count() - BukuInduk::count()),
            ],
        ]);
    }

    public function edit(Siswa $siswa): Response
    {
        $record = BukuInduk::find($siswa->id_siswa);
        [$tahun, $semester] = Period::current();

        $kelas = $tahun && $semester
            ? $siswa->kelas()
                ->wherePivot('id_tp', $tahun->id_tp)
                ->wherePivot('id_smt', $semester->id_smt)
                ->pluck('nama_kelas')
                ->join(', ')
            : '';

        return Inertia::render('BukuInduk/Form', [
            'siswa' => [
                'id_siswa' => (int) $siswa->id_siswa,
                'nama' => (string) $siswa->nama,
                'nis' => (string) $siswa->nis,
                'nisn' => (string) $siswa->nisn,
                'jenis_kelamin' => (string) ($siswa->jenis_kelamin ?? ''),
                'tempat_lahir' => (string) ($siswa->tempat_lahir ?? ''),
                'tanggal_lahir' => (string) ($siswa->tanggal_lahir ?? ''),
                'agama' => (string) ($siswa->agama ?? ''),
                'alamat' => (string) ($siswa->alamat ?? ''),
                'kelas' => $kelas,
                'nama_ayah' => (string) ($siswa->nama_ayah ?? ''),
                'pekerjaan_ayah' => (string) ($siswa->pekerjaan_ayah ?? ''),
                'nama_ibu' => (string) ($siswa->nama_ibu ?? ''),
                'pekerjaan_ibu' => (string) ($siswa->pekerjaan_ibu ?? ''),
                'nama_wali' => (string) ($siswa->nama_wali ?? ''),
                'pekerjaan_wali' => (string) ($siswa->pekerjaan_wali ?? ''),
            ],
            'buku' => $record ?? $this->emptyRecord($siswa),
            'statusOptions' => collect(self::STATUS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $this->validated($request);
        $data['uid'] = (string) $siswa->uid;

        BukuInduk::updateOrCreate(['id_siswa' => $siswa->id_siswa], $data);

        AuditLog::record('master.siswa.buku-induk', 'Buku induk #'.$siswa->id_siswa.' '.$siswa->nama.' disimpan');

        return redirect()->route('master.buku-induk.index')->with('success', 'Buku induk '.$siswa->nama.' disimpan.');
    }

    public function pdf(Siswa $siswa)
    {
        $record = BukuInduk::find($siswa->id_siswa) ?? $this->emptyRecord($siswa);
        [$tahun, $semester] = Period::current();

        $kelas = $tahun && $semester
            ? $siswa->kelas()
                ->wherePivot('id_tp', $tahun->id_tp)
                ->wherePivot('id_smt', $semester->id_smt)
                ->pluck('nama_kelas')
                ->join(', ')
            : '';

        $setting = Setting::first();

        $kode = VerifikasiDokumen::terbitkan('buku-induk', (string) $siswa->id_siswa, [
            'siswa' => (string) $siswa->nama,
            'nis' => (string) $siswa->nis,
            'kelas' => $kelas,
            'status' => self::STATUS[(int) ($record->status ?? 1)] ?? '-',
        ]);

        $html = view('pdf.buku-induk', [
            'siswa' => $siswa,
            'buku' => $record,
            'kelas' => $kelas,
            'statusLabel' => self::STATUS[(int) ($record->status ?? 1)] ?? '-',
            'setting' => $setting,
            'tanggal' => now()->translatedFormat('d F Y'),
            'verifikasi' => VerifikasiDokumen::qr($kode, 80),
            'logoKiriData' => SchoolLogo::dataUrl($setting?->logo_kiri),
            'logoKananData' => SchoolLogo::dataUrl($setting?->logo_kanan),
        ])->render();

        $pdf = new Dompdf(['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'buku-induk-'.$siswa->nis.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'rombel_awal' => ['nullable', 'string', 'max:50'],
            'nama_panggilan' => ['nullable', 'string', 'max:50'],
            'bahasa' => ['nullable', 'string', 'max:50'],
            'jml_saudara_kandung' => ['nullable', 'integer', 'min:0', 'max:20'],
            'jml_saudara_tiri' => ['nullable', 'integer', 'min:0', 'max:20'],
            'jml_saudara_angkat' => ['nullable', 'integer', 'min:0', 'max:20'],
            'yatim' => ['nullable', 'integer', 'in:0,1,2'],
            'tinggal_bersama' => ['nullable', 'string', 'in:1,2,3,4,5,6'],
            'jarak' => ['nullable', 'string', 'max:10'],
            'gol_darah' => ['nullable', 'string', 'max:4'],
            'penyakit' => ['nullable', 'string', 'max:2000'],
            'kelainan_fisik' => ['nullable', 'string', 'max:100'],
            'kegemaran' => ['nullable', 'string', 'max:2000'],
            'beasiswa' => ['nullable', 'string', 'max:2000'],
            'no_ijazah_sebelumnya' => ['nullable', 'string', 'max:50'],
            'tahun_lulus_sebelumnya' => ['nullable', 'string', 'max:10'],
            'pindahan_dari' => ['nullable', 'string', 'max:100'],
            'alasan_kepindahan' => ['nullable', 'string', 'max:200'],
            'agama_ayah' => ['nullable', 'string', 'max:20'],
            'tempat_lahir_ayah' => ['nullable', 'string', 'max:50'],
            'wn_ayah' => ['nullable', 'string', 'max:50'],
            'penghasilan_ayah' => ['nullable', 'string', 'max:50'],
            'hidup_meninggal_ayah' => ['nullable', 'string', 'max:50'],
            'agama_ibu' => ['nullable', 'string', 'max:50'],
            'tempat_lahir_ibu' => ['nullable', 'string', 'max:50'],
            'wn_ibu' => ['nullable', 'string', 'max:50'],
            'penghasilan_ibu' => ['nullable', 'string', 'max:50'],
            'hidup_meninggal_ibu' => ['nullable', 'string', 'max:50'],
            'tempat_lahir_wali' => ['nullable', 'string', 'max:50'],
            'agama_wali' => ['nullable', 'string', 'max:20'],
            'wn_wali' => ['nullable', 'string', 'max:50'],
            'penghasilan_wali' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'integer', 'in:1,2,3,4'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'no_ijazah' => ['nullable', 'string', 'max:50'],
            'kelas_akhir' => ['nullable', 'string', 'max:50'],
            'lanjut_ke' => ['nullable', 'string', 'max:50'],
            'pindah_ke' => ['nullable', 'string', 'max:100'],
            'alasan_pindah' => ['nullable', 'string', 'max:100'],
            'tgl_pindah' => ['nullable', 'date'],
            'bekerja_di' => ['nullable', 'string', 'max:100'],
            'catatan_penting' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['jml_saudara_kandung'] = (int) ($data['jml_saudara_kandung'] ?? 0);
        $data['jml_saudara_tiri'] = (int) ($data['jml_saudara_tiri'] ?? 0);
        $data['jml_saudara_angkat'] = (int) ($data['jml_saudara_angkat'] ?? 0);
        $data['yatim'] = (int) ($data['yatim'] ?? 0);
        $data['tinggal_bersama'] = (string) ($data['tinggal_bersama'] ?? '1');

        return $data;
    }

    private function emptyRecord(Siswa $siswa): BukuInduk
    {
        $record = new BukuInduk(['uid' => (string) $siswa->uid, 'status' => 1]);
        $record->id_siswa = $siswa->id_siswa;

        return $record;
    }

    private function classes(): array
    {
        [$tahun, $semester] = Period::current();

        if (! $tahun || ! $semester) {
            return [];
        }

        return DB::table('master_kelas')
            ->where('id_tp', $tahun->id_tp)
            ->where('id_smt', $semester->id_smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas'])
            ->map(fn ($kelas) => ['id_kelas' => (int) $kelas->id_kelas, 'nama_kelas' => (string) $kelas->nama_kelas])
            ->all();
    }
}
