<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $guru = $user->isGuru() ? Guru::where('id_user', $user->id)->first() : null;
        $siswa = $user->isSiswa() ? Siswa::where('username', $user->username)->first() : null;

        return Inertia::render('Profil/Index', [
            'profile' => [
                'nama' => $user->nama,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'foto_url' => $guru?->photo_url ?? $siswa?->photo_url ?? $user->photo_url,
                'last_login' => $user->last_login,
                'details' => $guru
                    ? ['type' => 'guru', 'fields' => $this->guruFields($guru)]
                    : ($siswa ? ['type' => 'siswa', 'fields' => $this->siswaFields($siswa)] : null),
            ],
            'mustChange' => (bool) $user->must_change_password,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isGuru() || $user->isSiswa(), 403);

        if ($user->isGuru()) {
            $record = Guru::where('id_user', $user->id)->firstOrFail();
            $data = $request->validate([
                'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'nama_guru' => ['required', 'string', 'max:50'],
                'jenis_kelamin' => ['nullable', 'in:L,P'],
                'tempat_lahir' => ['nullable', 'string', 'max:30'],
                'tgl_lahir' => ['nullable', 'date'],
                'agama' => ['nullable', 'string', 'max:10'],
                'no_hp' => ['nullable', 'string', 'max:13'],
                'email' => ['nullable', 'email', 'max:254'],
                'alamat_jalan' => ['nullable', 'string', 'max:255'],
                'rt_rw' => ['nullable', 'string', 'max:8'],
                'dusun' => ['nullable', 'string', 'max:50'],
                'kelurahan' => ['nullable', 'string', 'max:50'],
                'kecamatan' => ['nullable', 'string', 'max:30'],
                'kabupaten' => ['nullable', 'string', 'max:30'],
                'provinsi' => ['nullable', 'string', 'max:30'],
                'kode_pos' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'kewarganegaraan' => ['nullable', 'string', 'max:10'],
                'status_nikah' => ['nullable', 'string', 'max:20'],
            ]);
            $nameField = 'nama_guru';
        } else {
            $record = Siswa::where('username', $user->username)->firstOrFail();
            $data = $request->validate([
                'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'nama' => ['required', 'string', 'max:50'],
                'jenis_kelamin' => ['nullable', 'in:L,P'],
                'tempat_lahir' => ['nullable', 'string', 'max:100'],
                'tanggal_lahir' => ['nullable', 'date'],
                'agama' => ['nullable', 'string', 'max:10'],
                'hp' => ['nullable', 'string', 'max:15'],
                'email' => ['nullable', 'email', 'max:254'],
                'alamat' => ['nullable', 'string', 'max:2000'],
                'rt' => ['nullable', 'string', 'max:5'],
                'rw' => ['nullable', 'string', 'max:5'],
                'kelurahan' => ['nullable', 'string', 'max:100'],
                'kecamatan' => ['nullable', 'string', 'max:100'],
                'kabupaten' => ['nullable', 'string', 'max:100'],
                'provinsi' => ['nullable', 'string', 'max:100'],
                'kode_pos' => ['nullable', 'integer', 'min:0', 'max:99999'],
            ]);
            $nameField = 'nama';
        }

        unset($data['photo']);

        $foto = (string) ($record->foto ?? '');

        if ($request->hasFile('photo')) {
            if ($foto !== '' && $foto !== 'siswa.png' && Storage::disk('public')->exists($foto)) {
                Storage::disk('public')->delete($foto);
            }

            $foto = $request->file('photo')->store('profil/'.$user->role, 'public');
        }

        $record->fill($data);
        $record->foto = $foto !== '' ? $foto : $record->foto;
        $record->save();

        $user->forceFill(['email' => $data['email'] ?? null])->save();

        UserProfile::updateOrCreate(
            ['id_user' => $user->id],
            [
                'nama_lengkap' => $data[$nameField],
                'foto' => $record->foto,
                'level_access' => (int) ($user->profile?->level_access ?? 0),
            ]
        );

        return back()->with('success', 'Profil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $this->logoutOtherSessions($request);

        return back()->with('success', 'Password diperbarui. Sesi lain telah dikeluarkan.');
    }

    private function guruFields(Guru $guru): array
    {
        return [
            'nip' => $guru->nip,
            'nama_guru' => $guru->nama_guru,
            'jenis_kelamin' => $guru->jenis_kelamin,
            'tempat_lahir' => $guru->tempat_lahir,
            'tgl_lahir' => $this->dateValue($guru->tgl_lahir),
            'agama' => $guru->agama,
            'no_hp' => $guru->no_hp,
            'email' => $guru->email,
            'alamat_jalan' => $guru->alamat_jalan,
            'rt_rw' => $guru->rt_rw,
            'dusun' => $guru->dusun,
            'kelurahan' => $guru->kelurahan,
            'kecamatan' => $guru->kecamatan,
            'kabupaten' => $guru->kabupaten,
            'provinsi' => $guru->provinsi,
            'kode_pos' => $guru->kode_pos,
            'kewarganegaraan' => $guru->kewarganegaraan,
            'status_nikah' => $guru->status_nikah,
        ];
    }

    private function siswaFields(Siswa $siswa): array
    {
        return [
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'nama' => $siswa->nama,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $this->dateValue($siswa->tanggal_lahir),
            'agama' => $siswa->agama,
            'hp' => $siswa->hp,
            'email' => $siswa->email,
            'alamat' => $siswa->alamat,
            'rt' => $siswa->rt,
            'rw' => $siswa->rw,
            'kelurahan' => $siswa->kelurahan,
            'kecamatan' => $siswa->kecamatan,
            'kabupaten' => $siswa->kabupaten,
            'provinsi' => $siswa->provinsi,
            'kode_pos' => $siswa->kode_pos,
        ];
    }

    private function dateValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function logoutOtherSessions(Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
