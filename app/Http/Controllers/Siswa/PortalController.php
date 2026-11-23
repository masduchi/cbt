<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\KelasCatatanMapel;
use App\Models\KelasCatatanWali;
use App\Models\KelasJadwalKbm;
use App\Models\KelasJadwalMapel;
use App\Models\KelasJadwalMateri;
use App\Models\KelasSiswa;
use App\Models\LogMateri;
use App\Models\Siswa;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    private const LEVELS = [
        1 => 'Saran',
        2 => 'Teguran',
        3 => 'Peringatan',
        4 => 'Sangsi',
    ];

    public function jadwal(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $siswa = $this->student($request);
        $classIds = $tahun && $semester ? $this->classIds($siswa, (int) $tahun->id_tp, (int) $semester->id_smt) : [];

        $classes = $classIds
            ? Kelas::whereIn('id_kelas', $classIds)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
            : collect();

        $kelasId = (int) $request->query('kelas', $classes->first()->id_kelas ?? 0);
        $kelas = $classes->firstWhere('id_kelas', $kelasId) ?? $classes->first();
        $kelasId = (int) ($kelas?->id_kelas ?? 0);

        $kbm = $kelasId && $tahun && $semester
            ? KelasJadwalKbm::where('id_tp', $tahun->id_tp)->where('id_smt', $semester->id_smt)->where('id_kelas', $kelasId)->first()
            : null;

        $breaks = $this->breaks($kbm?->istirahat);
        $times = $this->lessonTimes($kbm, $breaks);

        $slots = $kelasId && $tahun && $semester
            ? KelasJadwalMapel::with('mapel:id_mapel,nama_mapel,kode')
                ->where('id_tp', $tahun->id_tp)
                ->where('id_smt', $semester->id_smt)
                ->where('id_kelas', $kelasId)
                ->get(['id_hari', 'jam_ke', 'id_mapel'])
            : collect();

        $grid = [];

        foreach ($slots as $slot) {
            if (! $slot->mapel) {
                continue;
            }

            $grid[$slot->id_hari.'-'.$slot->jam_ke] = [
                'id_mapel' => (int) $slot->mapel->id_mapel,
                'nama_mapel' => (string) $slot->mapel->nama_mapel,
                'kode' => (string) ($slot->mapel->kode ?? ''),
            ];
        }

        $maxJam = $kbm?->kbm_jml_mapel_hari ?: 8;
        $slotRows = [];

        foreach ($times as $time) {
            if ($time['jam'] > $maxJam) {
                continue;
            }

            $slotRows[] = $time + [
                'mapel' => collect(self::HARI)->mapWithKeys(
                    fn ($nama, $hari) => [$hari => $grid[$hari.'-'.$time['jam']] ?? null]
                )->all(),
            ];
        }

        $days = collect(self::HARI)->map(fn ($nama, $id) => [
            'id' => $id,
            'nama' => $nama,
            'today' => (int) now()->isoWeekday() === $id,
        ])->values();

        return Inertia::render('Siswa/Jadwal', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'days' => $days,
            'slots' => $slotRows,
            'breaks' => $breaks,
            'kbm' => [
                'jam_pel' => (int) ($kbm?->kbm_jam_pel ?? 40),
                'jam_mulai' => (string) ($kbm?->kbm_jam_mulai ?? '07:00'),
                'jml_mapel' => (int) $maxJam,
            ],
            'student' => $this->studentPayload($siswa, $classes),
            'periode' => $tahun && $semester ? $tahun->tahun.' · '.$semester->smt : '-',
        ]);
    }

    public function kehadiran(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $siswa = $this->student($request);
        $classIds = $tahun && $semester ? $this->classIds($siswa, (int) $tahun->id_tp, (int) $semester->id_smt) : [];

        $bulan = (string) $request->query('bulan', now()->format('Y-m'));
        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan) ? $bulan : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $items = $classIds && $tahun && $semester
            ? KelasJadwalMateri::with(['materi:id_materi,judul_materi,kode_materi', 'mapel:id_mapel,nama_mapel'])
                ->where('id_tp', $tahun->id_tp)
                ->where('id_smt', $semester->id_smt)
                ->whereIn('id_kelas', $classIds)
                ->whereBetween('jadwal_materi', [$start->toDateString(), $end->toDateString().' 23:59:59'])
                ->whereHas('materi', fn ($query) => $query->where('status', 1))
                ->orderBy('jadwal_materi')
                ->get()
            : collect();

        $logs = $items->isEmpty()
            ? collect()
            : LogMateri::where('id_siswa', $siswa->id_siswa)
                ->whereIn('id_materi', $items->pluck('id_kjm'))
                ->get()
                ->keyBy('id_materi');

        $payload = $items->map(function (KelasJadwalMateri $item) use ($logs) {
            $log = $logs->get($item->id_kjm);

            return [
                'id_kjm' => (string) $item->id_kjm,
                'date' => substr((string) $item->jadwal_materi, 0, 10),
                'jenis' => (int) $item->jenis,
                'judul' => (string) ($item->materi?->judul_materi ?? ''),
                'mapel' => (string) ($item->mapel?->nama_mapel ?? ''),
                'jam' => preg_match('/-J(\d+)-T\d+$/', (string) $item->id_kjm, $match) ? (int) $match[1] : null,
                'status' => $this->attendanceStatus($log),
            ];
        });

        $byDate = $payload->groupBy('date');

        $days = collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start, $byDate) {
            $date = $start->copy()->setDay($day);
            $key = $date->toDateString();
            $dayItems = collect($byDate->get($key, []))->values();
            $selesai = $dayItems->where('status', 'selesai')->count();
            $dibuka = $dayItems->where('status', 'dibuka')->count();

            if (! $dayItems->count()) {
                $status = 'libur';
            } elseif ($selesai === $dayItems->count()) {
                $status = 'hadir';
            } elseif ($selesai + $dibuka > 0) {
                $status = 'sebagian';
            } else {
                $status = 'alpa';
            }

            return [
                'date' => $key,
                'day' => $day,
                'weekday' => $date->isoFormat('dd'),
                'status' => $status,
                'selesai' => $selesai,
                'total' => $dayItems->count(),
                'items' => $dayItems->all(),
            ];
        });

        $totals = ['hadir' => 0, 'sebagian' => 0, 'alpa' => 0, 'libur' => 0];

        foreach ($days as $day) {
            $totals[$day['status']]++;
        }

        $today = now()->toDateString();

        return Inertia::render('Siswa/Kehadiran', [
            'student' => $this->studentPayload($siswa, $classIds ? Kelas::whereIn('id_kelas', $classIds)->get(['id_kelas', 'nama_kelas']) : collect()),
            'bulan' => $bulan,
            'bulanLabel' => $start->isoFormat('MMMM YYYY'),
            'prevBulan' => $start->copy()->subMonth()->format('Y-m'),
            'nextBulan' => $start->copy()->addMonth()->format('Y-m'),
            'days' => $days,
            'today' => [
                'date' => $today,
                'items' => $payload->where('date', $today)->values(),
            ],
            'totals' => $totals,
            'hariEfektif' => $days->where('status', '!=', 'libur')->count(),
            'periode' => $tahun && $semester ? $tahun->tahun.' · '.$semester->smt : '-',
        ]);
    }

    public function catatan(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $siswa = $this->student($request);
        $classIds = $tahun && $semester ? $this->classIds($siswa, (int) $tahun->id_tp, (int) $semester->id_smt) : [];

        $mapelNotes = collect();
        $waliNotes = collect();

        if ($classIds && $tahun && $semester) {
            $mapelNotes = DB::table('kelas_catatan_mapel')
                ->join('master_mapel', 'master_mapel.id_mapel', '=', 'kelas_catatan_mapel.id_mapel')
                ->leftJoin('master_guru', 'master_guru.id_guru', '=', 'kelas_catatan_mapel.id_guru')
                ->where('kelas_catatan_mapel.id_tp', $tahun->id_tp)
                ->where('kelas_catatan_mapel.id_smt', $semester->id_smt)
                ->whereIn('kelas_catatan_mapel.id_kelas', $classIds)
                ->where(fn ($query) => $query
                    ->where('kelas_catatan_mapel.type', 1)
                    ->orWhere(fn ($inner) => $inner
                        ->where('kelas_catatan_mapel.type', 2)
                        ->where('kelas_catatan_mapel.id_siswa', $siswa->id_siswa)))
                ->orderByDesc('kelas_catatan_mapel.tgl')
                ->orderByDesc('kelas_catatan_mapel.id_catatan')
                ->get([
                    'kelas_catatan_mapel.id_catatan',
                    'kelas_catatan_mapel.type',
                    'kelas_catatan_mapel.level',
                    'kelas_catatan_mapel.tgl',
                    'kelas_catatan_mapel.text',
                    'kelas_catatan_mapel.readed',
                    'kelas_catatan_mapel.reading',
                    'master_mapel.nama_mapel',
                    'master_guru.nama_guru as guru',
                ])
                ->map(fn ($row) => $this->notePayload($row, $siswa, 'mapel', (string) $row->nama_mapel));

            $waliNotes = DB::table('kelas_catatan_wali')
                ->where('kelas_catatan_wali.id_tp', $tahun->id_tp)
                ->where('kelas_catatan_wali.id_smt', $semester->id_smt)
                ->whereIn('kelas_catatan_wali.id_kelas', $classIds)
                ->where(fn ($query) => $query
                    ->where('kelas_catatan_wali.type', 1)
                    ->orWhere(fn ($inner) => $inner
                        ->where('kelas_catatan_wali.type', 2)
                        ->where('kelas_catatan_wali.id_siswa', $siswa->id_siswa)))
                ->orderByDesc('kelas_catatan_wali.tgl')
                ->orderByDesc('kelas_catatan_wali.id_catatan')
                ->get([
                    'kelas_catatan_wali.id_catatan',
                    'kelas_catatan_wali.type',
                    'kelas_catatan_wali.level',
                    'kelas_catatan_wali.tgl',
                    'kelas_catatan_wali.text',
                    'kelas_catatan_wali.readed',
                    'kelas_catatan_wali.reading',
                ])
                ->map(fn ($row) => $this->notePayload($row, $siswa, 'wali', 'Wali kelas'));
        }

        $all = $mapelNotes->concat($waliNotes)->sortByDesc('sort')->values();

        return Inertia::render('Siswa/Catatan', [
            'student' => $this->studentPayload($siswa, $classIds ? Kelas::whereIn('id_kelas', $classIds)->get(['id_kelas', 'nama_kelas']) : collect()),
            'notes' => $all,
            'summary' => [
                'total' => $all->count(),
                'belum' => $all->where('read', false)->count(),
                'mapel' => $mapelNotes->count(),
                'wali' => $waliNotes->count(),
            ],
            'periode' => $tahun && $semester ? $tahun->tahun.' · '.$semester->smt : '-',
        ]);
    }

    public function baca(Request $request, string $table, int $catatan): RedirectResponse
    {
        $siswa = $this->student($request);
        [$tahun, $semester] = Period::current();

        abort_unless($tahun && $semester, 404);

        $classIds = $this->classIds($siswa, (int) $tahun->id_tp, (int) $semester->id_smt);
        $model = $table === 'mapel' ? KelasCatatanMapel::class : ($table === 'wali' ? KelasCatatanWali::class : null);

        abort_unless($model, 404);

        $note = $model::findOrFail($catatan);

        abort_unless(
            in_array((int) $note->id_kelas, $classIds, true)
            && ((int) $note->type === 1 || (int) $note->id_siswa === (int) $siswa->id_siswa),
            403
        );

        $field = (int) $note->type === 1 ? 'reading' : 'readed';

        if ($field === 'reading') {
            $reading = $this->reading($note->reading);

            if (! in_array((int) $siswa->id_siswa, $reading, true)) {
                $reading[] = (int) $siswa->id_siswa;
            }

            $value = serialize($reading);
        } else {
            $value = (string) $note->readed === '0' ? now()->format('Y-m-d H:i:s') : $note->readed;
        }

        // Write tgl back explicitly: the column carries ON UPDATE CURRENT_TIMESTAMP,
        // and a plain Eloquent save would bump the note's date when marking it read.
        $model::whereKey($note->getKey())->update([
            $field => $value,
            'tgl' => $note->getRawOriginal('tgl'),
        ]);

        return back()->with('success', 'Catatan ditandai sudah dibaca.');
    }

    private function notePayload(object $row, Siswa $siswa, string $table, string $source): array
    {
        $type = (int) $row->type;
        $read = $type === 1
            ? in_array((int) $siswa->id_siswa, $this->reading($row->reading), true)
            : (string) $row->readed !== '0';

        return [
            'id_catatan' => (int) $row->id_catatan,
            'table' => $table,
            'type' => $type,
            'level' => self::LEVELS[(int) $row->level] ?? '-',
            'source' => $source,
            'guru' => (string) ($row->guru ?? ''),
            'text' => (string) $row->text,
            'tanggal' => $row->tgl ? Carbon::parse($row->tgl)->isoFormat('D MMM YYYY, HH:mm') : '-',
            'sort' => (string) $row->tgl,
            'read' => $read,
        ];
    }

    private function reading(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $data = @unserialize($value, ['allowed_classes' => false]);

        return is_array($data) ? array_map('intval', $data) : [];
    }

    private function attendanceStatus(?LogMateri $log): string
    {
        if (! $log) {
            return 'alpa';
        }

        return $log->finish_time ? 'selesai' : 'dibuka';
    }

    /**
     * @return array<int, array{jam_ke: int, durasi: int}>
     */
    private function breaks(?string $raw): array
    {
        $data = @unserialize((string) $raw, ['allowed_classes' => false]);
        $result = [];

        if (! is_array($data)) {
            return $result;
        }

        foreach ($data as $item) {
            if (! is_array($item) || empty($item['ist'])) {
                continue;
            }

            $result[] = ['jam_ke' => (int) $item['ist'], 'durasi' => (int) ($item['dur'] ?? 0)];
        }

        return $result;
    }

    /**
     * @return array<int, array{jam: int, mulai: string, selesai: string, istirahat: ?array{jam_ke: int, durasi: int, mulai: string, selesai: string}}>
     */
    private function lessonTimes(?KelasJadwalKbm $kbm, array $breaks): array
    {
        $length = max(1, (int) ($kbm?->kbm_jam_pel ?? 40));
        $cursor = Carbon::createFromFormat('H:i', (string) ($kbm?->kbm_jam_mulai ?? '07:00'));
        $breakAfter = collect($breaks)->keyBy('jam_ke');
        $rows = [];

        for ($jam = 1; $jam <= max((int) ($kbm?->kbm_jml_mapel_hari ?? 8), 1); $jam++) {
            $mulai = $cursor->format('H:i');
            $cursor->addMinutes($length);
            $selesai = $cursor->format('H:i');
            $break = $breakAfter->get($jam);
            $istirahat = null;

            if ($break && (int) $break['durasi'] > 0) {
                $breakStart = $cursor->format('H:i');
                $cursor->addMinutes((int) $break['durasi']);
                $istirahat = [
                    'jam_ke' => (int) $break['jam_ke'],
                    'durasi' => (int) $break['durasi'],
                    'mulai' => $breakStart,
                    'selesai' => $cursor->format('H:i'),
                ];
            }

            $rows[] = ['jam' => $jam, 'mulai' => $mulai, 'selesai' => $selesai, 'istirahat' => $istirahat];
        }

        return $rows;
    }

    private function student(Request $request): Siswa
    {
        $siswa = Siswa::where('username', $request->user()->username)->first();

        abort_unless($siswa, 403, 'Akun ini tidak terhubung dengan data siswa.');

        return $siswa;
    }

    private function classIds(Siswa $siswa, int $tp, int $smt): array
    {
        return KelasSiswa::where('id_siswa', $siswa->id_siswa)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->pluck('id_kelas')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function studentPayload(Siswa $siswa, $classes): array
    {
        return [
            'nama' => $siswa->nama,
            'nis' => $siswa->nis,
            'kelas' => $classes->pluck('nama_kelas')->implode(', ') ?: null,
        ];
    }
}
