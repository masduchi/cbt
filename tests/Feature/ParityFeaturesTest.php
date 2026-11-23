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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private Group $guruGroup;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $this->guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminparitas');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('guruparitas');
        $this->guru->groups()->attach($this->guruGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X Paritas 1',
            'level_id' => 10,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '2029000101',
            'nis' => '2029001',
            'nama' => 'Siswa Paritas',
            'username' => 'siswaparitas',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswaparitas',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_kelompok_mapel_crud_and_usage_guard(): void
    {
        $this->actingAs($this->admin)->get('/master/kelompok-mapel')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Master/KelompokMapel')->has('kelompok'));

        $this->actingAs($this->admin)
            ->post('/master/kelompok-mapel', [
                'kode_kel_mapel' => 'a',
                'nama_kel_mapel' => 'Kelompok A',
                'kategori' => 'WAJIB',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_kelompok_mapel', ['kode_kel_mapel' => 'A']);

        $mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kelompok' => 'A', 'urutan' => 1]);
        $id = DB::table('master_kelompok_mapel')->where('kode_kel_mapel', 'A')->value('id_kel_mapel');

        $this->actingAs($this->admin)
            ->put('/master/kelompok-mapel/'.$id, [
                'kode_kel_mapel' => 'A1',
                'nama_kel_mapel' => 'Kelompok A1',
                'kategori' => 'WAJIB',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('A1', $mapel->refresh()->kelompok);

        $this->actingAs($this->admin)
            ->delete('/master/kelompok-mapel/'.$id)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->guru)->get('/master/kelompok-mapel')->assertForbidden();
    }

    public function test_hari_efektif_is_saved_per_month(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/tahun/hari-efektif', ['hari' => [1 => 20, 2 => 18]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_hari_efektif', ['id_hari_efektif' => 1, 'jml_hari_efektif' => 20]);
        $this->assertDatabaseHas('master_hari_efektif', ['id_hari_efektif' => 2, 'jml_hari_efektif' => 18]);

        $this->actingAs($this->admin)
            ->post('/master/tahun/hari-efektif', ['hari' => [1 => 22]])
            ->assertRedirect();

        $this->assertDatabaseHas('master_hari_efektif', ['id_hari_efektif' => 1, 'jml_hari_efektif' => 22]);

        $this->actingAs($this->admin)
            ->post('/master/tahun/hari-efektif', ['hari' => [1 => 99]])
            ->assertSessionHasErrors('hari.1');
    }

    public function test_kop_settings_are_stored_for_three_documents(): void
    {
        $this->actingAs($this->admin)
            ->post('/setting/kop', [
                'kartu' => ['header_1' => 'SMA Negeri 1', 'header_2' => 'Jl. Merdeka 1'],
                'berita' => ['header_1' => 'SMA Negeri 1', 'header_3' => 'Berita Acara'],
                'absensi' => ['header_2' => 'Daftar Hadir', 'proktor' => 'Budi'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cbt_kop_kartu', ['id_set_kartu' => 1, 'header_1' => 'SMA Negeri 1']);
        $this->assertDatabaseHas('cbt_kop_berita', ['id_kop' => 1, 'header_3' => 'Berita Acara']);
        $this->assertDatabaseHas('cbt_kop_absensi', ['id_kop' => 1, 'proktor' => 'Budi']);

        $this->actingAs($this->guru)->get('/setting/kop')->assertForbidden();
    }

    public function test_rapor_backup_export_and_restore_roundtrip(): void
    {
        DB::table('rapor_kkm')->insert([
            'id_kkm' => 1,
            'jenis' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $export = $this->actingAs($this->admin)->get('/rapor/backup')->assertOk();
        $json = $export->streamedContent();

        $this->assertStringContainsString('rapor_kkm', $json);

        DB::table('rapor_kkm')->delete();
        $this->assertDatabaseCount('rapor_kkm', 0);

        $this->actingAs($this->admin)
            ->post('/rapor/backup', [
                'file' => UploadedFile::fake()->createWithContent('backup.json', $json),
                'confirm' => 'backup.json',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('rapor_kkm', 1);
    }

    public function test_rapor_restore_ditolak_bila_konfirmasi_nama_berkas_tidak_cocok(): void
    {
        DB::table('rapor_kkm')->insert([
            'id_kkm' => 1,
            'jenis' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $json = json_encode([
            'tables' => ['rapor_kkm' => []],
        ]);

        $this->actingAs($this->admin)
            ->post('/rapor/backup', [
                'file' => UploadedFile::fake()->createWithContent('backup.json', $json),
                'confirm' => 'nama-lain.json',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseCount('rapor_kkm', 1);
    }

    public function test_rapor_restore_menolak_kolom_tidak_dikenal_tanpa_menghapus_data(): void
    {
        DB::table('rapor_kkm')->insert([
            'id_kkm' => 1,
            'jenis' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $json = json_encode([
            'tables' => [
                'rapor_kkm' => [['id_kkm' => 2, 'kolom_aneh' => 'x']],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post('/rapor/backup', [
                'file' => UploadedFile::fake()->createWithContent('backup.json', $json),
                'confirm' => 'backup.json',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('rapor_kkm', 1);
        $this->assertDatabaseHas('rapor_kkm', ['id_kkm' => 1]);
    }

    public function test_student_room_and_session_assignment(): void
    {
        $ruang = DB::table('cbt_ruang')->insertGetId(['nama_ruang' => 'Ruang 1', 'kode_ruang' => 'R1']);
        $sesi = DB::table('cbt_sesi')->insertGetId([
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '07:30:00',
            'waktu_akhir' => '09:30:00',
            'aktif' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/kelas-ruang/siswa?kelas='.$this->kelas->id_kelas)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/KelasRuangSiswa')
                ->has('students', 1)
                ->where('students.0.id_siswa', $this->siswa->id_siswa));

        $this->actingAs($this->admin)
            ->post('/cbt/kelas-ruang/siswa', [
                'kelas_id' => $this->kelas->id_kelas,
                'rows' => [[
                    'id_siswa' => $this->siswa->id_siswa,
                    'ruang_id' => $ruang,
                    'sesi_id' => $sesi,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cbt_sesi_siswa', [
            'siswa_id' => $this->siswa->id_siswa,
            'ruang_id' => $ruang,
            'sesi_id' => $sesi,
            'tp_id' => $this->tahun->id_tp,
            'smt_id' => $this->semester->id_smt,
        ]);

        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.kelasruang: Penempatan ruang & sesi 1 siswa kelas #'.$this->kelas->id_kelas.' disimpan',
        ]);
    }

    public function test_bulk_bank_soal_delete(): void
    {
        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BUJI',
            'bank_nama' => 'Bank Uji',
            'bank_level' => 10,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->actingAs($this->admin)
            ->post('/cbt/bank-soal/hapus-terpilih', ['ids' => [$bankId]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cbt_bank_soal', ['id_bank' => $bankId]);
    }

    public function test_admin_bulk_activates_and_deactivates_accounts(): void
    {
        $satu = $this->user('bulksatu');
        $satu->groups()->attach($this->guruGroup->id);
        $dua = $this->user('bulkdua');
        $dua->groups()->attach($this->guruGroup->id);

        $this->actingAs($this->admin)
            ->post('/users/guru/status', ['ids' => [$satu->id, $dua->id], 'active' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, (int) $satu->refresh()->active);
        $this->assertSame(0, (int) $dua->refresh()->active);

        $this->actingAs($this->admin)
            ->post('/users/guru/status', ['ids' => [$satu->id], 'active' => true])
            ->assertRedirect();

        $this->assertSame(1, (int) $satu->refresh()->active);
    }

    public function test_database_truncate_requires_transaction_table_and_confirmation(): void
    {
        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'users', 'confirm' => 'users'])
            ->assertRedirect()
            ->assertSessionHas('error');

        DB::table('cbt_sesi_siswa')->insert([
            'siswa_id' => $this->siswa->id_siswa,
            'kelas_id' => $this->kelas->id_kelas,
            'ruang_id' => 1,
            'sesi_id' => 1,
            'tp_id' => $this->tahun->id_tp,
            'smt_id' => $this->semester->id_smt,
        ]);

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'cbt_sesi_siswa', 'confirm' => 'salah'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cbt_sesi_siswa', 1);

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'cbt_sesi_siswa', 'confirm' => 'cbt_sesi_siswa'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cbt_sesi_siswa', 0);
    }

    public function test_backup_folder_can_be_deleted(): void
    {
        $path = storage_path('app/backups/2026-11-12-test');
        @mkdir($path, 0777, true);
        file_put_contents($path.'/database.sql.gz', 'dump');

        $this->actingAs($this->admin)
            ->delete('/setting/database/backup/2026-11-12-test')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDirectoryDoesNotExist($path);
    }

    public function test_students_can_be_exported_to_excel(): void
    {
        $response = $this->actingAs($this->admin)->get('/master/siswa/ekspor');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
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
        ]);
    }
}
