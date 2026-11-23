<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use App\Models\WaliSiswa;
use App\Support\AuditLog;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use ResolvesPerPage;

    private const ROLES = [
        'admin' => 'Administrator',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
        'orangtua' => 'Wali Murid',
    ];

    public function index(Request $request, string $role): Response
    {
        abort_unless(isset(self::ROLES[$role]), 404);

        $search = trim((string) $request->query('search'));

        $users = User::whereHas('groups', fn ($query) => $query->where('name', $role))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'nama' => $user->nama,
                'email' => $user->email,
                'active' => (int) $user->active === 1,
                'last_login' => $user->last_login,
                'siswa' => $role === 'orangtua' ? $this->linkedStudents($user->id) : [],
            ]);

        $counts = collect(self::ROLES)
            ->map(fn (string $label, string $key) => User::whereHas('groups', fn ($query) => $query->where('name', $key))->count())
            ->all();

        return Inertia::render('Users/Index', [
            'role' => $role,
            'roleLabel' => self::ROLES[$role],
            'users' => $users,
            'counts' => $counts,
            'siswaOptions' => $role === 'orangtua'
                ? Siswa::orderBy('nama')->get(['id_siswa', 'nama', 'nis'])
                    ->map(fn (Siswa $siswa) => [
                        'id_siswa' => $siswa->id_siswa,
                        'label' => $siswa->nama.' · '.$siswa->nis,
                    ])
                : [],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        abort_unless(isset(self::ROLES[$role]), 404);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'max:100'],
            'active' => ['nullable', 'boolean'],
            'siswa_ids' => ['nullable', 'array'],
            'siswa_ids.*' => ['integer', 'exists:master_siswa,id_siswa'],
        ]);

        $groupId = Group::firstOrCreate(['name' => $role], ['description' => self::ROLES[$role]])->id;
        abort_unless($groupId, 500, 'Grup peran belum tersedia.');

        $user = User::create([
            'ip_address' => '',
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'] ?? null,
            'first_name' => $data['nama'],
            'active' => (int) ($data['active'] ?? 1),
            'must_change_password' => 1,
            'created_on' => time(),
        ]);

        $user->groups()->sync([$groupId]);

        if ($role === 'guru') {
            Guru::where('username', $data['username'])
                ->whereNull('id_user')
                ->update(['id_user' => $user->id]);
        }

        if ($role === 'orangtua') {
            $this->syncStudents($user->id, $data['siswa_ids'] ?? []);
        }

        AuditLog::record('user.create', self::ROLES[$role].' '.$user->username.' (ID '.$user->id.')');

        return back()->with('success', "Akun {$role} berhasil ditambahkan.");
    }

    public function update(Request $request, string $role, int $user): RedirectResponse
    {
        abort_unless(isset(self::ROLES[$role]), 404);

        $account = User::whereHas('groups', fn ($query) => $query->where('name', $role))->findOrFail($user);

        $oldUsername = $account->username;

        $data = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($account->id),
                function (string $attribute, mixed $value, Closure $fail) use ($role, $account): void {
                    if ($this->usernameTakenInMasterData($role, (string) $value, $account)) {
                        $fail('Username sudah dipakai pada data '.($role === 'guru' ? 'guru' : 'siswa').'.');
                    }
                },
            ],
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($account->id)],
            'password' => ['nullable', 'string', Password::defaults(), 'max:100'],
            'active' => ['nullable', 'boolean'],
            'siswa_ids' => ['nullable', 'array'],
            'siswa_ids.*' => ['integer', 'exists:master_siswa,id_siswa'],
        ]);

        $active = (int) ($data['active'] ?? 0);

        if ($account->id === (int) $request->user()->id && $active === 0) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $account->username = $data['username'];
        $account->first_name = $data['nama'];
        $account->email = $data['email'] ?? null;
        $account->active = $active;

        if (! empty($data['password'])) {
            $account->password = $data['password'];
            $account->must_change_password = 1;
        }

        $account->save();

        if ($oldUsername !== $account->username) {
            $this->syncMasterDataUsername($role, $account, $oldUsername);
        }

        if ($role === 'orangtua' && $request->has('siswa_ids')) {
            $this->syncStudents($account->id, $data['siswa_ids'] ?? []);
        }

        AuditLog::record('user.update', self::ROLES[$role].' '.$account->username.' (ID '.$account->id.')');

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function bulkStatus(Request $request, string $role): RedirectResponse
    {
        abort_unless(isset(self::ROLES[$role]), 404);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'active' => ['required', 'boolean'],
        ]);

        $ids = array_values(array_diff($data['ids'], [(int) $request->user()->id]));

        $count = User::whereHas('groups', fn ($query) => $query->where('name', $role))
            ->whereIn('id', $ids)
            ->update(['active' => (int) $data['active']]);

        AuditLog::record('user.status', $count.' akun '.$role.' '.($data['active'] ? 'diaktifkan' : 'dinonaktifkan'));

        return back()->with('success', "{$count} akun ".($data['active'] ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    public function destroy(Request $request, string $role, int $user): RedirectResponse
    {
        abort_unless(isset(self::ROLES[$role]), 404);

        if ((int) $request->user()->id === $user) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $account = User::whereHas('groups', fn ($query) => $query->where('name', $role))->findOrFail($user);
        $username = $account->username;
        $account->groups()->detach();
        $account->delete();
        WaliSiswa::where('id_user', $account->id)->delete();

        AuditLog::record('user.delete', self::ROLES[$role].' '.$username.' (ID '.$account->id.')');

        if ($role === 'guru') {
            Guru::withTrashed()->where('id_user', $account->id)->update(['id_user' => null]);
        }

        return back()->with('success', 'Akun berhasil dihapus.');
    }

    /**
     * @param  array<int, int|string>  $siswaIds
     */
    private function syncStudents(int $userId, array $siswaIds): void
    {
        $ids = collect($siswaIds)->map(fn ($value) => (int) $value)->filter()->unique()->values();

        $removed = WaliSiswa::where('id_user', $userId)->whereNotIn('id_siswa', $ids)->delete();

        $existing = WaliSiswa::where('id_user', $userId)->pluck('id_siswa')->map(fn ($value) => (int) $value);
        $added = $ids->diff($existing);

        foreach ($added as $siswaId) {
            WaliSiswa::create([
                'id_user' => $userId,
                'id_siswa' => $siswaId,
                'time_create' => now(),
            ]);
        }

        if ($removed > 0 || $added->isNotEmpty()) {
            AuditLog::record('user.wali', $userId.' → '.$ids->count().' siswa');
        }
    }

    /**
     * @return array<int, array{id_siswa: int, nama: string, nis: string}>
     */
    private function linkedStudents(int $userId): array
    {
        $ids = WaliSiswa::where('id_user', $userId)->pluck('id_siswa');

        return Siswa::whereIn('id_siswa', $ids)->orderBy('nama')->get(['id_siswa', 'nama', 'nis'])
            ->map(fn (Siswa $siswa) => [
                'id_siswa' => $siswa->id_siswa,
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
            ])
            ->all();
    }

    /**
     * Username akun dipakai sebagai kunci tautan ke data guru/siswa, jadi tidak boleh
     * bertabrakan dengan username yang sudah dipakai data master peran tersebut.
     */
    private function usernameTakenInMasterData(string $role, string $username, User $account): bool
    {
        return match ($role) {
            'guru' => Guru::where('username', $username)
                ->where('id_user', '!=', $account->id)
                ->exists(),
            'siswa' => Siswa::where('username', $username)
                ->where('username', '!=', $account->username)
                ->exists(),
            default => false,
        };
    }

    /**
     * Ganti nama akun juga mengganti username pada data master agar tautan identitas tetap utuh.
     */
    private function syncMasterDataUsername(string $role, User $account, string $oldUsername): void
    {
        if ($role === 'guru') {
            Guru::where('id_user', $account->id)->update(['username' => $account->username]);

            return;
        }

        if ($role === 'siswa') {
            Siswa::where('username', $oldUsername)->update(['username' => $account->username]);
        }
    }
}
