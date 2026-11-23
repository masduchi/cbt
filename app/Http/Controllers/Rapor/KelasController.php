<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Ekstra;
use App\Models\Kelas;
use App\Models\KelasEkstra;
use App\Models\RaporCatatanWali;
use App\Models\RaporFisik;
use App\Models\RaporKunci;
use App\Models\RaporNaik;
use App\Models\RaporNilaiEkstra;
use App\Models\RaporNilaiSikap;
use App\Models\RaporPrestasi;
use App\Support\AuditLog;
use App\Support\Rapor;
use App\Support\SheetImport;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KelasController extends Controller
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

        return Inertia::render('Rapor/Kelas', [
            'kelas' => $kelas,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function data(Request $request, int $kelas): Response
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);

        $kelasModel = Kelas::with('waliKelas:id_guru,nama_guru')->findOrFail($kelas);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $sikap = [];

        foreach (RaporNilaiSikap::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $sikap[$row->id_siswa][(int) $row->jenis] = [
                'predikat' => $nilai['predikat'] ?? '',
                'deskripsi' => $row->deskripsi ?? '',
            ];
        }

        $catatan = [];

        foreach (RaporCatatanWali::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $catatan[$row->id_siswa] = [
                's' => $nilai['s'] ?? '',
                'i' => $nilai['i'] ?? '',
                'a' => $nilai['a'] ?? '',
                'catatan' => $row->deskripsi ?? '',
            ];
        }

        $prestasi = [];

        foreach (RaporPrestasi::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $prestasi[$row->id_siswa] = [
                'ranking' => $row->ranking,
                'deskripsi' => $row->deskripsi,
                'p1' => $row->p1,
                'p1_desk' => $row->p1_desk,
                'p2' => $row->p2,
                'p2_desk' => $row->p2_desk,
                'p3' => $row->p3,
                'p3_desk' => $row->p3_desk,
            ];
        }

        $fisik = [];

        foreach (RaporFisik::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $kondisi = Rapor::unserialize($row->kondisi);
            $fisik[$row->id_siswa] = [
                'tinggi' => $row->tinggi,
                'berat' => $row->berat,
                'telinga' => $kondisi['telinga'] ?? '',
                'mata' => $kondisi['mata'] ?? '',
                'gigi' => $kondisi['gigi'] ?? '',
                'lain' => $kondisi['lain'] ?? '',
            ];
        }

        $nilaiEkstra = RaporNilaiEkstra::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->map(fn (RaporNilaiEkstra $row) => [
                'id_siswa' => $row->id_siswa,
                'id_ekstra' => $row->id_ekstra,
                'nilai' => $row->nilai,
                'predikat' => $row->predikat,
                'deskripsi' => $row->deskripsi,
            ])
            ->all();

        $naik = RaporNaik::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereIn('id_siswa', collect($siswas)->pluck('id_siswa'))
            ->pluck('naik', 'id_siswa')
            ->all();

        $ekstraIds = [];

        foreach (KelasEkstra::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            foreach (Rapor::unserialize($row->ekstra) as $item) {
                $item = (array) $item;
                if (isset($item['ekstra'])) {
                    $ekstraIds[] = (int) $item['ekstra'];
                }
            }
        }

        $ekstras = $ekstraIds
            ? Ekstra::whereIn('id_ekstra', array_unique($ekstraIds))->orderBy('nama_ekstra')->get(['id_ekstra', 'nama_ekstra'])
            : Ekstra::orderBy('nama_ekstra')->get(['id_ekstra', 'nama_ekstra']);

        $kunci = Rapor::lock($kelas, $tp->id_tp, $smt->id_smt);

        return Inertia::render('Rapor/KelasData', [
            'kelas' => [
                'id_kelas' => $kelasModel->id_kelas,
                'nama_kelas' => $kelasModel->nama_kelas,
                'wali' => $kelasModel->waliKelas?->nama_guru,
            ],
            'siswas' => $siswas,
            'sikap' => $sikap,
            'catatan' => $catatan,
            'prestasi' => $prestasi,
            'fisik' => $fisik,
            'ekstras' => $ekstras,
            'nilaiEkstra' => $nilaiEkstra,
            'naik' => $naik,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'kunci' => $kunci ? [
                'terkunci' => true,
                'oleh' => $kunci->lockedBy?->nama ?? 'Administrator',
                'pada' => $kunci->locked_at?->locale('id')->translatedFormat('j F Y H:i'),
            ] : ['terkunci' => false],
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function kunci(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        abort_unless($request->user()->isAdmin(), 403, 'Hanya administrator yang dapat mengunci rapor.');

        RaporKunci::updateOrCreate(
            ['id_tp' => $tp->id_tp, 'id_smt' => $smt->id_smt, 'id_kelas' => $kelas],
            ['locked_by' => $request->user()->id, 'locked_at' => now()]
        );

        $nama = Kelas::where('id_kelas', $kelas)->value('nama_kelas') ?? ('#'.$kelas);
        AuditLog::record('rapor.lock', "Rapor kelas {$nama} dikunci");

        return back()->with('success', 'Rapor kelas dikunci. Nilai tidak dapat diubah sampai kunci dibuka.');
    }

    public function bukaKunci(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        abort_unless($request->user()->isAdmin(), 403, 'Hanya administrator yang dapat membuka kunci rapor.');

        RaporKunci::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->where('id_kelas', $kelas)
            ->update(['locked_by' => null, 'locked_at' => null]);

        $nama = Kelas::where('id_kelas', $kelas)->value('nama_kelas') ?? ('#'.$kelas);
        AuditLog::record('rapor.unlock', "Rapor kelas {$nama} dibuka kuncinya");

        return back()->with('success', 'Kunci rapor dibuka. Nilai dapat diubah kembali.');
    }

    public function saveSikap(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.jenis' => ['required', 'integer', 'in:1,2'],
            'rows.*.predikat' => ['nullable', 'string', 'in:A,B,C,D'],
            'rows.*.deskripsi' => ['nullable', 'string', 'max:500'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];
            $jenis = (int) $row['jenis'];
            $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt.$jenis;
            $existing = RaporNilaiSikap::where('id_nilai_sikap', $id)->first();
            $nilai = $existing ? Rapor::unserialize($existing->nilai) : [];
            $nilai['predikat'] = $row['predikat'] ?? '';

            RaporNilaiSikap::updateOrCreate(
                ['id_nilai_sikap' => $id],
                [
                    'id_siswa' => $idSiswa,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'jenis' => $jenis,
                    'nilai' => serialize($nilai),
                    'deskripsi' => $row['deskripsi'] ?? '',
                ]
            );
        }

        return back()->with('success', 'Nilai sikap disimpan.');
    }

    public function saveCatatan(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.s' => ['nullable', 'integer', 'between:0,366'],
            'rows.*.i' => ['nullable', 'integer', 'between:0,366'],
            'rows.*.a' => ['nullable', 'integer', 'between:0,366'],
            'rows.*.catatan' => ['nullable', 'string', 'max:500'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];
            $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt;
            $existing = RaporCatatanWali::where('id_catatan_wali', $id)->first();
            $nilai = $existing ? Rapor::unserialize($existing->nilai) : [];
            $nilai['s'] = $row['s'] ?? 0;
            $nilai['i'] = $row['i'] ?? 0;
            $nilai['a'] = $row['a'] ?? 0;

            RaporCatatanWali::updateOrCreate(
                ['id_catatan_wali' => $id],
                [
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'id_kelas' => $kelas,
                    'id_siswa' => $idSiswa,
                    'nilai' => serialize($nilai),
                    'deskripsi' => $row['catatan'] ?? '',
                ]
            );
        }

        return back()->with('success', 'Absensi dan catatan wali disimpan.');
    }

    public function savePrestasi(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.ranking' => ['nullable', 'integer', 'between:0,999'],
            'rows.*.deskripsi' => ['nullable', 'string', 'max:100'],
            'rows.*.p1' => ['nullable', 'string', 'max:100'],
            'rows.*.p1_desk' => ['nullable', 'string', 'max:100'],
            'rows.*.p2' => ['nullable', 'string', 'max:100'],
            'rows.*.p2_desk' => ['nullable', 'string', 'max:100'],
            'rows.*.p3' => ['nullable', 'string', 'max:100'],
            'rows.*.p3_desk' => ['nullable', 'string', 'max:100'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];

            RaporPrestasi::updateOrCreate(
                ['id_ranking' => $kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_kelas' => $kelas,
                    'id_siswa' => $idSiswa,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'ranking' => $row['ranking'] ?? 0,
                    'deskripsi' => $row['deskripsi'] ?? '',
                    'p1' => $row['p1'] ?? '',
                    'p1_desk' => $row['p1_desk'] ?? '',
                    'p2' => $row['p2'] ?? '',
                    'p2_desk' => $row['p2_desk'] ?? '',
                    'p3' => $row['p3'] ?? '',
                    'p3_desk' => $row['p3_desk'] ?? '',
                ]
            );
        }

        return back()->with('success', 'Ranking dan prestasi disimpan.');
    }

    public function hitungPeringkat(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $totals = DB::table('rapor_nilai_akhir')
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->selectRaw('id_siswa, SUM(akhir) as total')
            ->groupBy('id_siswa')
            ->pluck('total', 'id_siswa');

        $sorted = collect($siswas)->sortByDesc(fn ($siswa) => (float) ($totals[$siswa['id_siswa']] ?? 0))->values();

        DB::transaction(function () use ($sorted, $kelas, $tp, $smt) {
            foreach ($sorted as $index => $siswa) {
                $prestasi = RaporPrestasi::firstOrNew([
                    'id_ranking' => $kelas.$siswa['id_siswa'].$tp->id_tp.$smt->id_smt,
                ]);

                $prestasi->fill([
                    'id_kelas' => $kelas,
                    'id_siswa' => $siswa['id_siswa'],
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'ranking' => $index + 1,
                ]);

                if (! $prestasi->exists) {
                    $prestasi->fill([
                        'deskripsi' => '',
                        'p1' => '',
                        'p1_desk' => '',
                        'p2' => '',
                        'p2_desk' => '',
                        'p3' => '',
                        'p3_desk' => '',
                    ]);
                }

                $prestasi->save();
            }
        });

        return back()->with('success', 'Peringkat kelas dihitung ulang dari total nilai akhir.');
    }

    public function saveFisik(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.tinggi' => ['nullable', 'integer', 'between:0,300'],
            'rows.*.berat' => ['nullable', 'integer', 'between:0,300'],
            'rows.*.telinga' => ['nullable', 'string', 'max:100'],
            'rows.*.mata' => ['nullable', 'string', 'max:100'],
            'rows.*.gigi' => ['nullable', 'string', 'max:100'],
            'rows.*.lain' => ['nullable', 'string', 'max:100'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];

            RaporFisik::updateOrCreate(
                ['id_fisik' => $kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_kelas' => $kelas,
                    'id_siswa' => $idSiswa,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'kondisi' => serialize([
                        'telinga' => $row['telinga'] ?? '',
                        'mata' => $row['mata'] ?? '',
                        'gigi' => $row['gigi'] ?? '',
                        'lain' => $row['lain'] ?? '',
                    ]),
                    'tinggi' => $row['tinggi'] ?? 0,
                    'berat' => $row['berat'] ?? 0,
                ]
            );
        }

        return back()->with('success', 'Data fisik siswa disimpan.');
    }

    public function saveEkstra(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.id_ekstra' => ['required', 'integer'],
            'rows.*.nilai' => ['nullable', 'integer', 'between:0,100'],
            'rows.*.predikat' => ['nullable', 'string', 'in:A,B,C,D'],
            'rows.*.deskripsi' => ['nullable', 'string', 'max:500'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];
            $idEkstra = (int) $row['id_ekstra'];
            $nilai = $row['nilai'] ?? null;

            RaporNilaiEkstra::updateOrCreate(
                ['id_nilai_ekstra' => $idEkstra.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_ekstra' => $idEkstra,
                    'id_kelas' => $kelas,
                    'id_siswa' => $idSiswa,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'nilai' => $nilai ?? 0,
                    'predikat' => $row['predikat'] ?? Rapor::predikat($nilai, 75),
                    'deskripsi' => $row['deskripsi'] ?? '',
                ]
            );
        }

        return back()->with('success', 'Nilai ekstrakurikuler disimpan.');
    }

    public function importComponent(Request $request, int $kelas, string $jenis): RedirectResponse
    {
        $labels = [
            'sikap' => 'sikap',
            'catatan' => 'absensi & catatan wali',
            'prestasi' => 'ranking & prestasi',
            'fisik' => 'kondisi fisik',
            'ekstra' => 'nilai ekstrakurikuler',
        ];

        abort_unless(isset($labels[$jenis]), 404);

        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $idEkstra = null;

        if ($jenis === 'ekstra') {
            $idEkstra = (int) $request->input('id_ekstra');

            abort_unless($idEkstra && Ekstra::whereKey($idEkstra)->exists(), 422, 'Pilih ekstrakurikuler terlebih dahulu.');
        }

        $students = $this->studentIndex($kelas, $tp->id_tp, $smt->id_smt);
        [$header, $rows] = SheetImport::read($request->file('file'), ['nisn', 'nis']);

        if (! in_array('nisn', $header, true) && ! in_array('nis', $header, true)) {
            return back()
                ->with('success', 'Impor dibatalkan.')
                ->with('import_errors', ['Kolom wajib tidak ditemukan: nisn atau nis.']);
        }

        $saved = 0;
        $errors = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $nisn = trim((string) ($data['nisn'] ?? ''));
            $nis = trim((string) ($data['nis'] ?? ''));
            $idSiswa = Rapor::studentIdByNomor($students, $nisn, $nis);

            if (! $idSiswa) {
                $label = $nisn !== '' ? $nisn : ($nis !== '' ? $nis : 'kosong');
                $errors[] = "Baris {$line}: siswa dengan NISN/NIS {$label} tidak ditemukan di kelas ini.";

                continue;
            }

            $result = match ($jenis) {
                'sikap' => $this->importSikap($data, $line, $idSiswa, $kelas, $tp, $smt),
                'catatan' => $this->importCatatan($data, $line, $idSiswa, $kelas, $tp, $smt),
                'prestasi' => $this->importPrestasi($data, $line, $idSiswa, $kelas, $tp, $smt),
                'fisik' => $this->importFisik($data, $line, $idSiswa, $kelas, $tp, $smt),
                'ekstra' => $this->importEkstra($data, $line, $idSiswa, $kelas, $tp, $smt, (int) $idEkstra),
            };

            if (is_string($result)) {
                $errors[] = $result;
            } elseif ($result === true) {
                $saved++;
            }
        }

        $message = "Impor selesai: {$saved} data {$labels[$jenis]} disimpan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        $redirect = back()->with('success', $message);

        if ($errors) {
            $redirect->with('import_errors', array_slice($errors, 0, 50));
        }

        return $redirect;
    }

    public function templateComponent(Request $request, int $kelas, string $jenis)
    {
        $headersByJenis = [
            'sikap' => ['nisn', 'nis', 'nama', 'predikat_spiritual', 'deskripsi_spiritual', 'predikat_sosial', 'deskripsi_sosial'],
            'catatan' => ['nisn', 'nis', 'nama', 'sakit', 'izin', 'alpa', 'catatan'],
            'prestasi' => ['nisn', 'nis', 'nama', 'ranking', 'deskripsi', 'p1', 'p1_desk', 'p2', 'p2_desk', 'p3', 'p3_desk'],
            'fisik' => ['nisn', 'nis', 'nama', 'tinggi', 'berat', 'telinga', 'mata', 'gigi', 'lain'],
            'ekstra' => ['nisn', 'nis', 'nama', 'nilai', 'predikat', 'deskripsi'],
        ];

        abort_unless(isset($headersByJenis[$jenis]), 404);

        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);

        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $rows = match ($jenis) {
            'sikap' => $this->sikapTemplateRows($siswas, $kelas, $tp, $smt),
            'catatan' => $this->catatanTemplateRows($siswas, $kelas, $tp, $smt),
            'prestasi' => $this->prestasiTemplateRows($siswas, $kelas, $tp, $smt),
            'fisik' => $this->fisikTemplateRows($siswas, $kelas, $tp, $smt),
            'ekstra' => $this->ekstraTemplateRows($siswas, $kelas, $tp, $smt, (int) $request->query('ekstra')),
        };

        $kelasModel = Kelas::findOrFail($kelas);
        $filename = 'template-'.$jenis.'-'.$kelasModel->id_kelas.'.xlsx';

        return XlsxExport::download($filename, $headersByJenis[$jenis], $rows, strtoupper($jenis));
    }

    private function importSikap(array $data, int $line, int $idSiswa, int $kelas, $tp, $smt): bool|string
    {
        $values = [
            1 => [strtoupper(trim((string) ($data['predikat_spiritual'] ?? ''))), trim((string) ($data['deskripsi_spiritual'] ?? ''))],
            2 => [strtoupper(trim((string) ($data['predikat_sosial'] ?? ''))), trim((string) ($data['deskripsi_sosial'] ?? ''))],
        ];

        if ($values[1][0] === '' && $values[1][1] === '' && $values[2][0] === '' && $values[2][1] === '') {
            return false;
        }

        $saved = false;

        foreach ($values as $jenis => [$predikat, $deskripsi]) {
            if ($predikat !== '' && ! in_array($predikat, ['A', 'B', 'C', 'D'], true)) {
                return "Baris {$line}: predikat sikap harus A, B, C, atau D.";
            }

            if (mb_strlen($deskripsi) > 500) {
                return "Baris {$line}: deskripsi sikap maksimal 500 karakter.";
            }

            if ($predikat === '' && $deskripsi === '') {
                continue;
            }

            $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt.$jenis;
            $existing = RaporNilaiSikap::find($id);
            $nilai = $existing ? Rapor::unserialize($existing->nilai) : [];
            $nilai['predikat'] = $predikat;

            RaporNilaiSikap::updateOrCreate(
                ['id_nilai_sikap' => $id],
                [
                    'id_siswa' => $idSiswa,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'jenis' => $jenis,
                    'nilai' => serialize($nilai),
                    'deskripsi' => $deskripsi,
                ]
            );

            $saved = true;
        }

        return $saved;
    }

    private function importCatatan(array $data, int $line, int $idSiswa, int $kelas, $tp, $smt): bool|string
    {
        $catatan = trim((string) ($data['catatan'] ?? ''));
        $numbers = [];

        foreach (['s' => 'sakit', 'i' => 'izin', 'a' => 'alpa'] as $key => $label) {
            $raw = trim((string) ($data[$label] ?? ''));

            if ($raw !== '' && (! is_numeric($raw) || (int) $raw < 0 || (int) $raw > 366)) {
                return "Baris {$line}: jumlah {$label} harus berupa angka 0 sampai 366.";
            }

            $numbers[$key] = $raw === '' ? null : (int) $raw;
        }

        if ($catatan === '' && $numbers['s'] === null && $numbers['i'] === null && $numbers['a'] === null) {
            return false;
        }

        if (mb_strlen($catatan) > 500) {
            return "Baris {$line}: catatan wali maksimal 500 karakter.";
        }

        $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt;
        $existing = RaporCatatanWali::find($id);
        $nilai = $existing ? Rapor::unserialize($existing->nilai) : [];

        foreach ($numbers as $key => $value) {
            $nilai[$key] = $value ?? (int) ($nilai[$key] ?? 0);
        }

        RaporCatatanWali::updateOrCreate(
            ['id_catatan_wali' => $id],
            [
                'id_tp' => $tp->id_tp,
                'id_smt' => $smt->id_smt,
                'id_kelas' => $kelas,
                'id_siswa' => $idSiswa,
                'nilai' => serialize($nilai),
                'deskripsi' => $catatan !== '' ? $catatan : (string) ($existing?->deskripsi ?? ''),
            ]
        );

        return true;
    }

    private function importPrestasi(array $data, int $line, int $idSiswa, int $kelas, $tp, $smt): bool|string
    {
        $fields = ['ranking', 'deskripsi', 'p1', 'p1_desk', 'p2', 'p2_desk', 'p3', 'p3_desk'];
        $values = [];
        $any = false;

        foreach ($fields as $field) {
            $raw = trim((string) ($data[$field] ?? ''));
            $any = $any || $raw !== '';
            $values[$field] = $raw;
        }

        if (! $any) {
            return false;
        }

        if ($values['ranking'] !== '' && (! is_numeric($values['ranking']) || (int) $values['ranking'] < 0 || (int) $values['ranking'] > 999)) {
            return "Baris {$line}: ranking harus berupa angka 0 sampai 999.";
        }

        foreach ($fields as $field) {
            $max = $field === 'ranking' ? 999 : 100;

            if ($field !== 'ranking' && mb_strlen($values[$field]) > $max) {
                return "Baris {$line}: kolom {$field} maksimal {$max} karakter.";
            }
        }

        $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt;
        $existing = RaporPrestasi::find($id);
        $payload = ['id_kelas' => $kelas, 'id_siswa' => $idSiswa, 'id_tp' => $tp->id_tp, 'id_smt' => $smt->id_smt];

        foreach ($fields as $field) {
            $payload[$field] = $values[$field] !== '' ? $values[$field] : ($existing?->{$field} ?? ($field === 'ranking' ? 0 : ''));
        }

        $payload['ranking'] = (int) $payload['ranking'];

        RaporPrestasi::updateOrCreate(['id_ranking' => $id], $payload);

        return true;
    }

    private function importFisik(array $data, int $line, int $idSiswa, int $kelas, $tp, $smt): bool|string
    {
        $numbers = [];

        foreach (['tinggi' => 'tinggi', 'berat' => 'berat'] as $key => $label) {
            $raw = trim((string) ($data[$label] ?? ''));

            if ($raw !== '' && (! is_numeric($raw) || (int) $raw < 0 || (int) $raw > 300)) {
                return "Baris {$line}: {$label} harus berupa angka 0 sampai 300.";
            }

            $numbers[$key] = $raw === '' ? null : (int) $raw;
        }

        $kondisi = [];

        foreach (['telinga' => 'telinga', 'mata' => 'mata', 'gigi' => 'gigi', 'lain' => 'lain'] as $key => $label) {
            $raw = trim((string) ($data[$label] ?? ''));

            if (mb_strlen($raw) > 100) {
                return "Baris {$line}: kolom {$label} maksimal 100 karakter.";
            }

            $kondisi[$key] = $raw;
        }

        $hasKondisi = count(array_filter($kondisi, fn ($value) => $value !== '')) > 0;

        if ($numbers['tinggi'] === null && $numbers['berat'] === null && ! $hasKondisi) {
            return false;
        }

        $id = $kelas.$idSiswa.$tp->id_tp.$smt->id_smt;
        $existing = RaporFisik::find($id);
        $current = $existing ? Rapor::unserialize($existing->kondisi) : [];

        foreach ($kondisi as $key => $value) {
            if ($value === '') {
                $kondisi[$key] = (string) ($current[$key] ?? '');
            }
        }

        RaporFisik::updateOrCreate(
            ['id_fisik' => $id],
            [
                'id_kelas' => $kelas,
                'id_siswa' => $idSiswa,
                'id_tp' => $tp->id_tp,
                'id_smt' => $smt->id_smt,
                'kondisi' => serialize($kondisi),
                'tinggi' => $numbers['tinggi'] ?? (int) ($existing?->tinggi ?? 0),
                'berat' => $numbers['berat'] ?? (int) ($existing?->berat ?? 0),
            ]
        );

        return true;
    }

    private function importEkstra(array $data, int $line, int $idSiswa, int $kelas, $tp, $smt, int $idEkstra): bool|string
    {
        $nilaiRaw = trim((string) ($data['nilai'] ?? ''));
        $predikat = strtoupper(trim((string) ($data['predikat'] ?? '')));
        $deskripsi = trim((string) ($data['deskripsi'] ?? ''));

        if ($nilaiRaw === '' && $predikat === '' && $deskripsi === '') {
            return false;
        }

        if ($nilaiRaw !== '' && (! is_numeric($nilaiRaw) || (int) $nilaiRaw < 0 || (int) $nilaiRaw > 100)) {
            return "Baris {$line}: nilai ekstrakurikuler harus berupa angka 0 sampai 100.";
        }

        if ($predikat !== '' && ! in_array($predikat, ['A', 'B', 'C', 'D'], true)) {
            return "Baris {$line}: predikat ekstrakurikuler harus A, B, C, atau D.";
        }

        if (mb_strlen($deskripsi) > 500) {
            return "Baris {$line}: deskripsi ekstrakurikuler maksimal 500 karakter.";
        }

        $id = $idEkstra.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt;
        $existing = RaporNilaiEkstra::find($id);
        $nilai = $nilaiRaw !== '' ? (int) $nilaiRaw : (int) ($existing?->nilai ?? 0);

        RaporNilaiEkstra::updateOrCreate(
            ['id_nilai_ekstra' => $id],
            [
                'id_ekstra' => $idEkstra,
                'id_kelas' => $kelas,
                'id_siswa' => $idSiswa,
                'id_tp' => $tp->id_tp,
                'id_smt' => $smt->id_smt,
                'nilai' => $nilai,
                'predikat' => $predikat !== '' ? $predikat : (string) ($existing?->predikat ?? Rapor::predikat($nilai, 75)),
                'deskripsi' => $deskripsi !== '' ? $deskripsi : (string) ($existing?->deskripsi ?? ''),
            ]
        );

        return true;
    }

    private function sikapTemplateRows(array $siswas, int $kelas, $tp, $smt): array
    {
        $sikap = [];

        foreach (RaporNilaiSikap::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $sikap[$row->id_siswa][(int) $row->jenis] = [
                'predikat' => $nilai['predikat'] ?? '',
                'deskripsi' => $row->deskripsi ?? '',
            ];
        }

        return array_map(fn (array $siswa) => [
            $siswa['nisn'] ?? '',
            $siswa['nis'] ?? '',
            $siswa['nama'],
            $sikap[$siswa['id_siswa']][1]['predikat'] ?? '',
            $sikap[$siswa['id_siswa']][1]['deskripsi'] ?? '',
            $sikap[$siswa['id_siswa']][2]['predikat'] ?? '',
            $sikap[$siswa['id_siswa']][2]['deskripsi'] ?? '',
        ], $siswas);
    }

    private function catatanTemplateRows(array $siswas, int $kelas, $tp, $smt): array
    {
        $catatan = [];

        foreach (RaporCatatanWali::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $nilai = Rapor::unserialize($row->nilai);
            $catatan[$row->id_siswa] = [
                's' => $nilai['s'] ?? 0,
                'i' => $nilai['i'] ?? 0,
                'a' => $nilai['a'] ?? 0,
                'catatan' => $row->deskripsi ?? '',
            ];
        }

        return array_map(fn (array $siswa) => [
            $siswa['nisn'] ?? '',
            $siswa['nis'] ?? '',
            $siswa['nama'],
            $catatan[$siswa['id_siswa']]['s'] ?? 0,
            $catatan[$siswa['id_siswa']]['i'] ?? 0,
            $catatan[$siswa['id_siswa']]['a'] ?? 0,
            $catatan[$siswa['id_siswa']]['catatan'] ?? '',
        ], $siswas);
    }

    private function prestasiTemplateRows(array $siswas, int $kelas, $tp, $smt): array
    {
        $prestasi = RaporPrestasi::where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

        return array_map(function (array $siswa) use ($prestasi) {
            $row = $prestasi->get($siswa['id_siswa']);

            return [
                $siswa['nisn'] ?? '',
                $siswa['nis'] ?? '',
                $siswa['nama'],
                $row?->ranking ?? '',
                $row?->deskripsi ?? '',
                $row?->p1 ?? '',
                $row?->p1_desk ?? '',
                $row?->p2 ?? '',
                $row?->p2_desk ?? '',
                $row?->p3 ?? '',
                $row?->p3_desk ?? '',
            ];
        }, $siswas);
    }

    private function fisikTemplateRows(array $siswas, int $kelas, $tp, $smt): array
    {
        $fisik = [];

        foreach (RaporFisik::where('id_kelas', $kelas)->where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $row) {
            $kondisi = Rapor::unserialize($row->kondisi);
            $fisik[$row->id_siswa] = [
                'tinggi' => $row->tinggi,
                'berat' => $row->berat,
                'telinga' => $kondisi['telinga'] ?? '',
                'mata' => $kondisi['mata'] ?? '',
                'gigi' => $kondisi['gigi'] ?? '',
                'lain' => $kondisi['lain'] ?? '',
            ];
        }

        return array_map(fn (array $siswa) => [
            $siswa['nisn'] ?? '',
            $siswa['nis'] ?? '',
            $siswa['nama'],
            $fisik[$siswa['id_siswa']]['tinggi'] ?? '',
            $fisik[$siswa['id_siswa']]['berat'] ?? '',
            $fisik[$siswa['id_siswa']]['telinga'] ?? '',
            $fisik[$siswa['id_siswa']]['mata'] ?? '',
            $fisik[$siswa['id_siswa']]['gigi'] ?? '',
            $fisik[$siswa['id_siswa']]['lain'] ?? '',
        ], $siswas);
    }

    private function ekstraTemplateRows(array $siswas, int $kelas, $tp, $smt, int $idEkstra): array
    {
        $nilai = $idEkstra
            ? RaporNilaiEkstra::where('id_kelas', $kelas)
                ->where('id_ekstra', $idEkstra)
                ->where('id_tp', $tp->id_tp)
                ->where('id_smt', $smt->id_smt)
                ->get()
                ->keyBy('id_siswa')
            : collect();

        return array_map(function (array $siswa) use ($nilai) {
            $row = $nilai->get($siswa['id_siswa']);

            return [
                $siswa['nisn'] ?? '',
                $siswa['nis'] ?? '',
                $siswa['nama'],
                $row?->nilai ?? '',
                $row?->predikat ?? '',
                $row?->deskripsi ?? '',
            ];
        }, $siswas);
    }

    private function studentIndex(int $kelas, int $tp, int $smt): array
    {
        $index = [];

        foreach ($this->students($kelas, $tp, $smt) as $siswa) {
            foreach (['nisn', 'nis'] as $field) {
                if (empty($siswa[$field])) {
                    continue;
                }

                $index[$siswa[$field]] = $siswa['id_siswa'];
                $index[Rapor::nisnKey((string) $siswa[$field])] ??= $siswa['id_siswa'];
            }
        }

        return $index;
    }

    public function saveNaik(Request $request, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $this->authorizeKelas($request, $kelas, $tp->id_tp, $smt->id_smt);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.naik' => ['required', 'integer', 'in:0,1'],
        ])['rows'];

        foreach ($rows as $row) {
            $idSiswa = (int) $row['id_siswa'];

            RaporNaik::updateOrCreate(
                ['id_naik' => $idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'id_siswa' => $idSiswa,
                    'naik' => (int) $row['naik'],
                ]
            );
        }

        return back()->with('success', 'Status kenaikan kelas disimpan.');
    }

    private function authorizeKelas(Request $request, int $kelas, int $tp, int $smt): void
    {
        $guruId = Rapor::guruId($request->user());

        abort_unless(Rapor::hasKelasAccess($request->user(), $guruId, $kelas, $tp, $smt), 403);
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

    private function period(): array
    {
        [$tp, $smt] = Rapor::period();
        abort_if(! $tp || ! $smt, 409, 'Tahun pelajaran / semester aktif belum diatur.');

        return [$tp, $smt];
    }
}
