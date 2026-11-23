<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiSetting;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $details = null;

        if ($user->isGuru()) {
            $guru = Guru::where('id_user', $user->id)->first();
            $details = $guru ? [
                'type' => 'guru',
                'nip' => $guru->nip,
                'nama' => $guru->nama_guru,
                'jenis_kelamin' => $guru->jenis_kelamin,
                'tempat_lahir' => $guru->tempat_lahir,
                'tgl_lahir' => $guru->tgl_lahir,
                'agama' => $guru->agama,
                'no_hp' => $guru->no_hp,
                'email' => $guru->email,
                'alamat_jalan' => $guru->alamat_jalan,
                'kelurahan' => $guru->kelurahan,
                'kecamatan' => $guru->kecamatan,
                'kabupaten' => $guru->kabupaten,
                'provinsi' => $guru->provinsi,
                'kode_pos' => $guru->kode_pos,
                'foto_url' => $guru->photo_url,
            ] : null;
        } elseif ($user->isSiswa()) {
            $siswa = Siswa::where('username', $user->username)->first();
            $details = $siswa ? [
                'type' => 'siswa',
                'nis' => $siswa->nis,
                'nisn' => $siswa->nisn,
                'nama' => $siswa->nama,
                'jenis_kelamin' => $siswa->jenis_kelamin,
                'tempat_lahir' => $siswa->tempat_lahir,
                'tanggal_lahir' => $siswa->tanggal_lahir,
                'agama' => $siswa->agama,
                'hp' => $siswa->hp,
                'email' => $siswa->email,
                'alamat' => $siswa->alamat,
                'kelurahan' => $siswa->kelurahan,
                'kecamatan' => $siswa->kecamatan,
                'kabupaten' => $siswa->kabupaten,
                'provinsi' => $siswa->provinsi,
                'kode_pos' => $siswa->kode_pos,
                'foto_url' => $siswa->photo_url,
            ] : null;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nama' => $user->nama,
                'email' => $user->email,
                'role' => $user->role,
                'foto_url' => $user->photo_url,
            ],
            'details' => $details,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $setting = ApiSetting::current();

        if ($user->isGuru()) {
            abort_unless($setting->edit_profile_guru, 403, 'Ubah profil guru melalui API dinonaktifkan.');
            $record = Guru::where('id_user', $user->id)->first();
            abort_unless($record, 404, 'Data guru tidak ditemukan.');
            $nameField = 'nama_guru';
            $data = $request->validate([
                'nama_guru' => ['required', 'string', 'max:50'],
                'jenis_kelamin' => ['nullable', 'in:L,P'],
                'tempat_lahir' => ['nullable', 'string', 'max:30'],
                'tgl_lahir' => ['nullable', 'date_format:Y-m-d'],
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
        } elseif ($user->isSiswa()) {
            abort_unless($setting->edit_profile_siswa, 403, 'Ubah profil siswa melalui API dinonaktifkan.');
            $record = Siswa::where('username', $user->username)->first();
            abort_unless($record, 404, 'Data siswa tidak ditemukan.');
            $nameField = 'nama';
            $data = $request->validate([
                'nama' => ['required', 'string', 'max:50'],
                'jenis_kelamin' => ['nullable', 'in:L,P'],
                'tempat_lahir' => ['nullable', 'string', 'max:100'],
                'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
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
        } else {
            abort(403, 'Akun ini tidak memiliki data profil mandiri.');
        }

        $record->fill($data)->save();

        $user->forceFill(['email' => $data['email'] ?? null])->save();

        UserProfile::updateOrCreate(
            ['id_user' => $user->id],
            [
                'nama_lengkap' => $data[$nameField],
                'foto' => $record->foto,
                'level_access' => (int) ($user->profile?->level_access ?? 0),
            ]
        );

        return response()->json([
            'message' => 'Profil diperbarui.',
            'nama' => $data[$nameField],
            'email' => $user->email,
        ]);
    }
}
