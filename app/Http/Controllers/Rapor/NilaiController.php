<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RaporKikd;
use App\Models\RaporNilaiAkhir;
use App\Models\RaporNilaiHarian;
use App\Models\RaporNilaiPts;
use App\Support\AuditLog;
use App\Support\Rapor;
use App\Support\SheetImport;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NilaiController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        $kelas = Kelas::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas']);

        $assignments = Rapor::allAssignments($tp->id_tp, $smt->id_smt);

        if ($user->isAdmin()) {
            $mapels = Mapel::orderBy('urutan')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kelompok']);
            $rows = [];

            foreach ($kelas as $k) {
                foreach ($mapels as $mapel) {
                    $guruNames = collect($assignments)
                        ->where('id_mapel', $mapel->id_mapel)
                        ->where('id_kelas', $k->id_kelas)
                        ->pluck('nama_guru')
                        ->unique()
                        ->values();

                    $rows[] = [
                        'id_mapel' => $mapel->id_mapel,
                        'nama_mapel' => $mapel->nama_mapel,
                        'id_kelas' => $k->id_kelas,
                        'nama_kelas' => $k->nama_kelas,
                        'guru' => $guruNames->implode(', '),
                    ];
                }
            }
        } else {
            $mapelNames = Mapel::pluck('nama_mapel', 'id_mapel');
            $kelasNames = $kelas->pluck('nama_kelas', 'id_kelas');
            $guruName = $assignments[0]['nama_guru'] ?? '';
            $rows = [];

            foreach (Rapor::assignments($guruId, $tp->id_tp, $smt->id_smt) as $mapel) {
                foreach ($mapel['kelas'] as $idKelas) {
                    if (! isset($kelasNames[$idKelas])) {
                        continue;
                    }

                    $rows[] = [
                        'id_mapel' => $mapel['id_mapel'],
                        'nama_mapel' => $mapelNames[$mapel['id_mapel']] ?? $mapel['nama_mapel'],
                        'id_kelas' => $idKelas,
                        'nama_kelas' => $kelasNames[$idKelas],
                        'guru' => $guruName,
                    ];
                }
            }
        }

        $harian = $this->counts(RaporNilaiHarian::query(), $tp->id_tp, $smt->id_smt);
        $pts = $this->counts(RaporNilaiPts::query(), $tp->id_tp, $smt->id_smt);
        $akhir = $this->counts(RaporNilaiAkhir::query(), $tp->id_tp, $smt->id_smt);

        foreach ($rows as &$row) {
            $key = $row['id_mapel'].'-'.$row['id_kelas'];
            $row['harian'] = $harian[$key] ?? 0;
            $row['pts'] = $pts[$key] ?? 0;
            $row['pas'] = $akhir[$key] ?? 0;
        }

        return Inertia::render('Rapor/Nilai', [
            'rows' => $rows,
            'kelasOptions' => $kelas,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function harian(Request $request, int $mapel, int $kelas): Response
    {
        [$tp, $smt, $mapelModel, $kelasModel] = $this->context($request, $mapel, $kelas);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $nilai = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

        $kikd = $this->kikd($mapel, $kelas, $tp->id_tp, $smt->id_smt);

        return Inertia::render('Rapor/Harian', [
            'mapel' => ['id_mapel' => $mapelModel->id_mapel, 'nama_mapel' => $mapelModel->nama_mapel],
            'kelas' => ['id_kelas' => $kelasModel->id_kelas, 'nama_kelas' => $kelasModel->nama_kelas],
            'siswas' => $siswas,
            'nilai' => $nilai,
            'kikd' => $kikd,
            'kkm' => $kkm,
            'setting' => $setting ? $setting->only(['kkm_tunggal', 'kkm']) : null,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
        ]);
    }

    public function saveHarian(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $rows = (array) $request->input('rows', []);
        $kikd = (array) $request->input('kikd', []);
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

        $kikdP = array_values((array) ($kikd['p'] ?? []));
        $kikdK = array_values((array) ($kikd['k'] ?? []));

        DB::transaction(function () use ($rows, $mapel, $kelas, $tp, $smt, $kkm, $kikdP, $kikdK) {
            foreach ($rows as $row) {
                $idSiswa = (int) ($row['id_siswa'] ?? 0);

                if (! $idSiswa) {
                    continue;
                }

                $p = [];
                $k = [];

                for ($i = 1; $i <= 8; $i++) {
                    $p[$i] = $this->nilai($row['p'.$i] ?? null);
                    $k[$i] = $this->nilai($row['k'.$i] ?? null);
                }

                $pAvg = Rapor::rata($p);
                $kAvg = Rapor::rata($k);
                $pPred = Rapor::predikat($pAvg, $kkm['kkm']);
                $kPred = Rapor::predikat($kAvg, $kkm['kkm']);

                $pDesc = trim((string) ($row['p_deskripsi'] ?? ''));
                $kDesc = trim((string) ($row['k_deskripsi'] ?? ''));

                if ($pDesc === '') {
                    $pDesc = Rapor::deskripsi($pAvg, $pPred, $kikdP);
                }

                if ($kDesc === '') {
                    $kDesc = Rapor::deskripsi($kAvg, $kPred, $kikdK);
                }

                $data = [
                    'id_nilai_harian' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt,
                    'id_siswa' => $idSiswa,
                    'id_mapel' => $mapel,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'p_rata_rata' => $pAvg,
                    'p_predikat' => $pPred,
                    'p_deskripsi' => $pDesc,
                    'k_rata_rata' => $kAvg,
                    'k_predikat' => $kPred,
                    'k_deskripsi' => $kDesc,
                    'jml' => array_sum(array_filter($p)) + array_sum(array_filter($k)),
                ];

                for ($i = 1; $i <= 8; $i++) {
                    $data['p'.$i] = $p[$i];
                    $data['k'.$i] = $k[$i];
                }

                RaporNilaiHarian::updateOrCreate(['id_nilai_harian' => $data['id_nilai_harian']], $data);
            }

            for ($i = 1; $i <= 8; $i++) {
                foreach ([1 => $kikdP, 2 => $kikdK] as $aspek => $materi) {
                    $value = trim((string) ($materi[$i - 1] ?? ''));

                    RaporKikd::updateOrCreate(
                        ['id_kikd' => $mapel.$kelas.$aspek.$i],
                        [
                            'id_mapel_kelas' => $mapel.$kelas,
                            'aspek' => $aspek,
                            'id_tp' => $tp->id_tp,
                            'id_smt' => $smt->id_smt,
                            'materi_kikd' => strip_tags($value),
                        ]
                    );
                }
            }
        });

        AuditLog::record('rapor.nilai.harian', 'Mapel #'.$mapel.' kelas #'.$kelas.' disimpan');

        return back()->with('success', 'Nilai harian dan indikator KD disimpan.');
    }

    public function importHarian(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        [$header, $rows] = SheetImport::read($request->file('file'), ['nisn', 'nis']);

        if (! in_array('nisn', $header, true) && ! in_array('nis', $header, true)) {
            return back()
                ->with('success', 'Impor nilai harian dibatalkan.')
                ->with('import_errors', ['Kolom wajib tidak ditemukan: nisn atau nis.']);
        }

        $nilaiColumns = array_values(array_filter($header, fn ($column) => preg_match('/^[pk][1-8]$/', $column) === 1));

        if (! $nilaiColumns) {
            return back()
                ->with('success', 'Impor nilai harian dibatalkan.')
                ->with('import_errors', ['Kolom nilai tidak ditemukan: gunakan p1 sampai p8 dan/atau k1 sampai k8.']);
        }

        $students = $this->studentIndex($kelas, $tp->id_tp, $smt->id_smt);
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);
        $kikd = $this->kikd($mapel, $kelas, $tp->id_tp, $smt->id_smt);

        $existing = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

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

            $current = $existing->get($idSiswa);
            $p = [];
            $k = [];
            $invalid = false;

            for ($i = 1; $i <= 8; $i++) {
                foreach (['p', 'k'] as $aspek) {
                    $key = $aspek.$i;
                    $raw = array_key_exists($key, $data) ? trim((string) $data[$key]) : null;

                    if ($raw !== null && $raw !== '' && (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100)) {
                        $errors[] = "Baris {$line}: nilai {$key} harus berupa angka 0 sampai 100.";
                        $invalid = true;

                        break 2;
                    }

                    $value = $raw === null
                        ? ($current?->{$key} ?? null)
                        : ($raw === '' ? null : (int) round((float) $raw));

                    if ($aspek === 'p') {
                        $p[$i] = $value;
                    } else {
                        $k[$i] = $value;
                    }
                }
            }

            if ($invalid) {
                continue;
            }

            if (count(array_filter($p, fn ($value) => $value !== null)) === 0
                && count(array_filter($k, fn ($value) => $value !== null)) === 0) {
                continue;
            }

            $pAvg = Rapor::rata($p);
            $kAvg = Rapor::rata($k);
            $pPred = Rapor::predikat($pAvg, $kkm['kkm']);
            $kPred = Rapor::predikat($kAvg, $kkm['kkm']);

            $pDesc = trim((string) ($data['p_deskripsi'] ?? ''));
            $kDesc = trim((string) ($data['k_deskripsi'] ?? ''));

            if ($pDesc === '') {
                $pDesc = Rapor::deskripsi($pAvg, $pPred, $kikd['p']);
            }

            if ($kDesc === '') {
                $kDesc = Rapor::deskripsi($kAvg, $kPred, $kikd['k']);
            }

            $payload = [
                'id_nilai_harian' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt,
                'id_siswa' => $idSiswa,
                'id_mapel' => $mapel,
                'id_kelas' => $kelas,
                'id_tp' => $tp->id_tp,
                'id_smt' => $smt->id_smt,
                'p_rata_rata' => $pAvg,
                'p_predikat' => $pPred,
                'p_deskripsi' => $pDesc,
                'k_rata_rata' => $kAvg,
                'k_predikat' => $kPred,
                'k_deskripsi' => $kDesc,
                'jml' => array_sum(array_filter($p)) + array_sum(array_filter($k)),
            ];

            for ($i = 1; $i <= 8; $i++) {
                $payload['p'.$i] = $p[$i];
                $payload['k'.$i] = $k[$i];
            }

            RaporNilaiHarian::updateOrCreate(['id_nilai_harian' => $payload['id_nilai_harian']], $payload);
            $saved++;
        }

        $message = "Impor selesai: {$saved} nilai harian disimpan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        AuditLog::record('rapor.nilai.harian.import', 'Mapel #'.$mapel.' kelas #'.$kelas.': '.$saved.' nilai disimpan');

        $redirect = back()->with('success', $message);

        if ($errors) {
            $redirect->with('import_errors', array_slice($errors, 0, 50));
        }

        return $redirect;
    }

    public function templateHarian(int $mapel, int $kelas)
    {
        [$tp, $smt] = $this->period();

        $mapelModel = Mapel::findOrFail($mapel);
        $kelasModel = Kelas::findOrFail($kelas);
        $students = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $nilai = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

        $headers = array_merge(
            ['nisn', 'nis', 'nama'],
            array_map(fn ($i) => 'p'.$i, range(1, 8)),
            array_map(fn ($i) => 'k'.$i, range(1, 8)),
            ['p_deskripsi', 'k_deskripsi']
        );

        $rows = array_map(function (array $siswa) use ($nilai) {
            $row = $nilai->get($siswa['id_siswa']);
            $values = [$siswa['nisn'] ?? '', $siswa['nis'] ?? '', $siswa['nama']];

            for ($i = 1; $i <= 8; $i++) {
                $values[] = $row?->{'p'.$i} ?? '';
            }

            for ($i = 1; $i <= 8; $i++) {
                $values[] = $row?->{'k'.$i} ?? '';
            }

            $values[] = $row?->p_deskripsi ?? '';
            $values[] = $row?->k_deskripsi ?? '';

            return $values;
        }, $students);

        $filename = 'template-nilai-harian-'.$mapelModel->id_mapel.'-'.$kelasModel->id_kelas.'.xlsx';

        return XlsxExport::download($filename, $headers, $rows, 'HARIAN');
    }

    public function pts(Request $request, int $mapel, int $kelas): Response
    {
        [$tp, $smt, $mapelModel, $kelasModel] = $this->context($request, $mapel, $kelas);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $nilai = RaporNilaiPts::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

        return Inertia::render('Rapor/Pts', [
            'mapel' => ['id_mapel' => $mapelModel->id_mapel, 'nama_mapel' => $mapelModel->nama_mapel],
            'kelas' => ['id_kelas' => $kelasModel->id_kelas, 'nama_kelas' => $kelasModel->nama_kelas],
            'siswas' => $siswas,
            'nilai' => $nilai,
            'kkm' => $kkm,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
        ]);
    }

    public function savePts(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

        foreach ((array) $request->input('rows', []) as $row) {
            $idSiswa = (int) ($row['id_siswa'] ?? 0);

            if (! $idSiswa) {
                continue;
            }

            $nilai = $this->nilai($row['nilai'] ?? null);

            RaporNilaiPts::updateOrCreate(
                ['id_nilai_pts' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_siswa' => $idSiswa,
                    'id_mapel' => $mapel,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'nilai' => $nilai,
                    'predikat' => Rapor::predikat($nilai, $kkm['kkm']),
                ]
            );
        }

        AuditLog::record('rapor.nilai.pts', 'Mapel #'.$mapel.' kelas #'.$kelas.' disimpan');

        return back()->with('success', 'Nilai PTS disimpan.');
    }

    public function pas(Request $request, int $mapel, int $kelas): Response
    {
        [$tp, $smt, $mapelModel, $kelasModel] = $this->context($request, $mapel, $kelas);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);
        $siswas = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $harian = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('p_rata_rata', 'id_siswa');

        $pts = RaporNilaiPts::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('nilai', 'id_siswa');

        $akhir = RaporNilaiAkhir::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy('id_siswa');

        return Inertia::render('Rapor/Pas', [
            'mapel' => ['id_mapel' => $mapelModel->id_mapel, 'nama_mapel' => $mapelModel->nama_mapel],
            'kelas' => ['id_kelas' => $kelasModel->id_kelas, 'nama_kelas' => $kelasModel->nama_kelas],
            'siswas' => $siswas,
            'harian' => $harian,
            'pts' => $pts,
            'akhir' => $akhir,
            'kkm' => $kkm,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
        ]);
    }

    public function savePas(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);

        $harian = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('p_rata_rata', 'id_siswa');

        $pts = RaporNilaiPts::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('nilai', 'id_siswa');

        foreach ((array) $request->input('rows', []) as $row) {
            $idSiswa = (int) ($row['id_siswa'] ?? 0);

            if (! $idSiswa) {
                continue;
            }

            $pas = $this->nilai($row['nilai'] ?? null);
            $akhir = Rapor::akhir($harian[$idSiswa] ?? null, $pts[$idSiswa] ?? null, $pas, $kkm);

            RaporNilaiAkhir::updateOrCreate(
                ['id_nilai_akhir' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_siswa' => $idSiswa,
                    'id_mapel' => $mapel,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'nilai' => $pas,
                    'akhir' => $akhir,
                    'predikat' => Rapor::predikat($akhir, $kkm['kkm']),
                ]
            );
        }

        AuditLog::record('rapor.nilai.pas', 'Mapel #'.$mapel.' kelas #'.$kelas.' disimpan');

        return back()->with('success', 'Nilai PAS dan nilai akhir disimpan.');
    }

    public function importPts(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);
        $students = $this->studentIndex($kelas, $tp->id_tp, $smt->id_smt);

        [$saved, $errors] = $this->readNilaiFile($request, $students, function (int $idSiswa, int $nilai) use ($mapel, $kelas, $tp, $smt, $kkm) {
            RaporNilaiPts::updateOrCreate(
                ['id_nilai_pts' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_siswa' => $idSiswa,
                    'id_mapel' => $mapel,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'nilai' => $nilai,
                    'predikat' => Rapor::predikat($nilai, $kkm['kkm']),
                ]
            );
        });

        $message = "Impor selesai: {$saved} nilai PTS disimpan.";
        AuditLog::record('rapor.nilai.pts.import', 'Mapel #'.$mapel.' kelas #'.$kelas.': '.$saved.' nilai disimpan');
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    public function importPas(Request $request, int $mapel, int $kelas): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);
        Rapor::ensureUnlocked($kelas, $tp->id_tp, $smt->id_smt);

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $kkm = Rapor::kkm($mapel, $kelas, $tp->id_tp, $smt->id_smt, $setting);
        $students = $this->studentIndex($kelas, $tp->id_tp, $smt->id_smt);

        $harian = RaporNilaiHarian::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('p_rata_rata', 'id_siswa');

        $pts = RaporNilaiPts::where('id_mapel', $mapel)
            ->where('id_kelas', $kelas)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->pluck('nilai', 'id_siswa');

        [$saved, $errors] = $this->readNilaiFile($request, $students, function (int $idSiswa, int $nilai) use ($mapel, $kelas, $tp, $smt, $kkm, $harian, $pts) {
            $akhir = Rapor::akhir($harian[$idSiswa] ?? null, $pts[$idSiswa] ?? null, $nilai, $kkm);

            RaporNilaiAkhir::updateOrCreate(
                ['id_nilai_akhir' => $mapel.$kelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                [
                    'id_siswa' => $idSiswa,
                    'id_mapel' => $mapel,
                    'id_kelas' => $kelas,
                    'id_tp' => $tp->id_tp,
                    'id_smt' => $smt->id_smt,
                    'nilai' => $nilai,
                    'akhir' => $akhir,
                    'predikat' => Rapor::predikat($akhir, $kkm['kkm']),
                ]
            );
        });

        $message = "Impor selesai: {$saved} nilai PAS disimpan.";
        AuditLog::record('rapor.nilai.pas.import', 'Mapel #'.$mapel.' kelas #'.$kelas.': '.$saved.' nilai disimpan');
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    public function templatePts(int $mapel, int $kelas)
    {
        return $this->templateNilai('pts', $mapel, $kelas);
    }

    public function templatePas(int $mapel, int $kelas)
    {
        return $this->templateNilai('pas', $mapel, $kelas);
    }

    private function templateNilai(string $jenis, int $mapel, int $kelas)
    {
        [$tp, $smt] = $this->period();

        $mapelModel = Mapel::findOrFail($mapel);
        $kelasModel = Kelas::findOrFail($kelas);
        $students = $this->students($kelas, $tp->id_tp, $smt->id_smt);

        $rows = array_map(fn ($siswa) => [$siswa['nisn'] ?? '', $siswa['nis'] ?? '', $siswa['nama'], ''], $students);
        $filename = 'template-nilai-'.$jenis.'-'.$mapelModel->id_mapel.'-'.$kelasModel->id_kelas.'.xlsx';

        return XlsxExport::download($filename, ['nisn', 'nis', 'nama', 'nilai'], $rows, strtoupper($jenis));
    }

    private function readNilaiFile(Request $request, array $students, callable $save): array
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        [$header, $rows] = SheetImport::read($request->file('file'), ['nilai', 'nisn', 'nis']);

        if (! in_array('nilai', $header, true) || (! in_array('nisn', $header, true) && ! in_array('nis', $header, true))) {
            return [0, ['Kolom wajib tidak ditemukan: nilai dan salah satu dari nisn / nis.']];
        }

        $saved = 0;
        $errors = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $nisn = trim((string) ($data['nisn'] ?? ''));
            $nis = trim((string) ($data['nis'] ?? ''));
            $nilai = trim((string) ($data['nilai'] ?? ''));

            if ($nilai === '') {
                continue;
            }

            if (! is_numeric($nilai) || (float) $nilai < 0 || (float) $nilai > 100) {
                $errors[] = "Baris {$line}: nilai harus berupa angka 0 sampai 100.";

                continue;
            }

            $idSiswa = Rapor::studentIdByNomor($students, $nisn, $nis);

            if (! $idSiswa) {
                $label = $nisn !== '' ? $nisn : ($nis !== '' ? $nis : 'kosong');
                $errors[] = "Baris {$line}: siswa dengan NISN/NIS {$label} tidak ditemukan di kelas ini.";

                continue;
            }

            $save((int) $idSiswa, (int) round((float) $nilai));
            $saved++;
        }

        return [$saved, $errors];
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

    private function context(Request $request, int $mapel, int $kelas): array
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        abort_unless(Rapor::hasMapelAccess($user, $guruId, $mapel, $kelas, $tp->id_tp, $smt->id_smt), 403);

        $mapelModel = Mapel::findOrFail($mapel);
        $kelasModel = Kelas::findOrFail($kelas);

        return [$tp, $smt, $mapelModel, $kelasModel];
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

    private function kikd(int $mapel, int $kelas, int $tp, int $smt): array
    {
        $rows = RaporKikd::where('id_mapel_kelas', $mapel.$kelas)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->get();

        $p = array_fill(0, 8, '');
        $k = array_fill(0, 8, '');

        foreach ($rows as $row) {
            $index = (int) substr((string) $row->id_kikd, -1) - 1;

            if ($index < 0 || $index > 7) {
                continue;
            }

            if ((int) $row->aspek === 1) {
                $p[$index] = $row->materi_kikd;
            } else {
                $k[$index] = $row->materi_kikd;
            }
        }

        return ['p' => $p, 'k' => $k];
    }

    private function nilai($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function counts($query, int $tp, int $smt): array
    {
        return $query
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->selectRaw('id_mapel, id_kelas, COUNT(*) as total')
            ->groupBy('id_mapel', 'id_kelas')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id_mapel.'-'.$row->id_kelas => (int) $row->total])
            ->all();
    }

    private function period(): array
    {
        [$tp, $smt] = Rapor::period();
        abort_if(! $tp || ! $smt, 409, 'Tahun pelajaran / semester aktif belum diatur.');

        return [$tp, $smt];
    }
}
