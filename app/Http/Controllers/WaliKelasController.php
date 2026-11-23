<?php

namespace App\Http\Controllers;

use App\Models\JabatanGuru;
use App\Models\Kelas;
use App\Models\KelasCatatanMapel;
use App\Models\KelasCatatanWali;
use App\Models\KelasStruktur;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\Period;
use App\Support\Rapor as RaporSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WaliKelasController extends Controller
{
    private const ROLES = [
        'ketua' => 'Ketua kelas',
        'wakil_ketua' => 'Wakil ketua',
        'sekretaris_1' => 'Sekretaris 1',
        'sekretaris_2' => 'Sekretaris 2',
        'bendahara_1' => 'Bendahara 1',
        'bendahara_2' => 'Bendahara 2',
        'sie_ekstrakurikuler' => 'Sie ekstrakurikuler',
        'sie_upacara' => 'Sie upacara',
        'sie_olahraga' => 'Sie olahraga',
        'sie_keagamaan' => 'Sie keagamaan',
        'sie_keamanan' => 'Sie keamanan',
        'sie_ketertiban' => 'Sie ketertiban',
        'sie_kebersihan' => 'Sie kebersihan',
        'sie_keindahan' => 'Sie keindahan',
        'sie_kesehatan' => 'Sie kesehatan',
        'sie_kekeluargaan' => 'Sie kekeluargaan',
        'sie_humas' => 'Sie humas',
    ];

    private const LEVELS = [
        1 => 'Saran',
        2 => 'Teguran',
        3 => 'Peringatan',
        4 => 'Sangsi',
    ];

    public function struktur(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $classes = $this->waliClasses($request->user(), $tp, $smt);
        $selected = $this->selectedClass($classes, $request->query('kelas'));

        $students = $selected ? $this->students($selected->id_kelas, $tp, $smt) : collect();
        $record = $selected ? KelasStruktur::find($selected->id_kelas) : null;

        return Inertia::render('WaliKelas/Struktur', [
            'classes' => $classes,
            'selected' => $selected ? [
                'id_kelas' => (int) $selected->id_kelas,
                'nama_kelas' => $selected->nama_kelas,
                'wali' => $selected->waliKelas?->nama_guru,
            ] : null,
            'students' => $students,
            'struktur' => $record?->toArray(),
            'roles' => collect(self::ROLES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'canManage' => $selected !== null,
        ]);
    }

    public function saveStruktur(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $classes = $this->waliClasses($request->user(), $tp, $smt);

        $data = $request->validate([
            'id_kelas' => ['required', 'integer'],
            ...collect(self::ROLES)->mapWithKeys(fn ($label, $key) => [$key => ['nullable', 'integer']])->all(),
        ]);

        $selected = $classes->firstWhere('id_kelas', (int) $data['id_kelas']);

        if (! $selected) {
            abort(403);
        }

        $kelasId = (int) $selected['id_kelas'];
        $studentIds = $this->students($kelasId, $tp, $smt)->pluck('id_siswa')->all();
        $payload = ['id_kelas' => $kelasId];

        foreach (array_keys(self::ROLES) as $key) {
            $value = $data[$key] ?? null;

            if ($value !== null && ! in_array((int) $value, $studentIds, true)) {
                throw ValidationException::withMessages([$key => 'Siswa yang dipilih bukan anggota kelas ini.']);
            }

            $payload[$key] = $value !== null ? (int) $value : null;
        }

        KelasStruktur::updateOrCreate(['id_kelas' => $kelasId], $payload);

        AuditLog::record('wali.struktur', 'Kelas #'.$kelasId.' '.$selected['nama_kelas'].' disimpan');

        return back()->with('success', 'Struktur organisasi kelas disimpan.');
    }

    public function catatan(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $classes = $this->teachClasses($user, $tp, $smt);
        $selected = $this->selectedClass($classes, $request->query('kelas'));
        $mapels = $selected ? $this->mapelsForClass($user, $selected, $tp, $smt) : collect();
        $requestedMapel = $request->query('mapel');
        $selectedMapel = $requestedMapel !== null
            ? $mapels->firstWhere('id_mapel', (int) $requestedMapel)
            : $mapels->first();

        $mapelId = $selectedMapel ? (int) $selectedMapel['id_mapel'] : null;
        $isWali = $selected !== null && $this->isWali($user, $selected);

        return Inertia::render('WaliKelas/Catatan', [
            'classes' => $classes,
            'selected' => $selected ? [
                'id_kelas' => (int) $selected->id_kelas,
                'nama_kelas' => $selected->nama_kelas,
                'wali' => $selected->waliKelas?->nama_guru,
            ] : null,
            'filters' => ['kelas' => $selected?->id_kelas, 'mapel' => $mapelId],
            'mapels' => $mapels,
            'selectedMapel' => $selectedMapel ? (int) $selectedMapel['id_mapel'] : null,
            'students' => $selected ? $this->students($selected->id_kelas, $tp, $smt) : collect(),
            'catatanKelasMapel' => $selected && $mapelId
                ? $this->notesMapel($selected->id_kelas, $mapelId, $tp, $smt, true)
                : collect(),
            'catatanSiswaMapel' => $selected && $mapelId
                ? $this->notesMapel($selected->id_kelas, $mapelId, $tp, $smt, false)
                : collect(),
            'catatanKelasWali' => $selected && $isWali ? $this->notesWali($selected->id_kelas, $tp, $smt, true) : collect(),
            'catatanSiswaWali' => $selected && $isWali ? $this->notesWali($selected->id_kelas, $tp, $smt, false) : collect(),
            'isWali' => $isWali,
            'levels' => collect(self::LEVELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function storeCatatanMapel(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();

        $data = $request->validate([
            'id_kelas' => ['required', 'integer'],
            'id_mapel' => ['required', 'integer'],
            'type' => ['required', 'integer', 'in:1,2'],
            'id_siswa' => ['nullable', 'required_if:type,2', 'integer'],
            'level' => ['required', 'integer', 'in:1,2,3,4'],
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $kelas = Kelas::where('id_kelas', $data['id_kelas'])->where('id_tp', $tp)->where('id_smt', $smt)->first();

        if (! $kelas) {
            abort(404);
        }

        $this->authorizeMapelClass($user, (int) $kelas->id_kelas, (int) $data['id_mapel'], $tp, $smt);

        if ((int) $data['type'] === 2) {
            $this->ensureStudent((int) $data['id_siswa'], (int) $kelas->id_kelas, $tp, $smt);
        }

        KelasCatatanMapel::create([
            'id_tp' => $tp,
            'id_smt' => $smt,
            'type' => (int) $data['type'],
            'id_siswa' => (int) $data['type'] === 2 ? (int) $data['id_siswa'] : null,
            'id_mapel' => (int) $data['id_mapel'],
            'id_kelas' => (int) $kelas->id_kelas,
            'id_guru' => RaporSupport::guruId($user),
            'level' => (string) $data['level'],
            'tgl' => now(),
            'text' => $data['text'],
            'readed' => '0',
            'reading' => serialize([]),
            'jml' => 0,
        ]);

        AuditLog::record('wali.catatan.mapel', 'Kelas #'.$kelas->id_kelas.' mapel #'.(int) $data['id_mapel'].' level '.$data['level']);

        return back()->with('success', 'Catatan mata pelajaran disimpan.');
    }

    public function destroyCatatanMapel(Request $request, int $catatan): RedirectResponse
    {
        $record = KelasCatatanMapel::findOrFail($catatan);
        $guruId = RaporSupport::guruId($request->user());

        abort_unless($request->user()->isAdmin() || ($guruId !== null && (int) $record->id_guru === $guruId), 403);

        AuditLog::record('wali.catatan.mapel.delete', 'Catatan #'.$record->id_catatan.' kelas #'.$record->id_kelas.' mapel #'.$record->id_mapel);

        $record->delete();

        return back()->with('success', 'Catatan mata pelajaran dihapus.');
    }

    public function storeCatatanWali(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();

        $data = $request->validate([
            'id_kelas' => ['required', 'integer'],
            'type' => ['required', 'integer', 'in:1,2'],
            'id_siswa' => ['nullable', 'required_if:type,2', 'integer'],
            'level' => ['required', 'integer', 'in:1,2,3,4'],
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $kelas = Kelas::where('id_kelas', $data['id_kelas'])->where('id_tp', $tp)->where('id_smt', $smt)->first();

        if (! $kelas) {
            abort(404);
        }

        if (! $this->isWali($user, $kelas)) {
            abort(403);
        }

        if ((int) $data['type'] === 2) {
            $this->ensureStudent((int) $data['id_siswa'], (int) $kelas->id_kelas, $tp, $smt);
        }

        KelasCatatanWali::create([
            'id_tp' => $tp,
            'id_smt' => $smt,
            'type' => (int) $data['type'],
            'level' => (string) $data['level'],
            'tgl' => now(),
            'id_siswa' => (int) $data['type'] === 2 ? (int) $data['id_siswa'] : null,
            'id_kelas' => (int) $kelas->id_kelas,
            'text' => $data['text'],
            'readed' => '0',
            'reading' => serialize([]),
            'jml' => 0,
        ]);

        AuditLog::record('wali.catatan.wali', 'Kelas #'.$kelas->id_kelas.' level '.$data['level']);

        return back()->with('success', 'Catatan wali kelas disimpan.');
    }

    public function destroyCatatanWali(Request $request, int $catatan): RedirectResponse
    {
        $record = KelasCatatanWali::findOrFail($catatan);
        $kelas = Kelas::find($record->id_kelas);

        abort_unless($request->user()->isAdmin() || ($kelas && $this->isWali($request->user(), $kelas)), 403);

        AuditLog::record('wali.catatan.wali.delete', 'Catatan #'.$record->id_catatan.' kelas #'.$record->id_kelas);

        $record->delete();

        return back()->with('success', 'Catatan wali kelas dihapus.');
    }

    public function siswa(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $classes = $this->waliClasses($request->user(), $tp, $smt);
        $selected = $this->selectedClass($classes, $request->query('kelas'));

        return Inertia::render('WaliKelas/Siswa', [
            'classes' => $classes,
            'selected' => $selected ? [
                'id_kelas' => (int) $selected->id_kelas,
                'nama_kelas' => $selected->nama_kelas,
                'wali' => $selected->waliKelas?->nama_guru,
            ] : null,
            'students' => $selected ? $this->studentDetails($selected->id_kelas, $tp, $smt) : collect(),
            'agamaOptions' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'],
        ]);
    }

    public function updateSiswa(Request $request, int $siswa): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $record = $this->waliStudent($request->user(), $siswa, $tp, $smt);

        $data = $request->validate([
            'id_kelas' => ['required', 'integer'],
            'nisn' => ['required', 'numeric', 'max:4294967295', Rule::unique('master_siswa', 'nisn')->ignore($record->id_siswa, 'id_siswa')],
            'nis' => ['required', 'string', 'max:20', Rule::unique('master_siswa', 'nis')->ignore($record->id_siswa, 'id_siswa')],
            'nama' => ['required', 'string', 'max:50'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'tahun_masuk' => ['nullable', 'string', 'max:30'],
            'sekolah_asal' => ['nullable', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:10'],
            'status_keluarga' => ['nullable', 'string', 'max:1'],
            'anak_ke' => ['nullable', 'integer', 'min:0'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'integer', 'min:0'],
            'hp' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:254'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'tgl_lahir_ayah' => ['nullable', 'string', 'max:50'],
            'pendidikan_ayah' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:100'],
            'nohp_ayah' => ['nullable', 'string', 'max:20'],
            'alamat_ayah' => ['nullable', 'string', 'max:1000'],
            'nama_ibu' => ['nullable', 'string', 'max:50'],
            'tgl_lahir_ibu' => ['nullable', 'string', 'max:50'],
            'pendidikan_ibu' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:100'],
            'nohp_ibu' => ['nullable', 'string', 'max:20'],
            'alamat_ibu' => ['nullable', 'string', 'max:1000'],
            'nama_wali' => ['nullable', 'string', 'max:150'],
            'tgl_lahir_wali' => ['nullable', 'string', 'max:50'],
            'pendidikan_wali' => ['nullable', 'string', 'max:50'],
            'pekerjaan_wali' => ['nullable', 'string', 'max:100'],
            'nohp_wali' => ['nullable', 'string', 'max:20'],
            'alamat_wali' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureStudentInWaliClass($request->user(), $record, (int) $data['id_kelas'], $tp, $smt);
        unset($data['id_kelas']);

        $record->update($data);

        AuditLog::record('wali.siswa', 'Siswa #'.$record->id_siswa.' '.$record->nama.' diperbarui');

        return back()->with('success', 'Data siswa diperbarui.');
    }

    public function uploadFoto(Request $request, int $siswa): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $record = $this->waliStudent($request->user(), $siswa, $tp, $smt);

        $request->validate([
            'foto' => ['required', 'image', 'max:2048'],
        ]);

        if ($record->foto && $record->foto !== 'siswa.png') {
            Storage::disk('public')->delete($record->foto);
        }

        $record->update(['foto' => $request->file('foto')->store('siswa', 'public')]);

        AuditLog::record('wali.siswa.foto', 'Foto siswa #'.$record->id_siswa.' diperbarui');

        return back()->with('success', 'Foto siswa diperbarui.');
    }

    public function deleteFoto(Request $request, int $siswa): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $record = $this->waliStudent($request->user(), $siswa, $tp, $smt);

        if ($record->foto && $record->foto !== 'siswa.png') {
            Storage::disk('public')->delete($record->foto);
        }

        $record->update(['foto' => null]);

        AuditLog::record('wali.siswa.foto', 'Foto siswa #'.$record->id_siswa.' dihapus');

        return back()->with('success', 'Foto siswa dihapus.');
    }

    private function period(): array
    {
        [$tahun, $semester] = Period::current();

        return [(int) ($tahun?->id_tp ?? 0), (int) ($semester?->id_smt ?? 0)];
    }

    private function waliClasses(User $user, int $tp, int $smt): Collection
    {
        $query = Kelas::query()
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->with('waliKelas:id_guru,nama_guru')
            ->orderBy('nama_kelas');

        if (! $user->isAdmin()) {
            $guruId = RaporSupport::guruId($user);

            if (! $guruId) {
                return collect();
            }

            $query->where('guru_id', $guruId);
        }

        return $query->get(['id_kelas', 'nama_kelas', 'guru_id'])->map(fn (Kelas $kelas) => [
            'id_kelas' => (int) $kelas->id_kelas,
            'nama_kelas' => (string) $kelas->nama_kelas,
            'wali' => $kelas->waliKelas?->nama_guru,
        ]);
    }

    private function teachClasses(User $user, int $tp, int $smt): Collection
    {
        if ($user->isAdmin()) {
            return $this->waliClasses($user, $tp, $smt);
        }

        $guruId = RaporSupport::guruId($user);

        if (! $guruId) {
            return collect();
        }

        $classIds = $this->waliClasses($user, $tp, $smt)->pluck('id_kelas')->map(fn ($id) => (int) $id);

        foreach (JabatanGuru::where('id_guru', $guruId)->where('id_tp', $tp)->where('id_smt', $smt)->get() as $jabatan) {
            foreach (RaporSupport::mapelKelas($jabatan->mapel_kelas) as $mapel) {
                $classIds = $classIds->merge($mapel['kelas']);
            }

            if ($jabatan->id_kelas) {
                $classIds->push((int) $jabatan->id_kelas);
            }
        }

        $classIds = $classIds->unique()->values();

        return Kelas::query()
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->whereIn('id_kelas', $classIds)
            ->with('waliKelas:id_guru,nama_guru')
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas', 'guru_id'])
            ->map(fn (Kelas $kelas) => [
                'id_kelas' => (int) $kelas->id_kelas,
                'nama_kelas' => (string) $kelas->nama_kelas,
                'wali' => $kelas->waliKelas?->nama_guru,
            ]);
    }

    private function selectedClass(Collection $classes, mixed $requested): ?Kelas
    {
        if ($classes->isEmpty()) {
            return null;
        }

        $row = null;

        if ($requested !== null) {
            $row = $classes->firstWhere('id_kelas', (int) $requested);
        }

        $row ??= $classes->first();

        return Kelas::with('waliKelas:id_guru,nama_guru')->find((int) $row['id_kelas']);
    }

    private function mapelsForClass(User $user, Kelas $kelas, int $tp, int $smt): Collection
    {
        if ($user->isAdmin()) {
            return Mapel::orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel'])->map(fn (Mapel $mapel) => [
                'id_mapel' => (int) $mapel->id_mapel,
                'nama_mapel' => (string) $mapel->nama_mapel,
            ]);
        }

        $guruId = RaporSupport::guruId($user);
        $rows = collect();

        if ($guruId) {
            foreach (JabatanGuru::where('id_guru', $guruId)->where('id_tp', $tp)->where('id_smt', $smt)->get() as $jabatan) {
                foreach (RaporSupport::mapelKelas($jabatan->mapel_kelas) as $mapel) {
                    if (in_array((int) $kelas->id_kelas, $mapel['kelas'], true)) {
                        $rows->push(['id_mapel' => $mapel['id_mapel'], 'nama_mapel' => $mapel['nama_mapel']]);
                    }
                }
            }
        }

        return $rows->unique('id_mapel')->sortBy('nama_mapel')->values();
    }

    private function students(int $kelasId, int $tp, int $smt): Collection
    {
        return DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->where('kelas_siswa.id_kelas', $kelasId)
            ->whereNull('master_siswa.deleted_at')
            ->orderBy('master_siswa.nama')
            ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nisn', 'master_siswa.nama'])
            ->map(fn ($row) => [
                'id_siswa' => (int) $row->id_siswa,
                'nis' => (string) $row->nis,
                'nisn' => (string) $row->nisn,
                'nama' => (string) $row->nama,
            ]);
    }

    private function studentDetails(int $kelasId, int $tp, int $smt): Collection
    {
        $ids = DB::table('kelas_siswa')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('id_kelas', $kelasId)
            ->pluck('id_siswa');

        return Siswa::whereIn('id_siswa', $ids)
            ->orderBy('nama')
            ->get()
            ->map(fn (Siswa $siswa) => [
                ...$siswa->toArray(),
                'id_siswa' => (int) $siswa->id_siswa,
                'foto_url' => $siswa->photo_url,
            ]);
    }

    private function waliStudent(User $user, int $siswaId, int $tp, int $smt): Siswa
    {
        $query = DB::table('kelas_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->where('kelas_siswa.id_siswa', $siswaId);

        if (! $user->isAdmin()) {
            $guruId = RaporSupport::guruId($user);
            abort_if($guruId === null, 403);
            $query->where('master_kelas.guru_id', $guruId);
        }

        abort_unless($query->exists(), 403);

        return Siswa::findOrFail($siswaId);
    }

    private function ensureStudentInWaliClass(User $user, Siswa $siswa, int $kelasId, int $tp, int $smt): void
    {
        $kelas = Kelas::where('id_kelas', $kelasId)->where('id_tp', $tp)->where('id_smt', $smt)->firstOrFail();

        abort_unless($this->isWali($user, $kelas), 403);

        $exists = DB::table('kelas_siswa')
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('id_kelas', $kelasId)
            ->where('id_siswa', $siswa->id_siswa)
            ->exists();

        abort_unless($exists, 403);
    }

    private function notesMapel(int $kelasId, int $mapelId, int $tp, int $smt, bool $classWide): Collection
    {
        return DB::table('kelas_catatan_mapel')
            ->leftJoin('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_catatan_mapel.id_siswa')
            ->leftJoin('master_guru', 'master_guru.id_guru', '=', 'kelas_catatan_mapel.id_guru')
            ->where('kelas_catatan_mapel.id_tp', $tp)
            ->where('kelas_catatan_mapel.id_smt', $smt)
            ->where('kelas_catatan_mapel.id_kelas', $kelasId)
            ->where('kelas_catatan_mapel.id_mapel', $mapelId)
            ->where('kelas_catatan_mapel.type', $classWide ? 1 : 2)
            ->orderByDesc('kelas_catatan_mapel.tgl')
            ->orderByDesc('kelas_catatan_mapel.id_catatan')
            ->get([
                'kelas_catatan_mapel.id_catatan',
                'kelas_catatan_mapel.id_siswa',
                'kelas_catatan_mapel.tgl',
                'kelas_catatan_mapel.level',
                'kelas_catatan_mapel.text',
                'kelas_catatan_mapel.id_guru',
                'master_siswa.nama as siswa',
                'master_guru.nama_guru as guru',
            ])
            ->map(fn ($row) => [
                'id_catatan' => (int) $row->id_catatan,
                'id_siswa' => $row->id_siswa ? (int) $row->id_siswa : null,
                'siswa' => $row->siswa,
                'guru' => $row->guru,
                'id_guru' => $row->id_guru ? (int) $row->id_guru : null,
                'tgl' => $row->tgl ? date('d.m.Y H:i', strtotime($row->tgl)) : '-',
                'level' => (int) $row->level,
                'level_label' => self::LEVELS[(int) $row->level] ?? '-',
                'text' => (string) $row->text,
            ]);
    }

    private function notesWali(int $kelasId, int $tp, int $smt, bool $classWide): Collection
    {
        return DB::table('kelas_catatan_wali')
            ->leftJoin('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_catatan_wali.id_siswa')
            ->where('kelas_catatan_wali.id_tp', $tp)
            ->where('kelas_catatan_wali.id_smt', $smt)
            ->where('kelas_catatan_wali.id_kelas', $kelasId)
            ->where('kelas_catatan_wali.type', $classWide ? 1 : 2)
            ->orderByDesc('kelas_catatan_wali.tgl')
            ->orderByDesc('kelas_catatan_wali.id_catatan')
            ->get([
                'kelas_catatan_wali.id_catatan',
                'kelas_catatan_wali.id_siswa',
                'kelas_catatan_wali.tgl',
                'kelas_catatan_wali.level',
                'kelas_catatan_wali.text',
                'master_siswa.nama as siswa',
            ])
            ->map(fn ($row) => [
                'id_catatan' => (int) $row->id_catatan,
                'id_siswa' => $row->id_siswa ? (int) $row->id_siswa : null,
                'siswa' => $row->siswa,
                'tgl' => $row->tgl ? date('d.m.Y H:i', strtotime($row->tgl)) : '-',
                'level' => (int) $row->level,
                'level_label' => self::LEVELS[(int) $row->level] ?? '-',
                'text' => (string) $row->text,
            ]);
    }

    private function isWali(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $guruId = RaporSupport::guruId($user);

        return $guruId !== null && (int) $kelas->guru_id === $guruId;
    }

    private function authorizeMapelClass(User $user, int $kelasId, int $mapelId, int $tp, int $smt): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $guruId = RaporSupport::guruId($user);

        if (! $guruId) {
            abort(403);
        }

        $allowed = false;

        foreach (JabatanGuru::where('id_guru', $guruId)->where('id_tp', $tp)->where('id_smt', $smt)->get() as $jabatan) {
            foreach (RaporSupport::mapelKelas($jabatan->mapel_kelas) as $mapel) {
                if ($mapel['id_mapel'] === $mapelId && in_array($kelasId, $mapel['kelas'], true)) {
                    $allowed = true;
                    break 2;
                }
            }
        }

        abort_unless($allowed, 403);
    }

    private function ensureStudent(int $siswaId, int $kelasId, int $tp, int $smt): void
    {
        $exists = DB::table('kelas_siswa')
            ->where('id_siswa', $siswaId)
            ->where('id_kelas', $kelasId)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['id_siswa' => 'Siswa bukan anggota kelas ini.']);
        }
    }
}
