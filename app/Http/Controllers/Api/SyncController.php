<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiSetting;
use App\Models\Guru;
use App\Models\JabatanGuru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Setting;
use App\Support\Period;
use App\Support\Rapor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function __invoke(): JsonResponse
    {
        [$tp, $smt] = Period::current();

        if (! $tp || ! $smt) {
            return response()->json(['message' => 'Tahun pelajaran atau semester aktif belum diatur.'], 409);
        }

        $setting = ApiSetting::current();
        $school = Setting::first();

        $kelas = Kelas::with('waliKelas:id_guru,nama_guru')
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas', 'kode_kelas', 'jurusan_id', 'level_id', 'guru_id'])
            ->map(fn (Kelas $row) => [
                'id_kelas' => (int) $row->id_kelas,
                'nama_kelas' => $row->nama_kelas,
                'kode_kelas' => $row->kode_kelas,
                'jurusan_id' => $row->jurusan_id ? (int) $row->jurusan_id : null,
                'level_id' => $row->level_id ? (int) $row->level_id : null,
                'wali' => $row->waliKelas?->nama_guru,
            ]);

        $siswa = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_tp', $tp->id_tp)
            ->where('kelas_siswa.id_smt', $smt->id_smt)
            ->whereNull('master_siswa.deleted_at')
            ->orderBy('master_siswa.nama')
            ->get([
                'master_siswa.id_siswa',
                'master_siswa.nis',
                'master_siswa.nisn',
                'master_siswa.nama',
                'master_siswa.jenis_kelamin',
                'master_siswa.email',
                'master_siswa.hp',
                'master_siswa.username',
                'kelas_siswa.id_kelas',
            ])
            ->groupBy('id_siswa')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'id_siswa' => (int) $first->id_siswa,
                    'nis' => $first->nis,
                    'nisn' => $first->nisn,
                    'nama' => $first->nama,
                    'jenis_kelamin' => $first->jenis_kelamin,
                    'email' => $first->email,
                    'hp' => $first->hp,
                    'username' => $first->username,
                    'kelas' => $rows->pluck('id_kelas')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                ];
            })
            ->values();

        $assignments = [];

        foreach (JabatanGuru::where('id_tp', $tp->id_tp)->where('id_smt', $smt->id_smt)->get() as $jabatan) {
            foreach (Rapor::mapelKelas($jabatan->mapel_kelas) as $row) {
                $assignments[(int) $jabatan->id_guru][] = [
                    'id_mapel' => $row['id_mapel'],
                    'kelas' => $row['kelas'],
                ];
            }
        }

        $guru = Guru::orderBy('nama_guru')
            ->get(['id_guru', 'nip', 'nama_guru', 'email', 'no_hp', 'status_aktif'])
            ->map(fn (Guru $row) => [
                'id_guru' => (int) $row->id_guru,
                'nip' => $row->nip,
                'nama' => $row->nama_guru,
                'email' => $row->email,
                'no_hp' => $row->no_hp,
                'status_aktif' => $row->status_aktif,
                'mapel' => $assignments[(int) $row->id_guru] ?? [],
            ]);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'auto_sync' => $setting->auto_sync,
            'profile_edit' => [
                'siswa' => $setting->edit_profile_siswa,
                'guru' => $setting->edit_profile_guru,
            ],
            'periode' => [
                'id_tp' => (int) $tp->id_tp,
                'tahun' => $tp->tahun,
                'id_smt' => (int) $smt->id_smt,
                'semester' => $smt->nama_smt ?: $smt->smt,
            ],
            'sekolah' => [
                'nama' => $school?->sekolah,
                'npsn' => $school?->npsn,
                'alamat' => $school?->alamat,
                'kota' => $school?->kota,
                'provinsi' => $school?->provinsi,
            ],
            'kelas' => $kelas,
            'mapel' => Mapel::orderBy('urutan')->orderBy('nama_mapel')
                ->get(['id_mapel', 'nama_mapel', 'kode', 'kelompok'])
                ->map(fn (Mapel $row) => [
                    'id_mapel' => (int) $row->id_mapel,
                    'nama_mapel' => $row->nama_mapel,
                    'kode' => $row->kode,
                    'kelompok' => $row->kelompok,
                ]),
            'jurusan' => Jurusan::with('mapels:id_mapel')
                ->orderBy('nama_jurusan')
                ->get(['id_jurusan', 'nama_jurusan', 'kode_jurusan'])
                ->map(fn (Jurusan $row) => [
                    'id_jurusan' => (int) $row->id_jurusan,
                    'nama_jurusan' => $row->nama_jurusan,
                    'kode_jurusan' => $row->kode_jurusan,
                    'mapel' => $row->mapels->map(fn (Mapel $mapel) => (int) $mapel->id_mapel)->values()->all(),
                ]),
            'siswa' => $siswa,
            'guru' => $guru,
        ]);
    }
}
