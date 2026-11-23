<?php

namespace App\Http\Controllers\Elearning;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\KelasJadwalKbm;
use App\Models\KelasJadwalMapel;
use App\Models\Mapel;
use App\Support\AuditLog;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JadwalPelajaranController extends Controller
{
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;

        $classes = $tp && $smt
            ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
            : collect();

        $kelasId = (int) $request->query('kelas', $classes->first()->id_kelas ?? 0);
        $kelas = $classes->firstWhere('id_kelas', $kelasId) ?? $classes->first();
        $kelasId = $kelas?->id_kelas ?? 0;

        $kbm = $kelasId
            ? KelasJadwalKbm::where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)->first()
            : null;

        $istirahat = [];
        $rawIstirahat = @unserialize((string) $kbm?->istirahat, ['allowed_classes' => false]);

        if (is_array($rawIstirahat)) {
            foreach ($rawIstirahat as $item) {
                if (! is_array($item) || empty($item['ist'])) {
                    continue;
                }

                $istirahat[] = [
                    'jam_ke' => (int) $item['ist'],
                    'durasi' => (int) ($item['dur'] ?? 0),
                ];
            }
        }

        $template = $kelasId
            ? KelasJadwalMapel::where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)
                ->get(['id_hari', 'jam_ke', 'id_mapel'])
                ->map(fn (KelasJadwalMapel $row) => [
                    'hari' => (int) $row->id_hari,
                    'jam_ke' => (int) $row->jam_ke,
                    'id_mapel' => $row->id_mapel ? (int) $row->id_mapel : null,
                ])
                ->values()
            : collect();

        return Inertia::render('Elearning/JadwalPelajaran', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'kbm' => [
                'jam_pel' => (int) ($kbm?->kbm_jam_pel ?? 40),
                'jam_mulai' => (string) ($kbm?->kbm_jam_mulai ?? '07:00'),
                'jml_mapel' => (int) ($kbm?->kbm_jml_mapel_hari ?? 8),
                'istirahat' => $istirahat,
            ],
            'template' => $template,
            'mapels' => Mapel::orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kode']),
            'hari' => collect(self::HARI)->map(fn ($nama, $id) => ['id' => $id, 'nama' => $nama])->values(),
            'periode' => $tahun && $semester ? $tahun->tahun.' · '.$semester->smt : '-',
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        [$tahun, $semester] = Period::current();

        if (! $tahun || ! $semester) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'jam_pel' => ['required', 'integer', 'min:5', 'max:120'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jml_mapel' => ['required', 'integer', 'min:1', 'max:12'],
            'istirahat' => ['array', 'max:4'],
            'istirahat.*.jam_ke' => ['nullable', 'integer', 'min:1', 'max:12'],
            'istirahat.*.durasi' => ['nullable', 'integer', 'min:5', 'max:120'],
            'template' => ['array'],
            'template.*.jam_ke' => ['required', 'integer', 'min:1', 'max:12', 'distinct'],
            'template.*.mapel' => ['array'],
            'template.*.mapel.*' => ['nullable', 'integer', 'exists:master_mapel,id_mapel'],
        ]);

        $kelasId = (int) $data['kelas'];
        $kelas = Kelas::where('id_tp', $tahun->id_tp)->where('id_smt', $semester->id_smt)->find($kelasId);

        if (! $kelas) {
            throw ValidationException::withMessages([
                'kelas' => 'Kelas tidak termasuk periode yang dipilih.',
            ]);
        }

        $breaks = [];
        foreach ($data['istirahat'] ?? [] as $item) {
            $jam = (int) ($item['jam_ke'] ?? 0);
            $durasi = (int) ($item['durasi'] ?? 0);

            if (! $jam && ! $durasi) {
                continue;
            }

            if (! $jam || ! $durasi) {
                throw ValidationException::withMessages([
                    'istirahat' => 'Lengkapi jam ke dan durasi untuk setiap istirahat.',
                ]);
            }

            if ($jam > (int) $data['jml_mapel']) {
                throw ValidationException::withMessages([
                    'istirahat' => 'Jam istirahat tidak boleh melebihi jumlah jam per hari.',
                ]);
            }

            if (isset($breaks[$jam])) {
                throw ValidationException::withMessages([
                    'istirahat' => 'Jam istirahat tidak boleh duplikat.',
                ]);
            }

            $breaks[$jam] = $durasi;
        }

        ksort($breaks);
        $istirahat = [];

        foreach ($breaks as $jam => $durasi) {
            $istirahat[] = ['ist' => $jam, 'dur' => $durasi];
        }

        DB::transaction(function () use ($data, $kelasId, $tahun, $semester, $istirahat) {
            DB::table('kelas_jadwal_kbm')->updateOrInsert(
                ['id_kbm' => $tahun->id_tp.$semester->id_smt.$kelasId],
                [
                    'id_tp' => $tahun->id_tp,
                    'id_smt' => $semester->id_smt,
                    'id_kelas' => $kelasId,
                    'kbm_jam_pel' => (int) $data['jam_pel'],
                    'kbm_jam_mulai' => $data['jam_mulai'],
                    'kbm_jml_mapel_hari' => (int) $data['jml_mapel'],
                    'istirahat' => serialize($istirahat),
                ]
            );

            DB::table('kelas_jadwal_mapel')
                ->where('id_tp', $tahun->id_tp)
                ->where('id_smt', $semester->id_smt)
                ->where('id_kelas', $kelasId)
                ->delete();

            $rows = [];

            foreach ($data['template'] ?? [] as $row) {
                $jam = (int) $row['jam_ke'];

                if ($jam < 1 || $jam > (int) $data['jml_mapel']) {
                    continue;
                }

                foreach ($row['mapel'] ?? [] as $hari => $idMapel) {
                    $hari = (int) $hari;

                    if ($hari < 1 || $hari > 6 || ! $idMapel) {
                        continue;
                    }

                    $rows[] = [
                        'id_jadwal' => (int) ($tahun->id_tp.$semester->id_smt.$kelasId.$hari.$jam),
                        'id_tp' => $tahun->id_tp,
                        'id_smt' => $semester->id_smt,
                        'id_kelas' => $kelasId,
                        'id_hari' => $hari,
                        'jam_ke' => $jam,
                        'id_mapel' => (int) $idMapel,
                    ];
                }
            }

            if ($rows) {
                DB::table('kelas_jadwal_mapel')->insert($rows);
            }
        });

        AuditLog::record('elearning.jadwal-pelajaran', 'Jadwal pelajaran kelas #'.$kelasId.' disimpan');

        return back()->with('success', 'Jadwal pelajaran berhasil disimpan.');
    }
}
