<?php

namespace App\Http\Controllers\Elearning;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasJadwalMapel;
use App\Models\KelasJadwalMateri;
use App\Models\KelasMateri;
use App\Models\LogMateri;
use App\Models\Mapel;
use App\Support\AuditLog;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LaporanController extends Controller
{
    public function nilaiHarian(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = (int) ($tahun?->id_tp ?? 0);
        $smt = (int) ($semester?->id_smt ?? 0);
        $user = $request->user();
        $guruId = $this->scopeGuru($user);

        $classes = $this->classes($tp, $smt);
        $kelasId = $this->selectedClass($request, $classes);
        $tanggal = $this->tanggal($request);

        $items = $tp && $smt && $kelasId
            ? $this->schedules($tp, $smt, $kelasId, $guruId, null, $tanggal->toDateString(), $tanggal->toDateString())
            : collect();

        $students = $tp && $smt && $kelasId ? $this->students($kelasId, $tp, $smt) : collect();
        $logs = $this->logs($items, $students);

        $rows = $students->map(function ($student) use ($items, $logs) {
            $cells = [];

            foreach ($items as $item) {
                $log = $logs->get($student->id_siswa.'|'.$item->id_kjm);

                $cells[$item->id_kjm] = [
                    'id_log' => $log?->id_log,
                    'status' => $this->status($log),
                    'nilai' => $this->nilai($log),
                    'catatan' => (string) ($log?->catatan ?? ''),
                ];
            }

            return [
                'id_siswa' => (int) $student->id_siswa,
                'nis' => (string) $student->nis,
                'nama' => (string) $student->nama,
                'cells' => $cells,
            ];
        })->values();

        $filled = $rows->sum(fn ($row) => collect($row['cells'])->filter(fn ($cell) => $cell['nilai'] !== null)->count());
        $graded = $rows->flatMap(fn ($row) => collect($row['cells'])->pluck('nilai')->filter(fn ($n) => $n !== null));

        return Inertia::render('Elearning/NilaiHarian', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'tanggal' => $tanggal->toDateString(),
            'items' => $items->map(fn (KelasJadwalMateri $item) => $this->item($item))->values(),
            'rows' => $rows,
            'summary' => [
                'siswa' => $students->count(),
                'item' => $items->count(),
                'terisi' => $filled,
                'rata' => $graded->count() ? round($graded->avg(), 1) : null,
            ],
            'periode' => $this->periodeLabel($tahun, $semester),
        ]);
    }

    public function simpanNilaiHarian(Request $request): RedirectResponse
    {
        [$tahun, $semester] = Period::current();

        if (! $tahun || ! $semester) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $request->validate([
            'kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'tanggal' => ['required', 'date'],
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.id_kjm' => ['required', 'string', 'max:50'],
            'rows.*.nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $kelasId = (int) $data['kelas'];
        $this->ensureClass((int) $tahun->id_tp, (int) $semester->id_smt, $kelasId, $request);

        $guruId = $request->user()->isAdmin() ? null : $this->guruId($request->user());
        $items = $this->schedules(
            (int) $tahun->id_tp,
            (int) $semester->id_smt,
            $kelasId,
            $guruId,
            null,
            $data['tanggal'],
            $data['tanggal']
        )->keyBy('id_kjm');

        $studentIds = $this->students($kelasId, (int) $tahun->id_tp, (int) $semester->id_smt)
            ->pluck('id_siswa')
            ->map(fn ($id) => (int) $id)
            ->all();

        $saved = 0;

        DB::transaction(function () use ($data, $items, $studentIds, $request, &$saved) {
            foreach ($data['rows'] as $row) {
                $idSiswa = (int) $row['id_siswa'];
                $kjm = (string) $row['id_kjm'];
                $item = $items->get($kjm);

                if (! $item || ! in_array($idSiswa, $studentIds, true)) {
                    continue;
                }

                $nilai = $row['nilai'] === null || $row['nilai'] === ''
                    ? null
                    : (string) max(0, min(100, (int) round((float) $row['nilai'])));
                $catatan = trim((string) ($row['catatan'] ?? '')) ?: null;
                $idLog = $idSiswa.$kjm;

                $log = LogMateri::where('id_log', $idLog)->first();

                if (! $log) {
                    if ($nilai === null && $catatan === null) {
                        continue;
                    }

                    LogMateri::create([
                        'id_log' => $idLog,
                        'log_time' => now(),
                        'id_siswa' => $idSiswa,
                        'jam_ke' => preg_match('/-J(\d+)-T\d+$/', $kjm, $match) ? (int) $match[1] : 0,
                        'id_materi' => $kjm,
                        'id_mapel' => (int) ($item->id_mapel ?? 0),
                        'log_type' => 2,
                        'log_desc' => 'Dinilai guru',
                        'address' => (string) $request->ip(),
                        'agent' => substr((string) $request->userAgent(), 0, 255),
                        'device' => 'Web',
                        'nilai' => $nilai,
                        'catatan' => $catatan,
                        'dinilai_oleh' => $request->user()->id,
                        'dinilai_at' => $nilai !== null ? now() : null,
                    ]);
                } else {
                    $log->update([
                        'nilai' => $nilai,
                        'catatan' => $catatan,
                        'dinilai_oleh' => $request->user()->id,
                        'dinilai_at' => $nilai !== null ? now() : $log->dinilai_at,
                    ]);
                }

                $saved++;
            }
        });

        if ($saved === 0) {
            return back()->with('error', 'Tidak ada nilai yang tersimpan. Periksa kembali kelas, tanggal, dan siswa yang dipilih.');
        }

        AuditLog::record('elearning.nilai', $saved.' nilai materi/tugas disimpan');

        return back()->with('success', "Nilai harian tersimpan untuk {$saved} data.");
    }

    public function kehadiranHarian(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = (int) ($tahun?->id_tp ?? 0);
        $smt = (int) ($semester?->id_smt ?? 0);
        $user = $request->user();
        $guruId = $this->scopeGuru($user);

        $classes = $this->classes($tp, $smt);
        $kelasId = $this->selectedClass($request, $classes);
        $tanggal = $this->tanggal($request);

        $items = $tp && $smt && $kelasId
            ? $this->schedules($tp, $smt, $kelasId, $guruId, null, $tanggal->toDateString(), $tanggal->toDateString())
            : collect();

        $students = $tp && $smt && $kelasId ? $this->students($kelasId, $tp, $smt) : collect();
        $logs = $this->logs($items, $students);

        $rows = $students->map(function ($student) use ($items, $logs) {
            $cells = [];
            $selesai = 0;
            $dibuka = 0;

            foreach ($items as $item) {
                $log = $logs->get($student->id_siswa.'|'.$item->id_kjm);
                $status = $this->status($log);

                if ($status === 'selesai') {
                    $selesai++;
                } elseif ($status === 'dibuka') {
                    $dibuka++;
                }

                $cells[$item->id_kjm] = $status;
            }

            $status = ! $items->count()
                ? 'libur'
                : ($selesai === $items->count() ? 'hadir' : ($selesai + $dibuka > 0 ? 'sebagian' : 'alpa'));

            return [
                'id_siswa' => (int) $student->id_siswa,
                'nis' => (string) $student->nis,
                'nama' => (string) $student->nama,
                'cells' => $cells,
                'selesai' => $selesai,
                'dibuka' => $dibuka,
                'status' => $status,
            ];
        })->values();

        return Inertia::render('Elearning/KehadiranHarian', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'tanggal' => $tanggal->toDateString(),
            'items' => $items->map(fn (KelasJadwalMateri $item) => $this->item($item))->values(),
            'rows' => $rows,
            'summary' => [
                'siswa' => $students->count(),
                'item' => $items->count(),
                'hadir' => $rows->where('status', 'hadir')->count(),
                'sebagian' => $rows->where('status', 'sebagian')->count(),
                'alpa' => $rows->where('status', 'alpa')->count(),
            ],
            'periode' => $this->periodeLabel($tahun, $semester),
        ]);
    }

    public function kehadiranBulanan(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = (int) ($tahun?->id_tp ?? 0);
        $smt = (int) ($semester?->id_smt ?? 0);
        $user = $request->user();
        $guruId = $this->scopeGuru($user);

        $classes = $this->classes($tp, $smt);
        $kelasId = $this->selectedClass($request, $classes);
        $mapels = $kelasId && $tp && $smt ? $this->mapelOptions($kelasId, $tp, $smt, $guruId) : collect();

        $bulan = $this->queryString($request, 'bulan') ?? now()->format('Y-m');
        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan) ? $bulan : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $mapelId = (int) $request->query('mapel');
        $mapelId = $mapels->firstWhere('id_mapel', $mapelId)?->id_mapel ?? null;

        $items = $tp && $smt && $kelasId
            ? $this->schedules($tp, $smt, $kelasId, $guruId, $mapelId, $start->toDateString(), $end->toDateString())
            : collect();

        $byDate = $items->groupBy(fn (KelasJadwalMateri $item) => substr((string) $item->jadwal_materi, 0, 10));
        $students = $tp && $smt && $kelasId ? $this->students($kelasId, $tp, $smt) : collect();
        $logs = $this->logs($items, $students);

        $days = collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start) {
            $date = $start->copy()->setDay($day);

            return [
                'date' => $date->toDateString(),
                'day' => $day,
                'weekday' => $date->isoFormat('dd'),
            ];
        });

        $rows = $students->map(function ($student) use ($days, $byDate, $logs) {
            $cells = [];
            $totals = ['hadir' => 0, 'sebagian' => 0, 'alpa' => 0];

            foreach ($days as $day) {
                $dayItems = $byDate->get($day['date'], collect());

                if (! $dayItems->count()) {
                    $cells[$day['date']] = ['status' => 'libur', 'selesai' => 0, 'total' => 0];

                    continue;
                }

                $selesai = $dayItems->filter(fn (KelasJadwalMateri $item) => $this->status($logs->get($student->id_siswa.'|'.$item->id_kjm)) === 'selesai')->count();
                $dibuka = $dayItems->filter(fn (KelasJadwalMateri $item) => $this->status($logs->get($student->id_siswa.'|'.$item->id_kjm)) === 'dibuka')->count();

                $status = $selesai === $dayItems->count() ? 'hadir' : ($selesai + $dibuka > 0 ? 'sebagian' : 'alpa');
                $totals[$status]++;
                $cells[$day['date']] = ['status' => $status, 'selesai' => $selesai, 'total' => $dayItems->count()];
            }

            return [
                'id_siswa' => (int) $student->id_siswa,
                'nis' => (string) $student->nis,
                'nama' => (string) $student->nama,
                'cells' => $cells,
                'totals' => $totals,
            ];
        })->values();

        return Inertia::render('Elearning/KehadiranBulanan', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'mapels' => $mapels,
            'mapelId' => $mapelId,
            'bulan' => $bulan,
            'bulanLabel' => $start->isoFormat('MMMM YYYY'),
            'days' => $days,
            'rows' => $rows,
            'summary' => [
                'siswa' => $students->count(),
                'hariEfektif' => $byDate->count(),
            ],
            'periode' => $this->periodeLabel($tahun, $semester),
        ]);
    }

    public function rekapNilai(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = (int) ($tahun?->id_tp ?? 0);
        $smt = (int) ($semester?->id_smt ?? 0);
        $user = $request->user();
        $guruId = $this->scopeGuru($user);

        $classes = $this->classes($tp, $smt);
        $kelasId = $this->selectedClass($request, $classes);
        $mapels = $kelasId && $tp && $smt ? $this->mapelOptions($kelasId, $tp, $smt, $guruId) : collect();

        $mapelId = (int) $request->query('mapel');
        $mapelId = $mapels->firstWhere('id_mapel', $mapelId)?->id_mapel ?? null;

        $items = $tp && $smt && $kelasId && $mapelId
            ? $this->schedules($tp, $smt, $kelasId, $guruId, $mapelId)
            : collect();

        $students = $tp && $smt && $kelasId ? $this->students($kelasId, $tp, $smt) : collect();
        $logs = $this->logs($items, $students);

        $rows = $students->map(function ($student) use ($items, $logs) {
            $nilai = [];
            $materi = [];
            $tugas = [];

            foreach ($items as $item) {
                $log = $logs->get($student->id_siswa.'|'.$item->id_kjm);
                $value = $this->nilai($log);
                $nilai[$item->id_kjm] = $value;

                if ($value === null) {
                    continue;
                }

                if ((int) $item->jenis === 2) {
                    $tugas[] = $value;
                } else {
                    $materi[] = $value;
                }
            }

            $all = array_merge($materi, $tugas);

            return [
                'id_siswa' => (int) $student->id_siswa,
                'nis' => (string) $student->nis,
                'nama' => (string) $student->nama,
                'nilai' => $nilai,
                'rata_materi' => $materi ? round(array_sum($materi) / count($materi), 1) : null,
                'rata_tugas' => $tugas ? round(array_sum($tugas) / count($tugas), 1) : null,
                'rata' => $all ? round(array_sum($all) / count($all), 1) : null,
            ];
        })->values();

        $graded = $rows->pluck('rata')->filter(fn ($n) => $n !== null);

        return Inertia::render('Elearning/RekapNilai', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'mapels' => $mapels,
            'mapelId' => $mapelId,
            'items' => $items->map(fn (KelasJadwalMateri $item) => $this->item($item))->values(),
            'rows' => $rows,
            'summary' => [
                'siswa' => $students->count(),
                'item' => $items->count(),
                'rata' => $graded->count() ? round($graded->avg(), 1) : null,
            ],
            'periode' => $this->periodeLabel($tahun, $semester),
        ]);
    }

    private function schedules(
        int $tp,
        int $smt,
        int $kelasId,
        ?int $guruId,
        ?int $mapelId = null,
        ?string $from = null,
        ?string $to = null
    ) {
        return KelasJadwalMateri::with([
            'materi:id_materi,judul_materi,kode_materi,id_mapel,id_guru',
            'materi.mapel:id_mapel,nama_mapel',
            'materi.guru:id_guru,nama_guru',
        ])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('id_kelas', $kelasId)
            ->when($mapelId, fn ($query) => $query->where('id_mapel', $mapelId))
            ->when($guruId, fn ($query) => $query->whereHas('materi', fn ($materi) => $materi->where('id_guru', $guruId)))
            ->when($from, fn ($query) => $query->whereDate('jadwal_materi', '>=', substr((string) $from, 0, 10)))
            ->when($to, fn ($query) => $query->whereDate('jadwal_materi', '<=', substr((string) $to, 0, 10)))
            ->orderBy('jadwal_materi')
            ->orderBy('id_kjm')
            ->get();
    }

    private function logs($items, $students)
    {
        $scheduleIds = $items->pluck('id_kjm')->map(fn ($id) => (string) $id)->all();
        $studentIds = $students->pluck('id_siswa')->map(fn ($id) => (int) $id)->all();

        if (! $scheduleIds || ! $studentIds) {
            return collect();
        }

        return LogMateri::whereIn('id_materi', $scheduleIds)
            ->whereIn('id_siswa', $studentIds)
            ->orderBy('log_time')
            ->get()
            ->keyBy(fn (LogMateri $log) => $log->id_siswa.'|'.$log->id_materi);
    }

    private function item(KelasJadwalMateri $item): array
    {
        preg_match('/-J(\d+)-T\d+$/', (string) $item->id_kjm, $match);

        return [
            'id_kjm' => (string) $item->id_kjm,
            'jenis' => (int) $item->jenis,
            'judul' => (string) ($item->materi?->judul_materi ?? ''),
            'kode' => (string) ($item->materi?->kode_materi ?? ''),
            'mapel' => $item->materi?->mapel?->nama_mapel,
            'guru' => $item->materi?->guru?->nama_guru,
            'tanggal' => substr((string) $item->jadwal_materi, 0, 10),
            'jam_ke' => (int) ($match[1] ?? 0),
        ];
    }

    private function status(?LogMateri $log): string
    {
        if (! $log) {
            return 'kosong';
        }

        return $log->finish_time ? 'selesai' : 'dibuka';
    }

    private function nilai(?LogMateri $log): ?int
    {
        if (! $log || $log->nilai === null || $log->nilai === '') {
            return null;
        }

        return (int) $log->nilai;
    }

    private function classes(int $tp, int $smt)
    {
        if (! $tp || ! $smt) {
            return collect();
        }

        return Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas']);
    }

    private function selectedClass(Request $request, $classes): ?int
    {
        if (! $classes->count()) {
            return null;
        }

        $id = (int) $request->query('kelas');

        return $classes->firstWhere('id_kelas', $id)?->id_kelas ?? (int) $classes->first()->id_kelas;
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    private function tanggal(Request $request): Carbon
    {
        $value = $this->queryString($request, 'tanggal') ?? now()->toDateString();

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function students(int $kelasId, int $tp, int $smt)
    {
        return DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->where('kelas_siswa.id_kelas', $kelasId)
            ->orderBy('master_siswa.nama')
            ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nama']);
    }

    private function mapelOptions(int $kelasId, int $tp, int $smt, ?int $guruId)
    {
        $ids = KelasMateri::where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->when($guruId, fn ($query) => $query->where('id_guru', $guruId))
            ->get(['id_mapel', 'materi_kelas'])
            ->filter(fn (KelasMateri $item) => in_array($kelasId, $this->kelasIds($item), true))
            ->pluck('id_mapel')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if (! $guruId) {
            $ids = $ids->merge(
                KelasJadwalMapel::where('id_tp', $tp)
                    ->where('id_smt', $smt)
                    ->where('id_kelas', $kelasId)
                    ->pluck('id_mapel')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
            )->unique();
        }

        return Mapel::whereIn('id_mapel', $ids)->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel']);
    }

    private function kelasIds(KelasMateri $item): array
    {
        $ids = @unserialize((string) $item->materi_kelas, ['allowed_classes' => false]);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    private function ensureClass(int $tp, int $smt, int $kelasId, Request $request): void
    {
        $exists = Kelas::where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'kelas' => 'Kelas tidak termasuk periode yang dipilih.',
            ]);
        }

        if (! $request->user()->isAdmin() && ! $this->guruId($request->user())) {
            abort(403);
        }
    }

    private function guruId($user): ?int
    {
        return Guru::where('id_user', $user->id)->value('id_guru');
    }

    /**
     * Admin melihat semua guru (null = tanpa filter). Guru yang belum tertaut ke
     * data guru memakai -1 supaya tidak ikut melihat data guru lain.
     */
    private function scopeGuru($user): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        return $this->guruId($user) ?? -1;
    }

    private function periodeLabel($tahun, $semester): string
    {
        return trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-'));
    }
}
