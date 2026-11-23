<?php

namespace App\Http\Controllers\Elearning;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasJadwalMateri;
use App\Models\KelasMateri;
use App\Models\LogMateri;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MateriController extends Controller
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

    public function tugasDetail(Request $request, int $id): Response
    {
        $item = $this->owned($request, $id);
        abort_unless((int) $item->jenis === 2, 404);
        $item->load(['mapel:id_mapel,nama_mapel', 'guru:id_guru,nama_guru']);

        $schedules = KelasJadwalMateri::with('kelas:id_kelas,nama_kelas')
            ->where('id_materi', $item->id_materi)
            ->where('id_tp', $item->id_tp)
            ->where('id_smt', $item->id_smt)
            ->orderBy('jadwal_materi')
            ->get();

        $scheduleIds = $schedules->pluck('id_kjm')->all();
        $scheduleByKelas = [];

        foreach ($schedules as $schedule) {
            $scheduleByKelas[(int) $schedule->id_kelas] ??= (string) $schedule->id_kjm;
        }

        $classIds = array_keys($scheduleByKelas);

        $students = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $item->id_tp)
            ->where('kelas_siswa.id_smt', $item->id_smt)
            ->whereIn('kelas_siswa.id_kelas', $classIds)
            ->orderBy('master_kelas.nama_kelas')
            ->orderBy('master_siswa.nama')
            ->get([
                'master_siswa.id_siswa',
                'master_siswa.nis',
                'master_siswa.nama',
                'master_kelas.id_kelas',
                'master_kelas.nama_kelas',
            ]);

        $logs = $scheduleIds === []
            ? collect()
            : LogMateri::whereIn('id_materi', $scheduleIds)
                ->whereIn('id_siswa', $students->pluck('id_siswa')->all())
                ->get()
                ->keyBy(fn (LogMateri $log) => $log->id_siswa.'-'.$log->id_materi);

        $tenggat = $item->tenggat ? Carbon::parse($item->tenggat) : null;

        $rows = $students->map(function ($student) use ($scheduleByKelas, $logs, $tenggat) {
            $scheduleId = $scheduleByKelas[(int) $student->id_kelas] ?? null;
            $log = $scheduleId ? $logs->get($student->id_siswa.'-'.$scheduleId) : null;

            $submitted = $log && ($log->finish_time || trim((string) $log->text) !== '' || trim((string) $log->file) !== '');
            $late = $submitted && $tenggat && $log->finish_time && Carbon::parse($log->finish_time)->greaterThan($tenggat);
            $graded = $log && $log->nilai !== null && $log->nilai !== '';

            return [
                'id_siswa' => $student->id_siswa,
                'nis' => $student->nis,
                'nama' => $student->nama,
                'kelas' => $student->nama_kelas,
                'id_log' => $log?->id_log,
                'submitted' => $submitted,
                'late' => (bool) $late,
                'finish_time' => $log?->finish_time ? Carbon::parse($log->finish_time)->isoFormat('D MMM YYYY, HH:mm') : null,
                'text' => (string) ($log->text ?? ''),
                'files' => $this->files($log?->file),
                'nilai' => $graded ? (int) $log->nilai : null,
                'catatan' => (string) ($log->catatan ?? ''),
                'graded' => (bool) $graded,
            ];
        })->values();

        return Inertia::render('Elearning/Nilai', [
            'item' => [
                'id_materi' => (int) $item->id_materi,
                'judul' => (string) $item->judul_materi,
                'kode' => (string) $item->kode_materi,
                'mapel' => $item->mapel?->nama_mapel,
                'guru' => $item->guru?->nama_guru,
                'tenggat' => $tenggat?->isoFormat('D MMMM YYYY, HH:mm'),
                'tenggat_raw' => $item->tenggat,
                'kelas' => $schedules->pluck('kelas.nama_kelas')->filter()->unique()->values()->all(),
            ],
            'rows' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'submitted' => $rows->where('submitted', true)->count(),
                'late' => $rows->where('late', true)->count(),
                'graded' => $rows->where('graded', true)->count(),
            ],
        ]);
    }

    public function gradeTugas(Request $request, int $id): RedirectResponse
    {
        $item = $this->owned($request, $id);
        abort_unless((int) $item->jenis === 2, 404);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id_siswa' => ['required', 'integer'],
            'rows.*.nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $scheduleIds = KelasJadwalMateri::where('id_materi', $item->id_materi)->pluck('id_kjm')->all();
        $saved = 0;
        $skipped = 0;

        foreach ($data['rows'] as $row) {
            $log = LogMateri::where('id_siswa', (int) $row['id_siswa'])
                ->whereIn('id_materi', $scheduleIds)
                ->orderByDesc('log_time')
                ->first();

            if (! $log) {
                $skipped++;

                continue;
            }

            $nilai = $row['nilai'] === null || $row['nilai'] === '' ? null : (string) max(0, min(100, (int) round((float) $row['nilai'])));

            $log->update([
                'nilai' => $nilai,
                'catatan' => trim((string) ($row['catatan'] ?? '')) ?: null,
                'dinilai_oleh' => $request->user()->id,
                'dinilai_at' => now(),
            ]);

            $saved++;
        }

        AuditLog::record('elearning.tugas.nilai', 'Tugas #'.$item->id_materi.': '.$saved.' siswa dinilai');

        return back()->with('success', "Penilaian tersimpan untuk {$saved} siswa, {$skipped} dilewati karena belum mengumpulkan.");
    }

    public function storeMateri(Request $request): RedirectResponse
    {
        [$response, $item] = $this->store($request, 1);

        if ($item) {
            AuditLog::record('elearning.materi', 'Materi #'.$item->id_materi.' '.$item->judul_materi.' dibuat');
        }

        return $response;
    }

    public function storeTugas(Request $request): RedirectResponse
    {
        [$response, $item] = $this->store($request, 2);

        if ($item) {
            AuditLog::record('elearning.tugas', 'Tugas #'.$item->id_materi.' '.$item->judul_materi.' dibuat');
        }

        return $response;
    }

    private function list(Request $request, int $jenis): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();
        $guruId = $this->guruId($user);

        $query = KelasMateri::query()
            ->with(['mapel:id_mapel,nama_mapel,kode', 'guru:id_guru,nama_guru', 'jadwal.kelas:id_kelas,nama_kelas'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('jenis', $jenis);

        if (! $user->isAdmin()) {
            $query->where('id_guru', $guruId ?? 0);
        } elseif ($guru = $request->query('guru')) {
            $query->where('id_guru', $guru);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_materi', 'like', "%{$search}%")
                    ->orWhere('kode_materi', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('created_on')->get();

        $kelasIds = $items->flatMap(fn (KelasMateri $item) => $this->kelasIds($item))->unique()->values();
        $kelasNames = Kelas::whereIn('id_kelas', $kelasIds)->pluck('nama_kelas', 'id_kelas');

        $scheduleIds = $items->flatMap(fn (KelasMateri $item) => $item->jadwal->pluck('id_kjm'))->unique()->values();
        $logStats = LogMateri::whereIn('id_materi', $scheduleIds)
            ->selectRaw('id_materi, COUNT(*) as dibuka, SUM(CASE WHEN finish_time IS NOT NULL THEN 1 ELSE 0 END) as selesai')
            ->groupBy('id_materi')
            ->get()
            ->keyBy('id_materi');

        $rows = $items->map(function (KelasMateri $item) use ($kelasNames, $logStats) {
            $ids = $this->kelasIds($item);
            $dibuka = 0;
            $selesai = 0;

            foreach ($item->jadwal as $jadwal) {
                $stat = $logStats->get($jadwal->id_kjm);
                $dibuka += (int) ($stat->dibuka ?? 0);
                $selesai += (int) ($stat->selesai ?? 0);
            }

            return [
                'id_materi' => (int) $item->id_materi,
                'jenis' => (int) $item->jenis,
                'kode_materi' => (string) $item->kode_materi,
                'judul_materi' => (string) $item->judul_materi,
                'isi_materi' => (string) ($item->isi_materi ?? ''),
                'id_mapel' => (int) ($item->id_mapel ?? 0),
                'mapel' => $item->mapel?->nama_mapel,
                'kode_mapel' => $item->mapel?->kode,
                'guru_id' => (int) ($item->id_guru ?? 0),
                'guru' => $item->guru?->nama_guru,
                'status' => (int) $item->status,
                'created_on' => $this->dateLabel($item->created_on),
                'tgl_mulai' => $item->tgl_mulai ? substr((string) $item->tgl_mulai, 0, 10) : null,
                'tenggat' => $item->tenggat ? substr((string) $item->tenggat, 0, 16) : null,
                'link_file' => (string) ($item->link_file ?? ''),
                'youtube' => (string) ($item->youtube ?? ''),
                'files' => $this->files($item->file),
                'kelas_ids' => $ids,
                'kelas' => collect($ids)->map(fn ($id) => $kelasNames[$id] ?? null)->filter()->values()->all(),
                'jadwal_count' => $item->jadwal->count(),
                'dibuka' => $dibuka,
                'selesai' => $selesai,
            ];
        })->values();

        return Inertia::render('Elearning/Index', [
            'jenis' => $jenis,
            'items' => $rows,
            'mapels' => Mapel::orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kode']),
            'kelasOptions' => $tp && $smt
                ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
                : [],
            'gurus' => Guru::orderBy('nama_guru')->get(['id_guru', 'nama_guru']),
            'isAdmin' => $user->isAdmin(),
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'guru' => (string) $request->query('guru', ''),
            ],
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    /**
     * @return array{0: RedirectResponse, 1: KelasMateri|null}
     */
    private function store(Request $request, int $jenis): array
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return [back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.'), null];
        }

        $data = $this->validated($request);
        $user = $request->user();

        if ($user->isAdmin()) {
            $guruId = (int) ($data['guru_id'] ?? 0);
            if (! $guruId) {
                return [back()->withErrors(['guru_id' => 'Pilih guru pengampu.'])->withInput(), null];
            }
        } else {
            $guruId = (int) ($this->guruId($user) ?? 0);
            if (! $guruId) {
                return [back()->with('error', 'Akun Anda tidak terhubung dengan data guru.'), null];
            }
        }

        $kodeMapel = Mapel::where('id_mapel', $data['id_mapel'])->value('kode') ?: 'MTR';
        $kodeMateri = $this->uniqueKode((string) $kodeMapel);

        $kelasIds = collect($data['kelas'])->map(fn ($id) => (int) $id)->unique()->values()->all();

        $materi = KelasMateri::create([
            'id_tp' => $tp,
            'id_smt' => $smt,
            'jenis' => $jenis,
            'kode_materi' => $kodeMateri,
            'kode_mapel' => $kodeMapel,
            'id_guru' => $guruId,
            'id_mapel' => (int) $data['id_mapel'],
            'judul_materi' => $data['judul'],
            'isi_materi' => (string) ($data['isi'] ?? ''),
            'link_file' => $data['link'] ?? null,
            'youtube' => $data['youtube'] ?? '',
            'tgl_mulai' => $data['tgl_mulai'] ?? null,
            'tenggat' => $data['tenggat'] ?? null,
            'materi_kelas' => serialize($kelasIds),
            'file' => serialize($this->storeFiles($request, $jenis)),
            'status' => (int) ($data['status'] ?? 1),
            'created_on' => now(),
            'updated_on' => now(),
        ]);

        return [back()->with('success', ($jenis === 1 ? 'Materi' : 'Tugas').' berhasil dibuat.'), $materi];
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->owned($request, $id);
        $data = $this->validated($request);

        $kelasIds = collect($data['kelas'])->map(fn ($kelas) => (int) $kelas)->unique()->values()->all();
        $payload = [
            'id_mapel' => (int) $data['id_mapel'],
            'judul_materi' => $data['judul'],
            'isi_materi' => (string) ($data['isi'] ?? ''),
            'link_file' => $data['link'] ?? null,
            'youtube' => $data['youtube'] ?? '',
            'tgl_mulai' => $data['tgl_mulai'] ?? null,
            'tenggat' => $data['tenggat'] ?? null,
            'materi_kelas' => serialize($kelasIds),
            'status' => (int) ($data['status'] ?? 0),
            'updated_on' => now(),
        ];

        if ($request->user()->isAdmin() && ! empty($data['guru_id'])) {
            $payload['id_guru'] = (int) $data['guru_id'];
        }

        $newFiles = $this->storeFiles($request, (int) $item->jenis);

        if ($newFiles) {
            $payload['file'] = serialize(array_merge($this->filesRaw($item->file), $newFiles));
        }

        $item->update($payload);

        if ((int) $item->jenis === 2) {
            AuditLog::record('elearning.tugas', 'Tugas #'.$item->id_materi.' '.$item->judul_materi.' diperbarui');
        } else {
            AuditLog::record('elearning.materi', 'Materi #'.$item->id_materi.' '.$item->judul_materi.' diperbarui');
        }

        return back()->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $item = $this->owned($request, $id);

        DB::transaction(function () use ($item) {
            $schedules = KelasJadwalMateri::where('id_materi', $item->id_materi)->pluck('id_kjm');
            LogMateri::whereIn('id_materi', $schedules)->delete();
            KelasJadwalMateri::where('id_materi', $item->id_materi)->delete();
            $item->delete();
        });

        $this->deleteStoredFiles($this->filesRaw($item->file));

        AuditLog::record('elearning.delete', 'Item #'.$item->id_materi.' '.$item->judul_materi.' (jenis '.$item->jenis.') dihapus');

        return back()->with('success', 'Data berhasil dihapus.');
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $item = $this->owned($request, $id);
        $item->update(['status' => (int) $item->status === 1 ? 0 : 1, 'updated_on' => now()]);

        AuditLog::record('elearning.status', 'Item #'.$item->id_materi.' → '.((int) $item->status === 1 ? 'aktif' : 'nonaktif'));

        return back()->with('success', 'Status berhasil diubah.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'id_mapel' => ['required', 'integer', 'exists:master_mapel,id_mapel'],
            'kelas' => ['required', 'array', 'min:1'],
            'kelas.*' => ['integer', 'exists:master_kelas,id_kelas'],
            'isi' => ['nullable', 'string', 'max:50000'],
            'link' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'tgl_mulai' => ['nullable', 'date'],
            'tenggat' => ['nullable', 'date'],
            'status' => ['nullable', 'boolean'],
            'guru_id' => ['nullable', 'integer', 'exists:master_guru,id_guru'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:'.self::ALLOWED_FILES],
        ]);
    }

    private function owned(Request $request, int $id): KelasMateri
    {
        $item = KelasMateri::where('id_tp', $this->activePeriod()[0])
            ->where('id_smt', $this->activePeriod()[1])
            ->findOrFail($id);

        if (! $request->user()->isAdmin() && (int) $item->id_guru !== (int) $this->guruId($request->user())) {
            abort(403);
        }

        return $item;
    }

    /**
     * Kode materi dan tugas harus unik lintas jenis, karena keduanya memakai
     * pola yang sama dan bisa dibuat pada tanggal yang sama.
     */
    private function uniqueKode(string $kodeMapel): string
    {
        $base = strtoupper($kodeMapel).'-'.now()->format('Ymd').'-';
        $seq = 1;

        while (KelasMateri::where('kode_materi', $base.str_pad((string) $seq, 2, '0', STR_PAD_LEFT))->exists()) {
            $seq++;
        }

        return $base.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
    }

    private function storeFiles(Request $request, int $jenis): array
    {
        $files = $request->file('files', []);
        $files = is_array($files) ? $files : [$files];
        $result = [];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('elearning/'.($jenis === 1 ? 'materi' : 'tugas'), 'public');
            $result[] = [
                'src' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getClientMimeType(),
            ];
        }

        return $result;
    }

    private function deleteStoredFiles(array $files): void
    {
        foreach ($files as $file) {
            $src = (string) ($file['src'] ?? '');
            if ($src !== '' && ! str_starts_with($src, 'http') && ! str_starts_with($src, 'uploads/')) {
                Storage::disk('public')->delete($src);
            }
        }
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
                'src' => $src,
                'url' => $url,
                'size' => $file['size'] ?? null,
                'type' => (string) ($file['type'] ?? ''),
            ];
        })->values()->all();
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

    private function dateLabel(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->isoFormat('D MMM YYYY');
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
