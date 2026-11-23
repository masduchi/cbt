<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Guru;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\ReferenceGuard;
use App\Support\SheetImport;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class GuruController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        $query = Guru::query();

        if ($request->boolean('arsip')) {
            $query = Guru::onlyTrashed();
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_guru', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Master/Guru', [
            'guru' => $query->orderBy('nama_guru')->paginate($this->perPage($request))->withQueryString(),
            'filters' => [
                'search' => $request->query('search', ''),
                'arsip' => $request->boolean('arsip'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = $data['password'];

        $user = User::create([
            'ip_address' => substr((string) $request->ip(), 0, 45),
            'username' => $data['username'],
            'password' => $password,
            'email' => $data['email'] ?? null,
            'first_name' => $data['nama_guru'],
            'active' => 1,
            'must_change_password' => 1,
            'created_on' => time(),
        ]);
        $user->groups()->attach($this->guruGroupId());

        unset($data['password']);
        $data['id_user'] = $user->id;
        $data['password'] = bcrypt($password);

        $guru = Guru::create($data);

        AuditLog::record('master.guru', 'Guru '.$guru->nama_guru.' ditambahkan');

        return back()->with('success', 'Guru dan akun login berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $guru = Guru::findOrFail($id);
        $data = $this->validated($request, $guru->id_guru);

        $user = $guru->id_user ? User::find($guru->id_user) : null;

        if ($user) {
            $user->username = $data['username'];
            $user->email = $data['email'] ?? null;
            $user->first_name = $data['nama_guru'];
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = 1;
            }
            $user->save();
        } else {
            $user = User::create([
                'ip_address' => substr((string) $request->ip(), 0, 45),
                'username' => $data['username'],
                'password' => ($data['password'] ?? null) ?: $data['nip'],
                'email' => $data['email'] ?? null,
                'first_name' => $data['nama_guru'],
                'active' => 1,
                'must_change_password' => 1,
                'created_on' => time(),
            ]);
            $user->groups()->attach($this->guruGroupId());
            $data['id_user'] = $user->id;
        }

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $guru->update($data);

        AuditLog::record('master.guru', 'Guru #'.$guru->id_guru.' '.$guru->nama_guru.' diperbarui');

        return back()->with('success', 'Data guru berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $guru = Guru::findOrFail($id);
        $used = ReferenceGuard::guru($guru->id_guru);

        if ($used) {
            DB::transaction(function () use ($guru) {
                if ($guru->id_user) {
                    User::where('id', $guru->id_user)->update(['active' => 0]);
                }

                $guru->update(['status_aktif' => 'Nonaktif']);
                $guru->delete();
            });

            AuditLog::record('master.guru.delete', 'Guru #'.$guru->id_guru.' '.$guru->nama_guru.' diarsipkan, akun dinonaktifkan, riwayat tersimpan: '.implode(', ', $used));

            return back()->with('success', 'Guru diarsipkan dan akun dinonaktifkan. Data berikut tetap tersimpan: '.implode(', ', $used).'.');
        }

        DB::transaction(function () use ($guru) {
            if ($guru->id_user) {
                $user = User::find($guru->id_user);
                if ($user) {
                    $user->groups()->detach();
                    $user->delete();
                }
            }
            $guru->forceDelete();
        });

        AuditLog::record('master.guru.delete', 'Guru #'.$guru->id_guru.' '.$guru->nama_guru.' beserta akun login dihapus');

        return back()->with('success', 'Data guru dan akun login berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $guru = Guru::withTrashed()->findOrFail($id);
        $guru->restore();
        $guru->update(['status_aktif' => 'Aktif']);

        if ($guru->id_user) {
            User::where('id', $guru->id_user)->update(['active' => 1]);
        }

        AuditLog::record('master.guru', 'Guru #'.$guru->id_guru.' '.$guru->nama_guru.' dipulihkan dari arsip');

        return back()->with('success', 'Data guru dipulihkan dari arsip.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        [$header, $rows] = SheetImport::read($request->file('file'), ['nip', 'nama_guru', 'username']);
        $missing = array_diff(['nip', 'nama_guru', 'username'], $header);

        if ($missing) {
            return back()->with('error', 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.');
        }

        $created = 0;
        $errors = [];
        $seenNip = [];
        $seenUsername = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $nip = trim((string) ($data['nip'] ?? ''));
            $nama = trim((string) ($data['nama_guru'] ?? ''));
            $username = trim((string) ($data['username'] ?? ''));

            if ($nip === '' || $nama === '' || $username === '') {
                $errors[] = "Baris {$line}: NIP, nama guru, dan username wajib diisi.";

                continue;
            }

            if (isset($seenNip[$nip]) || isset($seenUsername[$username])) {
                $errors[] = "Baris {$line}: NIP atau username ganda di dalam berkas.";

                continue;
            }

            if (Guru::withTrashed()->where('nip', $nip)->exists()
                || Guru::withTrashed()->where('username', $username)->exists()
                || User::where('username', $username)->exists()) {
                $errors[] = "Baris {$line}: NIP {$nip} atau username {$username} sudah terdaftar.";

                continue;
            }

            $email = trim((string) ($data['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$line}: format email tidak valid.";

                continue;
            }

            $gender = strtoupper(trim((string) ($data['jenis_kelamin'] ?? '')));
            $providedPassword = trim((string) ($data['password'] ?? ''));
            $plainPassword = $providedPassword ?: $nip;

            $user = User::create([
                'ip_address' => substr((string) $request->ip(), 0, 45),
                'username' => $username,
                'password' => $plainPassword,
                'email' => $email ?: null,
                'first_name' => $nama,
                'active' => 1,
                'must_change_password' => $providedPassword === '' ? 1 : 0,
                'created_on' => time(),
            ]);
            $user->groups()->attach($this->guruGroupId());

            Guru::create([
                'id_user' => $user->id,
                'nip' => $nip,
                'nama_guru' => $nama,
                'email' => $email ?: null,
                'kode_guru' => trim((string) ($data['kode_guru'] ?? '')) ?: null,
                'jenis_kelamin' => in_array($gender, ['L', 'P'], true) ? $gender : null,
                'no_hp' => trim((string) ($data['no_hp'] ?? '')) ?: null,
                'alamat_jalan' => trim((string) ($data['alamat_jalan'] ?? '')) ?: null,
                'status_aktif' => trim((string) ($data['status_aktif'] ?? '')) ?: 'Aktif',
                'username' => $username,
                'password' => bcrypt($plainPassword),
            ]);

            $seenNip[$nip] = true;
            $seenUsername[$username] = true;
            $created++;
        }

        AuditLog::record('master.guru.import', sprintf('Impor guru: %d ditambahkan, %d baris dilewati', $created, count($errors)));

        $message = "Impor selesai: {$created} guru ditambahkan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    public function importTemplate()
    {
        $headers = ['nip', 'nama_guru', 'jenis_kelamin', 'email', 'username', 'password', 'no_hp', 'kode_guru', 'status_aktif'];
        $sample = ['198703152010011001', 'Budi Santoso', 'L', 'budi@contoh.sch.id', 'budi.santoso', '', '081234567890', 'BD', 'Aktif'];

        return XlsxExport::download('template-impor-guru.xlsx', $headers, [$sample], 'Guru');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $guru = $ignoreId ? Guru::find($ignoreId) : null;

        return $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'nama_guru' => ['required', 'string', 'max:50'],
            'kode_guru' => ['nullable', 'string', 'max:6'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'agama' => ['nullable', 'string', 'max:10'],
            'tempat_lahir' => ['nullable', 'string', 'max:30'],
            'tgl_lahir' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:254'],
            'no_hp' => ['nullable', 'string', 'max:13'],
            'alamat_jalan' => ['nullable', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'string', 'max:20'],
            'username' => [
                'required', 'string', 'max:50',
                Rule::unique('master_guru', 'username')->ignore($ignoreId, 'id_guru'),
                Rule::unique('users', 'username')->ignore($guru?->id_user, 'id'),
            ],
            'password' => [$guru ? 'nullable' : 'required', 'string', Password::defaults()],
        ]);
    }

    private function guruGroupId(): int
    {
        return (int) (Group::where('name', 'guru')->value('id') ?? 2);
    }
}
