<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Kelas;
use App\Models\LevelKelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\ReferenceGuard;
use App\Support\SheetImport;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiswaController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();

        $query = Siswa::query()
            ->with(['kelas' => fn ($q) => $tp && $smt
                ? $q->wherePivot('id_tp', $tp)->wherePivot('id_smt', $smt)
                : $q]);

        if ($request->boolean('arsip')) {
            $query->onlyTrashed();
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($kelasId = $request->query('kelas')) {
            $query->whereHas('kelas', function ($q) use ($kelasId, $tp, $smt) {
                $q->where('master_kelas.id_kelas', $kelasId);

                if ($tp && $smt) {
                    $q->where('kelas_siswa.id_tp', $tp)->where('kelas_siswa.id_smt', $smt);
                }
            });
        }

        $siswa = $query->orderBy('nama')->paginate($this->perPage($request))->withQueryString()->through(function (Siswa $item) {
            return [
                'id_siswa' => $item->id_siswa,
                'nisn' => $item->nisn,
                'nis' => $item->nis,
                'nama' => $item->nama,
                'jenis_kelamin' => $item->jenis_kelamin,
                'username' => $item->username,
                'email' => $item->email,
                'hp' => $item->hp,
                'foto_url' => $item->photo_url,
                'kelas' => $item->kelas->pluck('nama_kelas')->join(', '),
                'arsip' => $item->trashed(),
            ];
        });

        return Inertia::render('Master/Siswa', [
            'siswa' => $siswa,
            'filters' => [
                'search' => $request->query('search', ''),
                'kelas' => $request->query('kelas', ''),
                'arsip' => $request->boolean('arsip'),
            ],
            'kelasOptions' => Kelas::where('id_tp', $tp)->where('id_smt', $smt)
                ->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas']),
        ]);
    }

    public function export(): StreamedResponse
    {
        [$tp, $smt] = $this->activePeriod();

        $rows = Siswa::with(['kelas' => fn ($q) => $q->wherePivot('id_tp', $tp)->wherePivot('id_smt', $smt)])
            ->orderBy('nama')
            ->get()
            ->map(fn (Siswa $siswa) => [
                $siswa->nisn,
                $siswa->nis,
                $siswa->nama,
                match ($siswa->jenis_kelamin) {
                    'L' => 'Laki-laki',
                    'P' => 'Perempuan',
                    default => $siswa->jenis_kelamin,
                },
                $siswa->kelas->pluck('nama_kelas')->join(', '),
                $siswa->username,
                $siswa->email,
                $siswa->hp,
            ]);

        return XlsxExport::download(
            'data-siswa-'.now()->format('Ymd').'.xlsx',
            ['NISN', 'NIS', 'Nama', 'Jenis Kelamin', 'Kelas', 'Username', 'Email', 'HP'],
            $rows,
            'Siswa'
        );
    }

    /**
     * Ekspor daftar siswa dengan kolom sesuai templat Dapodik.
     */
    public function exportDapodik(): StreamedResponse
    {
        return $this->exportSiswa(
            'data-dapodik-'.now()->format('Ymd').'.xlsx',
            ['peserta_didik_id', 'nama', 'nisn', 'nis', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nik', 'agama', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'no_hp', 'email', 'nama_ayah', 'nama_ibu', 'nama_wali', 'rombel', 'tahun_ajaran', 'semester'],
            ['peserta_didik_id', 'nama', 'nisn', 'nis', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nik', 'agama', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'no_hp', 'email', 'nama_ayah', 'nama_ibu', 'nama_wali', 'rombel', 'tahun_ajaran', 'semester'],
            'Dapodik'
        );
    }

    /**
     * Ekspor daftar siswa dengan kolom sesuai unggahan EMIS.
     */
    public function exportEmis(): StreamedResponse
    {
        return $this->exportSiswa(
            'data-emis-'.now()->format('Ymd').'.xlsx',
            ['NISN', 'NIS', 'Nama Siswa', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'NIK', 'Agama', 'Alamat', 'RT', 'RW', 'Kelurahan', 'Kecamatan', 'Kabupaten', 'Provinsi', 'Kode Pos', 'No. HP', 'Email', 'Nama Ayah', 'Nama Ibu', 'Nama Wali', 'Kelas', 'Tahun Pelajaran', 'Semester'],
            ['nisn', 'nis', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nik', 'agama', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'no_hp', 'email', 'nama_ayah', 'nama_ibu', 'nama_wali', 'rombel', 'tahun_ajaran', 'semester'],
            'EMIS'
        );
    }

    /**
     * @param  array<int, string>  $headers  Judul kolom pada berkas.
     * @param  array<int, string>  $keys  Kunci data sesuai urutan judul kolom.
     */
    private function exportSiswa(string $filename, array $headers, array $keys, string $sheet): StreamedResponse
    {
        [$tp, $smt] = $this->activePeriod();
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('nama_smt') : null;

        $rows = Siswa::with(['kelas' => fn ($q) => $q->wherePivot('id_tp', $tp)->wherePivot('id_smt', $smt)])
            ->orderBy('nama')
            ->get()
            ->map(fn (Siswa $siswa) => [
                'peserta_didik_id' => (string) $siswa->uid,
                'nama' => (string) $siswa->nama,
                'nisn' => (string) $siswa->nisn,
                'nis' => (string) $siswa->nis,
                'jenis_kelamin' => in_array($siswa->jenis_kelamin, ['L', 'P'], true) ? (string) $siswa->jenis_kelamin : '',
                'tempat_lahir' => (string) ($siswa->tempat_lahir ?? ''),
                'tanggal_lahir' => (string) ($siswa->tanggal_lahir ?? ''),
                'nik' => (string) ($siswa->nik ?? ''),
                'agama' => (string) ($siswa->agama ?? ''),
                'alamat' => (string) ($siswa->alamat ?? ''),
                'rt' => (string) ($siswa->rt ?? ''),
                'rw' => (string) ($siswa->rw ?? ''),
                'kelurahan' => (string) ($siswa->kelurahan ?? ''),
                'kecamatan' => (string) ($siswa->kecamatan ?? ''),
                'kabupaten' => (string) ($siswa->kabupaten ?? ''),
                'provinsi' => (string) ($siswa->provinsi ?? ''),
                'kode_pos' => (string) ($siswa->kode_pos ?? ''),
                'no_hp' => (string) ($siswa->hp ?? ''),
                'email' => (string) ($siswa->email ?? ''),
                'nama_ayah' => (string) ($siswa->nama_ayah ?? ''),
                'nama_ibu' => (string) ($siswa->nama_ibu ?? ''),
                'nama_wali' => (string) ($siswa->nama_wali ?? ''),
                'rombel' => (string) $siswa->kelas->pluck('nama_kelas')->join(', '),
                'tahun_ajaran' => (string) ($tahun ?? ''),
                'semester' => (string) ($semester ?? ''),
            ])
            ->map(fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '', $keys));

        return XlsxExport::download($filename, $headers, $rows, $sheet);
    }

    public function create(): Response
    {
        return Inertia::render('Master/SiswaForm', [
            'siswa' => null,
            'levels' => LevelKelas::orderBy('id_level')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plainPassword = $data['password'] ?? null;
        $requiresChange = empty($plainPassword);
        $plainPassword = $plainPassword ?: $data['nisn'];
        $data['uid'] = (string) Str::uuid();
        $data['password'] = bcrypt($plainPassword);
        $data['nik'] = $data['nik'] ?? '';
        $data['warga_negara'] = $data['warga_negara'] ?? 'WNI';
        $data['foto'] = $request->hasFile('foto')
            ? $request->file('foto')->store('siswa', 'public')
            : null;

        $siswa = Siswa::create($data);
        $this->syncAccount($siswa, $plainPassword, null, $requiresChange);

        AuditLog::record('master.siswa', 'Siswa '.$siswa->nama.' ('.$siswa->nis.') ditambahkan');

        return redirect()->route('master.siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit(int $id): Response
    {
        $siswa = Siswa::findOrFail($id);

        return Inertia::render('Master/SiswaForm', [
            'siswa' => [
                ...$siswa->toArray(),
                'foto_url' => $siswa->photo_url,
            ],
            'levels' => LevelKelas::orderBy('id_level')->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $siswa = Siswa::findOrFail($id);
        $oldUsername = $siswa->username;
        $data = $this->validated($request, $siswa->id_siswa);
        $data['nik'] = $data['nik'] ?? '';
        $data['warga_negara'] = $data['warga_negara'] ?? 'WNI';
        $plainPassword = $data['password'] ?? null;
        $requiresChange = $plainPassword !== null;

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('foto')) {
            if ($siswa->foto && $siswa->foto !== 'siswa.png') {
                Storage::disk('public')->delete($siswa->foto);
            }
            $data['foto'] = $request->file('foto')->store('siswa', 'public');
        } else {
            unset($data['foto']);
        }

        $siswa->update($data);
        $this->syncAccount($siswa, $plainPassword, $oldUsername, $requiresChange);

        AuditLog::record('master.siswa', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' diperbarui');

        return redirect()->route('master.siswa.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $siswa = Siswa::findOrFail($id);
        $used = ReferenceGuard::siswa($siswa->id_siswa);
        $account = User::where('username', $siswa->username)->first();

        if ($used) {
            $siswa->delete();

            if ($account && $account->isSiswa()) {
                $account->update(['active' => 0]);
            }

            AuditLog::record('master.siswa.delete', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' diarsipkan, riwayat tetap tersimpan: '.implode(', ', $used));

            return back()->with('success', 'Siswa diarsipkan. Riwayat berikut tetap tersimpan: '.implode(', ', $used).'.');
        }

        DB::table('kelas_siswa')->where('id_siswa', $siswa->id_siswa)->delete();

        if ($account && $account->isSiswa()) {
            $account->groups()->detach();
            $account->delete();
        }

        if ($siswa->foto && $siswa->foto !== 'siswa.png') {
            Storage::disk('public')->delete($siswa->foto);
        }

        $siswa->forceDelete();

        AuditLog::record('master.siswa.delete', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' dihapus');

        return back()->with('success', 'Siswa berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $siswa = Siswa::withTrashed()->findOrFail($id);
        $siswa->restore();

        $account = User::where('username', $siswa->username)->first();
        $account?->update(['active' => 1]);

        AuditLog::record('master.siswa', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' dipulihkan dari arsip');

        return back()->with('success', 'Siswa dipulihkan dari arsip.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        [$header, $rows] = SheetImport::read($request->file('file'), ['nisn', 'nis', 'nama', 'username']);
        $missing = array_diff(['nisn', 'nis', 'nama', 'username'], $header);

        if ($missing) {
            return back()->with('error', 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.');
        }

        $created = 0;
        $errors = [];
        $seenNisn = [];
        $seenUsername = [];
        $defaultLevel = LevelKelas::orderBy('id_level')->value('id_level') ?? 1;

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $nisn = trim((string) ($data['nisn'] ?? ''));
            $nis = trim((string) ($data['nis'] ?? ''));
            $nama = trim((string) ($data['nama'] ?? ''));
            $username = trim((string) ($data['username'] ?? ''));

            if ($nisn === '' || $nis === '' || $nama === '' || $username === '') {
                $errors[] = "Baris {$line}: NISN, NIS, nama, dan username wajib diisi.";

                continue;
            }

            if (isset($seenNisn[$nisn]) || isset($seenUsername[$username])) {
                $errors[] = "Baris {$line}: NISN atau username ganda di dalam berkas.";

                continue;
            }

            if (Siswa::where('nisn', $nisn)->orWhere('username', $username)->exists()) {
                $errors[] = "Baris {$line}: NISN {$nisn} atau username {$username} sudah terdaftar.";

                continue;
            }

            $email = trim((string) ($data['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$line}: format email tidak valid.";

                continue;
            }

            $gender = strtoupper(trim((string) ($data['jenis_kelamin'] ?? '')));
            $level = (int) ($data['kelas_awal'] ?? 0);
            $providedPassword = trim((string) ($data['password'] ?? ''));
            $plainPassword = $providedPassword ?: $nisn;

            $siswa = Siswa::create([
                'nisn' => $nisn,
                'nis' => $nis,
                'nama' => $nama,
                'jenis_kelamin' => in_array($gender, ['L', 'P'], true) ? $gender : null,
                'username' => $username,
                'password' => bcrypt($plainPassword),
                'kelas_awal' => ($level >= 1 && $level <= 12) ? $level : $defaultLevel,
                'tahun_masuk' => trim((string) ($data['tahun_masuk'] ?? '')) ?: null,
                'email' => $email ?: null,
                'hp' => trim((string) ($data['hp'] ?? '')) ?: null,
                'nik' => trim((string) ($data['nik'] ?? '')),
                'warga_negara' => trim((string) ($data['warga_negara'] ?? '')) ?: 'WNI',
                'uid' => (string) Str::uuid(),
                'foto' => null,
            ]);
            $this->syncAccount($siswa, $plainPassword, null, $providedPassword === '');

            $seenNisn[$nisn] = true;
            $seenUsername[$username] = true;
            $created++;
        }

        AuditLog::record('master.siswa.import', sprintf('Impor siswa: %d ditambahkan, %d baris dilewati', $created, count($errors)));

        $message = "Impor selesai: {$created} siswa ditambahkan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    public function importTemplate()
    {
        $headers = ['nisn', 'nis', 'nama', 'jenis_kelamin', 'username', 'password', 'kelas_awal', 'tahun_masuk', 'email', 'hp', 'nik', 'warga_negara'];
        $sample = ['0061234567', '2026001', 'Ahmad Fauzi', 'L', 'ahmad.fauzi', '', '10', '2026', 'ahmad@contoh.sch.id', '081234567890', '', 'WNI'];

        return XlsxExport::download('template-impor-siswa.xlsx', $headers, [$sample], 'Siswa');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nisn' => ['required', 'string', 'max:20', Rule::unique('master_siswa', 'nisn')->ignore($ignoreId, 'id_siswa')],
            'nis' => ['required', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:50'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'username' => ['required', 'string', 'max:50', Rule::unique('master_siswa', 'username')->ignore($ignoreId, 'id_siswa')],
            'password' => ['nullable', 'string', Password::defaults()],
            'kelas_awal' => ['required', 'integer', 'exists:level_kelas,id_level'],
            'tahun_masuk' => ['nullable', 'string', 'max:30'],
            'sekolah_asal' => ['nullable', 'string', 'max:100'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:10'],
            'hp' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:254'],
            'nik' => ['nullable', 'string', 'max:30'],
            'warga_negara' => ['nullable', 'string', 'max:20'],
            'anak_ke' => ['nullable', 'integer', 'min:1'],
            'status_keluarga' => ['nullable', 'string', 'max:1'],
            'alamat' => ['nullable', 'string'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'integer'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'pendidikan_ayah' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:100'],
            'nohp_ayah' => ['nullable', 'string', 'max:20'],
            'nama_ibu' => ['nullable', 'string', 'max:50'],
            'pendidikan_ibu' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:100'],
            'nohp_ibu' => ['nullable', 'string', 'max:20'],
            'nama_wali' => ['nullable', 'string', 'max:150'],
            'pekerjaan_wali' => ['nullable', 'string', 'max:100'],
            'nohp_wali' => ['nullable', 'string', 'max:20'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ]);
    }

    private function syncAccount(Siswa $siswa, ?string $plainPassword = null, ?string $oldUsername = null, ?bool $requireChange = null): void
    {
        $account = $oldUsername
            ? User::where('username', $oldUsername)->first()
            : User::where('username', $siswa->username)->first();

        if (! $account) {
            $account = new User([
                'ip_address' => '',
                'username' => $siswa->username,
                'password' => $plainPassword ?: $siswa->nisn,
                'email' => $siswa->email,
                'first_name' => $siswa->nama,
                'active' => 1,
                'created_on' => time(),
            ]);
        }

        $account->username = $siswa->username;
        $account->first_name = $siswa->nama;
        $account->email = $siswa->email;

        if ($plainPassword) {
            $account->password = $plainPassword;
        }

        if ($requireChange !== null) {
            $account->must_change_password = $requireChange;
        }

        $account->save();

        $groupId = (int) (Group::where('name', 'siswa')->value('id') ?? 3);
        $account->groups()->syncWithoutDetaching([$groupId]);
    }

    private function activePeriod(): array
    {
        $tp = TahunPelajaran::where('active', 1)->value('id_tp');
        $smt = Semester::where('active', 1)->value('id_smt');

        return [$tp, $smt];
    }
}
