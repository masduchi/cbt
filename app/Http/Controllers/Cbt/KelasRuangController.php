<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\KelasRuang;
use App\Models\Ruang;
use App\Models\Semester;
use App\Models\Sesi;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KelasRuangController extends Controller
{
    public function index(): Response
    {
        [$tp, $smt] = $this->activePeriod();

        $kelas = $tp && $smt
            ? Kelas::with('level:id_level,level')
                ->withCount(['siswa as siswa_count' => fn ($q) => $q->where('kelas_siswa.id_tp', $tp)->where('kelas_siswa.id_smt', $smt)])
                ->where('id_tp', $tp)
                ->where('id_smt', $smt)
                ->orderBy('nama_kelas')
                ->get()
            : collect();

        $assignments = $tp && $smt
            ? KelasRuang::where('id_tp', $tp)->where('id_smt', $smt)->get()
                ->keyBy('id_kelas')
            : collect();

        return Inertia::render('Cbt/KelasRuang', [
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id_kelas' => $item->id_kelas,
                'nama_kelas' => $item->nama_kelas,
                'level' => $item->level?->level,
                'siswa_count' => $item->siswa_count,
                'id_ruang' => $assignments->get($item->id_kelas)?->id_ruang,
                'id_sesi' => $assignments->get($item->id_kelas)?->id_sesi,
            ]),
            'ruang' => Ruang::orderBy('nama_ruang')->get(['id_ruang', 'nama_ruang']),
            'sesi' => Sesi::orderBy('waktu_mulai')->get(['id_sesi', 'nama_sesi', 'waktu_mulai', 'waktu_akhir']),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.id_kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'assignments.*.id_ruang' => ['required', 'integer', 'exists:cbt_ruang,id_ruang'],
            'assignments.*.id_sesi' => ['required', 'integer', 'exists:cbt_sesi,id_sesi'],
        ]);

        $count = 0;

        DB::transaction(function () use ($data, $tp, $smt, &$count) {
            foreach ($data['assignments'] as $row) {
                DB::table('cbt_kelas_ruang')->updateOrInsert(
                    ['id_kelas_ruang' => $row['id_kelas'].$tp.$smt],
                    [
                        'id_kelas' => $row['id_kelas'],
                        'id_ruang' => $row['id_ruang'],
                        'id_sesi' => $row['id_sesi'],
                        'id_tp' => $tp,
                        'id_smt' => $smt,
                        'set_siswa' => 1,
                    ]
                );

                $siswaIds = DB::table('kelas_siswa')
                    ->where('id_kelas', $row['id_kelas'])
                    ->where('id_tp', $tp)->where('id_smt', $smt)
                    ->pluck('id_siswa');

                foreach ($siswaIds as $siswaId) {
                    DB::table('cbt_sesi_siswa')->updateOrInsert(
                        ['siswa_id' => $siswaId],
                        [
                            'kelas_id' => $row['id_kelas'],
                            'ruang_id' => $row['id_ruang'],
                            'sesi_id' => $row['id_sesi'],
                            'tp_id' => $tp,
                            'smt_id' => $smt,
                        ]
                    );
                }

                $count++;
            }
        });

        AuditLog::record('cbt.kelasruang', 'Penempatan ruang & sesi disimpan untuk '.$count.' kelas');

        return back()->with('success', "Penempatan ruang & sesi disimpan untuk {$count} kelas.");
    }

    public function siswaIndex(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $kelasId = (int) $request->query('kelas');

        $kelas = $tp && $smt
            ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
            : collect();

        $students = [];

        if ($kelasId && $tp && $smt) {
            $assignment = KelasRuang::where('id_kelas', $kelasId)->where('id_tp', $tp)->where('id_smt', $smt)->first();

            $overrides = DB::table('cbt_sesi_siswa')
                ->where('kelas_id', $kelasId)
                ->where('tp_id', $tp)
                ->where('smt_id', $smt)
                ->get()
                ->keyBy('siswa_id');

            $students = DB::table('kelas_siswa')
                ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
                ->where('kelas_siswa.id_kelas', $kelasId)
                ->where('kelas_siswa.id_tp', $tp)
                ->where('kelas_siswa.id_smt', $smt)
                ->orderBy('master_siswa.nama')
                ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nama'])
                ->map(function ($row) use ($assignment, $overrides) {
                    $override = $overrides->get($row->id_siswa);

                    return [
                        'id_siswa' => (int) $row->id_siswa,
                        'nis' => $row->nis,
                        'nama' => $row->nama,
                        'ruang_id' => $override->ruang_id ?? $assignment?->id_ruang,
                        'sesi_id' => $override->sesi_id ?? $assignment?->id_sesi,
                    ];
                })
                ->all();
        }

        return Inertia::render('Cbt/KelasRuangSiswa', [
            'kelas' => $kelas,
            'selectedKelas' => $kelasId ?: null,
            'students' => $students,
            'ruang' => Ruang::orderBy('nama_ruang')->get(['id_ruang', 'nama_ruang']),
            'sesi' => Sesi::orderBy('waktu_mulai')->get(['id_sesi', 'nama_sesi']),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function saveSiswa(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer', 'exists:master_siswa,id_siswa'],
            'rows.*.ruang_id' => ['required', 'integer', 'exists:cbt_ruang,id_ruang'],
            'rows.*.sesi_id' => ['required', 'integer', 'exists:cbt_sesi,id_sesi'],
        ]);

        DB::transaction(function () use ($data, $tp, $smt) {
            foreach ($data['rows'] as $row) {
                DB::table('cbt_sesi_siswa')->updateOrInsert(
                    ['siswa_id' => $row['id_siswa']],
                    [
                        'kelas_id' => $data['kelas_id'],
                        'ruang_id' => $row['ruang_id'],
                        'sesi_id' => $row['sesi_id'],
                        'tp_id' => $tp,
                        'smt_id' => $smt,
                    ]
                );
            }
        });

        AuditLog::record('cbt.kelasruang', 'Penempatan ruang & sesi '.count($data['rows']).' siswa kelas #'.$data['kelas_id'].' disimpan');

        return back()->with('success', 'Penempatan ruang & sesi per siswa disimpan.');
    }

    public function release(int $id): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        KelasRuang::where('id_kelas', $id)->where('id_tp', $tp)->where('id_smt', $smt)->delete();
        DB::table('cbt_sesi_siswa')
            ->where('kelas_id', $id)->where('tp_id', $tp)->where('smt_id', $smt)
            ->delete();

        AuditLog::record('cbt.kelasruang', 'Penempatan kelas #'.$id.' dilepas');

        return back()->with('success', 'Penempatan kelas dilepas.');
    }

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel(?int $tp, ?int $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
