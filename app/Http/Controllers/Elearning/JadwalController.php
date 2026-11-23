<?php

namespace App\Http\Controllers\Elearning;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasJadwalKbm;
use App\Models\KelasJadwalMapel;
use App\Models\KelasJadwalMateri;
use App\Models\KelasMateri;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\Week;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JadwalController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();
        $guruId = $this->guruId($user);

        $classes = $tp && $smt
            ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
            : collect();

        $kelasId = (int) $request->query('kelas', $classes->first()->id_kelas ?? 0);

        $weekStart = Week::start($request->query('week'));
        $weekEnd = $weekStart->copy()->addDays(5);

        $kbm = KelasJadwalKbm::where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)->first();
        $mapelTemplate = KelasJadwalMapel::with('mapel:id_mapel,nama_mapel,kode')
            ->where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)
            ->get()
            ->keyBy(fn ($row) => $row->id_hari.'-'.$row->jam_ke);

        $schedules = KelasJadwalMateri::with(['materi.mapel:id_mapel,nama_mapel,kode', 'materi.guru:id_guru,nama_guru'])
            ->where('id_tp', $tp)->where('id_smt', $smt)->where('id_kelas', $kelasId)
            ->whereBetween('jadwal_materi', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        $slotMap = [];
        foreach ($schedules as $schedule) {
            if (! preg_match('/-J(\d+)-T(\d+)$/', (string) $schedule->id_kjm, $match)) {
                continue;
            }
            $slotMap[$schedule->jadwal_materi.'-'.$match[1].'-'.$match[2]] = [
                'id_kjm' => (string) $schedule->id_kjm,
                'id_materi' => (int) $schedule->id_materi,
                'judul' => $schedule->materi?->judul_materi,
                'mapel' => $schedule->materi?->mapel?->nama_mapel,
                'kode' => $schedule->materi?->kode_materi,
                'jenis' => (int) $schedule->jenis,
            ];
        }

        $jamCount = min(12, max(1, (int) ($kbm?->kbm_jml_mapel_hari ?? 8)));

        $days = collect(range(0, 5))->map(function (int $offset) use ($weekStart, $slotMap, $mapelTemplate, $jamCount) {
            $date = $weekStart->copy()->addDays($offset);
            $dayNumber = $date->dayOfWeekIso;

            return [
                'date' => $date->toDateString(),
                'label' => $date->isoFormat('ddd, D MMM'),
                'slots' => collect(range(1, $jamCount))->map(function (int $jam) use ($date, $dayNumber, $slotMap, $mapelTemplate) {
                    $template = $mapelTemplate->get($dayNumber.'-'.$jam);

                    return [
                        'jam' => $jam,
                        'mapel' => $template?->mapel?->nama_mapel,
                        'materi' => $slotMap[$date->toDateString().'-'.$jam.'-1'] ?? null,
                        'tugas' => $slotMap[$date->toDateString().'-'.$jam.'-2'] ?? null,
                    ];
                })->values(),
            ];
        });

        $items = KelasMateri::with('mapel:id_mapel,nama_mapel,kode')
            ->where('id_tp', $tp)->where('id_smt', $smt)
            ->where('status', 1)
            ->whereIn('jenis', [1, 2])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('id_guru', $guruId ?? 0))
            ->orderBy('judul_materi')
            ->get()
            ->filter(fn (KelasMateri $item) => in_array($kelasId, $this->kelasIds($item), true))
            ->map(fn (KelasMateri $item) => [
                'id_materi' => (int) $item->id_materi,
                'jenis' => (int) $item->jenis,
                'judul' => (string) $item->judul_materi,
                'kode' => (string) $item->kode_materi,
                'mapel' => $item->mapel?->nama_mapel,
            ])
            ->values();

        return Inertia::render('Elearning/Jadwal', [
            'classes' => $classes,
            'kelasId' => $kelasId,
            'days' => $days,
            'items' => $items,
            'weekStart' => $weekStart->toDateString(),
            'weekLabel' => $weekStart->isoFormat('D MMM').' – '.$weekEnd->isoFormat('D MMM YYYY'),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'jamCount' => $jamCount,
            'jamMulai' => $kbm?->kbm_jam_mulai,
            'jamPel' => (int) ($kbm?->kbm_jam_pel ?? 0),
            'isAdmin' => $user->isAdmin(),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function set(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();

        $data = $request->validate([
            'kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'date' => ['required', 'date'],
            'jam' => ['required', 'integer', 'min:1', 'max:12'],
            'jenis' => ['required', 'integer', 'in:1,2'],
            'id_materi' => ['nullable', 'integer', 'exists:kelas_materi,id_materi'],
        ]);

        $kelasId = (int) $data['kelas'];
        $idKjm = 'K'.$kelasId.'-'.str_replace('-', '', $data['date']).'-J'.$data['jam'].'-T'.$data['jenis'];

        if (empty($data['id_materi'])) {
            KelasJadwalMateri::where('id_kjm', $idKjm)->delete();

            AuditLog::record('elearning.jadwal', 'Kelas #'.$kelasId.' '.$data['date'].' jam '.$data['jam'].' dikosongkan');

            return back()->with('success', 'Jadwal dikosongkan.');
        }

        $materi = KelasMateri::where('id_tp', $tp)->where('id_smt', $smt)->findOrFail($data['id_materi']);

        if (! $user->isAdmin() && (int) $materi->id_guru !== (int) $this->guruId($user)) {
            abort(403);
        }

        if (! in_array($kelasId, $this->kelasIds($materi), true)) {
            return back()->with('error', 'Materi ini tidak ditujukan untuk kelas tersebut.');
        }

        KelasJadwalMateri::updateOrCreate(
            ['id_kjm' => $idKjm],
            [
                'id_tp' => $tp,
                'id_smt' => $smt,
                'id_materi' => (int) $materi->id_materi,
                'id_mapel' => (int) ($materi->id_mapel ?? 0),
                'id_kelas' => $kelasId,
                'jadwal_materi' => $data['date'],
                'jenis' => (int) $data['jenis'],
            ]
        );

        AuditLog::record('elearning.jadwal', 'Kelas #'.$kelasId.' '.$data['date'].' jam '.$data['jam'].' → materi #'.(int) $materi->id_materi);

        return back()->with('success', 'Jadwal berhasil disimpan.');
    }

    private function kelasIds(KelasMateri $item): array
    {
        $ids = @unserialize((string) $item->materi_kelas, ['allowed_classes' => false]);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    private function guruId($user): ?int
    {
        return Guru::where('id_user', $user->id)->value('id_guru');
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
