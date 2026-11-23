<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            $legacy = file_get_contents(database_path('seed/legacy_data.sql'));
            DB::unprepared(str_replace('INSERT INTO', 'INSERT IGNORE INTO', $legacy));

            $admin = User::create([
                'ip_address' => '127.0.0.1',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'email' => 'admin@sekolah.sch.id',
                'created_on' => time(),
                'active' => 1,
                'first_name' => 'Administrator',
                'last_name' => '',
            ]);
            $admin->groups()->attach(Group::where('name', 'admin')->first()->id);
            $admin->profile()->create([
                'nama_lengkap' => 'Administrator',
                'jabatan' => 'Administrator',
                'level_access' => 0,
            ]);

            Setting::create([
                'id_setting' => 1,
                'sekolah' => 'SMA Negeri 1 Contoh',
                'nama_aplikasi' => 'CBT Sekolah',
                'alamat' => 'Jl. Pendidikan No. 1',
                'kota' => 'Kota Contoh',
                'provinsi' => 'Provinsi Contoh',
                'kepsek' => 'Kepala Sekolah',
                'versi' => '2.0.0',
                'db_versi' => '1.0',
            ]);

            // Tahun pelajaran aktif mengikuti tahun berjalan (semester ganjil).
            $tahun = date('Y').'/'.(date('Y') + 1);
            DB::table('master_tp')->update(['active' => 0]);
            $existing = DB::table('master_tp')->where('tahun', $tahun)->first();
            if ($existing) {
                DB::table('master_tp')->where('id_tp', $existing->id_tp)->update(['active' => 1]);
            } else {
                DB::table('master_tp')->insert(['tahun' => $tahun, 'active' => 1]);
            }
            DB::table('master_smt')->update(['active' => 0]);
            DB::table('master_smt')->where('id_smt', 1)->update(['active' => 1]);
        }
    }
}
