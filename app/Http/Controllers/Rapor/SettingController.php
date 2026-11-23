<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\JabatanGuru;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RaporAdminSetting;
use App\Models\RaporKkm;
use App\Support\AuditLog;
use App\Support\Rapor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const SETTING_FIELDS = [
        'kkm_tunggal', 'kkm', 'bobot_ph', 'bobot_pts', 'bobot_pas', 'bobot_absen',
        'tgl_rapor_pts', 'tgl_rapor_akhir', 'tgl_rapor_kelas_akhir', 'nip_kepsek', 'nip_walikelas',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);
        [$tp, $smt] = $this->period();

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);

        $classes = Kelas::with('waliKelas:id_guru,nama_guru')
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas')
            ->get()
            ->map(fn (Kelas $kelas) => [
                'id_kelas' => $kelas->id_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'guru_id' => $kelas->guru_id,
                'wali' => $kelas->waliKelas?->nama_guru,
            ]);

        $assignments = [];

        foreach (JabatanGuru::where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $jabatan) {
            $mapelRows = Rapor::mapelKelas($jabatan->mapel_kelas);

            if (! $mapelRows) {
                continue;
            }

            $assignments[] = [
                'id_guru' => (int) $jabatan->id_guru,
                'mapel' => $mapelRows,
            ];
        }

        return Inertia::render('Rapor/Setting', [
            'setting' => $setting ? $setting->only(self::SETTING_FIELDS) : null,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'semesterNama' => $smt->nama_smt,
            'classes' => $classes,
            'gurus' => Guru::orderBy('nama_guru')->get(['id_guru', 'nama_guru']),
            'mapels' => Mapel::orderBy('urutan')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kelompok']),
            'assignments' => $assignments,
        ]);
    }

    public function saveSetting(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        [$tp, $smt] = $this->period();

        $data = $request->validate([
            'kkm_tunggal' => ['required', 'integer', 'in:0,1'],
            'kkm' => ['required', 'integer', 'between:0,100'],
            'bobot_ph' => ['required', 'integer', 'between:0,100'],
            'bobot_pts' => ['required', 'integer', 'between:0,100'],
            'bobot_pas' => ['required', 'integer', 'between:0,100'],
            'bobot_absen' => ['nullable', 'integer', 'between:0,100'],
            'tgl_rapor_pts' => ['nullable', 'string', 'max:100'],
            'tgl_rapor_akhir' => ['nullable', 'string', 'max:100'],
            'tgl_rapor_kelas_akhir' => ['nullable', 'string', 'max:100'],
            'nip_kepsek' => ['nullable', 'integer'],
            'nip_walikelas' => ['nullable', 'integer'],
        ]);

        if ((int) $data['bobot_ph'] + (int) $data['bobot_pts'] + (int) $data['bobot_pas'] !== 100) {
            return back()->withErrors(['bobot_pas' => 'Total bobot PH, PTS, dan PAS harus 100.']);
        }

        $data['id_setting'] = $tp->id_tp.$smt->id_smt;
        $data['id_tp'] = $tp->id_tp;
        $data['id_smt'] = $smt->id_smt;
        $data['bobot_absen'] = $data['bobot_absen'] ?? 0;

        RaporAdminSetting::updateOrCreate(['id_setting' => $data['id_setting']], $data);

        AuditLog::record('rapor.setting', 'Pengaturan rapor '.$tp->tahun.' semester '.$smt->nama_smt.' disimpan');

        return back()->with('success', 'Pengaturan rapor disimpan.');
    }

    public function saveWali(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        [$tp, $smt] = $this->period();

        $data = $request->validate([
            'id_kelas' => ['required', 'integer'],
            'guru_id' => ['nullable', 'integer', 'exists:master_guru,id_guru'],
        ]);

        Kelas::where('id_kelas', $data['id_kelas'])
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->update(['guru_id' => $data['guru_id']]);

        AuditLog::record('rapor.setting', 'Wali kelas #'.$data['id_kelas'].' → guru #'.($data['guru_id'] ?? 0));

        return back()->with('success', 'Wali kelas diperbarui.');
    }

    public function savePengampu(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        [$tp, $smt] = $this->period();

        $data = $request->validate([
            'id_guru' => ['required', 'integer', 'exists:master_guru,id_guru'],
            'mapel' => ['array'],
            'mapel.*.id_mapel' => ['required', 'integer'],
            'mapel.*.kelas' => ['array'],
            'mapel.*.kelas.*' => ['integer'],
        ]);

        $mapelNames = Mapel::pluck('nama_mapel', 'id_mapel');
        $mapels = [];

        foreach ((array) $request->input('mapel', []) as $row) {
            $kelas = array_values(array_unique(array_map('intval', (array) ($row['kelas'] ?? []))));

            if (! $kelas) {
                continue;
            }

            $idMapel = (int) $row['id_mapel'];
            $mapels[] = [
                'id_mapel' => $idMapel,
                'nama_mapel' => $mapelNames[$idMapel] ?? '',
                'kelas_mapel' => array_map(fn (int $id) => ['kelas' => $id], $kelas),
            ];
        }

        $idGuru = (int) $data['id_guru'];
        $idJabatan = $idGuru.$tp->id_tp.$smt->id_smt;
        $existing = JabatanGuru::find($idJabatan);
        $wali = Kelas::where('guru_id', $idGuru)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->value('id_kelas');

        if (! $mapels && ! $existing) {
            return back()->with('success', 'Tidak ada penugasan untuk disimpan.');
        }

        AuditLog::record('rapor.setting', 'Penugasan pengampu guru #'.$idGuru.' ('.count($mapels).' mapel) disimpan');

        JabatanGuru::updateOrCreate(
            ['id_jabatan_guru' => $idJabatan],
            [
                'id_guru' => $idGuru,
                'id_jabatan' => 2,
                'id_kelas' => $wali ?? 0,
                'mapel_kelas' => serialize($mapels),
                'ekstra_kelas' => $existing->ekstra_kelas ?? serialize([]),
                'id_tp' => $tp->id_tp,
                'id_smt' => $smt->id_smt,
            ]
        );

        return back()->with('success', 'Penugasan guru pengampu disimpan.');
    }

    public function kkm(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);
        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);

        $kelasNames = Kelas::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas')
            ->pluck('nama_kelas', 'id_kelas');

        if ($user->isAdmin()) {
            $mapels = Mapel::orderBy('urutan')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel']);

            if ($kelas = $request->integer('kelas')) {
                $kelasNames = $kelasNames->only([$kelas]);
            }

            $rows = [];

            foreach ($kelasNames as $idKelas => $namaKelas) {
                foreach ($mapels as $mapel) {
                    $rows[] = $this->kkmRow((int) $mapel->id_mapel, $mapel->nama_mapel, (int) $idKelas, $namaKelas, $tp->id_tp, $smt->id_smt, $setting);
                }
            }
        } else {
            $rows = [];

            foreach (Rapor::assignments($guruId, $tp->id_tp, $smt->id_smt) as $mapel) {
                foreach ($mapel['kelas'] as $idKelas) {
                    if (! isset($kelasNames[$idKelas])) {
                        continue;
                    }

                    $rows[] = $this->kkmRow($mapel['id_mapel'], $mapel['nama_mapel'], $idKelas, $kelasNames[$idKelas], $tp->id_tp, $smt->id_smt, $setting);
                }
            }
        }

        return Inertia::render('Rapor/Kkm', [
            'rows' => $rows,
            'kelasOptions' => Kelas::where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas']),
            'setting' => $setting ? $setting->only(['kkm_tunggal', 'kkm', 'bobot_ph', 'bobot_pts', 'bobot_pas', 'bobot_absen']) : null,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function saveKkm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_mapel' => ['required', 'integer'],
            'rows.*.id_kelas' => ['required', 'integer'],
            'rows.*.kkm' => ['required', 'integer', 'between:0,100'],
            'rows.*.bobot_ph' => ['required', 'integer', 'between:0,100'],
            'rows.*.bobot_pts' => ['required', 'integer', 'between:0,100'],
            'rows.*.bobot_pas' => ['required', 'integer', 'between:0,100'],
            'rows.*.bobot_absen' => ['nullable', 'integer', 'between:0,100'],
            'rows.*.beban_jam' => ['nullable', 'integer', 'between:0,24'],
        ]);

        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        DB::transaction(function () use ($data, $tp, $smt, $user, $guruId) {
            foreach ($data['rows'] as $row) {
                $idMapel = (int) $row['id_mapel'];
                $idKelas = (int) $row['id_kelas'];

                if (! Rapor::hasMapelAccess($user, $guruId, $idMapel, $idKelas, $tp->id_tp, $smt->id_smt)) {
                    continue;
                }

                RaporKkm::updateOrCreate(
                    ['id_kkm' => $idMapel.$idKelas.$tp->id_tp.$smt->id_smt.'1'],
                    [
                        'kkm' => $row['kkm'],
                        'bobot_ph' => $row['bobot_ph'],
                        'bobot_pts' => $row['bobot_pts'],
                        'bobot_pas' => $row['bobot_pas'],
                        'bobot_absen' => $row['bobot_absen'] ?? 0,
                        'beban_jam' => $row['beban_jam'] ?? 0,
                        'id_tp' => $tp->id_tp,
                        'id_smt' => $smt->id_smt,
                        'jenis' => 1,
                        'id_kelas' => $idKelas,
                        'id_mapel' => $idMapel,
                    ]
                );
            }
        });

        AuditLog::record('rapor.kkm', count($data['rows']).' baris KKM & bobot disimpan');

        return back()->with('success', 'KKM dan bobot nilai disimpan.');
    }

    private function kkmRow(int $idMapel, string $namaMapel, int $idKelas, string $namaKelas, int $tp, int $smt, ?RaporAdminSetting $setting): array
    {
        $stored = RaporKkm::where('id_kkm', $idMapel.$idKelas.$tp.$smt.'1')->first();

        // Nilai per mata pelajaran tetap ditampilkan walau KKM tunggal aktif,
        // supaya menyimpan ulang halaman ini tidak menimpa KKM yang tersimpan.
        $kkm = $stored ? [
            'kkm' => (int) $stored->kkm,
            'bobot_ph' => (int) $stored->bobot_ph,
            'bobot_pts' => (int) $stored->bobot_pts,
            'bobot_pas' => (int) $stored->bobot_pas,
            'bobot_absen' => (int) $stored->bobot_absen,
            'beban_jam' => (int) $stored->beban_jam,
        ] : Rapor::kkm($idMapel, $idKelas, $tp, $smt, $setting);

        return [
            'id_mapel' => $idMapel,
            'nama_mapel' => $namaMapel,
            'id_kelas' => $idKelas,
            'nama_kelas' => $namaKelas,
            'kkm' => $kkm['kkm'],
            'bobot_ph' => $kkm['bobot_ph'],
            'bobot_pts' => $kkm['bobot_pts'],
            'bobot_pas' => $kkm['bobot_pas'],
            'bobot_absen' => $kkm['bobot_absen'],
            'beban_jam' => $kkm['beban_jam'],
        ];
    }

    private function period(): array
    {
        [$tp, $smt] = Rapor::period();
        abort_if(! $tp || ! $smt, 409, 'Tahun pelajaran / semester aktif belum diatur.');

        return [$tp, $smt];
    }
}
