<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private int $jenisId;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('admin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('guru');
        $this->guru->groups()->attach($guruGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTR', 'urutan' => 1]);

        $this->siswa = $this->siswa('Siswa A', 1);

        $this->jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir',
            'kode_jenis' => 'PA',
        ]);
    }

    public function test_arsip_log_memindahkan_hanya_baris_lama(): void
    {
        DB::table('log_ujian')->insert([
            ['id_log' => 1, 'log_time' => now()->subDays(30), 'id_siswa' => $this->siswa->id_siswa, 'id_jadwal' => 1, 'log_type' => 1, 'log_desc' => 'lama', 'kode' => 'LAMA', 'address' => '', 'agent' => '', 'device' => 'web', 'reset' => 0],
            ['id_log' => 2, 'log_time' => now()->subDay(), 'id_siswa' => $this->siswa->id_siswa, 'id_jadwal' => 1, 'log_type' => 1, 'log_desc' => 'baru', 'kode' => 'BARU', 'address' => '', 'agent' => '', 'device' => 'web', 'reset' => 0],
        ]);

        DB::table('log')->insert([
            ['id_log' => 1, 'log_time' => now()->subDays(30), 'id_user' => $this->admin->id, 'id_group' => 1, 'name_group' => 'admin', 'log_type' => 1, 'log_desc' => 'lama', 'address' => '', 'agent' => '', 'device' => 'web'],
            ['id_log' => 2, 'log_time' => now()->subDay(), 'id_user' => $this->admin->id, 'id_group' => 1, 'name_group' => 'admin', 'log_type' => 1, 'log_desc' => 'baru', 'address' => '', 'agent' => '', 'device' => 'web'],
        ]);

        $this->artisan('cbt:arsip-log', ['--before' => now()->subDays(10)->toDateString()])
            ->assertSuccessful();

        $this->assertDatabaseCount('log_ujian', 1);
        $this->assertDatabaseCount('log_ujian_arsip', 1);
        $this->assertDatabaseHas('log_ujian_arsip', ['log_desc' => 'lama']);
        $this->assertDatabaseMissing('log_ujian_arsip', ['log_desc' => 'baru']);

        $this->assertDatabaseCount('log', 1);
        $this->assertDatabaseCount('log_arsip', 1);
        $this->assertDatabaseHas('log_arsip', ['log_desc' => 'lama']);
    }

    public function test_halaman_audit_menampilkan_log_dari_tabel_arsip(): void
    {
        DB::table('log_arsip')->insert([
            'id_log' => 9001,
            'log_time' => now()->subMonths(8),
            'id_user' => $this->admin->id,
            'id_group' => 1,
            'name_group' => 'admin',
            'log_type' => 2,
            'log_desc' => 'master.mapel: arsip lama',
            'address' => '127.0.0.1',
            'agent' => 'test',
            'device' => 'web',
        ]);

        $this->actingAs($this->admin)->get('/setting/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Audit')
                ->has('logs.data', 1)
                ->where('logs.data.0.detail', 'arsip lama')
                ->where('logs.data.0.aksi', 'Buat/ubah mata pelajaran')
                ->has('users', 1));
    }

    public function test_audit_modul_alumni_dan_pengumuman_tercatat(): void
    {
        $this->actingAs($this->admin)->post('/alumni/generate', [
            'kelas' => $this->kelas->id_kelas,
            'status' => 2,
            'tahun_lulus' => 2027,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'alumni.generate: 1 siswa kelas X IPA 1 → Lulus']);

        $this->actingAs($this->admin)->post('/pengumuman', [
            'kepada' => ['semua_guru'],
            'text' => 'Rapat guru besok pagi.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'pengumuman.store: Pengumuman #1 → semua_guru']);
    }

    public function test_jadwal_bentrok_memberi_peringatan(): void
    {
        $bankA = $this->bank(['bank_kode' => 'BANK-A', 'bank_nama' => 'Bank A']);
        $bankB = $this->bank(['bank_kode' => 'BANK-B', 'bank_nama' => 'Bank B']);

        $this->jadwal($bankA, '2026-11-20T08:00', '2026-11-20T10:00');

        $this->actingAs($this->admin)->post('/cbt/jadwal', [
            'id_bank' => $bankB,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20T09:00',
            'tgl_selesai' => '2026-11-20T11:00',
            'durasi_ujian' => 60,
        ])->assertSessionHas('success')->assertSessionHas('warning');

        $kelasLain = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
        ]);

        $bankC = $this->bank(['bank_kode' => 'BANK-C', 'bank_kelas' => (string) $kelasLain->id_kelas]);

        $this->actingAs($this->admin)->post('/cbt/jadwal', [
            'id_bank' => $bankC,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20T09:00',
            'tgl_selesai' => '2026-11-20T11:00',
            'durasi_ujian' => 60,
        ])->assertSessionHas('success')->assertSessionMissing('warning');
    }

    public function test_butir_soal_dapat_disimpan_diperbarui_dan_divalidasi(): void
    {
        $bankId = $this->bank();

        $payload = [
            'jenis' => 1,
            'soal' => '<p>Ibu kota Indonesia?</p>',
            'opsi_a' => 'Jakarta',
            'opsi_b' => 'Bandung',
            'opsi_c' => 'Surabaya',
            'opsi_d' => 'Medan',
            'jawaban' => 'a',
            'kesulitan' => 4,
        ];

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/soal', $payload)
            ->assertSessionHas('success');

        $soal = DB::table('cbt_soal')->where('bank_id', $bankId)->first();
        $this->assertNotNull($soal);
        $this->assertSame(0, (int) $soal->tampilkan);
        $this->assertSame(1, (int) $soal->nomor_soal);
        $this->assertStringContainsString('Ibu kota', (string) $soal->soal);

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/soal/'.$soal->id_soal, array_merge($payload, [
            'jawaban' => 'c',
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_soal', ['id_soal' => $soal->id_soal, 'jawaban' => 'c']);

        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.soal: Bank #'.$bankId.' soal #'.$soal->id_soal.' (jenis 1) dibuat',
        ]);
        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.soal: Bank #'.$bankId.' soal #'.$soal->id_soal.' diperbarui',
        ]);

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/pilih', ['ids' => [$soal->id_soal]])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.soal.pilih: Bank #'.$bankId.': 1 soal dipilih untuk ujian',
        ]);

        $this->actingAs($this->admin)->delete('/cbt/bank-soal/'.$bankId.'/soal/'.$soal->id_soal)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.soal.delete: Bank #'.$bankId.' soal #'.$soal->id_soal.' dihapus',
        ]);

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/soal', [
            'jenis' => 1,
            'soal' => '<p>Tanpa opsi</p>',
        ])->assertSessionHasErrors(['opsi_a']);
    }

    public function test_halaman_cetak_kartu_dan_absensi_memuat_peserta(): void
    {
        $bankId = $this->bank();
        $jadwalId = $this->jadwal($bankId, '2026-11-20T08:00', '2026-11-20T10:00');

        $this->actingAs($this->admin)->get('/cbt/jadwal/'.$jadwalId.'/kartu')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/Kartu')
                ->has('peserta', 1)
                ->where('peserta.0.nama', 'Siswa A')
            );

        $this->actingAs($this->admin)->get('/cbt/jadwal/'.$jadwalId.'/hadir')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/Hadir')
                ->has('peserta', 1)
                ->where('peserta.0.nama', 'Siswa A')
            );
    }

    public function test_ekspor_analisis_berisi_butir_dan_daya_beda(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100]);
        $jadwalId = $this->jadwal($bankId, '2026-11-20T08:00', '2026-11-20T10:00');

        $soalId = DB::table('cbt_soal')->insertGetId([
            'bank_id' => $bankId,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>Soal 1</p>',
            'deskripsi' => '',
            'jawaban' => 'B',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => $this->siswa->id_siswa.$jadwalId.$soalId,
            'id_bank' => $bankId,
            'id_jadwal' => $jadwalId,
            'id_soal' => $soalId,
            'id_siswa' => $this->siswa->id_siswa,
            'jenis_soal' => 1,
            'no_soal_alias' => 1,
            'jawaban_siswa' => 'B',
            'jawaban_benar' => 'B',
            'point_soal' => '100',
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 0,
            'soal_end' => 1,
            'time_create' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/cbt/analisis/export?jadwal='.$jadwalId)
            ->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'analisis').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame('No', $sheet->getCell('A1')->getValue());
        $this->assertSame('Daya beda', $sheet->getCell('I1')->getValue());
        $this->assertSame('Soal 1', $sheet->getCell('B2')->getValue());
    }

    public function test_login_dan_pengaturan_rapor_memakai_kode_aksi_audit(): void
    {
        $this->post('/login', [
            'identity' => 'admin',
            'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'log_type' => 1,
            'log_desc' => 'auth.login: admin (admin)',
        ]);

        $this->actingAs($this->admin)->post('/rapor/pengaturan', [
            'kkm_tunggal' => 0,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
            'bobot_absen' => 0,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'rapor.setting: Pengaturan rapor 2026/2027 semester Ganjil disimpan',
        ]);

        $this->actingAs($this->admin)->post('/rapor/kkm', [
            'rows' => [[
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'kkm' => 75,
                'bobot_ph' => 40,
                'bobot_pts' => 30,
                'bobot_pas' => 30,
                'bobot_absen' => 0,
                'beban_jam' => 4,
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'rapor.kkm: 1 baris KKM & bobot disimpan',
        ]);

        $this->actingAs($this->admin)->post('/rapor/kelas/'.$this->kelas->id_kelas.'/kunci')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'rapor.lock: Rapor kelas X IPA 1 dikunci',
        ]);
    }

    public function test_audit_master_data_mencatat_perubahan_utama(): void
    {
        $this->actingAs($this->admin)->post('/master/mapel', [
            'nama_mapel' => 'Fisika',
            'kode' => 'FIS',
            'status' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.mapel: Mapel Fisika ditambahkan']);

        $mapel = Mapel::where('nama_mapel', 'Fisika')->firstOrFail();

        $this->actingAs($this->admin)->put('/master/mapel/'.$mapel->id_mapel, [
            'nama_mapel' => 'Fisika Terapan',
            'kode' => 'FIS',
            'status' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.mapel: Mapel #'.$mapel->id_mapel.' Fisika Terapan diperbarui',
        ]);

        $this->actingAs($this->admin)->delete('/master/mapel/'.$mapel->id_mapel)->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.mapel.delete: Mapel #'.$mapel->id_mapel.' Fisika Terapan dihapus',
        ]);

        $this->actingAs($this->admin)->post('/master/jurusan', ['nama_jurusan' => 'IPA'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.jurusan: Jurusan IPA ditambahkan']);

        $this->actingAs($this->admin)->post('/master/ekstra', [
            'nama_ekstra' => 'Pramuka',
            'kode_ekstra' => 'PRM',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.ekstra: Ekstrakurikuler Pramuka ditambahkan']);

        $this->actingAs($this->admin)->post('/master/kelas', [
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.kelas: Kelas X IPA 2 ditambahkan']);

        $this->actingAs($this->admin)->post('/master/kelas/'.$this->kelas->id_kelas.'/anggota', [
            'siswa' => [$this->siswa->id_siswa],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.kelas.anggota: 0 siswa ditambahkan ke kelas X IPA 1']);

        $this->actingAs($this->admin)->post('/master/tahun', ['tahun' => '2027/2028'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.tahun: Tahun pelajaran 2027/2028 ditambahkan']);

        $tahun = TahunPelajaran::where('tahun', '2027/2028')->firstOrFail();

        $this->actingAs($this->admin)->post('/master/tahun/'.$tahun->id_tp.'/aktif')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log', ['log_desc' => 'master.tahun.aktif: Tahun pelajaran 2027/2028 diaktifkan']);

        $this->actingAs($this->admin)->post('/master/siswa/'.$this->siswa->id_siswa, [
            'nisn' => $this->siswa->nisn,
            'nis' => $this->siswa->nis,
            'nama' => 'Siswa A Baru',
            'username' => $this->siswa->username,
            'kelas_awal' => 10,
        ])->assertRedirect(route('master.siswa.index'));

        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.siswa: Siswa #'.$this->siswa->id_siswa.' Siswa A Baru diperbarui',
        ]);
    }

    private function bank(array $overrides = []): int
    {
        return DB::table('cbt_bank_soal')->insertGetId(array_merge([
            'bank_kode' => 'BANK-TEST',
            'bank_nama' => 'Bank Tes',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => 10,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'kkm' => 0,
            'tampil_pg' => 0,
            'bobot_pg' => 0,
        ], $overrides));
    }

    private function jadwal(int $bankId, string $mulai, string $selesai): int
    {
        return DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => $mulai,
            'tgl_selesai' => $selesai,
            'durasi_ujian' => 90,
            'status' => 1,
            'remedial' => 0,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);
    }

    public function test_login_gagal_dibatasi_per_akun_dan_dicatat(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'identity' => 'admin',
                'password' => 'salah',
            ])->assertSessionHasErrors('identity');
        }

        $this->assertSame(5, DB::table('login_attempts')->where('login', 'admin')->count());
        $this->assertDatabaseHas('log', [
            'id_user' => 0,
            'log_desc' => 'auth.login_failed: admin dari 127.0.0.1',
        ]);

        $this->post('/login', [
            'identity' => 'admin',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('identity');

        $this->assertGuest();
    }

    public function test_login_dari_ip_lain_tidak_ikut_terkunci(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->post('/login', [
                    'identity' => 'admin',
                    'password' => 'salah',
                ])->assertSessionHasErrors('identity');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post('/login', [
                'identity' => 'admin',
                'password' => 'rahasia123',
            ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_login_terkunci_setelah_percobaan_terdistribusi(): void
    {
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->post('/login', [
                        'identity' => 'admin',
                        'password' => 'salah',
                    ])->assertSessionHasErrors('identity');
            }
        }

        $this->assertSame(20, DB::table('login_attempts')->where('login', 'admin')->count());

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post('/login', [
                'identity' => 'admin',
                'password' => 'rahasia123',
            ])->assertSessionHasErrors('identity');

        $this->assertGuest();
    }

    public function test_login_berhasil_menghapus_riwayat_percobaan_gagal(): void
    {
        $this->post('/login', [
            'identity' => 'admin',
            'password' => 'salah',
        ])->assertSessionHasErrors('identity');

        $this->post('/login', [
            'identity' => 'admin',
            'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertSame(0, DB::table('login_attempts')->where('login', 'admin')->count());
    }

    public function test_jejak_audit_dapat_diekspor_ke_xlsx(): void
    {
        DB::table('log')->insert([
            'log_time' => now(),
            'id_user' => $this->admin->id,
            'id_group' => 1,
            'name_group' => 'admin',
            'log_type' => 1,
            'log_desc' => 'auth.login: admin (admin)',
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'web',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/setting/audit/export?aksi=auth.login')
            ->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'audit').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame('Waktu', $sheet->getCell('A1')->getValue());
        $this->assertSame('Masuk aplikasi', $sheet->getCell('E2')->getValue());
        $this->assertSame('admin (admin)', $sheet->getCell('F2')->getValue());
        $this->assertDatabaseHas('log', ['log_desc' => 'setting.audit.export: Ekspor jejak audit']);
    }

    private function siswa(string $nama, int $index): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => '2029000'.(100 + $index),
            'nis' => '20290'.(100 + $index),
            'nama' => $nama,
            'username' => 'siswa'.$index,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswa-'.$index,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $index,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        return $siswa;
    }

    private function user(string $username): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => $username,
            'last_name' => '',
        ]);
    }
}
