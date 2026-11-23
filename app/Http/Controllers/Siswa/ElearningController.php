<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\KelasJadwalMateri;
use App\Models\KelasSiswa;
use App\Models\LogMateri;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\Week;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ElearningController extends Controller
{
    private const ALLOWED_FILES = 'jpg,jpeg,png,gif,webp,mp3,wav,mp4,avi,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,rtf';

    public function materi(Request $request): Response
    {
        return $this->list($request, 1);
    }

    public function tugas(Request $request): Response
    {
        return $this->list($request, 2);
    }

    private function list(Request $request, int $jenis): Response
    {
        $siswa = $this->student($request);
        [$tp, $smt] = $this->activePeriod();
        $classIds = $this->classIds($siswa, (int) $tp, (int) $smt);

        $weekStart = Week::start($request->query('week'));
        $weekEnd = $weekStart->copy()->addDays(5);

        $schedules = collect();

        if ($classIds) {
            $schedules = KelasJadwalMateri::with(['materi.mapel:id_mapel,nama_mapel', 'materi.guru:id_guru,nama_guru'])
                ->where('id_tp', $tp)->where('id_smt', $smt)
                ->where('jenis', $jenis)
                ->whereIn('id_kelas', $classIds)
                ->whereBetween('jadwal_materi', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->whereHas('materi', fn ($query) => $query->where('status', 1))
                ->get();
        }

        $logs = $schedules->isEmpty()
            ? collect()
            : LogMateri::where('id_siswa', $siswa->id_siswa)
                ->whereIn('id_materi', $schedules->pluck('id_kjm'))
                ->get()
                ->keyBy('id_materi');

        $items = $schedules->map(function (KelasJadwalMateri $schedule) use ($logs) {
            $log = $logs->get($schedule->id_kjm);
            $jam = preg_match('/-J(\d+)-T\d+$/', (string) $schedule->id_kjm, $match) ? (int) $match[1] : null;

            return [
                'id_kjm' => (string) $schedule->id_kjm,
                'jenis' => (int) $schedule->jenis,
                'judul' => (string) ($schedule->materi?->judul_materi ?? ''),
                'kode' => (string) ($schedule->materi?->kode_materi ?? ''),
                'mapel' => $schedule->materi?->mapel?->nama_mapel,
                'guru' => $schedule->materi?->guru?->nama_guru,
                'date' => (string) $schedule->jadwal_materi,
                'jam' => $jam,
                'selesai' => (bool) ($log?->finish_time),
                'nilai' => $log?->nilai,
                'tenggat' => $schedule->materi?->tenggat ? Carbon::parse($schedule->materi->tenggat)->isoFormat('D MMM YYYY, HH:mm') : null,
                'late' => $log?->finish_time && $schedule->materi?->tenggat
                    && Carbon::parse($log->finish_time)->greaterThan(Carbon::parse($schedule->materi->tenggat)),
            ];
        })->groupBy('date');

        $days = collect(range(0, 5))->map(function (int $offset) use ($weekStart, $items) {
            $date = $weekStart->copy()->addDays($offset);
            $key = $date->toDateString();

            return [
                'date' => $key,
                'label' => $date->isoFormat('dddd, D MMMM YYYY'),
                'items' => collect($items->get($key, []))->sortBy('jam')->values(),
            ];
        });

        $flat = $items->flatten(1);

        return Inertia::render('Siswa/Elearning/Index', [
            'jenis' => $jenis,
            'days' => $days,
            'student' => $this->studentPayload($siswa, $classIds),
            'weekStart' => $weekStart->toDateString(),
            'weekLabel' => $weekStart->isoFormat('D MMM').' – '.$weekEnd->isoFormat('D MMM YYYY'),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'summary' => [
                'total' => $flat->count(),
                'selesai' => $flat->where('selesai', true)->count(),
            ],
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function show(Request $request, string $kjm): Response
    {
        $siswa = $this->student($request);
        $schedule = $this->schedule($siswa, $kjm);
        $log = $this->open($request, $siswa, $schedule);
        $jenis = (int) $schedule->jenis;

        return Inertia::render('Siswa/Elearning/Show', [
            'jenis' => $jenis,
            'item' => [
                'id_kjm' => (string) $schedule->id_kjm,
                'jenis' => $jenis,
                'kode' => (string) ($schedule->materi?->kode_materi ?? ''),
                'judul' => (string) ($schedule->materi?->judul_materi ?? ''),
                'isi' => (string) ($schedule->materi?->isi_materi ?? ''),
                'mapel' => $schedule->materi?->mapel?->nama_mapel,
                'guru' => $schedule->materi?->guru?->nama_guru,
                'kelas' => $schedule->kelas?->nama_kelas,
                'date' => (string) $schedule->jadwal_materi,
                'date_label' => $this->dateLabel($schedule->jadwal_materi),
                'jam' => preg_match('/-J(\d+)-T\d+$/', (string) $schedule->id_kjm, $match) ? (int) $match[1] : null,
                'link' => (string) ($schedule->materi?->link_file ?? ''),
                'youtube' => (string) ($schedule->materi?->youtube ?? ''),
                'files' => $this->files($schedule->materi?->file),
                'tenggat' => $schedule->materi?->tenggat ? $this->dateLabel($schedule->materi->tenggat, 'D MMM YYYY, HH:mm') : null,
                'tenggat_raw' => $schedule->materi?->tenggat,
            ],
            'log' => [
                'text' => (string) ($log->text ?? ''),
                'files' => $this->files($log->file),
                'finish_time' => $log->finish_time ? $this->dateLabel($log->finish_time, 'D MMM YYYY, HH:mm') : null,
                'late' => (bool) ($log->finish_time && $schedule->materi?->tenggat
                    && Carbon::parse($log->finish_time)->greaterThan(Carbon::parse($schedule->materi->tenggat))),
                'nilai' => $log->nilai,
                'catatan' => $log->catatan,
                'graded' => $this->graded($log),
            ],
            'backUrl' => '/siswa/elearning/'.($jenis === 1 ? 'materi' : 'tugas'),
        ]);
    }

    public function submit(Request $request, string $kjm): RedirectResponse
    {
        $siswa = $this->student($request);
        $schedule = $this->schedule($siswa, $kjm);
        $jenis = (int) $schedule->jenis;

        $log = LogMateri::where('id_log', $siswa->id_siswa.$kjm)->first();
        $wasGraded = $log ? $this->graded($log) : false;

        if ($wasGraded) {
            return back()->with('error', 'Hasil ini sudah dinilai dan tidak dapat diubah.');
        }

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:50000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:'.self::ALLOWED_FILES],
        ]);

        $storedFiles = $this->storeFiles($request);
        $files = array_merge($this->filesRaw($log?->file), $storedFiles);

        $payload = [
            'log_time' => $log?->log_time ?? now(),
            'id_siswa' => $siswa->id_siswa,
            'jam_ke' => preg_match('/-J(\d+)-T\d+$/', $kjm, $match) ? (int) $match[1] : 0,
            'id_materi' => $kjm,
            'id_mapel' => (int) ($schedule->id_mapel ?? 0),
            'log_type' => 2,
            'log_desc' => 'Menyelesaikan '.($jenis === 1 ? 'materi' : 'tugas'),
            'text' => (string) ($data['text'] ?? ''),
            'file' => serialize($files),
            'address' => substr((string) $request->ip(), 0, 45),
            'agent' => substr((string) $request->userAgent(), 0, 255),
            'device' => 'Web',
            'finish_time' => now(),
        ];

        LogMateri::updateOrCreate(['id_log' => $siswa->id_siswa.$kjm], $payload);

        AuditLog::record('elearning.selesai', ucfirst($jenis === 1 ? 'materi' : 'tugas').' '.$kjm.' diselesaikan');

        return back()->with('success', 'Hasil '.($jenis === 1 ? 'materi' : 'tugas').' berhasil disimpan.');
    }

    private function open(Request $request, Siswa $siswa, KelasJadwalMateri $schedule): LogMateri
    {
        $log = LogMateri::where('id_log', $siswa->id_siswa.$schedule->id_kjm)->first();

        if ($log) {
            return $log;
        }

        $jenis = (int) $schedule->jenis;

        $log = LogMateri::create([
            'id_log' => $siswa->id_siswa.$schedule->id_kjm,
            'log_time' => now(),
            'id_siswa' => $siswa->id_siswa,
            'jam_ke' => preg_match('/-J(\d+)-T\d+$/', (string) $schedule->id_kjm, $match) ? (int) $match[1] : 0,
            'id_materi' => (string) $schedule->id_kjm,
            'id_mapel' => (int) ($schedule->id_mapel ?? 0),
            'log_type' => 1,
            'log_desc' => 'Membuka '.($jenis === 1 ? 'materi' : 'tugas'),
            'address' => substr((string) $request->ip(), 0, 45),
            'agent' => substr((string) $request->userAgent(), 0, 255),
            'device' => 'Web',
        ]);

        AuditLog::record('elearning.buka', ucfirst($jenis === 1 ? 'materi' : 'tugas').' '.$schedule->id_kjm.' dibuka');

        return $log;
    }

    private function schedule(Siswa $siswa, string $kjm): KelasJadwalMateri
    {
        [$tp, $smt] = $this->activePeriod();
        $classIds = $this->classIds($siswa, (int) $tp, (int) $smt);

        $schedule = KelasJadwalMateri::with([
            'materi.mapel:id_mapel,nama_mapel',
            'materi.guru:id_guru,nama_guru',
            'kelas:id_kelas,nama_kelas',
        ])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $classIds)
            ->where('id_kjm', $kjm)
            ->whereHas('materi', fn ($query) => $query->where('status', 1))
            ->firstOrFail();

        return $schedule;
    }

    private function storeFiles(Request $request): array
    {
        $files = $request->file('files', []);
        $files = is_array($files) ? $files : [$files];
        $result = [];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('elearning/hasil', 'public');
            $result[] = [
                'src' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getClientMimeType(),
            ];
        }

        return $result;
    }

    private function filesRaw(?string $serialized): array
    {
        if (! $serialized) {
            return [];
        }

        $files = @unserialize($serialized, ['allowed_classes' => false]);

        return is_array($files) ? $files : [];
    }

    private function files(?string $serialized): array
    {
        return collect($this->filesRaw($serialized))->map(function ($file) {
            $src = (string) ($file['src'] ?? '');

            if (str_starts_with($src, 'http')) {
                $url = $src;
            } elseif (str_starts_with($src, 'uploads/')) {
                $url = asset($src);
            } else {
                $url = Storage::disk('public')->url($src);
            }

            return [
                'name' => (string) ($file['name'] ?? basename($src)),
                'url' => $url,
                'size' => $file['size'] ?? null,
                'type' => (string) ($file['type'] ?? ''),
            ];
        })->values()->all();
    }

    private function graded(?LogMateri $log): bool
    {
        if (! $log) {
            return false;
        }

        return $log->nilai !== null && $log->nilai !== '';
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

    private function studentPayload(Siswa $siswa, array $classIds): array
    {
        return [
            'id_siswa' => $siswa->id_siswa,
            'nama' => $siswa->nama,
            'kelas' => $classIds ? Kelas::whereIn('id_kelas', $classIds)->pluck('nama_kelas')->implode(', ') : null,
        ];
    }

    private function dateLabel(?string $value, string $format = 'D MMM YYYY'): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->isoFormat($format);
        } catch (\Throwable) {
            return (string) $value;
        }
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
