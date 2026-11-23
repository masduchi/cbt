<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\JabatanGuru;
use App\Models\Kelas;
use App\Models\RaporNaik;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RolloverController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tahun = TahunPelajaran::orderByDesc('tahun')->orderByDesc('id_tp')->get();
        $semester = Semester::orderBy('id_smt')->get();

        $sourceTp = (int) ($request->query('sumber_tp') ?: ($tahun->firstWhere('active', 1)?->id_tp ?? $tahun->first()?->id_tp ?? 0));
        $sourceSmt = (int) ($request->query('sumber_smt') ?: ($semester->firstWhere('active', 1)?->id_smt ?? 1));
        $sourceYear = $tahun->firstWhere('id_tp', $sourceTp);

        $classes = collect();
        if ($sourceTp) {
            $classes = Kelas::query()
                ->leftJoin('master_guru', 'master_guru.id_guru', '=', 'master_kelas.guru_id')
                ->where('master_kelas.id_tp', $sourceTp)
                ->where('master_kelas.id_smt', $sourceSmt)
                ->whereNull('master_kelas.deleted_at')
                ->orderBy('master_kelas.nama_kelas')
                ->get([
                    'master_kelas.id_kelas',
                    'master_kelas.nama_kelas',
                    'master_kelas.level_id',
                    'master_kelas.guru_id',
                    'master_guru.nama_guru as wali',
                ]);

            $enrolled = DB::table('kelas_siswa')
                ->where('id_tp', $sourceTp)
                ->where('id_smt', $sourceSmt)
                ->whereIn('id_kelas', $classes->pluck('id_kelas'))
                ->get(['id_kelas', 'id_siswa']);

            $naik = RaporNaik::where('id_tp', $sourceTp)
                ->where('id_smt', $sourceSmt)
                ->pluck('naik', 'id_siswa');

            $counts = $enrolled->groupBy('id_kelas')->map(function ($rows) use ($naik) {
                $promoted = $rows->filter(fn ($row) => (int) ($naik[$row->id_siswa] ?? 1) === 1);

                return ['total' => $rows->count(), 'naik' => $promoted->count()];
            });

            $classes = $classes->map(fn ($class) => [
                'id_kelas' => (int) $class->id_kelas,
                'nama' => (string) $class->nama_kelas,
                'level' => (int) $class->level_id,
                'wali' => $class->wali,
                'guru_id' => (int) $class->guru_id,
                'total' => $counts[$class->id_kelas]['total'] ?? 0,
                'naik' => $counts[$class->id_kelas]['naik'] ?? 0,
                'target_nama' => $this->nextName((string) $class->nama_kelas),
                'target_level' => $this->nextLevel((int) $class->level_id),
            ]);
        }

        $suggestion = $this->nextYearName((string) ($sourceYear?->tahun ?? ''));
        while ($suggestion !== '' && TahunPelajaran::where('tahun', $suggestion)->exists()) {
            $suggestion = $this->nextYearName($suggestion);
        }

        return Inertia::render('Master/Rollover', [
            'years' => $tahun,
            'semesters' => $semester,
            'sourceTp' => $sourceTp,
            'sourceSmt' => $sourceSmt,
            'suggestion' => $suggestion,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'sumber_tp' => ['required', 'integer', 'exists:master_tp,id_tp'],
            'sumber_smt' => ['required', 'integer', 'exists:master_smt,id_smt'],
            'tahun' => ['required', 'string', 'max:20', Rule::unique('master_tp', 'tahun')],
            'target_smt' => ['required', 'integer', 'exists:master_smt,id_smt'],
            'classes' => ['array'],
            'classes.*.id_kelas' => ['required', 'integer'],
            'classes.*.ikut' => ['boolean'],
            'classes.*.target_nama' => ['nullable', 'string', 'max:50'],
            'classes.*.target_level' => ['nullable', 'integer', 'min:1', 'max:13'],
            'salin_pengampu' => ['boolean'],
            'aktifkan' => ['boolean'],
        ]);

        $sourceTp = (int) $data['sumber_tp'];
        $sourceSmt = (int) $data['sumber_smt'];
        $targetSmt = (int) $data['target_smt'];

        $sourceClasses = Kelas::where('id_tp', $sourceTp)
            ->where('id_smt', $sourceSmt)
            ->get()
            ->keyBy('id_kelas');

        $selected = collect($data['classes'] ?? [])
            ->filter(fn ($row) => ! empty($row['ikut']))
            ->keyBy('id_kelas');

        foreach ($selected as $id => $row) {
            if (! isset($sourceClasses[(int) $id])) {
                throw ValidationException::withMessages(['classes' => 'Kelas sumber tidak ditemukan pada periode yang dipilih.']);
            }
            if (trim((string) ($row['target_nama'] ?? '')) === '') {
                throw ValidationException::withMessages(['classes' => 'Nama kelas tujuan wajib diisi untuk setiap kelas yang disertakan.']);
            }
        }

        $summary = DB::transaction(function () use ($data, $sourceTp, $sourceSmt, $targetSmt, $sourceClasses, $selected) {
            $target = TahunPelajaran::create([
                'tahun' => $data['tahun'],
                'active' => 0,
            ]);

            $classMap = [];

            foreach ($selected as $id => $row) {
                $source = $sourceClasses[(int) $id];

                $created = Kelas::create([
                    'id_tp' => $target->id_tp,
                    'id_smt' => $targetSmt,
                    'nama_kelas' => trim((string) $row['target_nama']),
                    'kode_kelas' => '',
                    'jurusan_id' => $source->jurusan_id,
                    'level_id' => (int) ($row['target_level'] ?: $this->nextLevel((int) $source->level_id)),
                    'guru_id' => $source->guru_id,
                    'siswa_id' => null,
                    'jumlah_siswa' => 0,
                    'set_siswa' => 0,
                ]);

                $classMap[(int) $source->id_kelas] = (int) $created->id_kelas;
            }

            $naik = RaporNaik::where('id_tp', $sourceTp)
                ->where('id_smt', $sourceSmt)
                ->pluck('naik', 'id_siswa');

            $enrollRows = [];
            $skipped = 0;

            if ($classMap) {
                $enrolled = DB::table('kelas_siswa')
                    ->where('id_tp', $sourceTp)
                    ->where('id_smt', $sourceSmt)
                    ->whereIn('id_kelas', array_keys($classMap))
                    ->get(['id_siswa', 'id_kelas']);

                $nextId = (int) DB::table('kelas_siswa')->max('id_kelas_siswa');

                foreach ($enrolled as $row) {
                    if ((int) ($naik[$row->id_siswa] ?? 1) !== 1) {
                        $skipped++;

                        continue;
                    }

                    $enrollRows[] = [
                        'id_kelas_siswa' => ++$nextId,
                        'id_tp' => $target->id_tp,
                        'id_smt' => $targetSmt,
                        'id_siswa' => $row->id_siswa,
                        'id_kelas' => $classMap[(int) $row->id_kelas],
                    ];
                }

                if ($enrollRows) {
                    DB::table('kelas_siswa')->insertOrIgnore($enrollRows);
                }

                $totals = collect($enrollRows)->countBy('id_kelas');
                foreach ($classMap as $newId) {
                    Kelas::where('id_kelas', $newId)->update(['jumlah_siswa' => (int) ($totals[$newId] ?? 0)]);
                }
            }

            $pengampu = 0;

            if (! empty($data['salin_pengampu']) && $classMap) {
                $assignments = JabatanGuru::where('id_tp', $sourceTp)->where('id_smt', $sourceSmt)->get();

                foreach ($assignments as $assignment) {
                    $mapels = @unserialize((string) $assignment->mapel_kelas, ['allowed_classes' => false]);
                    $mapels = is_array($mapels) ? $mapels : [];

                    foreach ($mapels as &$mapel) {
                        $mapped = [];
                        foreach ((array) ($mapel['kelas_mapel'] ?? []) as $entry) {
                            $old = (int) ($entry['kelas'] ?? 0);
                            if (isset($classMap[$old])) {
                                $mapped[] = ['kelas' => $classMap[$old]];
                            }
                        }
                        $mapel['kelas_mapel'] = $mapped;
                    }
                    unset($mapel);

                    $mapels = array_values(array_filter($mapels, fn ($mapel) => ! empty($mapel['kelas_mapel'])));

                    JabatanGuru::updateOrCreate(
                        ['id_jabatan_guru' => (int) $assignment->id_guru.$target->id_tp.$targetSmt],
                        [
                            'id_guru' => $assignment->id_guru,
                            'id_jabatan' => $assignment->id_jabatan,
                            'id_kelas' => $classMap[(int) $assignment->id_kelas] ?? 0,
                            'mapel_kelas' => serialize($mapels),
                            'ekstra_kelas' => $assignment->ekstra_kelas ?? serialize([]),
                            'id_tp' => $target->id_tp,
                            'id_smt' => $targetSmt,
                        ]
                    );

                    $pengampu++;
                }
            }

            if (! empty($data['aktifkan'])) {
                TahunPelajaran::where('active', 1)->update(['active' => 0]);
                $target->update(['active' => 1]);
            }

            return [
                'tahun' => $target->tahun,
                'kelas' => count($classMap),
                'siswa' => count($enrollRows),
                'tinggal' => $skipped,
                'pengampu' => $pengampu,
            ];
        });

        AuditLog::record('master.rollover', sprintf(
            'Rollover ke %s: %d kelas, %d siswa, %d tinggal kelas, %d pengampu',
            $summary['tahun'], $summary['kelas'], $summary['siswa'], $summary['tinggal'], $summary['pengampu']
        ));

        $message = "Tahun {$summary['tahun']} dibuat: {$summary['kelas']} kelas dan {$summary['siswa']} siswa dipindahkan.";
        if ($summary['tinggal'] > 0) {
            $message .= " {$summary['tinggal']} siswa berstatus tinggal kelas tidak disertakan.";
        }
        if ($summary['pengampu'] > 0) {
            $message .= " {$summary['pengampu']} penugasan guru disalin.";
        }

        return redirect('/master/tahun')->with('success', $message);
    }

    private function nextYearName(string $current): string
    {
        if (preg_match('/(\d{4})\s*\/\s*(\d{4})/', $current, $match)) {
            return ((int) $match[1] + 1).'/'.((int) $match[2] + 1);
        }

        return '';
    }

    private function nextName(string $name): string
    {
        if (preg_match('/^\s*XII\b/i', $name)) {
            return preg_replace('/^\s*XII/i', 'XII', $name);
        }

        if (preg_match('/^\s*XI\b/i', $name)) {
            return preg_replace('/^\s*XI/i', 'XII', $name);
        }

        if (preg_match('/^\s*X\b/i', $name)) {
            return preg_replace('/^\s*X/i', 'XI', $name);
        }

        return $name;
    }

    private function nextLevel(int $level): int
    {
        return $level >= 1 && $level < 12 ? $level + 10 : $level;
    }
}
