<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Support\CbtScoring;
use App\Support\SchoolLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Simulasi alur fitur utama (happy path + pengaman dasar) agar setiap aksi
 * tidak menghasilkan error 500 maupun kegagalan validasi yang tidak disengaja.
 */
class SimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private Siswa $siswa;

    private int $jenisId;

    private int $levelId = 10;

    private User $guruLainUser;

    private User $siswaUser;

    private Siswa $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('admin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('guru');
        $this->guruUser->groups()->attach($guruGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => $this->levelId, 'level' => '10']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => $this->levelId,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTR', 'urutan' => 1]);

        $this->siswa = Siswa::create([
            'nisn' => '2029000101',
            'nis' => '2029001',
            'nama' => 'Siswa Uji',
            'username' => 'siswauji',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswauji',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        $this->jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Tengah Semester',
            'kode_jenis' => 'PTS',
        ]);
    }

    public function test_data_master_menus_render_for_each_role(): void
    {
        $adminPages = [
            '/master/tahun',
            '/master/tahun/rollover',
            '/master/jurusan',
            '/master/mapel',
            '/master/kelompok-mapel',
            '/master/ekstra',
            '/master/siswa',
            '/master/siswa/create',
            '/master/siswa/'.$this->siswa->id_siswa.'/edit',
            '/master/kelas',
            '/master/kelas/'.$this->kelas->id_kelas.'/anggota',
            '/master/guru',
            '/master/buku-induk',
            '/master/buku-induk/'.$this->siswa->id_siswa,
            '/alumni',
        ];

        foreach ($adminPages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        foreach (['/master/siswa', '/master/kelas', '/master/mapel'] as $url) {
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        foreach (['/master/tahun', '/master/jurusan', '/master/kelompok-mapel', '/master/ekstra', '/master/guru', '/alumni'] as $url) {
            $this->actingAs($this->guruUser)->get($url)->assertForbidden();
        }
    }

    public function test_student_and_buku_induk_kelas_filter_matches_membership(): void
    {
        $this->actingAs($this->admin)
            ->get('/master/siswa?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Siswa')
                ->has('siswa.data', 1)
                ->where('siswa.data.0.nama', 'Siswa Uji'));

        $this->actingAs($this->admin)
            ->get('/master/buku-induk?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('BukuInduk/Index')
                ->has('siswa.data', 1)
                ->where('siswa.data.0.nama', 'Siswa Uji'));

        $this->actingAs($this->admin)
            ->get('/master/siswa?arsip=1&kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Siswa')
                ->has('siswa.data', 0));
    }

    public function test_year_with_classes_cannot_be_deleted_silently(): void
    {
        $baru = TahunPelajaran::create(['tahun' => '2028/2029', 'active' => 0]);
        $kelas = Kelas::create([
            'id_tp' => $baru->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'XII IPA 9',
            'level_id' => $this->levelId,
        ]);

        $this->sim('delete', '/master/tahun/'.$baru->id_tp)->assertSessionHas('error');

        $this->assertDatabaseHas('master_tp', ['id_tp' => $baru->id_tp]);
        $this->assertDatabaseHas('master_kelas', ['id_kelas' => $kelas->id_kelas]);
    }

    public function test_removing_class_member_keeps_previous_period_history(): void
    {
        $lama = TahunPelajaran::create(['tahun' => '2025/2026', 'active' => 0]);
        $kelasLama = Kelas::create([
            'id_tp' => $lama->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'IX IPA 1',
            'level_id' => $this->levelId,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 99,
            'id_tp' => $lama->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $kelasLama->id_kelas,
        ]);

        $this->sim('delete', '/master/kelas/'.$this->kelas->id_kelas.'/anggota/'.$this->siswa->id_siswa)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('kelas_siswa', [
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->siswa->id_siswa,
        ]);
        $this->assertDatabaseHas('kelas_siswa', [
            'id_kelas' => $kelasLama->id_kelas,
            'id_siswa' => $this->siswa->id_siswa,
        ]);
    }

    public function test_student_import_and_jurusan_delete_guard(): void
    {
        $file = $this->uploadedBinary('siswa.csv', "nisn,nis,nama,username,password\n"
            ."2029555001,2029501,Impor Satu,imporsatu,\n"
            ."2029555002,2029502,Impor Dua,impordos,\n"
            ."2029555003,2029503,Impor Tiga,imporsatu,\n");

        $this->sim('post', '/master/siswa/import', ['file' => $file])->assertSessionHas('success');

        $this->assertDatabaseHas('master_siswa', ['nisn' => '2029555001', 'nama' => 'Impor Satu']);
        $this->assertDatabaseHas('master_siswa', ['nisn' => '2029555002', 'nama' => 'Impor Dua']);
        $this->assertDatabaseMissing('master_siswa', ['nisn' => '2029555003']);

        $account = User::where('username', 'imporsatu')->firstOrFail();
        $this->assertTrue(Hash::check('2029555001', $account->password));
        $this->assertSame(1, (int) $account->must_change_password);

        DB::table('master_jurusan')->insert(['nama_jurusan' => 'TKJ', 'kode_jurusan' => 'TKJ']);
        $jurusanId = (int) DB::table('master_jurusan')->where('nama_jurusan', 'TKJ')->value('id_jurusan');

        $kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X TKJ 1',
            'level_id' => $this->levelId,
            'jurusan_id' => $jurusanId,
        ]);

        $this->sim('delete', '/master/jurusan/'.$jurusanId)->assertSessionHas('error');
        $this->assertDatabaseHas('master_jurusan', ['id_jurusan' => $jurusanId]);
        $this->assertDatabaseHas('master_kelas', ['id_kelas' => $kelas->id_kelas]);
    }

    public function test_data_master_filters_accept_odd_input(): void
    {
        $urls = [
            '/master/mapel?search=%25',
            '/master/mapel?page=2',
            '/master/guru?arsip=1&search=zzz',
            '/master/siswa?arsip=1&kelas=abc&search=zzz',
            '/master/siswa?kelas='.$this->kelas->id_kelas,
            '/master/kelas?arsip=1&search=zzz',
            '/master/kelas?search=%27',
            '/master/kelas/'.$this->kelas->id_kelas.'/anggota?search=%25',
            '/master/buku-induk?search=zzz&kelas=abc',
            '/master/buku-induk?kelas='.$this->kelas->id_kelas,
            '/alumni?status=abc&search=zzz',
            '/master/tahun/rollover?sumber_tp=abc&sumber_smt=abc',
            '/master/tahun/rollover?sumber_tp=999999&sumber_smt=999999',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_data_master_pages_survive_without_active_period(): void
    {
        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        foreach ([
            '/master/kelas',
            '/master/siswa',
            '/master/kelas/'.$this->kelas->id_kelas.'/anggota',
            '/master/buku-induk',
            '/alumni',
            '/master/siswa/create',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->sim('post', '/master/kelas', [
            'nama_kelas' => 'XI Tanpa Periode',
            'level_id' => $this->levelId,
        ])->assertSessionHas('error');
    }

    public function test_master_data_journey(): void
    {
        $this->sim('post', '/master/tahun', ['tahun' => '2027/2028'])->assertSessionHas('success');
        $tahunBaru = TahunPelajaran::where('tahun', '2027/2028')->firstOrFail();

        $this->sim('put', '/master/tahun/'.$tahunBaru->id_tp, ['tahun' => '2027/2028']);
        $this->sim('post', '/master/tahun/'.$tahunBaru->id_tp.'/aktif')->assertSessionHas('success');
        $this->assertSame(1, (int) $tahunBaru->refresh()->active);
        $this->sim('post', '/master/tahun/'.$this->tahun->id_tp.'/aktif');
        $this->sim('delete', '/master/tahun/'.$tahunBaru->id_tp)->assertSessionHas('success');

        $this->sim('post', '/master/ekstra', ['nama_ekstra' => 'Pramuka', 'kode_ekstra' => 'PRM']);
        $ekstraId = DB::table('master_ekstra')->where('kode_ekstra', 'PRM')->value('id_ekstra');
        $this->assertNotNull($ekstraId);
        $this->sim('put', '/master/ekstra/'.$ekstraId, ['nama_ekstra' => 'Pramuka Inti', 'kode_ekstra' => 'PRM']);
        $this->sim('delete', '/master/ekstra/'.$ekstraId);

        $this->sim('post', '/master/jurusan', [
            'nama_jurusan' => 'IPA',
            'kode_jurusan' => 'IPA',
            'mapel' => [$this->mapel->id_mapel],
        ]);
        $jurusanId = DB::table('master_jurusan')->where('nama_jurusan', 'IPA')->value('id_jurusan');
        $this->assertNotNull($jurusanId);
        $this->sim('put', '/master/jurusan/'.$jurusanId, ['nama_jurusan' => 'IPA Murni', 'kode_jurusan' => 'IPA']);

        $this->sim('post', '/master/mapel', ['nama_mapel' => 'Fisika', 'kode' => 'FSK', 'urutan' => 2]);
        $mapelId = Mapel::where('nama_mapel', 'Fisika')->value('id_mapel');
        $this->sim('put', '/master/mapel/'.$mapelId, ['nama_mapel' => 'Fisika Terapan', 'kode' => 'FSK']);

        $this->sim('post', '/master/kelas', [
            'nama_kelas' => 'X IPA 2',
            'kode_kelas' => 'XIPA2',
            'level_id' => $this->levelId,
            'jurusan_id' => $jurusanId,
        ])->assertSessionHas('success');
        $kelasBaru = Kelas::where('nama_kelas', 'X IPA 2')->firstOrFail();

        $this->sim('put', '/master/kelas/'.$kelasBaru->id_kelas, [
            'nama_kelas' => 'X IPA 2A',
            'kode_kelas' => 'XIPA2',
            'level_id' => $this->levelId,
        ]);
        $this->assertSame('X IPA 2A', $kelasBaru->refresh()->nama_kelas);

        $siswaKedua = Siswa::create([
            'nisn' => '2029000102',
            'nis' => '2029002',
            'nama' => 'Siswa Anggota',
            'username' => 'siswaanggota',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswaanggota',
        ]);

        $this->sim('post', '/master/kelas/'.$kelasBaru->id_kelas.'/anggota', ['siswa' => [$siswaKedua->id_siswa]])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('kelas_siswa', ['id_kelas' => $kelasBaru->id_kelas, 'id_siswa' => $siswaKedua->id_siswa]);

        $this->sim('delete', '/master/kelas/'.$kelasBaru->id_kelas.'/anggota/'.$siswaKedua->id_siswa);
        $this->sim('post', '/master/kelas/'.$kelasBaru->id_kelas.'/anggota', ['siswa' => [$siswaKedua->id_siswa]]);

        $this->sim('delete', '/master/kelas/'.$kelasBaru->id_kelas)->assertSessionHas('success');
        $this->sim('post', '/master/kelas/'.$kelasBaru->id_kelas.'/restore')->assertSessionHas('success');
        $this->assertNull(Kelas::find($kelasBaru->id_kelas)->deleted_at ?? null);

        $this->sim('post', '/master/guru', [
            'nip' => '198501012010011001',
            'nama_guru' => 'Guru Simulasi',
            'kode_guru' => 'GS',
            'username' => 'gurusimulasi',
            'password' => 'rahasia123',
        ])->assertSessionHas('success');
        $guruId = DB::table('master_guru')->where('username', 'gurusimulasi')->value('id_guru');
        $this->assertNotNull($guruId);
        $this->assertDatabaseHas('users', ['username' => 'gurusimulasi']);

        $this->sim('put', '/master/guru/'.$guruId, [
            'nip' => '198501012010011001',
            'nama_guru' => 'Guru Simulasi Dua',
            'username' => 'gurusimulasi',
        ]);
        $this->sim('delete', '/master/guru/'.$guruId)->assertSessionHas('success');
        $this->sim('post', '/master/guru/'.$guruId.'/restore');

        $guruTanpaAkun = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011099',
            'nama_guru' => 'Guru Tanpa Akun',
            'username' => 'gurutanpaakun',
        ]);

        $this->sim('put', '/master/guru/'.$guruTanpaAkun, [
            'nip' => '198501012010011099',
            'nama_guru' => 'Guru Tanpa Akun Baru',
            'username' => 'gurutanpaakun',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['username' => 'gurutanpaakun']);

        $this->sim('post', '/master/siswa', [
            'nisn' => '2029000199',
            'nis' => '2029099',
            'nama' => 'Siswa Baru',
            'username' => 'siswabaru',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
        ])->assertSessionHas('success');
        $siswaId = Siswa::where('username', 'siswabaru')->value('id_siswa');
        $this->assertNotNull($siswaId);

        $this->sim('post', '/master/siswa/'.$siswaId, [
            'nisn' => '2029000199',
            'nis' => '2029099',
            'nama' => 'Siswa Baru Diubah',
            'username' => 'siswabaru',
            'kelas_awal' => $this->levelId,
        ]);
        $this->assertSame('Siswa Baru Diubah', Siswa::find($siswaId)->nama);

        $this->sim('delete', '/master/siswa/'.$siswaId);
        $this->sim('post', '/master/siswa/'.$siswaId.'/restore');
    }

    public function test_cbt_menus_render_for_each_role(): void
    {
        DB::table('cbt_sesi')->insert(['nama_sesi' => 'Sesi Uji', 'kode_sesi' => 'SU', 'waktu_mulai' => '07:00', 'waktu_akhir' => '09:00', 'aktif' => 1]);
        DB::table('cbt_ruang')->insert(['nama_ruang' => 'Ruang Uji', 'kode_ruang' => 'RU']);

        $adminPages = [
            '/cbt/jenis',
            '/cbt/sesi',
            '/cbt/ruang',
            '/cbt/kelas-ruang',
            '/cbt/kelas-ruang/siswa',
            '/cbt/kelas-ruang/siswa?kelas='.$this->kelas->id_kelas,
            '/cbt/nomor-peserta',
            '/cbt/nomor-peserta?kelas='.$this->kelas->id_kelas,
            '/cbt/bank-soal',
            '/cbt/jadwal',
            '/cbt/pengawas',
            '/cbt/token',
        ];

        foreach ($adminPages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        foreach (['/cbt/bank-soal', '/cbt/jadwal', '/cbt/token'] as $url) {
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        $bocor = [];
        foreach (['/cbt/jenis', '/cbt/sesi', '/cbt/ruang', '/cbt/kelas-ruang', '/cbt/kelas-ruang/siswa', '/cbt/nomor-peserta', '/cbt/pengawas'] as $url) {
            $status = $this->actingAs($this->guruUser)->get($url)->getStatusCode();

            if ($status !== 403) {
                $bocor[$url] = $status;
            }
        }

        $this->assertSame([], $bocor, 'Halaman khusus admin yang bisa dibuka guru: '.json_encode($bocor));
    }

    public function test_cbt_menus_accept_odd_input(): void
    {
        $this->makeJadwal($this->makeBank());

        foreach ([
            '/cbt/bank-soal?search=%25&mapel=abc&jenis=abc&page=2',
            '/cbt/bank-soal?search=%27',
            '/cbt/jadwal?jadwal=abc',
            '/cbt/pengawas?jadwal=abc',
            '/cbt/pengawas?jadwal=999999',
            '/cbt/kelas-ruang/siswa?kelas=abc',
            '/cbt/kelas-ruang/siswa?kelas=999999',
            '/cbt/nomor-peserta?kelas=abc',
            '/cbt/nomor-peserta?kelas=999999',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_cbt_menus_survive_without_active_period(): void
    {
        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        foreach ([
            '/cbt/sesi',
            '/cbt/ruang',
            '/cbt/kelas-ruang',
            '/cbt/kelas-ruang/siswa',
            '/cbt/nomor-peserta',
            '/cbt/bank-soal',
            '/cbt/jadwal',
            '/cbt/pengawas',
            '/cbt/token',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->sim('post', '/cbt/bank-soal', [
            'bank_kode' => 'TANPA-PERIODE',
            'bank_nama' => 'Bank Tanpa Periode',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'opsi' => 4,
        ])->assertSessionHas('error');

        $this->sim('post', '/cbt/kelas-ruang', [
            'assignments' => [['id_kelas' => $this->kelas->id_kelas, 'id_ruang' => 1, 'id_sesi' => 1]],
        ])->assertSessionHas('error');
    }

    public function test_rapor_generated_ids_support_large_values(): void
    {
        $idSikap = 4446132401;

        DB::table('rapor_nilai_sikap')->insert([
            'id_nilai_sikap' => $idSikap,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 1,
            'nilai' => serialize(['predikat' => 'A']),
            'deskripsi' => 'Baik.',
        ]);

        DB::table('rapor_catatan_wali')->insert([
            'id_catatan_wali' => $idSikap,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->siswa->id_siswa,
            'nilai' => serialize([]),
            'deskripsi' => 'Naik kelas.',
        ]);

        $this->assertSame($idSikap, (int) DB::table('rapor_nilai_sikap')->where('id_nilai_sikap', $idSikap)->value('id_nilai_sikap'));
        $this->assertSame($idSikap, (int) DB::table('rapor_catatan_wali')->where('id_catatan_wali', $idSikap)->value('id_catatan_wali'));
    }

    public function test_exam_schedule_accepts_three_digit_year_id(): void
    {
        $tahunBesar = new TahunPelajaran(['tahun' => '2100/2101', 'active' => 0]);
        $tahunBesar->id_tp = 123;
        $tahunBesar->save();

        $jadwalId = DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $this->makeBank(),
            'id_jenis' => $this->jenisId,
            'id_tp' => 123,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 90,
            'status' => 1,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);

        $this->assertSame(123, (int) DB::table('cbt_jadwal')->where('id_jadwal', $jadwalId)->value('id_tp'));
    }

    public function test_token_page_survives_legacy_invalid_timestamp(): void
    {
        DB::table('cbt_token')->insert([
            'id_token' => 1,
            'token' => 'ABCDEF',
            'auto' => 0,
            'jarak' => 1,
            'updated' => '0000-00-00 00:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/token')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Token')
                ->where('token.token', 'ABCDEF')
                ->where('token.updated', null)
                ->where('token.updated_iso', null));

        $this->sim('post', '/cbt/token', ['force' => true])->assertSessionHas('success');

        $token = (string) DB::table('cbt_token')->where('id_token', 1)->value('token');
        $this->assertSame(6, strlen($token));
        $this->assertNotSame('ABCDEF', $token);
        $this->assertNotSame('0000-00-00 00:00:00', (string) DB::table('cbt_token')->where('id_token', 1)->value('updated'));
    }

    public function test_room_session_and_supervisor_assignment_journey(): void
    {
        $sesiId = DB::table('cbt_sesi')->insertGetId([
            'nama_sesi' => 'Sesi Pagi',
            'kode_sesi' => 'SP',
            'waktu_mulai' => '07:30',
            'waktu_akhir' => '09:30',
            'aktif' => 1,
        ]);
        $ruangId = DB::table('cbt_ruang')->insertGetId(['nama_ruang' => 'Ruang 01', 'kode_ruang' => 'R01']);
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011077',
            'nama_guru' => 'Guru Pengawas',
            'username' => 'gurupengawas',
        ]);

        $this->sim('post', '/cbt/kelas-ruang', [
            'assignments' => [[
                'id_kelas' => $this->kelas->id_kelas,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_sesi_siswa', [
            'siswa_id' => $this->siswa->id_siswa,
            'ruang_id' => $ruangId,
            'sesi_id' => $sesiId,
        ]);

        $this->sim('post', '/cbt/kelas-ruang/siswa', [
            'kelas_id' => $this->kelas->id_kelas,
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'ruang_id' => $ruangId,
                'sesi_id' => $sesiId,
            ]],
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get('/cbt/kelas-ruang/siswa?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/KelasRuangSiswa')
                ->has('students', 1)
                ->where('students.0.ruang_id', $ruangId)
                ->where('students.0.sesi_id', $sesiId));

        $jadwalId = $this->makeJadwal($this->makeBank());

        $this->sim('post', '/cbt/pengawas', [
            'rows' => [[
                'id_jadwal' => $jadwalId,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
                'id_guru' => [$guruId],
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_pengawas', ['id_ruang' => (string) $ruangId, 'id_sesi' => (string) $sesiId, 'id_guru' => (string) $guruId]);

        $this->actingAs($this->admin)
            ->get('/cbt/pengawas?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Pengawas')
                ->where('selected', $jadwalId)
                ->has('combos'));
    }

    public function test_participant_numbers_cannot_duplicate_across_students(): void
    {
        DB::table('cbt_sesi')->insert(['nama_sesi' => 'Sesi Uji', 'kode_sesi' => 'SU', 'waktu_mulai' => '07:00', 'waktu_akhir' => '09:00', 'aktif' => 1]);

        $this->sim('post', '/cbt/nomor-peserta', [
            'items' => [['id_siswa' => $this->siswa->id_siswa, 'nomor_peserta' => '0001']],
        ])->assertSessionHas('success');

        $kedua = Siswa::create([
            'nisn' => '2029000103',
            'nis' => '2029003',
            'nama' => 'Siswa Duplikat',
            'username' => 'siswaduplikat',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswaduplikat',
        ]);

        $this->actingAs($this->admin)
            ->post('/cbt/nomor-peserta', [
                'items' => [['id_siswa' => $kedua->id_siswa, 'nomor_peserta' => '0001']],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(1, DB::table('cbt_nomor_peserta')->where('nomor_peserta', '0001')->count());
        $this->assertSame('', (string) DB::table('cbt_nomor_peserta')->where('id_siswa', $kedua->id_siswa)->value('nomor_peserta'));
    }

    public function test_session_and_room_with_placement_cannot_be_deleted(): void
    {
        $sesiId = DB::table('cbt_sesi')->insertGetId([
            'nama_sesi' => 'Sesi Terpakai',
            'kode_sesi' => 'ST',
            'waktu_mulai' => '07:00',
            'waktu_akhir' => '09:00',
            'aktif' => 1,
        ]);
        $ruangId = DB::table('cbt_ruang')->insertGetId(['nama_ruang' => 'Ruang Terpakai', 'kode_ruang' => 'RT']);

        $this->sim('post', '/cbt/kelas-ruang', [
            'assignments' => [[
                'id_kelas' => $this->kelas->id_kelas,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
            ]],
        ])->assertSessionHas('success');

        $this->sim('delete', '/cbt/sesi/'.$sesiId)->assertSessionHas('error');
        $this->sim('delete', '/cbt/ruang/'.$ruangId)->assertSessionHas('error');

        $this->assertDatabaseHas('cbt_sesi', ['id_sesi' => $sesiId]);
        $this->assertDatabaseHas('cbt_ruang', ['id_ruang' => $ruangId]);
        $this->assertDatabaseHas('cbt_kelas_ruang', ['id_kelas' => $this->kelas->id_kelas, 'id_sesi' => $sesiId]);
    }

    public function test_cbt_master_data_journey(): void
    {
        $this->sim('post', '/cbt/jenis', ['nama_jenis' => 'Ujian Sekolah', 'kode_jenis' => 'US']);
        $jenisBaru = DB::table('cbt_jenis')->where('kode_jenis', 'US')->value('id_jenis');
        $this->assertNotNull($jenisBaru);
        $this->sim('put', '/cbt/jenis/'.$jenisBaru, ['nama_jenis' => 'Ujian Sekolah', 'kode_jenis' => 'US']);
        $this->sim('delete', '/cbt/jenis/'.$jenisBaru);

        $this->sim('post', '/cbt/sesi', [
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '07:30',
            'waktu_akhir' => '09:30',
            'aktif' => true,
        ]);
        $sesiId = DB::table('cbt_sesi')->where('kode_sesi', 'S1')->value('id_sesi');
        $this->assertNotNull($sesiId);
        $this->sim('put', '/cbt/sesi/'.$sesiId, [
            'nama_sesi' => 'Sesi 1A',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '07:30',
            'waktu_akhir' => '10:00',
            'aktif' => true,
        ]);

        $this->sim('post', '/cbt/ruang', ['nama_ruang' => 'Ruang 1', 'kode_ruang' => 'R1']);
        $ruangId = DB::table('cbt_ruang')->where('kode_ruang', 'R1')->value('id_ruang');
        $this->assertNotNull($ruangId);
        $this->sim('put', '/cbt/ruang/'.$ruangId, ['nama_ruang' => 'Ruang A', 'kode_ruang' => 'R1']);

        $this->sim('post', '/cbt/kelas-ruang', [
            'assignments' => [[
                'id_kelas' => $this->kelas->id_kelas,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
            ]],
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('cbt_kelas_ruang', ['id_kelas' => $this->kelas->id_kelas]);

        $this->sim('delete', '/cbt/kelas-ruang/'.$this->kelas->id_kelas.$this->tahun->id_tp.$this->semester->id_smt);

        $this->sim('post', '/cbt/nomor-peserta', [
            'items' => [['id_siswa' => $this->siswa->id_siswa, 'nomor_peserta' => 'P-001']],
        ]);
        $this->sim('post', '/cbt/nomor-peserta/generate', ['id_kelas' => $this->kelas->id_kelas])->assertSessionHas('success');
        $this->sim('post', '/cbt/nomor-peserta/reset', ['id_kelas' => $this->kelas->id_kelas]);
    }

    public function test_cbt_bank_soal_and_question_journey(): void
    {
        $this->sim('post', '/cbt/bank-soal', [
            'bank_kode' => 'MTK-PTS',
            'bank_nama' => 'Matematika PTS',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => [$this->kelas->id_kelas],
            'opsi' => 4,
            'kkm' => 75,
            'tampil_pg' => 5,
            'bobot_pg' => 1,
        ])->assertSessionHas('success');

        $bankId = DB::table('cbt_bank_soal')->where('bank_kode', 'MTK-PTS')->value('id_bank');
        $this->assertNotNull($bankId);

        $this->sim('put', '/cbt/bank-soal/'.$bankId, [
            'bank_kode' => 'MTK-PTS',
            'bank_nama' => 'Matematika PTS Ganjil',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'opsi' => 4,
            'kkm' => 75,
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/soal', [
            'jenis' => 1,
            'soal' => '<p>2 + 2 = ...</p>',
            'opsi_a' => '3',
            'opsi_b' => '4',
            'opsi_c' => '5',
            'opsi_d' => '6',
            'jawaban' => 'b',
        ])->assertSessionHas('success');

        $soalId = DB::table('cbt_soal')->where('bank_id', $bankId)->value('id_soal');
        $this->assertNotNull($soalId);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/soal/'.$soalId, [
            'jenis' => 1,
            'soal' => '<p>2 + 3 = ...</p>',
            'opsi_a' => '3',
            'opsi_b' => '4',
            'opsi_c' => '5',
            'opsi_d' => '6',
            'jawaban' => 'c',
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/pilih', ['ids' => [$soalId]]);

        $this->sim('post', '/cbt/media', ['image' => UploadedFile::fake()->image('soal.png', 40, 40)]);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/copy')->assertSessionHas('success');
        $this->assertSame(2, DB::table('cbt_bank_soal')->count());

        $this->sim('delete', '/cbt/bank-soal/'.$bankId.'/soal/'.$soalId);
        $this->sim('delete', '/cbt/bank-soal/'.$bankId);
    }

    public function test_cbt_schedule_session_and_correction_journey(): void
    {
        $bankId = $this->makeBank();

        $this->sim('post', '/cbt/jadwal', [
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20 07:30:00',
            'tgl_selesai' => '2026-11-20 09:30:00',
            'durasi_ujian' => 90,
            'acak_soal' => true,
            'acak_opsi' => false,
            'token' => false,
            'status' => false,
        ])->assertSessionHas('success');

        $jadwalId = DB::table('cbt_jadwal')->where('id_bank', $bankId)->value('id_jadwal');
        $this->assertNotNull($jadwalId);

        $this->sim('put', '/cbt/jadwal/'.$jadwalId, [
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20 07:30:00',
            'tgl_selesai' => '2026-11-20 10:00:00',
            'durasi_ujian' => 100,
        ]);

        $this->sim('post', '/cbt/jadwal/'.$jadwalId.'/status');
        $this->sim('post', '/cbt/token', ['auto' => true]);

        $ruangId = DB::table('cbt_ruang')->insertGetId(['nama_ruang' => 'Ruang 1', 'kode_ruang' => 'R1']);
        $sesiId = DB::table('cbt_sesi')->insertGetId([
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '07:30:00',
            'waktu_akhir' => '09:30:00',
            'aktif' => 1,
        ]);

        $this->sim('post', '/cbt/pengawas', [
            'rows' => [[
                'id_jadwal' => $jadwalId,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
                'id_guru' => [],
            ]],
        ]);

        $this->sim('post', '/cbt/status/'.$jadwalId.'/force', ['siswa' => [$this->siswa->id_siswa], 'mode' => 2]);
        $this->sim('post', '/cbt/status/'.$jadwalId.'/repeat', ['siswa' => [$this->siswa->id_siswa]]);
        $this->sim('post', '/cbt/status/'.$jadwalId.'/reset', ['siswa' => [$this->siswa->id_siswa], 'mode' => 1]);

        $this->sim('post', '/cbt/koreksi/'.$jadwalId.'/tandai');
        $this->sim('delete', '/cbt/jadwal/'.$jadwalId)->assertSessionHas('success');
    }

    public function test_elearning_journey(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011001',
            'nama_guru' => 'Guru Simulasi',
            'username' => 'gurusimulasi',
            'id_user' => $this->guruUser->id,
        ]);

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Pengantar Aljabar',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'isi' => 'Materi singkat.',
            'guru_id' => $guruId,
            'status' => true,
        ])->assertSessionHas('success');

        $materiId = DB::table('kelas_materi')->where('judul_materi', 'Pengantar Aljabar')->value('id_materi');
        $this->assertNotNull($materiId);

        $this->sim('put', '/elearning/'.$materiId, [
            'judul' => 'Pengantar Aljabar Lanjut',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ]);

        $this->sim('post', '/elearning/'.$materiId.'/status');

        $this->sim('post', '/elearning/tugas', [
            'judul' => 'Latihan Aljabar',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
            'tenggat' => '2026-12-01 23:59:00',
        ])->assertSessionHas('success');

        $this->sim('post', '/elearning/jadwal', [
            'kelas' => $this->kelas->id_kelas,
            'date' => '2026-11-20',
            'jam' => 1,
            'jenis' => 1,
        ])->assertSessionHas('success');

        $this->sim('post', '/elearning/jadwal', [
            'kelas' => $this->kelas->id_kelas,
            'date' => '2026-11-20',
            'jam' => 1,
            'jenis' => 1,
            'id_materi' => $materiId,
        ])->assertSessionHas('success');

        $this->sim('post', '/elearning/jadwal-pelajaran', [
            'kelas' => $this->kelas->id_kelas,
            'jam_pel' => 40,
            'jam_mulai' => '07:00',
            'jml_mapel' => 2,
            'template' => [[
                'jam_ke' => 1,
                'mapel' => [0 => $this->mapel->id_mapel],
            ]],
        ]);

        $this->sim('delete', '/elearning/'.$materiId)->assertSessionHas('success');
    }

    public function test_rapor_journey(): void
    {
        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 1,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
        ])->assertSessionHas('success');

        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011002',
            'nama_guru' => 'Wali Simulasi',
            'username' => 'walisimulasi',
            'id_user' => $this->guruUser->id,
        ]);

        $this->sim('post', '/rapor/pengaturan/wali', [
            'id_kelas' => $this->kelas->id_kelas,
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/pengaturan/pengampu', [
            'id_guru' => $guruId,
            'mapel' => [[
                'id_mapel' => $this->mapel->id_mapel,
                'kelas' => [$this->kelas->id_kelas],
            ]],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kkm', [
            'rows' => [[
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'kkm' => 75,
                'bobot_ph' => 40,
                'bobot_pts' => 30,
                'bobot_pas' => 30,
            ]],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/nilai/harian/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'p1' => 80,
                'p2' => 85,
                'k1' => 78,
            ]],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/nilai/pts/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
            'rows' => [['id_siswa' => $this->siswa->id_siswa, 'nilai' => 82]],
        ]);

        $this->sim('post', '/rapor/nilai/pas/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
            'rows' => [['id_siswa' => $this->siswa->id_siswa, 'nilai' => 88]],
        ]);

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/sikap', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'jenis' => 1,
                'predikat' => 'A',
                'deskripsi' => 'Baik.',
            ]],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/catatan', [
            'rows' => [['id_siswa' => $this->siswa->id_siswa, 'catatan' => 'Naik kelas.']],
        ]);

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/prestasi', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'ranking' => 1,
                'deskripsi' => 'Juara kelas.',
            ]],
        ]);

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/fisik', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'tinggi' => 165,
                'berat' => 55,
            ]],
        ]);

        $ekstraId = DB::table('master_ekstra')->insertGetId([
            'nama_ekstra' => 'Pramuka',
            'kode_ekstra' => 'PRM',
        ]);

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/ekstra', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'id_ekstra' => $ekstraId,
                'predikat' => 'A',
            ]],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/peringkat');
        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/naik', [
            'rows' => [['id_siswa' => $this->siswa->id_siswa, 'naik' => 1]],
        ]);

        $this->sim('post', '/rapor/kelas/'.$this->kelas->id_kelas.'/kunci')->assertSessionHas('success');
        $this->sim('delete', '/rapor/kelas/'.$this->kelas->id_kelas.'/kunci')->assertSessionHas('success');
    }

    public function test_accounts_settings_and_profile_journey(): void
    {
        $guruGroupId = Group::where('name', 'guru')->value('id');

        $this->sim('post', '/users/guru', [
            'username' => 'guruakun',
            'nama' => 'Guru Akun',
            'email' => 'guruakun@example.test',
            'password' => 'rahasia123',
            'active' => true,
        ])->assertSessionHas('success');

        $akun = User::where('username', 'guruakun')->firstOrFail();
        $this->assertTrue($akun->groups->contains('id', $guruGroupId));

        $this->sim('put', '/users/guru/'.$akun->id, [
            'username' => 'guruakun',
            'nama' => 'Guru Akun Baru',
        ]);
        $this->assertSame('Guru Akun Baru', $akun->refresh()->first_name);

        $this->sim('delete', '/users/guru/'.$akun->id)->assertSessionHas('success');

        $this->sim('post', '/setting/sekolah', [
            'sekolah' => 'SMA Negeri 1 Simulasi',
            'jenjang' => 3,
            'kepsek' => 'Kepala Sekolah',
        ])->assertSessionHas('success');

        $this->sim('post', '/setting/running-text', ['text' => 'Selamat datang']);
        $textId = DB::table('running_text')->value('id_text');
        $this->assertNotNull($textId);
        $this->sim('put', '/setting/running-text/'.$textId, ['text' => 'Selamat datang di CBT']);
        $this->sim('delete', '/setting/running-text/'.$textId);

        $this->sim('post', '/setting/api', [
            'auto_sync' => false,
            'edit_profile_siswa' => true,
            'edit_profile_guru' => true,
        ])->assertSessionHas('success');

        $this->sim('post', '/periode', [
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ])->assertSessionHas('success');

        $this->sim('post', '/profil', ['nama' => 'Administrator Uji']);
        $this->sim('post', '/profil/password', [
            'current_password' => 'rahasia123',
            'password' => 'rahasia456',
            'password_confirmation' => 'rahasia456',
        ]);
    }

    public function test_alumni_and_buku_induk_journey(): void
    {
        $this->sim('post', '/alumni/generate', [
            'kelas' => $this->kelas->id_kelas,
            'status' => 2,
            'tahun_lulus' => 2026,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('buku_induk', ['id_siswa' => $this->siswa->id_siswa, 'status' => 2]);

        $this->sim('post', '/master/buku-induk/'.$this->siswa->id_siswa, [
            'status' => 2,
            'nama_panggilan' => 'Uji',
            'gol_darah' => 'O',
            'anak_ke' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('buku_induk', [
            'id_siswa' => $this->siswa->id_siswa,
            'nama_panggilan' => 'Uji',
        ]);

        $this->sim('put', '/alumni/'.$this->siswa->id_siswa, [
            'status' => 2,
            'tahun_lulus' => 2026,
            'lanjut_ke' => 'Universitas',
        ]);
    }

    public function test_student_exam_flow(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'UJI-EXAM',
            'bank_nama' => 'Bank Ujian',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'kkm' => 75,
            'tampil_pg' => 1,
            'bobot_pg' => 1,
        ]);

        DB::table('cbt_soal')->insert([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>2 + 2 = ...</p>',
            'deskripsi' => '',
            'opsi_a' => '3',
            'opsi_b' => '4',
            'opsi_c' => '5',
            'opsi_d' => '6',
            'jawaban' => 'B',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        $jadwalId = $this->makeJadwal($bankId);

        $this->actingAs($siswaUser)->get('/ujian')->assertOk();
        $this->actingAs($siswaUser)->get('/ujian/'.$jadwalId)->assertOk();

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai')->assertRedirect('/ujian/'.$jadwalId.'/kerjakan');

        $this->assertDatabaseHas('cbt_durasi_siswa', [
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'status' => 1,
        ]);

        $this->actingAs($siswaUser)->get('/ujian/'.$jadwalId.'/kerjakan')->assertOk();

        $soalSiswaId = DB::table('cbt_soal_siswa')
            ->where('id_siswa', $this->siswa->id_siswa)
            ->value('id_soal_siswa');
        $this->assertNotNull($soalSiswaId);

        $this->actingAs($siswaUser)
            ->postJson('/ujian/'.$jadwalId.'/jawaban', [
                'id_soal_siswa' => (string) $soalSiswaId,
                'jenis' => 1,
                'jawaban' => 'B',
            ])
            ->assertOk();

        $this->assertSame('B', DB::table('cbt_soal_siswa')->where('id_soal_siswa', $soalSiswaId)->value('jawaban_siswa'));

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/checkpoint')->assertOk();

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/selesai')->assertOk();

        $this->assertSame(2, (int) DB::table('cbt_durasi_siswa')
            ->where('id_jadwal', $jadwalId)
            ->where('id_siswa', $this->siswa->id_siswa)
            ->value('status'));

        $this->assertDatabaseCount('cbt_nilai', 1);
        $this->actingAs($siswaUser)->get('/ujian/hasil')->assertOk();
    }

    public function test_pelaksanaan_menus_render_for_each_role(): void
    {
        $this->makeJadwal($this->makeBank());

        $pages = ['/cbt/status', '/cbt/koreksi', '/cbt/hasil', '/cbt/analisis', '/cbt/rekap'];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        foreach ([
            '/cbt/status?jadwal=abc',
            '/cbt/koreksi?jadwal=abc',
            '/cbt/hasil?jadwal=abc',
            '/cbt/analisis?jadwal=999999',
            '/cbt/rekap?jadwal=abc',
            '/cbt/status?jadwal[]=1',
            '/cbt/hasil?jadwal[]=1',
            '/cbt/analisis?jadwal[]=1',
            '/cbt/koreksi?jadwal[]=1',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $siswaUser = $this->user('siswapelaksanaan');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach ($pages as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_pelaksanaan_menus_show_exam_progress_and_results(): void
    {
        $bankId = $this->makeBank();
        $jadwalId = $this->makeJadwal($bankId);

        $soalPg = DB::table('cbt_soal')->insertGetId([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>2 + 2 = ...</p>',
            'deskripsi' => '',
            'opsi_a' => '3',
            'opsi_b' => '4',
            'jawaban' => 'B',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        $soalEsai = DB::table('cbt_soal')->insertGetId([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 5,
            'nomor_soal' => 2,
            'soal' => '<p>Jelaskan.</p>',
            'deskripsi' => '',
            'jawaban' => 'Jawaban',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        DB::table('cbt_durasi_siswa')->insert([
            'id_durasi' => $this->siswa->id_siswa.$jadwalId,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'mulai' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'lama_ujian' => '00:10:00',
            'status' => 1,
            'reset' => 0,
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => $this->siswa->id_siswa.$jadwalId.'P1',
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'id_soal' => $soalPg,
            'jenis_soal' => 1,
            'no_soal_alias' => 1,
            'point_soal' => 100,
            'jawaban_siswa' => 'B',
            'jawaban_benar' => 'B',
            'nilai_otomatis' => 1,
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => $this->siswa->id_siswa.$jadwalId.'E1',
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'id_soal' => $soalEsai,
            'jenis_soal' => 5,
            'no_soal_alias' => 2,
            'point_soal' => 100,
            'jawaban_siswa' => 'Jawaban esai',
            'nilai_koreksi' => '90',
            'nilai_otomatis' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/status?jadwal='.$jadwalId)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Status')
                ->where('selected', $jadwalId)
                ->has('peserta', 1)
                ->where('peserta.0.status', 1)
                ->where('peserta.0.nama', 'Siswa Uji'));

        $this->sim('post', '/cbt/status/'.$jadwalId.'/reset', [
            'siswa' => [$this->siswa->id_siswa],
            'mode' => 1,
        ])->assertSessionHas('success');

        $this->assertSame('00:00:00', DB::table('cbt_durasi_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('lama_ujian'));

        $this->sim('post', '/cbt/status/'.$jadwalId.'/force', ['siswa' => [$this->siswa->id_siswa]])
            ->assertSessionHas('success');

        $this->assertSame(2, (int) DB::table('cbt_durasi_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('status'));
        $this->assertDatabaseCount('cbt_nilai', 1);

        $this->actingAs($this->admin)
            ->get('/cbt/hasil?jadwal='.$jadwalId)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Hasil/Index')
                ->where('summary.peserta', 1)
                ->where('summary.selesai', 1)
                ->has('peserta', 1));

        $this->actingAs($this->admin)
            ->get('/cbt/analisis?jadwal='.$jadwalId)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Analisis/Index')
                ->has('butir', 2));

        $this->actingAs($this->admin)
            ->get('/cbt/rekap')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Rekap/Index')
                ->has('columns', 1)
                ->has('rows', 1));

        $this->sim('post', '/cbt/status/'.$jadwalId.'/repeat', ['siswa' => [$this->siswa->id_siswa]])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cbt_durasi_siswa', 0);
        $this->assertDatabaseCount('cbt_nilai', 0);
        $this->assertDatabaseCount('cbt_soal_siswa', 0);
    }

    public function test_exam_scoring_end_to_end_with_essay(): void
    {
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'SKOR-1',
            'bank_nama' => 'Bank Skor',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'kkm' => 70,
            'tampil_pg' => 2,
            'bobot_pg' => 50,
            'tampil_esai' => 1,
            'bobot_esai' => 50,
        ]);

        foreach ([1, 2] as $nomor) {
            DB::table('cbt_soal')->insert([
                'bank_id' => $bankId,
                'mapel_id' => $this->mapel->id_mapel,
                'jenis' => 1,
                'nomor_soal' => $nomor,
                'soal' => '<p>Soal PG '.$nomor.'</p>',
                'deskripsi' => '',
                'opsi_a' => 'Salah',
                'opsi_b' => 'Benar',
                'jawaban' => 'B',
                'tampilkan' => 1,
                'kesulitan' => 1,
            ]);
        }

        DB::table('cbt_soal')->insert([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 5,
            'nomor_soal' => 3,
            'soal' => '<p>Uraikan jawabanmu.</p>',
            'deskripsi' => '',
            'jawaban' => 'Kunci esai',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        $jadwalId = $this->makeJadwal($bankId);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai');
        $this->actingAs($siswaUser)->get('/ujian/'.$jadwalId.'/kerjakan')->assertOk();

        $pg = DB::table('cbt_soal_siswa')
            ->where('id_jadwal', $jadwalId)
            ->where('jenis_soal', 1)
            ->orderBy('no_soal_alias')
            ->get();

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/jawaban', [
            'id_soal_siswa' => (string) $pg[0]->id_soal_siswa,
            'jenis' => 1,
            'jawaban' => 'B',
        ])->assertOk();

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/jawaban', [
            'id_soal_siswa' => (string) $pg[1]->id_soal_siswa,
            'jenis' => 1,
            'jawaban' => 'A',
        ])->assertOk();

        $esai = DB::table('cbt_soal_siswa')
            ->where('id_jadwal', $jadwalId)
            ->where('jenis_soal', 5)
            ->first();

        $this->assertNotNull($esai);
        $this->assertEquals(50, (float) $esai->point_soal);

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/jawaban', [
            'id_soal_siswa' => (string) $esai->id_soal_siswa,
            'jenis' => 5,
            'jawaban' => 'Jawaban siswa',
        ])->assertOk();

        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/selesai')->assertOk();

        $nilai = DB::table('cbt_nilai')->where('id_jadwal', $jadwalId)->first();

        $this->assertEquals(25, (float) $nilai->pg_nilai);
        $this->assertEquals(0, (float) $nilai->essai_nilai);
        $this->assertSame(0, (int) $nilai->dikoreksi);

        $this->sim('post', '/cbt/koreksi/'.$jadwalId.'/'.$this->siswa->id_siswa, [
            'nilai' => [$esai->id_soal_siswa => 25],
        ])->assertSessionHas('success');

        $nilai = DB::table('cbt_nilai')->where('id_jadwal', $jadwalId)->first();

        $this->assertEquals(25, (float) $nilai->essai_nilai);
        $this->assertSame(1, (int) $nilai->dikoreksi);

        $this->actingAs($this->admin)
            ->get('/cbt/hasil?jadwal='.$jadwalId)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.rata_rata', 50)
                ->where('summary.tuntas', 0)
                ->where('peserta.0.total', 50));
    }

    public function test_pelaksanaan_menus_only_show_teachers_own_exams(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011055',
            'nama_guru' => 'Guru Pelaksanaan',
            'username' => 'gurupelaksanaan',
            'id_user' => $this->guruUser->id,
        ]);

        $guruLain = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011056',
            'nama_guru' => 'Guru Lain',
            'username' => 'gurulainpelaksanaan',
        ]);

        $bankSendiri = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'MILIK-SENDIRI',
            'bank_nama' => 'Bank Sendiri',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'bank_guru_id' => $guruId,
        ]);

        $bankLain = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'MILIK-LAIN',
            'bank_nama' => 'Bank Guru Lain',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'bank_guru_id' => $guruLain,
        ]);

        $jadwalSendiri = $this->makeJadwal($bankSendiri);
        $jadwalLain = $this->makeJadwal($bankLain);

        foreach (['/cbt/status', '/cbt/hasil', '/cbt/analisis', '/cbt/koreksi'] as $url) {
            $this->actingAs($this->guruUser)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('jadwalOptions', 1)
                    ->where('jadwalOptions.0.id_jadwal', $jadwalSendiri)
                    ->where('selected', $jadwalSendiri));
        }

        $this->actingAs($this->guruUser)
            ->get('/cbt/rekap')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('columns', 1));

        foreach (['reset', 'force', 'repeat'] as $action) {
            $this->actingAs($this->guruUser)
                ->post('/cbt/status/'.$jadwalLain.'/'.$action, ['siswa' => [$this->siswa->id_siswa], 'mode' => 1])
                ->assertForbidden();
        }

        $this->actingAs($this->guruUser)
            ->get('/cbt/koreksi/'.$jadwalLain.'/'.$this->siswa->id_siswa)
            ->assertForbidden();
    }

    public function test_time_reset_only_applies_to_students_still_working(): void
    {
        $jadwalId = $this->makeJadwal($this->makeBank());

        DB::table('cbt_durasi_siswa')->insert([
            'id_durasi' => $this->siswa->id_siswa.$jadwalId,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'lama_ujian' => '01:00:00',
            'status' => 2,
            'reset' => 0,
        ]);

        $this->sim('post', '/cbt/status/'.$jadwalId.'/reset', [
            'siswa' => [$this->siswa->id_siswa],
            'mode' => 1,
        ])->assertSessionHas('error');

        $durasi = DB::table('cbt_durasi_siswa')->where('id_siswa', $this->siswa->id_siswa)->first();

        $this->assertSame('01:00:00', $durasi->lama_ujian);
        $this->assertSame(0, (int) $durasi->reset);
    }

    public function test_koreksi_rejects_grading_unknown_answers(): void
    {
        $jadwalId = $this->makeJadwal($this->makeBank());

        $this->actingAs($this->admin)
            ->post('/cbt/koreksi/'.$jadwalId.'/'.$this->siswa->id_siswa, [
                'nilai' => ['999999' => 80],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cbt_nilai', 0);
    }

    public function test_hasil_and_rekap_exports_with_empty_exam(): void
    {
        $jadwalId = $this->makeJadwal($this->makeBank());

        $this->actingAs($this->admin)
            ->get('/cbt/hasil?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Hasil/Index')
                ->where('summary.selesai', 0)
                ->where('summary.rata_rata', null));

        $this->actingAs($this->admin)->get('/cbt/hasil/export?jadwal='.$jadwalId)->assertOk();
        $this->actingAs($this->admin)->get('/cbt/rekap/export?jadwal='.$jadwalId)->assertOk();
        $this->actingAs($this->admin)->get('/cbt/rekap/export-xlsx?jadwal='.$jadwalId)->assertOk();
    }

    public function test_pelaksanaan_menus_survive_without_active_period(): void
    {
        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        foreach (['/cbt/status', '/cbt/koreksi', '/cbt/hasil', '/cbt/analisis', '/cbt/rekap'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->admin)->get('/cbt/hasil/export')->assertNotFound();
        $this->actingAs($this->admin)->get('/cbt/rekap/export')->assertOk();
    }

    public function test_exports_and_print_outputs(): void
    {
        $bankId = $this->makeBank();

        $this->makeJadwal($bankId);

        $this->actingAs($this->admin)->get('/cbt/bank-soal/'.$bankId.'/ekspor')->assertOk();
        $this->actingAs($this->admin)->get('/cbt/hasil/export')->assertOk();
        $this->actingAs($this->admin)->get('/cbt/rekap/export')->assertOk();
        $this->actingAs($this->admin)->get('/cbt/rekap/export-xlsx')->assertOk();

        $this->actingAs($this->admin)->get('/master/buku-induk/'.$this->siswa->id_siswa.'/pdf')->assertOk();
        $this->actingAs($this->admin)->get('/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa.'/pdf')->assertOk();
        $this->actingAs($this->admin)->get('/rapor/leger/'.$this->kelas->id_kelas.'/pdf')->assertOk();
        $this->actingAs($this->admin)->get('/rapor/dkn/'.$this->kelas->id_kelas.'/pdf')->assertOk();
    }

    public function test_tarik_nilai_cbt_ke_rapor(): void
    {
        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 1,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
        ]);

        $bankId = $this->makeBank();
        $jadwalId = $this->makeJadwal($bankId);

        DB::table('cbt_nilai')->insert([
            'id_nilai' => $this->siswa->id_siswa.$jadwalId,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'pg_benar' => 1,
            'pg_nilai' => 90,
            'dikoreksi' => 1,
            'time_create' => now(),
        ]);

        $this->sim('post', '/rapor/tarik-cbt', [
            'id_jadwal' => $jadwalId,
            'target' => 'pts',
            'rows' => [['id_siswa' => $this->siswa->id_siswa]],
        ])->assertSessionHas('success');
    }

    public function test_guru_role_journey(): void
    {
        DB::table('master_guru')->insert([
            'nip' => '198501012010011001',
            'nama_guru' => 'Guru Simulasi',
            'username' => 'guru',
            'id_user' => $this->guruUser->id,
        ]);

        foreach (['/elearning/materi', '/elearning/tugas', '/cbt/bank-soal', '/wali/siswa', '/rapor/nilai'] as $uri) {
            $this->actingAs($this->guruUser)->get($uri)->assertOk();
        }

        $this->actingAs($this->guruUser)
            ->post('/elearning/materi', [
                'judul' => 'Materi Guru',
                'id_mapel' => $this->mapel->id_mapel,
                'kelas' => [$this->kelas->id_kelas],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_materi', ['judul_materi' => 'Materi Guru']);

        $this->actingAs($this->guruUser)
            ->post('/cbt/bank-soal', [
                'bank_kode' => 'GURU-BANK',
                'bank_nama' => 'Bank Guru',
                'bank_mapel_id' => $this->mapel->id_mapel,
                'bank_jenis_id' => $this->jenisId,
                'bank_level' => $this->levelId,
                'opsi' => 4,
            ])
            ->assertSessionHas('success');
    }

    public function test_reference_guards_do_not_crash(): void
    {
        $bankId = $this->makeBank();

        $this->sim('delete', '/master/mapel/'.$this->mapel->id_mapel)->assertSessionHas('success');
        $this->assertSame(0, (int) $this->mapel->refresh()->status);

        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011009',
            'nama_guru' => 'Guru Wali',
            'username' => 'guruwali',
        ]);

        $this->kelas->update(['guru_id' => $guruId]);

        $this->sim('delete', '/master/guru/'.$guruId);

        $jadwalId = $this->makeJadwal($bankId);

        DB::table('cbt_durasi_siswa')->insert([
            'id_durasi' => $this->siswa->id_siswa.$jadwalId,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'status' => 1,
            'lama_ujian' => '00:00:00',
            'mulai' => now(),
            'reset' => 0,
            'time_create' => now(),
        ]);

        $this->sim('delete', '/cbt/jadwal/'.$jadwalId)->assertSessionHas('error');
        $this->assertDatabaseHas('cbt_jadwal', ['id_jadwal' => $jadwalId]);
    }

    public function test_wali_class_journey(): void
    {
        $this->sim('post', '/wali/struktur', [
            'id_kelas' => $this->kelas->id_kelas,
            'ketua' => $this->siswa->id_siswa,
        ])->assertSessionHas('success');

        $this->sim('post', '/wali/siswa/'.$this->siswa->id_siswa, [
            'id_kelas' => $this->kelas->id_kelas,
            'nisn' => $this->siswa->nisn,
            'nis' => $this->siswa->nis,
            'nama' => 'Siswa Uji Wali',
        ])->assertSessionHas('success');

        $this->assertSame('Siswa Uji Wali', $this->siswa->refresh()->nama);

        $this->sim('post', '/wali/siswa/'.$this->siswa->id_siswa.'/foto', [
            'foto' => UploadedFile::fake()->image('foto.png', 40, 40),
        ])->assertSessionHas('success');

        $this->sim('delete', '/wali/siswa/'.$this->siswa->id_siswa.'/foto');
    }

    public function test_elearning_daily_score_journey(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011010',
            'nama_guru' => 'Guru Materi',
            'username' => 'gurumateri',
        ]);

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Materi Nilai Harian',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $materiId = DB::table('kelas_materi')->where('judul_materi', 'Materi Nilai Harian')->value('id_materi');

        $this->sim('post', '/elearning/jadwal', [
            'kelas' => $this->kelas->id_kelas,
            'date' => '2026-11-20',
            'jam' => 2,
            'jenis' => 1,
            'id_materi' => $materiId,
        ])->assertSessionHas('success');

        $idKjm = 'K'.$this->kelas->id_kelas.'-20261120-J2-T1';
        $this->assertDatabaseHas('kelas_jadwal_materi', ['id_kjm' => $idKjm]);

        $this->sim('post', '/elearning/nilai-harian', [
            'kelas' => $this->kelas->id_kelas,
            'tanggal' => '2026-11-20',
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'id_kjm' => $idKjm,
                'nilai' => 88,
                'catatan' => 'Baik.',
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log_materi', [
            'id_siswa' => $this->siswa->id_siswa,
            'id_materi' => $idKjm,
        ]);
    }

    public function test_alumni_removal_restores_status(): void
    {
        $this->sim('post', '/alumni/generate', [
            'kelas' => $this->kelas->id_kelas,
            'status' => 2,
            'tahun_lulus' => 2026,
        ]);

        $this->assertDatabaseHas('buku_induk', ['id_siswa' => $this->siswa->id_siswa, 'status' => 2]);

        $this->sim('delete', '/alumni/'.$this->siswa->id_siswa)->assertSessionHas('success');

        $this->assertDatabaseHas('buku_induk', ['id_siswa' => $this->siswa->id_siswa, 'status' => 1]);
    }

    public function test_tahun_rollover_journey(): void
    {
        DB::table('level_kelas')->insert(['id_level' => 11, 'level' => '11']);

        $this->sim('post', '/master/tahun/rollover', [
            'sumber_tp' => $this->tahun->id_tp,
            'sumber_smt' => $this->semester->id_smt,
            'tahun' => '2027/2028',
            'target_smt' => $this->semester->id_smt,
            'classes' => [[
                'id_kelas' => $this->kelas->id_kelas,
                'ikut' => true,
                'target_nama' => 'XI IPA 1',
                'target_level' => 11,
            ]],
            'salin_pengampu' => true,
            'aktifkan' => false,
        ])->assertSessionHas('success');

        $tahunBaru = TahunPelajaran::where('tahun', '2027/2028')->firstOrFail();
        $this->assertSame('XI IPA 1', Kelas::where('id_tp', $tahunBaru->id_tp)->value('nama_kelas'));
        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.rollover: Rollover ke 2027/2028: 1 kelas, 1 siswa, 0 tinggal kelas, 0 pengampu',
        ]);
    }

    public function test_essay_correction_journey(): void
    {
        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'ESAI-1',
            'bank_nama' => 'Bank Esai',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'kkm' => 75,
            'tampil_esai' => 1,
            'bobot_esai' => 10,
        ]);

        $jadwalId = $this->makeJadwal($bankId);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => $this->siswa->id_siswa.$jadwalId.'E1',
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwalId,
            'jenis_soal' => 5,
            'no_soal_alias' => 1,
            'point_soal' => 100,
            'jawaban_siswa' => 'Jawaban esai siswa',
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 0,
        ]);

        $soalSiswaId = $this->siswa->id_siswa.$jadwalId.'E1';

        $this->actingAs($this->admin)->get('/cbt/koreksi/'.$jadwalId.'/'.$this->siswa->id_siswa)->assertOk();

        $this->sim('post', '/cbt/koreksi/'.$jadwalId.'/'.$this->siswa->id_siswa, [
            'nilai' => [$soalSiswaId => 80],
        ])->assertSessionHas('success');

        $this->assertEquals(80, (float) DB::table('cbt_soal_siswa')
            ->where('id_soal_siswa', $soalSiswaId)
            ->value('nilai_koreksi'));

        $this->assertDatabaseCount('cbt_nilai', 1);

        $this->sim('post', '/cbt/koreksi/'.$jadwalId.'/tandai')->assertSessionHas('success');
    }

    public function test_elearning_menus_render_for_each_role(): void
    {
        $this->makeJadwal($this->makeBank());

        foreach (['/elearning/jadwal-pelajaran', '/elearning/materi', '/elearning/tugas', '/elearning/jadwal'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        foreach ([
            '/elearning/materi?search=%25',
            '/elearning/tugas?search=%27&guru=abc',
            '/elearning/jadwal?kelas=abc&week=abc',
            '/elearning/jadwal?kelas=999999',
            '/elearning/jadwal-pelajaran?kelas=abc',
            '/elearning/jadwal-pelajaran?kelas=999999',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $siswaUser = $this->user('siswaelearning');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach (['/elearning/materi', '/elearning/tugas', '/elearning/jadwal', '/elearning/jadwal-pelajaran'] as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_elearning_material_lifecycle_includes_teacher_change(): void
    {
        $guruSatu = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011021',
            'nama_guru' => 'Guru Satu',
            'username' => 'gurusatu',
        ]);
        $guruDua = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011022',
            'nama_guru' => 'Guru Dua',
            'username' => 'gurudua',
        ]);

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Materi Awal',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'isi' => 'Isi materi awal.',
            'guru_id' => $guruSatu,
            'status' => true,
        ])->assertSessionHas('success');

        $materi = DB::table('kelas_materi')->where('judul_materi', 'Materi Awal')->first();
        $this->assertNotNull($materi);
        $this->assertSame($guruSatu, (int) $materi->id_guru);

        $this->sim('put', '/elearning/'.$materi->id_materi, [
            'judul' => 'Materi Diubah',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'isi' => 'Isi materi baru.',
            'guru_id' => $guruDua,
            'status' => true,
        ])->assertSessionHas('success');

        $materi->judul_materi = DB::table('kelas_materi')->where('id_materi', $materi->id_materi)->value('judul_materi');
        $this->assertSame('Materi Diubah', $materi->judul_materi);
        $this->assertSame($guruDua, (int) DB::table('kelas_materi')->where('id_materi', $materi->id_materi)->value('id_guru'));

        $this->sim('post', '/elearning/'.$materi->id_materi.'/status')->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('kelas_materi')->where('id_materi', $materi->id_materi)->value('status'));

        $this->actingAs($this->admin)
            ->get('/elearning/materi')
            ->assertInertia(fn (Assert $page) => $page->component('Elearning/Index')->has('items', 1));

        $this->sim('delete', '/elearning/'.$materi->id_materi)->assertSessionHas('success');
        $this->assertDatabaseCount('kelas_materi', 0);
    }

    public function test_elearning_material_and_task_codes_are_unique(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011025',
            'nama_guru' => 'Guru Kode',
            'username' => 'gurukode',
        ]);

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Materi Kode',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $this->sim('post', '/elearning/tugas', [
            'judul' => 'Tugas Kode',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $kode = DB::table('kelas_materi')->pluck('kode_materi', 'judul_materi');

        $this->assertNotSame($kode['Materi Kode'], $kode['Tugas Kode']);
        $this->assertSame(2, DB::table('kelas_materi')->distinct()->count('kode_materi'));

        $idMateri = (int) DB::table('kelas_materi')->where('judul_materi', 'Materi Kode')->value('id_materi');
        $this->sim('delete', '/elearning/'.$idMateri)->assertSessionHas('success');

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Materi Kode Baru',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $this->assertSame(2, DB::table('kelas_materi')->distinct()->count('kode_materi'));
    }

    public function test_elearning_task_detail_and_grading(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011023',
            'nama_guru' => 'Guru Tugas Dua',
            'username' => 'gurutugasdua',
        ]);

        $this->sim('post', '/elearning/tugas', [
            'judul' => 'Tugas Esai',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'tenggat' => now()->addDay()->format('Y-m-d H:i'),
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $tugasId = (int) DB::table('kelas_materi')->where('judul_materi', 'Tugas Esai')->value('id_materi');
        $idKjm = 'K'.$this->kelas->id_kelas.'-'.now()->format('Ymd').'-J1-T2';

        DB::table('kelas_jadwal_materi')->insert([
            'id_kjm' => $idKjm,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $tugasId,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => now()->toDateString(),
            'jenis' => 2,
        ]);

        DB::table('log_materi')->insert([
            'id_log' => $this->siswa->id_siswa.$idKjm,
            'log_time' => now(),
            'id_siswa' => $this->siswa->id_siswa,
            'jam_ke' => 1,
            'id_materi' => $idKjm,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 2,
            'log_desc' => 'Mengumpulkan tugas',
            'text' => 'Ini jawaban saya.',
            'file' => serialize([]),
            'address' => '127.0.0.1',
            'agent' => 'test',
            'device' => 'Web',
            'finish_time' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/elearning/tugas/'.$tugasId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/Nilai')
                ->where('summary.total', 1)
                ->where('summary.submitted', 1)
                ->where('rows.0.text', 'Ini jawaban saya.'));

        $this->sim('post', '/elearning/tugas/'.$tugasId.'/nilai', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'nilai' => 85,
                'catatan' => 'Tulisannya rapi.',
            ]],
        ])->assertSessionHas('success');

        $log = DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$idKjm)->first();

        $this->assertSame('85', (string) $log->nilai);
        $this->assertSame('Tulisannya rapi.', $log->catatan);
        $this->assertSame($this->admin->id, (int) $log->dinilai_oleh);

        $this->actingAs($this->admin)
            ->post('/elearning/tugas/'.$tugasId.'/nilai', [
                'rows' => [[
                    'id_siswa' => $this->siswa->id_siswa,
                    'nilai' => 150,
                ]],
            ])
            ->assertSessionHasErrors('rows.0.nilai');

        $this->assertSame('85', (string) DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$idKjm)->value('nilai'));
    }

    public function test_elearning_class_schedule_grid_is_saved(): void
    {
        $this->sim('post', '/elearning/jadwal-pelajaran', [
            'kelas' => $this->kelas->id_kelas,
            'jam_pel' => 40,
            'jam_mulai' => '07:00',
            'jml_mapel' => 8,
            'istirahat' => [['jam_ke' => 4, 'durasi' => 30]],
            'template' => [
                ['jam_ke' => 1, 'mapel' => [1 => $this->mapel->id_mapel]],
                ['jam_ke' => 2, 'mapel' => [2 => $this->mapel->id_mapel]],
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_jadwal_kbm', ['id_kelas' => $this->kelas->id_kelas, 'kbm_jam_pel' => 40]);
        $this->assertSame(2, DB::table('kelas_jadwal_mapel')->where('id_kelas', $this->kelas->id_kelas)->count());

        $this->actingAs($this->admin)
            ->get('/elearning/jadwal-pelajaran?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/JadwalPelajaran')
                ->where('kbm.jam_pel', 40)
                ->where('kbm.istirahat.0.jam_ke', 4)
                ->has('template', 2));

        $this->actingAs($this->admin)
            ->post('/elearning/jadwal-pelajaran', [
                'kelas' => $this->kelas->id_kelas,
                'jam_pel' => 40,
                'jam_mulai' => '07:00',
                'jml_mapel' => 8,
                'istirahat' => [
                    ['jam_ke' => 4, 'durasi' => 30],
                    ['jam_ke' => 4, 'durasi' => 15],
                ],
                'template' => [],
            ])
            ->assertSessionHasErrors('istirahat');

        $this->assertSame(2, DB::table('kelas_jadwal_mapel')->where('id_kelas', $this->kelas->id_kelas)->count());
    }

    public function test_elearning_schedule_grid_rejects_duplicate_slot(): void
    {
        $this->actingAs($this->admin)
            ->post('/elearning/jadwal-pelajaran', [
                'kelas' => $this->kelas->id_kelas,
                'jam_pel' => 40,
                'jam_mulai' => '07:00',
                'jml_mapel' => 8,
                'istirahat' => [],
                'template' => [
                    ['jam_ke' => 1, 'mapel' => [1 => $this->mapel->id_mapel]],
                    ['jam_ke' => 1, 'mapel' => [1 => $this->mapel->id_mapel]],
                ],
            ])
            ->assertSessionHasErrors('template.0.jam_ke');
    }

    public function test_elearning_material_schedule_slot(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011024',
            'nama_guru' => 'Guru Jadwal',
            'username' => 'gurujadwal',
            'id_user' => $this->guruUser->id,
        ]);

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Materi Terjadwal',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $materiId = (int) DB::table('kelas_materi')->where('judul_materi', 'Materi Terjadwal')->value('id_materi');
        $tanggal = now()->startOfWeek()->format('Y-m-d');
        $idKjm = 'K'.$this->kelas->id_kelas.'-'.str_replace('-', '', $tanggal).'-J1-T1';

        $this->sim('post', '/elearning/jadwal', [
            'kelas' => $this->kelas->id_kelas,
            'date' => $tanggal,
            'jam' => 1,
            'jenis' => 1,
            'id_materi' => $materiId,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_jadwal_materi', ['id_kjm' => $idKjm, 'id_materi' => $materiId]);

        $this->actingAs($this->guruUser)
            ->get('/elearning/jadwal?kelas='.$this->kelas->id_kelas.'&week='.$tanggal)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/Jadwal')
                ->where('days.0.slots.0.materi.judul', 'Materi Terjadwal'));

        $kelasLain = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X Lain',
            'level_id' => $this->levelId,
        ]);

        $this->actingAs($this->admin)
            ->post('/elearning/jadwal', [
                'kelas' => $kelasLain->id_kelas,
                'date' => $tanggal,
                'jam' => 2,
                'jenis' => 1,
                'id_materi' => $materiId,
            ])
            ->assertSessionHas('error');

        $this->sim('post', '/elearning/jadwal', [
            'kelas' => $this->kelas->id_kelas,
            'date' => $tanggal,
            'jam' => 1,
            'jenis' => 1,
            'id_materi' => null,
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('kelas_jadwal_materi', ['id_kjm' => $idKjm]);
    }

    public function test_elearning_menus_survive_without_active_period(): void
    {
        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        foreach (['/elearning/materi', '/elearning/tugas', '/elearning/jadwal', '/elearning/jadwal-pelajaran'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->sim('post', '/elearning/materi', [
            'judul' => 'Tanpa Periode',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
        ])->assertSessionHas('error');

        $this->sim('post', '/elearning/jadwal-pelajaran', [
            'kelas' => $this->kelas->id_kelas,
            'jam_pel' => 40,
            'jam_mulai' => '07:00',
            'jml_mapel' => 8,
        ])->assertSessionHas('error');
    }

    public function test_elearning_reports_render_for_each_role(): void
    {
        $this->seedElearningReports();

        $pages = ['/elearning/nilai-harian', '/elearning/kehadiran-harian', '/elearning/kehadiran-bulanan', '/elearning/rekap-nilai'];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        foreach ([
            '/elearning/nilai-harian?kelas=abc&tanggal=abc',
            '/elearning/nilai-harian?kelas=999999',
            '/elearning/nilai-harian?tanggal[]=2026-01-01',
            '/elearning/kehadiran-harian?tanggal[]=x',
            '/elearning/kehadiran-bulanan?bulan=2026-13',
            '/elearning/kehadiran-bulanan?bulan[]=2026-01&mapel[]=1',
            '/elearning/rekap-nilai?mapel=abc',
            '/elearning/rekap-nilai?kelas[]=1&mapel[]=2',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $siswaUser = $this->user('siswareport');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach ($pages as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_elearning_daily_score_report_saves_and_recalculates(): void
    {
        $seeded = $this->seedElearningReports();
        $tanggal = now()->toDateString();

        $this->actingAs($this->admin)
            ->get('/elearning/nilai-harian?kelas='.$this->kelas->id_kelas.'&tanggal='.$tanggal)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/NilaiHarian')
                ->has('items', 2)
                ->where('summary.siswa', 1)
                ->where('summary.terisi', 0));

        $this->sim('post', '/elearning/nilai-harian', [
            'kelas' => $this->kelas->id_kelas,
            'tanggal' => $tanggal,
            'rows' => [
                ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => $seeded['kjm_materi'], 'nilai' => 90, 'catatan' => 'Baik'],
                ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => $seeded['kjm_tugas'], 'nilai' => 80],
            ],
        ])->assertSessionHas('success');

        $materiLog = DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$seeded['kjm_materi'])->first();

        $this->assertSame('90', (string) $materiLog->nilai);
        $this->assertSame('Baik', $materiLog->catatan);
        $this->assertSame($this->admin->id, (int) $materiLog->dinilai_oleh);

        $this->actingAs($this->admin)
            ->get('/elearning/nilai-harian?kelas='.$this->kelas->id_kelas.'&tanggal='.$tanggal)
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.terisi', 2)
                ->where('summary.rata', 85));

        $this->sim('post', '/elearning/nilai-harian', [
            'kelas' => $this->kelas->id_kelas,
            'tanggal' => $tanggal,
            'rows' => [
                ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => $seeded['kjm_materi'], 'nilai' => null, 'catatan' => null],
            ],
        ])->assertSessionHas('success');

        $this->assertNull(DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$seeded['kjm_materi'])->value('nilai'));

        $this->actingAs($this->admin)
            ->post('/elearning/nilai-harian', [
                'kelas' => $this->kelas->id_kelas,
                'tanggal' => $tanggal,
                'rows' => [
                    ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => $seeded['kjm_materi'], 'nilai' => 150],
                ],
            ])
            ->assertSessionHasErrors('rows.0.nilai');

        $this->actingAs($this->admin)
            ->post('/elearning/nilai-harian', [
                'kelas' => $this->kelas->id_kelas,
                'tanggal' => $tanggal,
                'rows' => [
                    ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => 'K999-99999999-J1-T1', 'nilai' => 50],
                    ['id_siswa' => 999999, 'id_kjm' => $seeded['kjm_materi'], 'nilai' => 50],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('log_materi', ['id_materi' => 'K999-99999999-J1-T1']);

        $this->actingAs($this->admin)
            ->get('/elearning/rekap-nilai?kelas='.$this->kelas->id_kelas.'&mapel='.$this->mapel->id_mapel)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/RekapNilai')
                ->has('items', 2)
                ->where('rows.0.rata_tugas', 80)
                ->where('rows.0.rata', 80));
    }

    public function test_elearning_attendance_reports_count_statuses(): void
    {
        $this->seedElearningReports();
        $tanggal = now()->toDateString();

        $this->actingAs($this->admin)
            ->get('/elearning/kehadiran-harian?kelas='.$this->kelas->id_kelas.'&tanggal='.$tanggal)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/KehadiranHarian')
                ->where('rows.0.selesai', 1)
                ->where('rows.0.dibuka', 1)
                ->where('rows.0.status', 'sebagian')
                ->where('summary.sebagian', 1)
                ->where('summary.alpa', 0));

        $this->actingAs($this->admin)
            ->get('/elearning/kehadiran-bulanan?kelas='.$this->kelas->id_kelas.'&bulan='.now()->format('Y-m'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/KehadiranBulanan')
                ->where('summary.hariEfektif', 1)
                ->where('rows.0.cells.'.$tanggal.'.status', 'sebagian')
                ->where('rows.0.totals.sebagian', 1));
    }

    public function test_elearning_reports_isolate_teacher_data(): void
    {
        $seeded = $this->seedElearningReports();

        $this->actingAs($this->guruUser)
            ->get('/elearning/nilai-harian?kelas='.$this->kelas->id_kelas.'&tanggal='.now()->toDateString())
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.id_kjm', $seeded['kjm_materi']));

        $guruTanpaData = $this->user('gurutnpdata');
        $guruTanpaData->groups()->attach(Group::where('name', 'guru')->value('id'));

        $this->actingAs($guruTanpaData)
            ->get('/elearning/nilai-harian?kelas='.$this->kelas->id_kelas.'&tanggal='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items', 0));

        $this->actingAs($guruTanpaData)
            ->get('/elearning/rekap-nilai?kelas='.$this->kelas->id_kelas.'&mapel='.$this->mapel->id_mapel)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items', 0));

        $this->actingAs($this->admin)
            ->post('/elearning/nilai-harian', [
                'kelas' => $this->kelas->id_kelas,
                'tanggal' => now()->toDateString(),
                'rows' => [
                    ['id_siswa' => $this->siswa->id_siswa, 'id_kjm' => $seeded['kjm_tugas'], 'nilai' => 70],
                ],
            ])->assertSessionHas('success');

        $this->assertSame('70', (string) DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$seeded['kjm_tugas'])->value('nilai'));
    }

    private function seedElearningReports(): array
    {
        $guruA = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011031',
            'nama_guru' => 'Guru Laporan A',
            'username' => 'gurulapA',
            'id_user' => $this->guruUser->id,
        ]);
        $guruB = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011032',
            'nama_guru' => 'Guru Laporan B',
            'username' => 'gurulapB',
        ]);

        $basis = [
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'id_mapel' => $this->mapel->id_mapel,
            'kode_mapel' => 'MTR',
            'isi_materi' => 'Isi laporan.',
            'youtube' => '',
            'status' => 1,
            'created_on' => now(),
        ];

        $materiId = DB::table('kelas_materi')->insertGetId($basis + [
            'kode_materi' => 'MTR-LAP-01',
            'judul_materi' => 'Materi Laporan',
            'id_guru' => $guruA,
            'jenis' => 1,
        ]);

        $tugasId = DB::table('kelas_materi')->insertGetId($basis + [
            'kode_materi' => 'MTR-LAP-02',
            'judul_materi' => 'Tugas Laporan',
            'id_guru' => $guruB,
            'jenis' => 2,
        ]);

        $tanggal = now()->toDateString();
        $prefix = 'K'.$this->kelas->id_kelas.'-'.now()->format('Ymd').'-J';
        $kjmMateri = $prefix.'1-T1';
        $kjmTugas = $prefix.'2-T2';

        DB::table('kelas_jadwal_materi')->insert([
            [
                'id_kjm' => $kjmMateri,
                'id_tp' => $this->tahun->id_tp,
                'id_smt' => $this->semester->id_smt,
                'id_materi' => $materiId,
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'jadwal_materi' => $tanggal,
                'jenis' => 1,
            ],
            [
                'id_kjm' => $kjmTugas,
                'id_tp' => $this->tahun->id_tp,
                'id_smt' => $this->semester->id_smt,
                'id_materi' => $tugasId,
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'jadwal_materi' => $tanggal,
                'jenis' => 2,
            ],
        ]);

        $log = [
            'log_time' => now(),
            'id_siswa' => $this->siswa->id_siswa,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 1,
            'log_desc' => 'Membuka materi',
            'text' => '',
            'file' => serialize([]),
            'address' => '127.0.0.1',
            'agent' => 'test',
            'device' => 'Web',
        ];

        DB::table('log_materi')->insert($log + [
            'id_log' => $this->siswa->id_siswa.$kjmMateri,
            'jam_ke' => 1,
            'id_materi' => $kjmMateri,
            'finish_time' => now(),
        ]);

        DB::table('log_materi')->insert($log + [
            'id_log' => $this->siswa->id_siswa.$kjmTugas,
            'jam_ke' => 2,
            'id_materi' => $kjmTugas,
            'finish_time' => null,
        ]);

        return [
            'materi' => $materiId,
            'tugas' => $tugasId,
            'kjm_materi' => $kjmMateri,
            'kjm_tugas' => $kjmTugas,
        ];
    }

    public function test_wali_kelas_menus_render_for_each_role(): void
    {
        $this->seedWaliKelas();

        $pages = ['/wali/struktur', '/wali/siswa', '/wali/catatan'];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->guruUser)->get($url)->assertOk();
            $this->actingAs($this->guruLainUser)->get($url)->assertOk();
        }

        foreach ([
            '/wali/struktur?kelas=abc',
            '/wali/struktur?kelas=999999',
            '/wali/siswa?kelas=abc',
            '/wali/catatan?kelas=abc&mapel=abc',
            '/wali/catatan?mapel[]=1',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->guruLainUser)
            ->get('/wali/struktur')
            ->assertInertia(fn (Assert $page) => $page->has('classes', 0)->where('canManage', false));

        $siswaUser = $this->user('siswawali');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach ($pages as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_wali_kelas_struktur_saves_and_rejects_outsiders(): void
    {
        $seeded = $this->seedWaliKelas();

        $this->actingAs($this->admin)
            ->get('/wali/struktur?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Struktur')
                ->where('canManage', true)
                ->has('students', 1)
                ->has('roles', 17));

        $this->sim('post', '/wali/struktur', [
            'id_kelas' => $this->kelas->id_kelas,
            'ketua' => $this->siswa->id_siswa,
            'sekretaris_1' => '',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_struktur', [
            'id_kelas' => $this->kelas->id_kelas,
            'ketua' => $this->siswa->id_siswa,
            'sekretaris_1' => null,
        ]);

        $this->actingAs($this->guruUser)
            ->get('/wali/struktur?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page->where('struktur.ketua', $this->siswa->id_siswa));

        $this->actingAs($this->admin)
            ->post('/wali/struktur', [
                'id_kelas' => $this->kelas->id_kelas,
                'ketua' => $seeded['outsider']->id_siswa,
            ])
            ->assertSessionHasErrors('ketua');

        $this->actingAs($this->guruLainUser)
            ->post('/wali/struktur', ['id_kelas' => $this->kelas->id_kelas, 'ketua' => $this->siswa->id_siswa])
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->post('/wali/struktur', ['id_kelas' => $seeded['kelas_lain']->id_kelas, 'ketua' => $seeded['outsider']->id_siswa])
            ->assertForbidden();

        $this->assertCount(1, DB::table('kelas_struktur')->get());
    }

    public function test_wali_kelas_updates_student_data_and_photo(): void
    {
        $seeded = $this->seedWaliKelas();
        Storage::fake('public');

        $payload = [
            'id_kelas' => $this->kelas->id_kelas,
            'nisn' => '2029000101',
            'nis' => '2029001',
            'nama' => 'Siswa Diubah',
            'email' => 'siswa.baru@example.test',
            'alamat' => 'Jl. Baru 1',
        ];

        $this->sim('post', '/wali/siswa/'.$this->siswa->id_siswa, $payload)->assertSessionHas('success');

        $this->assertDatabaseHas('master_siswa', [
            'id_siswa' => $this->siswa->id_siswa,
            'nama' => 'Siswa Diubah',
            'email' => 'siswa.baru@example.test',
        ]);

        $this->actingAs($this->guruUser)
            ->post('/wali/siswa/'.$this->siswa->id_siswa, [...$payload, 'nis' => '2029001'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/wali/siswa/'.$this->siswa->id_siswa, [...$payload, 'nis' => $seeded['outsider']->nis])
            ->assertSessionHasErrors('nis');

        $this->actingAs($this->guruLainUser)
            ->post('/wali/siswa/'.$this->siswa->id_siswa, $payload)
            ->assertForbidden();

        $this->sim('post', '/wali/siswa/'.$this->siswa->id_siswa.'/foto', [
            'foto' => UploadedFile::fake()->image('foto.png', 60, 60),
        ])->assertSessionHas('success');

        $foto = DB::table('master_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('foto');

        $this->assertNotEmpty($foto);
        Storage::disk('public')->assertExists($foto);

        $this->actingAs($this->admin)
            ->post('/wali/siswa/'.$this->siswa->id_siswa.'/foto', ['foto' => UploadedFile::fake()->create('data.pdf', 10)])
            ->assertSessionHasErrors('foto');

        $this->actingAs($this->admin)
            ->get('/wali/siswa?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Siswa')
                ->has('students', 1)
                ->where('students.0.foto_url', '/storage/'.$foto));

        $this->sim('delete', '/wali/siswa/'.$this->siswa->id_siswa.'/foto')->assertSessionHas('success');

        $this->assertNull(DB::table('master_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('foto'));
        Storage::disk('public')->assertMissing($foto);
    }

    public function test_wali_kelas_notes_flow(): void
    {
        $this->seedWaliKelas();

        $this->sim('post', '/wali/catatan/mapel', [
            'id_kelas' => $this->kelas->id_kelas,
            'id_mapel' => $this->mapel->id_mapel,
            'type' => 1,
            'level' => 3,
            'text' => 'Kelas ramai saat jam pelajaran.',
        ])->assertSessionHas('success');

        $this->sim('post', '/wali/catatan/mapel', [
            'id_kelas' => $this->kelas->id_kelas,
            'id_mapel' => $this->mapel->id_mapel,
            'type' => 2,
            'id_siswa' => $this->siswa->id_siswa,
            'level' => 2,
            'text' => 'Tugas tidak dikumpulkan.',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('kelas_catatan_mapel', 2);

        $this->sim('post', '/wali/catatan/wali', [
            'id_kelas' => $this->kelas->id_kelas,
            'type' => 1,
            'level' => 1,
            'text' => 'Jaga kebersihan kelas.',
        ])->assertSessionHas('success');

        $this->sim('post', '/wali/catatan/wali', [
            'id_kelas' => $this->kelas->id_kelas,
            'type' => 2,
            'id_siswa' => $this->siswa->id_siswa,
            'level' => 1,
            'text' => 'Tingkatkan belajarmu.',
        ])->assertSessionHas('success');

        $this->actingAs($this->guruUser)
            ->get('/wali/catatan?kelas='.$this->kelas->id_kelas.'&mapel='.$this->mapel->id_mapel)
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Catatan')
                ->where('isWali', true)
                ->has('catatanKelasMapel', 1)
                ->has('catatanSiswaMapel', 1)
                ->has('catatanKelasWali', 1)
                ->has('catatanSiswaWali', 1)
                ->where('catatanKelasMapel.0.level_label', 'Peringatan'));

        $this->actingAs($this->admin)
            ->post('/wali/catatan/mapel', [
                'id_kelas' => $this->kelas->id_kelas,
                'id_mapel' => $this->mapel->id_mapel,
                'type' => 2,
                'id_siswa' => $this->outsider->id_siswa,
                'level' => 1,
                'text' => 'Bukan anggota kelas.',
            ])
            ->assertSessionHasErrors('id_siswa');

        $this->actingAs($this->guruLainUser)
            ->post('/wali/catatan/wali', [
                'id_kelas' => $this->kelas->id_kelas,
                'type' => 1,
                'level' => 1,
                'text' => 'Bukan wali kelas.',
            ])
            ->assertForbidden();

        $waliNote = DB::table('kelas_catatan_wali')->where('type', 1)->value('id_catatan');
        $mapelNote = DB::table('kelas_catatan_mapel')->where('type', 1)->value('id_catatan');

        $this->actingAs($this->guruLainUser)
            ->delete('/wali/catatan/wali/'.$waliNote)
            ->assertForbidden();

        $this->actingAs($this->guruLainUser)
            ->delete('/wali/catatan/mapel/'.$mapelNote)
            ->assertForbidden();

        $this->sim('delete', '/wali/catatan/wali/'.$waliNote)->assertSessionHas('success');
        $this->sim('delete', '/wali/catatan/mapel/'.$mapelNote)->assertSessionHas('success');

        $this->assertDatabaseCount('kelas_catatan_wali', 1);
        $this->assertDatabaseCount('kelas_catatan_mapel', 1);
    }

    public function test_wali_kelas_survives_invalid_payloads(): void
    {
        $this->seedWaliKelas();

        $this->actingAs($this->admin)
            ->post('/wali/struktur', ['id_kelas' => [$this->kelas->id_kelas], 'ketua' => [$this->siswa->id_siswa]])
            ->assertSessionHasErrors(['id_kelas', 'ketua']);

        $this->actingAs($this->admin)
            ->post('/wali/siswa/'.$this->siswa->id_siswa, [
                'id_kelas' => $this->kelas->id_kelas,
                'nisn' => '2029000101',
                'nis' => '2029001',
                'nama' => 'Siswa Uji',
                'anak_ke' => 'dua',
                'email' => 'bukan-email',
            ])
            ->assertSessionHasErrors(['anak_ke', 'email']);

        $this->actingAs($this->admin)
            ->post('/wali/siswa/'.$this->siswa->id_siswa, ['id_kelas' => '', 'nisn' => '', 'nis' => '', 'nama' => ''])
            ->assertSessionHasErrors(['id_kelas', 'nisn', 'nis', 'nama']);

        $this->actingAs($this->admin)
            ->post('/wali/catatan/mapel', [
                'id_kelas' => $this->kelas->id_kelas,
                'id_mapel' => $this->mapel->id_mapel,
                'type' => 5,
                'level' => 0,
                'text' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors(['type', 'level', 'text']);

        $this->actingAs($this->admin)
            ->post('/wali/catatan/wali', ['id_kelas' => $this->kelas->id_kelas, 'type' => 2, 'level' => 1, 'text' => 'Tanpa siswa'])
            ->assertSessionHasErrors('id_siswa');

        $this->actingAs($this->admin)->delete('/wali/siswa/999999/foto')->assertStatus(403);
        $this->actingAs($this->admin)->delete('/wali/catatan/mapel/999999')->assertNotFound();
        $this->actingAs($this->admin)->delete('/wali/catatan/wali/999999')->assertNotFound();
        $this->actingAs($this->admin)->get('/wali/siswa/'.$this->siswa->id_siswa.'/foto')->assertStatus(405);

        $this->assertDatabaseCount('kelas_catatan_mapel', 0);
        $this->assertDatabaseCount('kelas_catatan_wali', 0);
    }

    public function test_wali_kelas_can_open_notes_for_own_class_without_teaching_there(): void
    {
        $seeded = $this->seedWaliKelas();

        Kelas::where('id_kelas', $seeded['kelas_lain']->id_kelas)
            ->update(['guru_id' => DB::table('master_guru')->where('id_user', $this->guruLainUser->id)->value('id_guru')]);

        $this->actingAs($this->guruLainUser)
            ->get('/wali/catatan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isWali', true)
                ->has('classes', 1)
                ->where('classes.0.id_kelas', $seeded['kelas_lain']->id_kelas));

        $this->actingAs($this->guruLainUser)
            ->post('/wali/catatan/wali', [
                'id_kelas' => $seeded['kelas_lain']->id_kelas,
                'type' => 1,
                'level' => 2,
                'text' => 'Catatan wali tanpa jam mengajar.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('kelas_catatan_wali', [
            'id_kelas' => $seeded['kelas_lain']->id_kelas,
            'text' => 'Catatan wali tanpa jam mengajar.',
        ]);

        $this->actingAs($this->guruUser)
            ->get('/wali/catatan')
            ->assertInertia(fn (Assert $page) => $page->has('classes', 1));
    }

    private function seedWaliKelas(): array
    {
        $guruWali = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011041',
            'nama_guru' => 'Guru Wali Kelas',
            'username' => 'guruwali',
            'id_user' => $this->guruUser->id,
        ]);

        Kelas::where('id_kelas', $this->kelas->id_kelas)->update(['guru_id' => $guruWali]);

        DB::table('jabatan_guru')->insert([
            'id_jabatan_guru' => 'JG-'.$guruWali.'-'.$this->mapel->id_mapel,
            'id_guru' => $guruWali,
            'id_jabatan' => 1,
            'id_kelas' => $this->kelas->id_kelas,
            'mapel_kelas' => serialize([[
                'id_mapel' => $this->mapel->id_mapel,
                'nama_mapel' => $this->mapel->nama_mapel,
                'kelas_mapel' => [['kelas' => $this->kelas->id_kelas]],
            ]]),
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->guruLainUser = $this->user('gurulainwali');
        $this->guruLainUser->groups()->attach(Group::where('name', 'guru')->value('id'));

        DB::table('master_guru')->insert([
            'nip' => '198501012010011042',
            'nama_guru' => 'Guru Lain Wali',
            'username' => 'gurulainwali',
            'id_user' => $this->guruLainUser->id,
        ]);

        $kelasLain = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPS 2',
            'level_id' => $this->levelId,
            'guru_id' => null,
        ]);

        $this->outsider = Siswa::create([
            'nisn' => '2029000102',
            'nis' => '2029002',
            'nama' => 'Siswa Kelas Lain',
            'username' => 'siswalain',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswalain',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => (int) DB::table('kelas_siswa')->max('id_kelas_siswa') + 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->outsider->id_siswa,
            'id_kelas' => $kelasLain->id_kelas,
        ]);

        return [
            'guru_wali' => $guruWali,
            'kelas_lain' => $kelasLain,
            'outsider' => $this->outsider,
        ];
    }

    public function test_rapor_menus_render_for_each_role(): void
    {
        $this->seedRaporPengampu();

        $pages = [
            '/rapor/kkm',
            '/rapor/nilai',
            '/rapor/tarik-cbt',
            '/rapor/kelas',
            '/rapor/cetak',
            '/rapor/dkn',
        ];

        foreach (['/rapor/pengaturan', ...$pages] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        foreach ($pages as $url) {
            $this->actingAs($this->guruUser)->get($url)->assertOk();
        }

        foreach (['/rapor/kkm?kelas=abc', '/rapor/nilai?kelas[]=1', '/rapor/tarik-cbt?jadwal=abc&target=zzz'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->admin)->get('/rapor/tarik-cbt?jadwal=999999')->assertNotFound();

        $this->actingAs($this->guruUser)->get('/rapor/pengaturan')->assertForbidden();

        $siswaUser = $this->user('siswarapor');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach (['/rapor/pengaturan', ...$pages] as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_rapor_setting_kkm_and_assignment_flow(): void
    {
        $guruId = $this->seedRaporPengampu();

        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 1,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
            'bobot_absen' => 10,
            'tgl_rapor_akhir' => '2026-12-20',
            'nip_kepsek' => 1,
            'nip_walikelas' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('rapor_admin_setting', [
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'kkm' => 75,
            'kkm_tunggal' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post('/rapor/pengaturan', ['kkm_tunggal' => 0, 'kkm' => 75, 'bobot_ph' => 40, 'bobot_pts' => 30, 'bobot_pas' => 20])
            ->assertSessionHasErrors('bobot_pas');

        $this->sim('post', '/rapor/pengaturan/wali', [
            'id_kelas' => $this->kelas->id_kelas,
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $this->assertSame($guruId, (int) DB::table('master_kelas')->where('id_kelas', $this->kelas->id_kelas)->value('guru_id'));

        $this->sim('post', '/rapor/pengaturan/pengampu', [
            'id_guru' => $guruId,
            'mapel' => [
                ['id_mapel' => $this->mapel->id_mapel, 'kelas' => [$this->kelas->id_kelas]],
            ],
        ])->assertSessionHas('success');

        $jabatan = DB::table('jabatan_guru')
            ->where('id_guru', $guruId)
            ->where('id_tp', $this->tahun->id_tp)
            ->where('id_smt', $this->semester->id_smt)
            ->first();

        $this->assertNotNull($jabatan);
        $this->assertStringContainsString('kelas_mapel', (string) $jabatan->mapel_kelas);

        $this->actingAs($this->admin)
            ->get('/rapor/pengaturan')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/Setting')
                ->where('setting.kkm', 75)
                ->has('classes')
                ->has('assignments'));

        $this->sim('post', '/rapor/kkm', [
            'rows' => [[
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'kkm' => 78,
                'bobot_ph' => 50,
                'bobot_pts' => 25,
                'bobot_pas' => 25,
                'bobot_absen' => 10,
                'beban_jam' => 4,
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('rapor_kkm', [
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'kkm' => 78,
            'beban_jam' => 4,
        ]);

        $this->actingAs($this->guruUser)
            ->get('/rapor/kkm')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/Kkm')
                ->has('rows', 1)
                ->where('rows.0.kkm', 78));

        $this->sim('post', '/rapor/kkm', [
            'rows' => [[
                'id_mapel' => $this->mapel->id_mapel,
                'id_kelas' => $this->kelas->id_kelas,
                'kkm' => 78,
                'bobot_ph' => 50,
                'bobot_pts' => 25,
                'bobot_pas' => 25,
            ]],
        ])->assertSessionHas('success');

        $this->assertSame(78, (int) DB::table('rapor_kkm')->where('id_mapel', $this->mapel->id_mapel)->value('kkm'));

        $this->actingAs($this->guruLainUser)
            ->get('/rapor/kkm')
            ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    public function test_rapor_score_input_and_final_grade(): void
    {
        $this->seedRaporPengampu();
        $mapel = $this->mapel->id_mapel;
        $kelas = $this->kelas->id_kelas;
        $siswa = $this->siswa->id_siswa;

        $this->sim('post', '/rapor/nilai/harian/'.$mapel.'/'.$kelas, [
            'rows' => [[
                'id_siswa' => $siswa,
                'p1' => 80, 'p2' => 90, 'p3' => 70,
                'k1' => 80, 'k2' => 80,
            ]],
            'kikd' => ['p' => ['Kesebangunan'], 'k' => ['Bangun ruang']],
        ])->assertSessionHas('success');

        $harian = DB::table('rapor_nilai_harian')->where('id_siswa', $siswa)->first();

        $this->assertNotNull($harian);
        $this->assertSame(80, (int) $harian->p_rata_rata);
        $this->assertSame('C', $harian->p_predikat);
        $this->assertSame(0, (int) $harian->p1 === 80 ? 0 : 1);
        $this->assertDatabaseHas('rapor_kikd', ['id_mapel_kelas' => $mapel.$kelas, 'aspek' => 1]);

        $this->sim('post', '/rapor/nilai/pts/'.$mapel.'/'.$kelas, [
            'rows' => [['id_siswa' => $siswa, 'nilai' => 88]],
        ])->assertSessionHas('success');

        $this->assertSame('B', DB::table('rapor_nilai_pts')->where('id_siswa', $siswa)->value('predikat'));

        $this->sim('post', '/rapor/nilai/pas/'.$mapel.'/'.$kelas, [
            'rows' => [['id_siswa' => $siswa, 'nilai' => 90]],
        ])->assertSessionHas('success');

        $akhir = DB::table('rapor_nilai_akhir')->where('id_siswa', $siswa)->first();

        $this->assertSame(85, (int) $akhir->akhir);
        $this->assertSame('B', $akhir->predikat);

        $this->actingAs($this->admin)
            ->get('/rapor/nilai/harian/'.$mapel.'/'.$kelas)
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/Harian')->has('siswas', 1));

        $this->actingAs($this->admin)
            ->get('/rapor/nilai/pas/'.$mapel.'/'.$kelas)
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/Pas')->has('siswas', 1));

        foreach (['harian', 'pts', 'pas'] as $jenis) {
            $this->actingAs($this->admin)
                ->get('/rapor/nilai/'.$jenis.'/'.$mapel.'/'.$kelas.'/template')
                ->assertOk();
        }

        $this->actingAs($this->guruLainUser)
            ->post('/rapor/nilai/pts/'.$mapel.'/'.$kelas, ['rows' => [['id_siswa' => $siswa, 'nilai' => 60]]])
            ->assertForbidden();

        $this->sim('post', '/rapor/kelas/'.$kelas.'/kunci')->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/rapor/nilai/pts/'.$mapel.'/'.$kelas, ['rows' => [['id_siswa' => $siswa, 'nilai' => 60]]])
            ->assertStatus(423);

        $this->sim('delete', '/rapor/kelas/'.$kelas.'/kunci')->assertSessionHas('success');

        $this->sim('post', '/rapor/nilai/pts/'.$mapel.'/'.$kelas, [
            'rows' => [['id_siswa' => $siswa, 'nilai' => 61]],
        ])->assertSessionHas('success');
    }

    public function test_rapor_import_matches_zero_padded_nisn(): void
    {
        $this->seedRaporPengampu();
        $mapel = $this->mapel->id_mapel;
        $kelas = $this->kelas->id_kelas;

        $lain = Siswa::create([
            'nisn' => '61234567',
            'nis' => '6123456',
            'nama' => 'Siswa NISN Pendek',
            'username' => 'siswanisnpendek',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-nisnpendek',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => (int) DB::table('kelas_siswa')->max('id_kelas_siswa') + 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $lain->id_siswa,
            'id_kelas' => $kelas,
        ]);

        $this->assertSame('0061234567', (string) DB::table('master_siswa')->where('id_siswa', $lain->id_siswa)->value('nisn'));

        $file = $this->uploadedBinary('nilai-harian.xlsx', $this->excelWithRows([
            ['nisn', 'nis', 'nama', 'p1', 'p2', 'k1'],
            ['2029000101', '2029001', 'Siswa Uji', 80, 90, 70],
            [61234567, '', 'Siswa NISN Pendek', 75, 75, 75],
        ]));

        $this->actingAs($this->admin)
            ->post('/rapor/nilai/harian/'.$mapel.'/'.$kelas.'/import', ['file' => $file])
            ->assertSessionHas('success', 'Impor selesai: 2 nilai harian disimpan.');

        $this->assertSame(2, DB::table('rapor_nilai_harian')->count());
        $this->assertDatabaseHas('rapor_nilai_harian', ['id_siswa' => $lain->id_siswa, 'p_rata_rata' => 75]);
    }

    public function test_rapor_class_data_components_and_lock(): void
    {
        $this->seedRaporPengampu();
        $kelas = $this->kelas->id_kelas;
        $siswa = $this->siswa->id_siswa;

        $ekstraId = DB::table('master_ekstra')->insertGetId(['kode_ekstra' => 'PRM', 'nama_ekstra' => 'Pramuka']);

        $this->sim('post', '/rapor/kelas/'.$kelas.'/sikap', [
            'rows' => [
                ['id_siswa' => $siswa, 'jenis' => 1, 'predikat' => 'A', 'deskripsi' => 'Sangat baik spiritual.'.str_repeat(' ', 60)],
                ['id_siswa' => $siswa, 'jenis' => 2, 'predikat' => 'B', 'deskripsi' => 'Baik sosial.'],
            ],
        ]);

        $this->assertSame(2, DB::table('rapor_nilai_sikap')->where('id_siswa', $siswa)->count());

        $this->sim('post', '/rapor/kelas/'.$kelas.'/catatan', [
            'rows' => [['id_siswa' => $siswa, 's' => 2, 'i' => 1, 'a' => 0, 'catatan' => 'Tingkatkan prestasi.']],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$kelas.'/fisik', [
            'rows' => [['id_siswa' => $siswa, 'tinggi' => 165, 'berat' => 55, 'telinga' => 'Normal', 'mata' => 'Normal', 'gigi' => 'Normal', 'lain' => '-']],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$kelas.'/ekstra', [
            'rows' => [['id_siswa' => $siswa, 'id_ekstra' => $ekstraId, 'nilai' => 90, 'predikat' => 'A', 'deskripsi' => 'Aktif']],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$kelas.'/prestasi', [
            'rows' => [['id_siswa' => $siswa, 'ranking' => 1, 'deskripsi' => 'Juara kelas', 'p1' => 'Olimpiade', 'p1_desk' => 'Juara 1']],
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/kelas/'.$kelas.'/naik', [
            'rows' => [['id_siswa' => $siswa, 'naik' => 1]],
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get('/rapor/kelas/'.$kelas)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/KelasData')
                ->where('siswas.0.id_siswa', $siswa)
                ->where('sikap.'.$siswa.'.1.predikat', 'A')
                ->where('catatan.'.$siswa.'.s', 2)
                ->where('fisik.'.$siswa.'.tinggi', 165));

        $this->actingAs($this->guruLainUser)->get('/rapor/kelas/'.$kelas)->assertForbidden();

        $this->sim('post', '/rapor/kelas/'.$kelas.'/kunci')->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/rapor/kelas/'.$kelas.'/catatan', ['rows' => [['id_siswa' => $siswa, 's' => 1]]])
            ->assertStatus(423);

        $this->sim('delete', '/rapor/kelas/'.$kelas.'/kunci')->assertSessionHas('success');

        foreach (['sikap', 'catatan', 'prestasi', 'fisik'] as $jenis) {
            $this->actingAs($this->admin)
                ->get('/rapor/kelas/'.$kelas.'/template/'.$jenis)
                ->assertOk();
        }

        $this->actingAs($this->admin)
            ->get('/rapor/kelas/'.$kelas.'/template/ekstra?ekstra='.$ekstraId)
            ->assertOk();

        $file = $this->uploadedBinary('sikap.xlsx', $this->excelWithRows([
            ['nisn', 'nis', 'nama', 'predikat_spiritual', 'deskripsi_spiritual', 'predikat_sosial', 'deskripsi_sosial'],
            ['2029000101', '2029001', 'Siswa Uji', 'B', 'Spiritual baik', 'A', 'Sosial baik'],
        ]));

        $this->actingAs($this->admin)
            ->post('/rapor/kelas/'.$kelas.'/import/sikap', ['file' => $file])
            ->assertSessionHas('success', 'Impor selesai: 1 data sikap disimpan.');

        $this->assertDatabaseHas('rapor_nilai_sikap', ['id_siswa' => $siswa, 'jenis' => 1, 'deskripsi' => 'Spiritual baik']);
        $this->assertDatabaseHas('rapor_nilai_sikap', ['id_siswa' => $siswa, 'jenis' => 2, 'deskripsi' => 'Sosial baik']);
    }

    public function test_rapor_printing_and_dkn_documents(): void
    {
        $this->seedRaporPengampu();
        $kelas = $this->kelas->id_kelas;
        $siswa = $this->siswa->id_siswa;

        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 0,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
            'tgl_rapor_akhir' => '2026-12-20',
            'nip_kepsek' => 1,
            'nip_walikelas' => 1,
        ])->assertSessionHas('success');

        $this->sim('post', '/rapor/nilai/pas/'.$this->mapel->id_mapel.'/'.$kelas, [
            'rows' => [['id_siswa' => $siswa, 'nilai' => 88]],
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)->get('/rapor/cetak/'.$kelas)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/CetakSiswa')->where('siswas.0.jumlah_nilai', 1));

        $this->actingAs($this->admin)->get('/rapor/cetak/'.$kelas.'/'.$siswa)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/PrintRapor')->where('siswa.id_siswa', $siswa));

        $pdf = $this->actingAs($this->admin)->get('/rapor/cetak/'.$kelas.'/'.$siswa.'/pdf');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertTrue($this->pdfContains($pdf->getContent(), 'Siswa Uji'));

        $this->actingAs($this->admin)->get('/rapor/cetak/'.$kelas.'/'.$siswa.'?jenis=pts')->assertOk();

        $this->actingAs($this->admin)->get('/rapor/leger/'.$kelas)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/PrintLeger'));

        $leger = $this->actingAs($this->admin)->get('/rapor/leger/'.$kelas.'/pdf');
        $leger->assertOk();
        $this->assertSame('application/pdf', $leger->headers->get('content-type'));

        $this->actingAs($this->admin)->get('/rapor/dkn/'.$kelas)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Rapor/Dkn')->where('rows.0.id_siswa', $siswa));

        $dkn = $this->actingAs($this->admin)->get('/rapor/dkn/'.$kelas.'/pdf');

        $dkn->assertOk();
        $this->assertSame('application/pdf', $dkn->headers->get('content-type'));

        $this->actingAs($this->guruLainUser)->get('/rapor/cetak/'.$kelas.'/'.$siswa.'/pdf')->assertForbidden();
        $this->actingAs($this->admin)->get('/rapor/cetak/'.$kelas.'/999999')->assertNotFound();
    }

    public function test_rapor_pulls_cbt_scores_into_report(): void
    {
        $this->seedRaporPengampu();
        $kelas = $this->kelas->id_kelas;
        $siswa = $this->siswa->id_siswa;

        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 0,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
        ])->assertSessionHas('success');

        $jadwalId = $this->makeJadwal($this->makeBank());

        DB::table('cbt_nilai')->insert([
            'id_nilai' => $siswa.$jadwalId,
            'id_jadwal' => $jadwalId,
            'id_siswa' => $siswa,
            'pg_nilai' => '80',
        ]);

        $this->actingAs($this->admin)
            ->get('/rapor/tarik-cbt?jadwal='.$jadwalId.'&target=pts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/TarikCbt')
                ->where('preview.summary.baru', 1)
                ->where('preview.rows.0.nilai', 80));

        $this->sim('post', '/rapor/tarik-cbt', [
            'id_jadwal' => $jadwalId,
            'target' => 'pts',
            'rows' => [['id_siswa' => $siswa]],
        ])->assertSessionHas('success');

        $this->assertSame('80', (string) DB::table('rapor_nilai_pts')->where('id_siswa', $siswa)->value('nilai'));

        $this->sim('post', '/rapor/tarik-cbt', [
            'id_jadwal' => $jadwalId,
            'target' => 'pas',
            'rows' => [['id_siswa' => $siswa]],
        ])->assertSessionHas('success');

        $this->assertSame(48, (int) DB::table('rapor_nilai_akhir')->where('id_siswa', $siswa)->value('akhir'));

        $this->sim('post', '/rapor/kelas/'.$kelas.'/kunci')->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/rapor/tarik-cbt', [
                'id_jadwal' => $jadwalId,
                'target' => 'pts',
                'rows' => [['id_siswa' => $siswa]],
            ])
            ->assertStatus(423);
    }

    private function seedRaporPengampu(): int
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011051',
            'nama_guru' => 'Guru Rapor',
            'username' => 'gururapor',
            'id_user' => $this->guruUser->id,
        ]);

        DB::table('jabatan_guru')->insert([
            'id_jabatan_guru' => 'JG-RAPOR-'.$guruId,
            'id_guru' => $guruId,
            'id_jabatan' => 2,
            'id_kelas' => $this->kelas->id_kelas,
            'mapel_kelas' => serialize([[
                'id_mapel' => $this->mapel->id_mapel,
                'nama_mapel' => $this->mapel->nama_mapel,
                'kelas_mapel' => [['kelas' => $this->kelas->id_kelas]],
            ]]),
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->guruLainUser = $this->user('gururaporlain');
        $this->guruLainUser->groups()->attach(Group::where('name', 'guru')->value('id'));

        DB::table('master_guru')->insert([
            'nip' => '198501012010011052',
            'nama_guru' => 'Guru Rapor Lain',
            'username' => 'gururaporlain',
            'id_user' => $this->guruLainUser->id,
        ]);

        return $guruId;
    }

    public function test_setting_menus_render_for_each_role(): void
    {
        $pages = [
            '/setting/sekolah',
            '/setting/kop',
            '/setting/running-text',
            '/setting/api',
            '/setting/database',
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        foreach ($pages as $url) {
            $this->actingAs($this->guruUser)->get($url)->assertForbidden();
        }

        $siswaUser = $this->user('siswasetting');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach ($pages as $url) {
            $this->actingAs($siswaUser)->get($url)->assertForbidden();
        }
    }

    public function test_setting_school_profile_saves_and_validates(): void
    {
        Storage::fake('public');

        $payload = [
            'sekolah' => 'SMA Simulasi Nusantara',
            'nama_aplikasi' => 'CBT Simulasi',
            'npsn' => '12345678',
            'nss' => '301020304050',
            'jenjang' => 3,
            'kepsek' => 'Drs. Kepala Sekolah',
            'nip' => '197001012000011001',
            'alamat' => 'Jl. Pendidikan 1',
            'desa' => 'Sukamaju',
            'kecamatan' => 'Cendekia',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'kode_pos' => 40123,
            'telp' => '022123456',
            'fax' => '022654321',
            'web' => 'https://sekolah.example.test',
            'email' => 'info@sekolah.example.test',
            'kode_sekolah' => 'SMA-SIM',
        ];

        $this->sim('post', '/setting/sekolah', $payload)->assertSessionHas('success');

        $this->assertDatabaseHas('setting', ['sekolah' => 'SMA Simulasi Nusantara', 'kode_pos' => 40123]);

        $this->actingAs($this->admin)
            ->post('/setting/sekolah', ['sekolah' => '', 'email' => 'bukan-email', 'jenjang' => 9])
            ->assertSessionHasErrors(['sekolah', 'email', 'jenjang']);

        $this->sim('post', '/setting/sekolah', $payload + [
            'logo_kiri' => UploadedFile::fake()->image('kiri.png', 40, 40),
            'logo_kanan' => UploadedFile::fake()->image('kanan.png', 40, 40),
        ])->assertSessionHas('success');

        $first = DB::table('setting')->first();

        $this->assertNotEmpty($first->logo_kiri);
        Storage::disk('public')->assertExists($first->logo_kiri);

        $this->sim('post', '/setting/sekolah', $payload + [
            'logo_kiri' => UploadedFile::fake()->image('kiri-baru.png', 40, 40),
        ])->assertSessionHas('success');

        $second = DB::table('setting')->first();

        $this->assertNotSame($first->logo_kiri, $second->logo_kiri);
        Storage::disk('public')->assertMissing($first->logo_kiri);
        Storage::disk('public')->assertExists($second->logo_kiri);

        $this->actingAs($this->admin)
            ->get('/setting/sekolah')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Sekolah')
                ->where('setting.sekolah', 'SMA Simulasi Nusantara')
                ->where('setting.jenjang', 3));

        $this->actingAs($this->admin)
            ->post('/setting/sekolah', $payload + ['logo_kiri' => UploadedFile::fake()->create('logo.pdf', 10)])
            ->assertSessionHasErrors('logo_kiri');
    }

    public function test_setting_print_headers_are_saved(): void
    {
        $this->sim('post', '/setting/kop', [
            'kartu' => [
                'header_1' => 'PEMERINTAH PROVINSI JAWA BARAT',
                'header_2' => 'DINAS PENDIDIKAN',
                'header_3' => 'SMA SIMULASI NUSANTARA',
                'header_4' => 'KARTU PESERTA UJIAN',
                'tanggal' => 'Bandung, 20 Desember 2026',
            ],
            'berita' => [
                'header_1' => 'PEMERINTAH PROVINSI JAWA BARAT',
                'header_2' => 'DINAS PENDIDIKAN',
                'header_3' => 'SMA SIMULASI NUSANTARA',
                'header_4' => 'BERITA ACARA UJIAN',
            ],
            'absensi' => [
                'header_1' => 'PEMERINTAH PROVINSI JAWA BARAT',
                'header_2' => 'DINAS PENDIDIKAN',
                'header_3' => 'SMA SIMULASI NUSANTARA',
                'header_4' => 'DAFTAR HADIR PESERTA',
                'proktor' => 'Proktor Uji',
                'pengawas_1' => 'Pengawas Satu',
                'pengawas_2' => 'Pengawas Dua',
            ],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_kop_kartu', ['id_set_kartu' => 1, 'header_4' => 'KARTU PESERTA UJIAN']);
        $this->assertDatabaseHas('cbt_kop_berita', ['id_kop' => 1, 'header_4' => 'BERITA ACARA UJIAN']);
        $this->assertDatabaseHas('cbt_kop_absensi', ['id_kop' => 1, 'proktor' => 'Proktor Uji']);

        $this->sim('post', '/setting/kop', [
            'kartu' => ['header_1' => '  PEMERINTAH PROVINSI JAWA TIMUR  '],
        ])->assertSessionHas('success');

        $this->assertSame('PEMERINTAH PROVINSI JAWA TIMUR', DB::table('cbt_kop_kartu')->where('id_set_kartu', 1)->value('header_1'));
        $this->assertSame('KARTU PESERTA UJIAN', DB::table('cbt_kop_kartu')->where('id_set_kartu', 1)->value('header_4'));

        $this->actingAs($this->admin)
            ->get('/setting/kop')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Kop')
                ->where('absensi.pengawas_2', 'Pengawas Dua'));

        $this->actingAs($this->admin)
            ->post('/setting/kop', ['berita' => ['header_1' => str_repeat('A', 101)]])
            ->assertSessionHasErrors('berita.header_1');
    }

    public function test_setting_running_text_crud(): void
    {
        $this->sim('post', '/setting/running-text', ['text' => 'Selamat datang di CBT'])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post('/setting/running-text', ['text' => '   '])
            ->assertSessionHasErrors('text');

        $this->assertDatabaseCount('running_text', 1);

        $text = DB::table('running_text')->where('text', 'Selamat datang di CBT')->first();
        $this->assertNotNull($text);

        $this->sim('put', '/setting/running-text/'.$text->id_text, ['text' => 'Ujian dimulai pukul 07.30'])->assertSessionHas('success');
        $this->assertDatabaseHas('running_text', ['id_text' => $text->id_text, 'text' => 'Ujian dimulai pukul 07.30']);

        $this->actingAs($this->admin)
            ->get('/setting/running-text')
            ->assertInertia(fn (Assert $page) => $page->component('Setting/RunningText')->has('texts', 1));

        $this->actingAs($this->admin)
            ->put('/setting/running-text/'.$text->id_text, ['text' => str_repeat('B', 256)])
            ->assertSessionHasErrors('text');

        $this->sim('delete', '/setting/running-text/'.$text->id_text)->assertSessionHas('success');
        $this->assertDatabaseCount('running_text', 0);
    }

    public function test_setting_api_configuration_and_token_revocation(): void
    {
        $tokenUser = $this->user('tokenapi');

        $tokenId = DB::table('api_token')->insertGetId([
            'id_user' => $tokenUser->id,
            'address' => '127.0.0.1',
            'agent' => 'test-agent',
            'device' => 'Web',
            'token' => 'rahasia-token-uji',
        ]);

        $this->sim('post', '/setting/api', [
            'auto_sync' => true,
            'edit_profile_siswa' => false,
            'edit_profile_guru' => true,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('api_setting', ['auto_sync' => 1, 'edit_profile_siswa' => 0, 'edit_profile_guru' => 1]);

        $this->actingAs($this->admin)
            ->post('/setting/api', ['auto_sync' => 'ya', 'edit_profile_siswa' => true, 'edit_profile_guru' => true])
            ->assertSessionHasErrors('auto_sync');

        $this->actingAs($this->admin)
            ->get('/setting/api')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Api')
                ->where('setting.auto_sync', true)
                ->has('tokens', 1)
                ->where('tokens.0.id_api', $tokenId));

        $this->sim('delete', '/setting/api/token/'.$tokenId)->assertSessionHas('success');
        $this->assertDatabaseCount('api_token', 0);

        $this->actingAs($this->admin)->delete('/setting/api/token/999999')->assertNotFound();
    }

    public function test_setting_database_page_guards_truncate_and_backups(): void
    {
        $this->actingAs($this->admin)
            ->get('/setting/database')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Database')
                ->has('tables')
                ->has('migrations')
                ->has('totals'));

        DB::table('log')->insert([
            'log_time' => now(),
            'id_user' => $this->admin->id,
            'id_group' => 1,
            'name_group' => 'admin',
            'log_type' => 1,
            'log_desc' => 'uji',
            'address' => '127.0.0.1',
            'agent' => 'test',
            'device' => 'Web',
        ]);

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'users', 'confirm' => 'users'])
            ->assertSessionHas('error');

        $this->assertGreaterThan(0, DB::table('users')->count());

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'log', 'confirm' => 'salah'])
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('log')->count());

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => 'tabel_tidak_ada', 'confirm' => 'tabel_tidak_ada'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post('/setting/database/truncate', ['table' => '../etc', 'confirm' => '../etc'])
            ->assertSessionHas('error');

        $this->sim('post', '/setting/database/truncate', ['table' => 'log', 'confirm' => 'log'])->assertSessionHas('success');

        // Tabel log dikosongkan, lalu aksi pengosongan itu sendiri dicatat sebagai audit.
        $this->assertSame(1, DB::table('log')->count());
        $this->assertStringStartsWith('database.truncate', (string) DB::table('log')->value('log_desc'));

        $namaBackup = '9999-12-31-23-59-59';
        $backupDir = storage_path('app/backups/'.$namaBackup);
        File::ensureDirectoryExists($backupDir);
        File::put($backupDir.'/db-dump.sql', 'dump');

        $this->actingAs($this->admin)
            ->get('/setting/database')
            ->assertInertia(fn (Assert $page) => $page->where('backups.0.name', $namaBackup));

        $this->sim('delete', '/setting/database/backup/'.$namaBackup)->assertSessionHas('success');
        $this->assertDirectoryDoesNotExist($backupDir);

        $this->actingAs($this->admin)->delete('/setting/database/backup/tidak-ada')->assertSessionHas('error');
    }

    public function test_pengumuman_menus_render_for_each_role(): void
    {
        $this->seedPengumuman();

        $this->actingAs($this->admin)
            ->get('/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Pengumuman/Index')->has('targets'));

        $this->actingAs($this->guruUser)->get('/pengumuman')->assertOk();

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Pengumuman/Siswa')->where('siswa.nama', 'Siswa Uji'));

        $this->actingAs($this->siswaUser)->get('/pengumuman')->assertForbidden();
        $this->actingAs($this->guruUser)->get('/siswa/pengumuman')->assertForbidden();
    }

    public function test_pengumuman_targeting_and_validation(): void
    {
        $this->seedPengumuman();

        $this->sim('post', '/pengumuman', [
            'kepada' => ['semua_guru', 'kelas:'.$this->kelas->id_kelas],
            'text' => 'Rapat guru dan siswa kelas X IPA 1 besok.',
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('post', 1);

        $this->actingAs($this->admin)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.targets', ['Semua Guru', 'X IPA 1'])
                ->where('posts.0.mine', true));

        $this->actingAs($this->guruUser)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1));

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1));

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => [], 'text' => ''])
            ->assertSessionHasErrors(['kepada', 'text']);

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => ['kelas:999999'], 'text' => 'Kelas hilang'])
            ->assertSessionHasErrors('kepada');

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => ['tidak_dikenal'], 'text' => 'Salah target'])
            ->assertSessionHasErrors('kepada.0');

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => ['semua_siswa'], 'text' => str_repeat('x', 10001)])
            ->assertSessionHasErrors('text');

        $this->sim('post', '/pengumuman', [
            'kepada' => ['semua_guru'],
            'text' => 'Hanya untuk guru.',
        ])->assertSessionHas('success');

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1));

        $this->actingAs($this->guruUser)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page->has('posts', 2));
    }

    public function test_pengumuman_discussion_and_permissions(): void
    {
        $this->seedPengumuman();

        $this->sim('post', '/pengumuman', [
            'kepada' => ['kelas:'.$this->kelas->id_kelas],
            'text' => 'Pengumuman untuk kelas X IPA 1.',
        ])->assertSessionHas('success');

        $postId = (int) DB::table('post')->value('id_post');

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => 'Baik, terima kasih.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_comments', ['id_post' => $postId, 'dari_group' => 3]);

        $commentId = (int) DB::table('post_comments')->value('id_comment');

        $this->actingAs($this->admin)
            ->post('/pengumuman/komentar/'.$commentId.'/balasan', ['text' => 'Sama-sama.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_reply', ['id_comment' => $commentId, 'dari_group' => 1]);

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.comments.0.text', 'Baik, terima kasih.')
                ->where('posts.0.comments.0.mine', true)
                ->where('posts.0.comments.0.replies.0.text', 'Sama-sama.'));

        $this->actingAs($this->guruLainUser)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => 'Guru lain ikut komentar.'])
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->delete('/pengumuman/komentar/'.$commentId)
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->delete('/pengumuman/'.$postId)
            ->assertForbidden();

        $this->sim('delete', '/pengumuman/komentar/'.$commentId)->assertSessionHas('success');
        $this->assertDatabaseCount('post_comments', 0);
        $this->assertDatabaseCount('post_reply', 0);

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => 'Komentar lagi.'])
            ->assertSessionHas('success');

        $this->sim('delete', '/pengumuman/'.$postId)->assertSessionHas('success');
        $this->assertDatabaseCount('post', 0);
        $this->assertDatabaseCount('post_comments', 0);
    }

    public function test_pengumuman_survives_bad_payloads_and_identity_guards(): void
    {
        $this->seedPengumuman();

        $this->sim('post', '/pengumuman', [
            'kepada' => ['semua_siswa'],
            'text' => 'Pengumuman uji.',
        ])->assertSessionHas('success');

        $postId = (int) DB::table('post')->value('id_post');

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => 'semua_siswa', 'text' => 'Bukan array'])
            ->assertSessionHasErrors('kepada');

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => [['semua_siswa']], 'text' => 'Array bersarang'])
            ->assertSessionHasErrors('kepada.0');

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => ['kelas:0'], 'text' => 'Kelas nol'])
            ->assertSessionHasErrors('kepada');

        $this->actingAs($this->admin)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('text');

        $this->actingAs($this->admin)
            ->post('/pengumuman/komentar/999999/balasan', ['text' => 'Balasan liar'])
            ->assertNotFound();

        $this->actingAs($this->admin)->delete('/pengumuman/999999')->assertNotFound();

        $guruTanpaData = $this->user('gurutnpengumuman');
        $guruTanpaData->groups()->attach(Group::where('name', 'guru')->value('id'));

        $this->actingAs($guruTanpaData)->get('/pengumuman')->assertForbidden();

        $siswaTanpaData = $this->user('siswatnpengumuman');
        $siswaTanpaData->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $this->actingAs($siswaTanpaData)->get('/siswa/pengumuman')->assertForbidden();

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => 'Komentar siswa.'])
            ->assertSessionHas('success');

        $commentId = (int) DB::table('post_comments')->value('id_comment');

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/komentar/'.$commentId.'/balasan', ['text' => 'Balasan siswa.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_reply', ['id_comment' => $commentId, 'dari_group' => 3]);

        $this->actingAs($this->siswaUser)
            ->delete('/pengumuman/komentar/'.$commentId)
            ->assertSessionHas('success');

        $this->assertDatabaseCount('post_comments', 0);
        $this->assertDatabaseCount('post_reply', 0);
    }

    public function test_pengumuman_menus_survive_without_active_period(): void
    {
        $this->seedPengumuman();

        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        $this->actingAs($this->admin)
            ->get('/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('siswa.kelas', null));

        $this->actingAs($this->admin)
            ->post('/pengumuman', ['kepada' => ['kelas:'.$this->kelas->id_kelas], 'text' => 'Tanpa periode'])
            ->assertSessionHas('success');

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    }

    private function seedPengumuman(): void
    {
        DB::table('master_guru')->insert([
            'nip' => '198501012010011061',
            'nama_guru' => 'Guru Pengumuman',
            'username' => 'gurupengumuman',
            'id_user' => $this->guruUser->id,
        ]);

        $this->guruLainUser = $this->user('gurupengumumanlain');
        $this->guruLainUser->groups()->attach(Group::where('name', 'guru')->value('id'));

        DB::table('master_guru')->insert([
            'nip' => '198501012010011062',
            'nama_guru' => 'Guru Pengumuman Lain',
            'username' => 'gurupengumumanlain',
            'id_user' => $this->guruLainUser->id,
        ]);

        $siswaGroup = Group::firstOrCreate(['name' => 'siswa'], ['description' => 'Siswa']);

        $this->siswaUser = $this->user($this->siswa->username);
        $this->siswaUser->groups()->sync([$siswaGroup->id]);
    }

    public function test_pengguna_menus_render_for_each_role(): void
    {
        $this->seedPengumuman();

        foreach (['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $role => $label) {
            $this->actingAs($this->admin)
                ->get('/users/'.$role)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Users/Index')
                    ->where('role', $role)
                    ->where('roleLabel', $label)
                    ->has('users.data'));
        }

        $this->actingAs($this->admin)
            ->get('/users/guru?search=zzz-tidak-ada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 0));

        $this->actingAs($this->admin)->get('/users/guru?search=guru')
            ->assertInertia(fn (Assert $page) => $page->where('filters.search', 'guru'));

        $this->actingAs($this->admin)->get('/users/kepala-sekolah')->assertNotFound();

        foreach ([$this->guruUser, $this->siswaUser] as $user) {
            $this->actingAs($user)->get('/users/admin')->assertForbidden();
        }
    }

    public function test_pengguna_account_crud_and_status(): void
    {
        $this->seedPengumuman();

        $guruGroupId = (int) Group::where('name', 'guru')->value('id');

        $this->sim('post', '/users/guru', [
            'username' => 'gurubaru',
            'nama' => 'Guru Baru',
            'email' => 'gurubaru@example.test',
            'password' => 'rahasia1',
            'active' => 1,
        ])->assertSessionHas('success');

        $created = User::where('username', 'gurubaru')->firstOrFail();

        $this->assertTrue(Hash::check('rahasia1', $created->password), 'Password akun baru harus disimpan ter-hash.');
        $this->assertSame([$guruGroupId], $created->groups()->pluck('group_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(1, (int) $created->active);
        $this->assertTrue((bool) $created->must_change_password);

        Auth::logout();
        $this->post('/login', ['identity' => 'gurubaru', 'password' => 'rahasia1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($created->fresh());
        Auth::logout();

        $this->actingAs($this->admin)->post('/users/guru', [
            'username' => 'gurubaru',
            'nama' => 'Duplikat',
            'password' => 'rahasia1',
        ])->assertSessionHasErrors('username');

        $this->actingAs($this->admin)->post('/users/guru', [
            'username' => 'gurulain',
            'nama' => 'Password Pendek',
            'password' => '123',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->admin)->post('/users/guru', [
            'username' => 'gurulain',
            'nama' => 'Email Duplikat',
            'email' => 'gurubaru@example.test',
            'password' => 'rahasia1',
        ])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)->put('/users/admin/'.$created->id, [
            'username' => 'gurubaru',
            'nama' => 'Guru Baru',
            'active' => 1,
        ])->assertNotFound();

        $this->sim('put', '/users/guru/'.$created->id, [
            'username' => 'gurubaru2',
            'nama' => 'Guru Baru Dua',
            'email' => 'gurubaru2@example.test',
            'password' => 'rahasia2',
            'active' => 1,
        ])->assertSessionHas('success');

        $created->refresh();
        $this->assertSame('gurubaru2', $created->username);
        $this->assertSame('Guru Baru Dua', $created->first_name);
        $this->assertTrue(Hash::check('rahasia2', $created->password));
        $this->assertTrue((bool) $created->must_change_password);

        $this->sim('put', '/users/guru/'.$created->id, [
            'username' => 'gurubaru2',
            'nama' => 'Guru Baru Dua',
            'email' => 'gurubaru2@example.test',
            'active' => 0,
        ])->assertSessionHas('success');
        $this->assertSame(0, (int) $created->refresh()->active);

        Auth::logout();
        $this->post('/login', ['identity' => 'gurubaru2', 'password' => 'rahasia2'])
            ->assertSessionHasErrors('identity');
        $this->assertGuest();

        $this->sim('post', '/users/guru/status', [
            'ids' => [$created->id, $this->guruUser->id],
            'active' => 1,
        ])->assertSessionHas('success');
        $this->assertSame(1, (int) $created->refresh()->active);
        $this->assertSame(1, (int) $this->guruUser->refresh()->active);

        $this->sim('post', '/users/admin/status', [
            'ids' => [$this->admin->id],
            'active' => 0,
        ]);
        $this->assertSame(1, (int) $this->admin->refresh()->active, 'Admin tidak boleh menonaktifkan akunnya sendiri lewat aksi massal.');

        $this->sim('put', '/users/admin/'.$this->admin->id, [
            'username' => $this->admin->username,
            'nama' => $this->admin->first_name,
            'email' => $this->admin->email,
            'active' => 0,
        ])->assertSessionHas('error');
        $this->assertSame(1, (int) $this->admin->refresh()->active, 'Admin tidak boleh menonaktifkan akunnya sendiri lewat form.');

        $this->actingAs($this->admin)->post('/users/guru/status', ['ids' => [], 'active' => 1])
            ->assertSessionHasErrors('ids');

        $this->sim('delete', '/users/guru/'.$created->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
        $this->assertDatabaseMissing('users_groups', ['user_id' => $created->id]);
        $this->assertDatabaseHas('users', ['id' => $this->guruUser->id]);

        $this->actingAs($this->admin)->delete('/users/admin/'.$this->admin->id)->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $this->actingAs($this->admin)->delete('/users/guru/999999')->assertNotFound();
    }

    public function test_pengguna_account_links_to_existing_teacher_data(): void
    {
        Group::firstOrCreate(['name' => 'siswa'], ['description' => 'Siswa']);

        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011099',
            'nama_guru' => 'Guru Tanpa Akun',
            'username' => 'gurutanpauser',
        ]);

        $this->sim('post', '/users/guru', [
            'username' => 'gurutanpauser',
            'nama' => 'Guru Tanpa Akun',
            'password' => 'rahasia1',
            'active' => 1,
        ])->assertSessionHas('success');

        $user = User::where('username', 'gurutanpauser')->firstOrFail();

        $this->assertSame(
            $user->id,
            (int) DB::table('master_guru')->where('id_guru', $guruId)->value('id_user'),
            'Akun guru baru harus tertaut ke data guru dengan username yang sama.'
        );

        $user->forceFill(['must_change_password' => false])->save();
        $this->actingAs($user->fresh())->get('/pengumuman')->assertOk();

        $this->sim('delete', '/users/guru/'.$user->id)->assertSessionHas('success');

        $this->assertNull(
            DB::table('master_guru')->where('id_guru', $guruId)->value('id_user'),
            'Tautan akun pada data guru harus dilepas saat akun dihapus.'
        );

        DB::table('master_siswa')->insert([
            'nisn' => '2029000188',
            'nis' => '20290018',
            'nama' => 'Siswa Tanpa Akun',
            'username' => 'siswatanpaakun',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswatanpaakun',
        ]);

        $this->sim('post', '/users/siswa', [
            'username' => 'siswatanpaakun',
            'nama' => 'Siswa Tanpa Akun',
            'password' => 'rahasia1',
            'active' => 1,
        ])->assertSessionHas('success');

        $siswaAccount = User::where('username', 'siswatanpaakun')->firstOrFail();
        $siswaAccount->forceFill(['must_change_password' => false])->save();

        $this->actingAs($siswaAccount->fresh())->get('/siswa/pengumuman')->assertOk();
    }

    public function test_pengguna_rename_keeps_master_data_username(): void
    {
        $this->seedPengumuman();

        $this->actingAs($this->siswaUser)->get('/siswa/pengumuman')->assertOk();

        $this->sim('put', '/users/siswa/'.$this->siswaUser->id, [
            'username' => 'siswauji-baru',
            'nama' => 'Siswa Uji',
            'email' => $this->siswaUser->email,
            'active' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(
            'siswauji-baru',
            DB::table('master_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('username'),
            'Username data siswa harus mengikuti username akun.'
        );

        $this->actingAs($this->siswaUser->fresh())->get('/siswa/pengumuman')->assertOk();

        $this->sim('put', '/users/guru/'.$this->guruUser->id, [
            'username' => 'guru-baru',
            'nama' => 'guru',
            'email' => $this->guruUser->email,
            'active' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(
            'guru-baru',
            DB::table('master_guru')->where('id_user', $this->guruUser->id)->value('username'),
            'Username data guru harus mengikuti username akun.'
        );

        $this->siswaUser->refresh()->update(['username' => 'siswa-lain']);
        DB::table('master_siswa')->insert([
            'nisn' => '2029000199',
            'nis' => '20290019',
            'nama' => 'Siswa Kembar',
            'username' => 'siswa-kembar',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswa-kembar',
        ]);

        $this->actingAs($this->admin)->put('/users/siswa/'.$this->siswaUser->id, [
            'username' => 'siswa-kembar',
            'nama' => 'Siswa Uji',
            'active' => 1,
        ])->assertSessionHasErrors('username');
    }

    public function test_task_grading_journey(): void
    {
        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011011',
            'nama_guru' => 'Guru Tugas',
            'username' => 'gurutugas',
        ]);

        $this->sim('post', '/elearning/tugas', [
            'judul' => 'Tugas Dinilai',
            'id_mapel' => $this->mapel->id_mapel,
            'kelas' => [$this->kelas->id_kelas],
            'guru_id' => $guruId,
        ])->assertSessionHas('success');

        $tugasId = DB::table('kelas_materi')->where('judul_materi', 'Tugas Dinilai')->value('id_materi');

        DB::table('kelas_jadwal_materi')->insert([
            'id_kjm' => 'K'.$this->kelas->id_kelas.'-20261121-J1-T2',
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $tugasId,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => '2026-11-21',
            'jenis' => 2,
        ]);

        DB::table('log_materi')->insert([
            'id_log' => $this->siswa->id_siswa.'K'.$this->kelas->id_kelas.'-20261121-J1-T2',
            'log_time' => now(),
            'id_siswa' => $this->siswa->id_siswa,
            'jam_ke' => 1,
            'id_materi' => 'K'.$this->kelas->id_kelas.'-20261121-J1-T2',
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 2,
            'log_desc' => 'Mengumpulkan tugas',
            'address' => '127.0.0.1',
            'agent' => 'test',
            'device' => 'Web',
            'finish_time' => now(),
        ]);

        $this->sim('post', '/elearning/tugas/'.$tugasId.'/nilai', [
            'rows' => [[
                'id_siswa' => $this->siswa->id_siswa,
                'nilai' => 90,
                'catatan' => 'Bagus.',
            ]],
        ])->assertSessionHas('success');

        $this->assertSame('90', (string) DB::table('log_materi')
            ->where('id_siswa', $this->siswa->id_siswa)
            ->value('nilai'));
    }

    public function test_teacher_cannot_modify_other_teachers_material(): void
    {
        $otherGuruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011012',
            'nama_guru' => 'Guru Lain',
            'username' => 'gurulain',
        ]);

        $materiId = DB::table('kelas_materi')->insertGetId([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 1,
            'kode_materi' => 'MTR-1',
            'kode_mapel' => 'MTR',
            'id_guru' => $otherGuruId,
            'id_mapel' => $this->mapel->id_mapel,
            'judul_materi' => 'Materi Guru Lain',
            'isi_materi' => 'Isi',
            'youtube' => '',
            'link_file' => null,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'file' => serialize([]),
            'status' => 1,
            'created_on' => now(),
            'updated_on' => now(),
        ]);

        DB::table('master_guru')->insert([
            'nip' => '198501012010011013',
            'nama_guru' => 'Guru Sendiri',
            'username' => 'guru',
            'id_user' => $this->guruUser->id,
        ]);

        $this->actingAs($this->guruUser)
            ->put('/elearning/'.$materiId, [
                'judul' => 'Diubah',
                'id_mapel' => $this->mapel->id_mapel,
                'kelas' => [$this->kelas->id_kelas],
            ])
            ->assertForbidden();

        $this->actingAs($this->guruUser)->delete('/elearning/'.$materiId)->assertForbidden();

        $this->assertSame('Materi Guru Lain', DB::table('kelas_materi')->where('id_materi', $materiId)->value('judul_materi'));
    }

    public function test_student_can_submit_elearning_result(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011014',
            'nama_guru' => 'Guru Elearning',
            'username' => 'guruele',
        ]);

        $materiId = DB::table('kelas_materi')->insertGetId([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 1,
            'kode_materi' => 'MTR-2',
            'kode_mapel' => 'MTR',
            'id_guru' => $guruId,
            'id_mapel' => $this->mapel->id_mapel,
            'judul_materi' => 'Materi Siswa',
            'isi_materi' => 'Isi materi.',
            'youtube' => '',
            'link_file' => null,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'file' => serialize([]),
            'status' => 1,
            'tgl_mulai' => now()->subDay()->toDateString(),
            'tenggat' => now()->addWeek()->toDateString(),
            'created_on' => now(),
            'updated_on' => now(),
        ]);

        $idKjm = 'K'.$this->kelas->id_kelas.'-'.now()->format('Ymd').'-J1-T1';

        DB::table('kelas_jadwal_materi')->insert([
            'id_kjm' => $idKjm,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $materiId,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => now()->toDateString(),
            'jenis' => 1,
        ]);

        $this->actingAs($siswaUser)->get('/siswa/elearning/materi/'.$idKjm)->assertOk();

        $this->actingAs($siswaUser)
            ->post('/siswa/elearning/materi/'.$idKjm.'/selesai', ['text' => 'Sudah saya pelajari.'])
            ->assertSessionHas('success');

        $log = DB::table('log_materi')->where('id_siswa', $this->siswa->id_siswa)->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->finish_time);
        $this->assertSame('Sudah saya pelajari.', $log->text);
    }

    public function test_student_import_reports_invalid_rows(): void
    {
        $csv = "nisn,nis,nama,username,email\n"
            ."2029000200,2029200,Siswa Impor,siswaimport,salah-email\n"
            ."2029000201,2029201,Siswa Impor Dua,siswaimportdua,ok@example.test\n";

        $this->sim('post', '/master/siswa/import', [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('master_siswa', ['username' => 'siswaimportdua']);
        $this->assertDatabaseMissing('master_siswa', ['username' => 'siswaimport']);
    }

    public function test_bulk_delete_schedules(): void
    {
        $bankId = $this->makeBank();
        $satu = $this->makeJadwal($bankId);

        $bankDua = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-SIM-2',
            'bank_nama' => 'Bank Simulasi 2',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $dua = $this->makeJadwal($bankDua);

        $this->sim('post', '/cbt/jadwal/hapus-terpilih', ['ids' => [$satu, $dua]])->assertSessionHas('success');

        $this->assertDatabaseCount('cbt_jadwal', 0);
    }

    public function test_malformed_input_is_validated_not_crashing(): void
    {
        $cases = [
            ['post', '/cbt/jadwal', [
                'id_bank' => $this->makeBank(),
                'id_jenis' => $this->jenisId,
                'tgl_mulai' => 'bukan-tanggal',
                'tgl_selesai' => 'juga-bukan-tanggal',
                'durasi_ujian' => 90,
            ]],
            ['post', '/master/kelas', [
                'nama_kelas' => 'X Salah',
                'level_id' => 999,
            ]],
            ['post', '/cbt/bank-soal', [
                'bank_kode' => 'SALAH',
                'bank_nama' => 'Bank Salah',
                'bank_mapel_id' => 999,
                'bank_jenis_id' => 999,
                'bank_level' => 999,
                'opsi' => 4,
            ]],
            ['post', '/rapor/nilai/harian/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
                'rows' => [['id_siswa' => $this->siswa->id_siswa, 'p1' => 999]],
            ]],
            ['post', '/profil/password', [
                'current_password' => 'salah',
                'password' => 'rahasia456',
                'password_confirmation' => 'rahasia456',
            ]],
        ];

        foreach ($cases as [$method, $uri, $payload]) {
            $response = $this->actingAs($this->admin)->{$method}($uri, $payload);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                "HTTP {$response->getStatusCode()} pada {$method} {$uri}: ".($response->exception?->getMessage() ?? '')
            );
        }
    }

    public function test_soal_excel_export_import_roundtrip(): void
    {
        $sumber = $this->makeBank();

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 1,
            'soal' => '<p>Ibu kota Indonesia?</p>',
            'opsi_a' => 'Bandung',
            'opsi_b' => 'Jakarta',
            'opsi_c' => 'Surabaya',
            'opsi_d' => 'Medan',
            'jawaban' => 'b',
        ])->assertSessionHas('success');

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 2,
            'soal' => '<p>Pilih bilangan genap.</p>',
            'opsi_a' => '2',
            'opsi_b' => '3',
            'opsi_c' => '4',
            'opsi_d' => '5',
            'jawaban' => ['a', 'c'],
        ])->assertSessionHas('success');

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 5,
            'soal' => '<p>Jelaskan proses fotosintesis.</p>',
        ])->assertSessionHas('success');

        $xlsx = $this->actingAs($this->admin)
            ->get('/cbt/bank-soal/'.$sumber.'/ekspor')
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('PK', $xlsx);

        $tujuan = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-TUJUAN',
            'bank_nama' => 'Bank Tujuan',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$tujuan.'/impor', [
            'file' => $this->uploadedBinary('soal.xlsx', $xlsx),
        ])->assertSessionHas('success');

        $this->assertSame(3, DB::table('cbt_soal')->where('bank_id', $tujuan)->count(), 'errors: '.json_encode(session('import_errors')));
        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.soal.impor: Impor 3 soal ke bank #'.$tujuan.' BANK-TUJUAN',
        ]);
        $this->assertDatabaseHas('cbt_soal', ['bank_id' => $tujuan, 'jawaban' => 'B']);
        $this->assertDatabaseHas('cbt_soal', ['bank_id' => $tujuan, 'jenis' => 2]);
        $this->assertDatabaseHas('cbt_soal', ['bank_id' => $tujuan, 'jenis' => 5]);

        $template = $this->actingAs($this->admin)
            ->get('/cbt/bank-soal/'.$sumber.'/ekspor?template=1')
            ->assertOk()
            ->getContent();

        $this->sim('post', '/cbt/bank-soal/'.$tujuan.'/impor', [
            'file' => $this->uploadedBinary('template.xlsx', $template),
        ])->assertSessionHas('success');

        $this->assertSame(3, DB::table('cbt_soal')->where('bank_id', $tujuan)->count());
    }

    public function test_soal_word_export_import_roundtrip(): void
    {
        $sumber = $this->makeBank();

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 1,
            'soal' => '<p>Hasil dari 3 x 4 adalah ...</p>',
            'opsi_a' => '7',
            'opsi_b' => '12',
            'opsi_c' => '15',
            'opsi_d' => '9',
            'jawaban' => 'b',
        ])->assertSessionHas('success');

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 2,
            'soal' => '<p>Pilih bilangan prima.</p>',
            'opsi_a' => '2',
            'opsi_b' => '3',
            'opsi_c' => '4',
            'opsi_d' => '6',
            'jawaban' => ['a', 'b'],
        ])->assertSessionHas('success');

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 3,
            'soal' => '<p>Jodohkan ibu kota negara.</p>',
            'pairs' => [
                ['kiri' => 'Indonesia', 'kanan' => 'Jakarta'],
                ['kiri' => 'Malaysia', 'kanan' => 'Kuala Lumpur'],
            ],
        ])->assertSessionHas('success');

        $this->sim('post', '/cbt/bank-soal/'.$sumber.'/soal', [
            'jenis' => 5,
            'soal' => '<p>Uraikan penyebab banjir.</p>',
        ])->assertSessionHas('success');

        $docx = $this->actingAs($this->admin)
            ->get('/cbt/bank-soal/'.$sumber.'/ekspor?format=docx')
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('PK', $docx);

        $tujuan = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-WORD',
            'bank_nama' => 'Bank Word',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$tujuan.'/impor', [
            'file' => $this->uploadedBinary('soal.docx', $docx),
        ])->assertSessionHas('success');

        $this->assertSame(4, DB::table('cbt_soal')->where('bank_id', $tujuan)->count());

        foreach ([1, 2, 3, 5] as $jenis) {
            $this->assertSame(1, DB::table('cbt_soal')->where('bank_id', $tujuan)->where('jenis', $jenis)->count(), "Jenis {$jenis} tidak terbaca dari Word.");
        }

        $this->assertDatabaseHas('cbt_soal', ['bank_id' => $tujuan, 'jenis' => 1, 'jawaban' => 'B']);
        $this->assertSame(1, DB::table('cbt_soal')
            ->where('bank_id', $tujuan)
            ->where('jenis', 1)
            ->where('soal', 'like', '%3 x 4%')
            ->count());
    }

    public function test_soal_excel_from_teacher_with_friendly_headers(): void
    {
        $bankId = $this->makeBank();

        $sheet = $this->excelWithRows([
            ['BANK SOAL MATEMATIKA KELAS X'],
            [],
            ['No', 'Jenis Soal', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Kunci Jawaban', 'Pembahasan', 'Tingkat Kesulitan'],
            [1, 'Pilihan Ganda', 'Ibu kota Indonesia?', 'Bandung', 'Jakarta', 'Surabaya', 'Medan', 'B', 'Jakarta ibu kota negara.', 3],
            [2, 'Esai', 'Jelaskan proses fotosintesis.', '', '', '', '', '', '', ''],
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/impor', [
            'file' => $this->uploadedBinary('buatan-guru.xlsx', $sheet),
        ])->assertSessionHas('success');

        $this->assertSame(2, DB::table('cbt_soal')->where('bank_id', $bankId)->count());
        $this->assertDatabaseHas('cbt_soal', [
            'bank_id' => $bankId,
            'jenis' => 1,
            'jawaban' => 'b',
            'kesulitan' => 3,
        ]);
        $this->assertDatabaseHas('cbt_soal', ['bank_id' => $bankId, 'jenis' => 5]);
        $this->assertSame(1, DB::table('cbt_soal')
            ->where('bank_id', $bankId)
            ->where('soal', 'like', '%Ibu kota Indonesia%')
            ->where('deskripsi', 'like', '%Jakarta ibu kota negara%')
            ->count());
    }

    public function test_soal_excel_accepts_alternative_answer_separators(): void
    {
        $bankId = $this->makeBank();

        $sheet = $this->excelWithRows([
            ['jenis', 'soal', 'KUNCI'],
            ['Jodohkan', 'Jodohkan negara dengan ibu kotanya.', 'Indonesia = Jakarta; Malaysia = Kuala Lumpur'],
        ]);

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/impor', [
            'file' => $this->uploadedBinary('jodohkan.xlsx', $sheet),
        ])->assertSessionHas('success');

        $jawaban = (string) DB::table('cbt_soal')->where('bank_id', $bankId)->value('jawaban');
        $decoded = json_decode($jawaban, true);

        $this->assertSame(3, (int) DB::table('cbt_soal')->where('bank_id', $bankId)->value('jenis'));
        $this->assertIsArray($decoded['pairs'] ?? null);
        $this->assertCount(2, $decoded['pairs']);
    }

    public function test_soal_excel_without_header_row_reports_error(): void
    {
        $bankId = $this->makeBank();

        $sheet = $this->excelWithRows([
            ['Catatan guru'],
            ['1. Soal tanpa judul kolom'],
        ]);

        $response = $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/impor', [
            'file' => $this->uploadedBinary('tanpa-header.xlsx', $sheet),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_errors');
        $this->assertSame(0, DB::table('cbt_soal')->where('bank_id', $bankId)->count());
    }

    public function test_invalid_soal_file_is_rejected_gracefully(): void
    {
        $bankId = $this->makeBank();

        $response = $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/impor', [
            'file' => $this->uploadedBinary('rusak.docx', 'bukan-docx'),
        ]);

        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertSame(0, DB::table('cbt_soal')->where('bank_id', $bankId)->count());
    }

    public function test_rapor_pdf_contains_student_data(): void
    {
        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 1,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
        ]);

        $this->sim('post', '/rapor/nilai/harian/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
            'rows' => [['id_siswa' => $this->siswa->id_siswa, 'p1' => 88, 'k1' => 84]],
        ])->assertSessionHas('success');

        DB::table('rapor_nilai_sikap')->insert([
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 1,
            'nilai' => serialize(['predikat' => 'A']),
            'deskripsi' => 'Sangat baik dalam ibadah dan akhlak.',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa.'/pdf')
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));

        $this->assertTrue($this->pdfContains($pdf, 'Siswa Uji'), 'Nama siswa tidak ditemukan di PDF rapor.');
        $this->assertTrue($this->pdfContains($pdf, 'Matematika'), 'Mata pelajaran tidak ditemukan di PDF rapor.');
        $this->assertTrue($this->pdfContains($pdf, 'Sangat Baik'), 'Predikat sikap harus tercetak sebagai label, bukan huruf mutu.');

        $leger = $this->actingAs($this->admin)
            ->get('/rapor/leger/'.$this->kelas->id_kelas.'/pdf')
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $leger);

        $this->assertTrue($this->pdfContains($leger, 'X IPA 1'), 'Nama kelas tidak ditemukan di PDF leger.');

        $dkn = $this->actingAs($this->admin)
            ->get('/rapor/dkn/'.$this->kelas->id_kelas.'/pdf')
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $dkn);
    }

    public function test_general_menu_pages_render_for_each_role(): void
    {
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        foreach (['/dashboard', '/profil'] as $uri) {
            $this->sim('get', $uri)->assertOk();
            $this->actingAs($this->guruUser)->get($uri)->assertOk();
            $this->actingAs($siswaUser)->get($uri)->assertOk();
        }

        $this->sim('get', '/pengumuman')->assertOk();
        $this->sim('get', '/search?q=siswa')->assertOk();
        $this->actingAs($this->guruUser)->get('/search?q=siswa')->assertOk();

        $this->actingAs($siswaUser)->get('/siswa/pengumuman')->assertOk();
        $this->actingAs($siswaUser)->get('/pengumuman')->assertForbidden();
    }

    public function test_all_umum_menu_pages_render_for_each_role(): void
    {
        DB::table('master_guru')->insert([
            'nip' => '198501012010011099',
            'nama_guru' => 'Guru Umum',
            'username' => 'guru',
            'id_user' => $this->guruUser->id,
        ]);

        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $adminMenu = ['/dashboard', '/pengumuman'];
        $guruMenu = ['/dashboard', '/pengumuman'];
        $siswaMenu = [
            '/dashboard',
            '/siswa/pengumuman',
            '/siswa/jadwal',
            '/siswa/elearning/materi',
            '/siswa/elearning/tugas',
            '/siswa/kehadiran',
            '/siswa/catatan',
            '/ujian',
            '/ujian/hasil',
        ];

        foreach ($adminMenu as $uri) {
            $this->sim('get', $uri)->assertOk();
        }

        foreach ($guruMenu as $uri) {
            $this->actingAs($this->guruUser)->get($uri)->assertOk();
        }

        foreach ($siswaMenu as $uri) {
            $response = $this->actingAs($siswaUser)->get($uri);

            $this->assertSame(200, $response->getStatusCode(), "{$uri} mengembalikan {$response->getStatusCode()}");
        }
    }

    public function test_elearning_week_parameter_is_safe(): void
    {
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $this->actingAs($siswaUser)->get('/siswa/elearning/materi?week=abc')->assertOk();
        $this->actingAs($siswaUser)->get('/siswa/elearning/tugas?week=9999-99-99')->assertOk();
        $this->actingAs($siswaUser)->get('/siswa/elearning/materi?week=2026-11-16')->assertOk();

        DB::table('master_guru')->insert([
            'nip' => '198501012010011088',
            'nama_guru' => 'Guru Elearning Umum',
            'username' => 'guru',
            'id_user' => $this->guruUser->id,
        ]);

        $this->actingAs($this->guruUser)->get('/elearning/jadwal?week=abc')->assertOk();
    }

    public function test_student_cannot_resubmit_task_graded_with_zero(): void
    {
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $materiId = DB::table('kelas_materi')->insertGetId([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 2,
            'kode_materi' => 'TGS-0',
            'kode_mapel' => 'MTR',
            'id_mapel' => $this->mapel->id_mapel,
            'judul_materi' => 'Tugas Nilai Nol',
            'isi_materi' => 'Kerjakan.',
            'youtube' => '',
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'file' => serialize([]),
            'status' => 1,
            'tgl_mulai' => now()->subDay()->toDateString(),
            'tenggat' => now()->addWeek()->toDateString(),
            'created_on' => now(),
            'updated_on' => now(),
        ]);

        $idKjm = 'K'.$this->kelas->id_kelas.'-'.now()->format('Ymd').'-J1-T2';

        DB::table('kelas_jadwal_materi')->insert([
            'id_kjm' => $idKjm,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $materiId,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => now()->toDateString(),
            'jenis' => 2,
        ]);

        DB::table('log_materi')->insert([
            'id_log' => $this->siswa->id_siswa.$idKjm,
            'log_time' => now(),
            'id_siswa' => $this->siswa->id_siswa,
            'jam_ke' => 1,
            'id_materi' => $idKjm,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 2,
            'log_desc' => 'Menyelesaikan tugas',
            'text' => 'Jawaban lama',
            'file' => serialize([]),
            'nilai' => '0',
            'address' => '',
            'agent' => '',
            'device' => 'Web',
            'finish_time' => now(),
        ]);

        $this->actingAs($siswaUser)
            ->post('/siswa/elearning/tugas/'.$idKjm.'/selesai', ['text' => 'Jawaban baru'])
            ->assertSessionHas('error');

        $this->assertSame('Jawaban lama', DB::table('log_materi')->where('id_log', $this->siswa->id_siswa.$idKjm)->value('text'));
    }

    public function test_announcement_targeting_and_student_comment(): void
    {
        $this->sim('post', '/pengumuman', [
            'kepada' => ['kelas:'.$this->kelas->id_kelas],
            'text' => 'Ujian praktik dilaksanakan Senin.',
        ])->assertSessionHas('success');

        $postId = (int) DB::table('post')->value('id_post');

        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $lain = Siswa::create([
            'nisn' => '2029000199',
            'nis' => '2029099',
            'nama' => 'Siswa Lain',
            'username' => 'siswalain',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswalain',
        ]);

        $siswaLain = $this->user('siswalain');
        $siswaLain->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $this->actingAs($siswaUser)
            ->get('/siswa/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1));

        $this->actingAs($siswaLain)
            ->get('/siswa/pengumuman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));

        $this->actingAs($siswaUser)
            ->post('/pengumuman/'.$postId.'/komentar', ['text' => 'Siap, terima kasih.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_comments', ['id_post' => $postId, 'dari' => $this->siswa->id_siswa]);

        $this->sim('post', '/pengumuman/'.$postId.'/komentar', ['text' => 'Catatan dari admin.'])
            ->assertSessionHas('success');

        $this->assertSame(2, DB::table('post_comments')->where('id_post', $postId)->count());
    }

    public function test_general_pages_survive_without_active_period(): void
    {
        DB::table('master_tp')->update(['active' => 0]);
        DB::table('master_smt')->update(['active' => 0]);

        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach(Group::where('name', 'siswa')->value('id'));

        $this->sim('get', '/dashboard')->assertOk();
        $this->actingAs($this->guruUser)->get('/dashboard')->assertOk();
        $this->actingAs($siswaUser)->get('/dashboard')->assertOk();
        $this->sim('get', '/pengumuman')->assertOk();
        $this->actingAs($siswaUser)->get('/siswa/pengumuman')->assertOk();
    }

    public function test_school_logo_is_shared_with_login_and_sidebar(): void
    {
        Storage::fake('public');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('logos/kiri.png', $png);
        Storage::disk('public')->put('logos/kanan.png', $png);

        $setting = Setting::first() ?: new Setting;
        $setting->fill([
            'sekolah' => 'SMA Negeri 1 Contoh',
            'logo_kiri' => 'logos/kiri.png',
            'logo_kanan' => 'logos/kanan.png',
        ])->save();

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('school.logo', '/storage/logos/kiri.png')
                ->where('school.logo_kanan', '/storage/logos/kanan.png'));

        $this->sim('get', '/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('school.logo', '/storage/logos/kiri.png'));
    }

    public function test_school_logo_url_is_host_agnostic(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'), 'URL disk publik harus relatif agar tidak bergantung pada APP_URL.');

        Storage::fake('public');
        Storage::disk('public')->put('logos/relatif.png', 'isi');

        $this->assertSame('/storage/logos/relatif.png', SchoolLogo::url('logos/relatif.png'));
    }

    public function test_school_logo_is_null_when_not_uploaded(): void
    {
        Storage::fake('public');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('school.logo', null)
                ->where('school.logo_kanan', null));
    }

    public function test_printed_documents_use_uploaded_school_logos(): void
    {
        Storage::fake('public');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('logos/kiri.png', $png);
        Storage::disk('public')->put('logos/kanan.png', $png);

        $setting = Setting::first() ?: new Setting;
        $setting->fill([
            'sekolah' => 'SMA Negeri 1 Contoh',
            'logo_kiri' => 'logos/kiri.png',
            'logo_kanan' => 'logos/kanan.png',
        ])->save();

        $this->sim('post', '/rapor/pengaturan', [
            'kkm_tunggal' => 1,
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get('/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa)
            ->assertInertia(fn ($page) => $page
                ->where('logoKiri', '/storage/logos/kiri.png')
                ->where('logoKanan', '/storage/logos/kanan.png'));

        $documents = [
            'rapor' => '/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa.'/pdf',
            'buku induk' => '/master/buku-induk/'.$this->siswa->id_siswa.'/pdf',
        ];

        foreach ($documents as $label => $uri) {
            $pdf = (string) $this->actingAs($this->admin)->get($uri)->assertOk()->getContent();

            $this->assertStringStartsWith('%PDF-', $pdf, "PDF {$label} tidak valid.");
            $this->assertStringContainsString('/Image', $pdf, "Logo tidak disematkan pada PDF {$label}.");
        }

    }

    public function test_rapor_nilai_import_accepts_teacher_style_file(): void
    {
        $nisn = (string) DB::table('master_siswa')->where('id_siswa', $this->siswa->id_siswa)->value('nisn');

        $sheet = $this->excelWithRows([
            ['DAFTAR NILAI HARIAN KELAS X'],
            [],
            ['NISN', 'Nama Siswa', 'P 1', 'P 2', 'K 1'],
            [$nisn, 'Siswa Simulasi', 80, 90, 70],
        ]);

        $this->sim('post', '/rapor/nilai/harian/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas.'/import', [
            'file' => $this->uploadedBinary('nilai-guru.xlsx', $sheet),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('rapor_nilai_harian', [
            'id_siswa' => $this->siswa->id_siswa,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'p1' => 80,
            'p2' => 90,
            'k1' => 70,
        ]);
    }

    public function test_complex_question_scoring_penalises_wrong_options(): void
    {
        $bankId = $this->makeBank();
        DB::table('cbt_bank_soal')->where('id_bank', $bankId)->update([
            'tampil_kompleks' => 1,
            'bobot_kompleks' => 1,
        ]);

        $jadwal = Jadwal::find($this->makeJadwal($bankId));

        $id = $this->siswa->id_siswa.$jadwal->id_jadwal.'K1';

        $insert = fn (string $jawaban) => DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => $id,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $jadwal->id_jadwal,
            'jenis_soal' => 2,
            'no_soal_alias' => 1,
            'point_soal' => 100,
            'jawaban_benar' => json_encode(['A', 'B']),
            'jawaban_siswa' => $jawaban,
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 0,
        ]);

        $insert(json_encode(['A', 'B']));
        $this->assertSame(1.0, CbtScoring::hitung($jadwal, $this->siswa->id_siswa)['kompleks_nilai']);

        DB::table('cbt_soal_siswa')->where('id_soal_siswa', $id)->update(['jawaban_siswa' => json_encode(['A', 'B', 'C'])]);
        $this->assertSame(0.5, CbtScoring::hitung($jadwal, $this->siswa->id_siswa)['kompleks_nilai']);

        DB::table('cbt_soal_siswa')->where('id_soal_siswa', $id)->update(['jawaban_siswa' => json_encode(['A', 'B', 'B', 'B'])]);
        $this->assertSame(1.0, CbtScoring::hitung($jadwal, $this->siswa->id_siswa)['kompleks_nilai']);
    }

    public function test_exam_token_rules(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $bankId = $this->makeBank();

        DB::table('cbt_soal')->insert([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>Soal token</p>',
            'deskripsi' => '',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'jawaban' => 'A',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        DB::table('cbt_bank_soal')->where('id_bank', $bankId)->update(['tampil_pg' => 1, 'bobot_pg' => 1]);

        $jadwalId = DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 60,
            'status' => 1,
            'token' => 1,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 0,
            'jam_ke' => 0,
        ]);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai', ['token' => 'SALAH'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('cbt_durasi_siswa', ['id_jadwal' => $jadwalId]);

        $this->sim('post', '/cbt/token', ['auto' => true]);
        $token = (string) DB::table('cbt_token')->orderBy('id_token')->value('token');
        $this->assertNotSame('', $token);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai', ['token' => 'SALAH'])
            ->assertSessionHas('error');

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai', ['token' => $token])
            ->assertRedirect('/ujian/'.$jadwalId.'/kerjakan');

        $this->assertDatabaseHas('cbt_durasi_siswa', ['id_jadwal' => $jadwalId, 'status' => 1]);
    }

    public function test_exam_with_incomplete_question_composition_is_rejected(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $bankId = $this->makeBank();
        DB::table('cbt_bank_soal')->where('id_bank', $bankId)->update(['tampil_pg' => 2]);

        $jadwalId = $this->makeJadwal($bankId);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai')
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('cbt_durasi_siswa', ['id_jadwal' => $jadwalId]);
    }

    public function test_teacher_cannot_open_other_teachers_bank_or_grades(): void
    {
        $otherGuruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011020',
            'nama_guru' => 'Guru Pemilik',
            'username' => 'gurupemilik',
        ]);

        DB::table('master_guru')->insert([
            'nip' => '198501012010011021',
            'nama_guru' => 'Guru Pendatang',
            'username' => 'guru',
            'id_user' => $this->guruUser->id,
        ]);

        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-PEMILIK',
            'bank_nama' => 'Bank Pemilik',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_guru_id' => $otherGuruId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->actingAs($this->guruUser)->get('/cbt/bank-soal/'.$bankId)->assertForbidden();
        $this->actingAs($this->guruUser)->get('/cbt/bank-soal/'.$bankId.'/ekspor')->assertForbidden();

        $this->actingAs($this->guruUser)
            ->post('/cbt/bank-soal/'.$bankId.'/soal', [
                'jenis' => 1,
                'soal' => '<p>Tidak boleh</p>',
                'opsi_a' => '1',
                'opsi_b' => '2',
                'opsi_c' => '3',
                'opsi_d' => '4',
                'jawaban' => 'a',
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('cbt_soal')->where('bank_id', $bankId)->count());

        $this->actingAs($this->guruUser)
            ->post('/rapor/nilai/harian/'.$this->mapel->id_mapel.'/'.$this->kelas->id_kelas, [
                'rows' => [['id_siswa' => $this->siswa->id_siswa, 'p1' => 90]],
            ])
            ->assertForbidden();
    }

    public function test_exam_retake_requires_admin_reset(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $bankId = $this->makeBank();
        DB::table('cbt_soal')->insert([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>Soal ulang</p>',
            'deskripsi' => '',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'jawaban' => 'A',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);
        DB::table('cbt_bank_soal')->where('id_bank', $bankId)->update(['tampil_pg' => 1, 'bobot_pg' => 1]);

        $jadwalId = $this->makeJadwal($bankId);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai');
        $this->actingAs($siswaUser)->postJson('/ujian/'.$jadwalId.'/selesai')->assertOk();

        $this->assertSame(2, (int) DB::table('cbt_durasi_siswa')->where('id_jadwal', $jadwalId)->value('status'));

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai')->assertSessionHas('error');

        $this->sim('post', '/cbt/status/'.$jadwalId.'/repeat', ['siswa' => [$this->siswa->id_siswa]])
            ->assertSessionHas('success');

        $this->assertSame(0, (int) DB::table('cbt_durasi_siswa')->where('id_jadwal', $jadwalId)->value('status'));

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai')
            ->assertRedirect('/ujian/'.$jadwalId.'/kerjakan');
    }

    public function test_exam_is_finalised_when_time_runs_out(): void
    {
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $siswaUser = $this->user('siswauji');
        $siswaUser->groups()->attach($siswaGroup->id);

        $bankId = $this->makeBank();
        DB::table('cbt_soal')->insert([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => 1,
            'soal' => '<p>Soal waktu</p>',
            'deskripsi' => '',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'jawaban' => 'A',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);
        DB::table('cbt_bank_soal')->where('id_bank', $bankId)->update(['tampil_pg' => 1, 'bobot_pg' => 1]);

        $jadwalId = $this->makeJadwal($bankId);

        $this->actingAs($siswaUser)->post('/ujian/'.$jadwalId.'/mulai');

        DB::table('cbt_durasi_siswa')
            ->where('id_jadwal', $jadwalId)
            ->update(['mulai' => now()->subHours(3)->format('Y-m-d H:i:s')]);

        $this->actingAs($siswaUser)->get('/ujian/'.$jadwalId.'/kerjakan')
            ->assertRedirect('/ujian/hasil');

        $this->assertSame(2, (int) DB::table('cbt_durasi_siswa')->where('id_jadwal', $jadwalId)->value('status'));
        $this->assertDatabaseHas('cbt_nilai', ['id_siswa' => $this->siswa->id_siswa]);
    }

    public function test_bank_copy_duplicates_questions(): void
    {
        $bankId = $this->makeBank();

        foreach ([1, 2] as $nomor) {
            DB::table('cbt_soal')->insert([
                'bank_id' => $bankId,
                'mapel_id' => $this->mapel->id_mapel,
                'jenis' => 1,
                'nomor_soal' => $nomor,
                'soal' => '<p>Soal nomor '.$nomor.'</p>',
                'deskripsi' => '',
                'opsi_a' => '1',
                'opsi_b' => '2',
                'opsi_c' => '3',
                'opsi_d' => '4',
                'jawaban' => 'A',
                'tampilkan' => 1,
                'kesulitan' => 1,
            ]);
        }

        $this->sim('post', '/cbt/bank-soal/'.$bankId.'/copy')->assertSessionHas('success');

        $salinan = DB::table('cbt_bank_soal')->where('id_bank', '!=', $bankId)->value('id_bank');

        $this->assertNotNull($salinan);
        $this->assertSame(2, DB::table('cbt_soal')->where('bank_id', $salinan)->count());
    }

    public function test_deleting_used_student_and_class_archives_them(): void
    {
        DB::table('cbt_nilai')->insert([
            'id_nilai' => $this->siswa->id_siswa * 100000 + 1,
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => 1,
            'dikoreksi' => 1,
            'time_create' => now(),
        ]);

        $this->sim('delete', '/master/siswa/'.$this->siswa->id_siswa)
            ->assertSessionHas('success');

        $this->assertSoftDeleted('master_siswa', ['id_siswa' => $this->siswa->id_siswa]);

        $this->sim('delete', '/master/kelas/'.$this->kelas->id_kelas)
            ->assertSessionHas('success');

        $this->assertSoftDeleted('master_kelas', ['id_kelas' => $this->kelas->id_kelas]);
    }

    public function test_search_handles_wildcard_and_empty_query(): void
    {
        $this->actingAs($this->admin)->get('/search?q=%25_%25')->assertOk();
        $this->actingAs($this->admin)->get('/search?q=')->assertOk();
        $this->actingAs($this->admin)->get('/search?q='.urlencode('Matematika'))->assertOk();
    }

    public function test_unknown_resources_return_404_not_error(): void
    {
        $uris = [
            '/cbt/bank-soal/999999',
            '/master/siswa/999999/edit',
            '/master/kelas/999999/anggota',
            '/rapor/cetak/999999/999999/pdf',
            '/rapor/leger/999999/pdf',
            '/rapor/dkn/999999/pdf',
            '/cbt/koreksi/999999/999999',
        ];

        foreach ($uris as $uri) {
            $response = $this->actingAs($this->admin)->get($uri);

            $this->assertSame(404, $response->getStatusCode(), "{$uri} mengembalikan {$response->getStatusCode()}");
        }
    }

    public function test_duplicate_schedule_for_same_bank_is_validated(): void
    {
        $bankId = $this->makeBank();
        $this->makeJadwal($bankId);

        $response = $this->actingAs($this->admin)->post('/cbt/jadwal', [
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-12-01 07:30:00',
            'tgl_selesai' => '2026-12-01 09:30:00',
            'durasi_ujian' => 90,
        ]);

        $response->assertSessionHasErrors('id_bank');
        $this->assertSame(1, DB::table('cbt_jadwal')->where('id_bank', $bankId)->count());
    }

    public function test_empty_payloads_never_crash(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(function ($route) {
                $methods = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
                $uri = $route->uri();

                // Rute pemeliharaan berat tidak ikut disapu: backup menjalankan mysqldump
                // dan menulis ke storage/app/backups (serta memangkas backup lama).
                return $methods
                    && ! str_contains($uri, '{')
                    && ! str_starts_with($uri, 'api/')
                    && ! str_starts_with($uri, '_boost/')
                    && ! in_array($uri, ['logout', 'login', 'lupa-password', 'reset-password', 'periode/reset', 'setting/database/backup'], true);
            });

        $failures = [];
        $backupDir = storage_path('app/backups');
        $backupsBefore = File::isDirectory($backupDir) ? File::directories($backupDir) : [];

        foreach ($routes as $route) {
            $method = strtolower(array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0]);
            $response = $this->actingAs($this->admin)->{$method}('/'.ltrim($route->uri(), '/'));

            if ($response->getStatusCode() >= 500) {
                $failures[] = $method.' '.$route->uri().' => '.$response->getStatusCode().' '.($response->exception?->getMessage() ?? '');
            }
        }

        $this->assertSame([], $failures, "Endpoint crash tanpa payload:\n".implode("\n", $failures));
        $this->assertSame(
            $backupsBefore,
            File::isDirectory($backupDir) ? File::directories($backupDir) : [],
            'Sapuan endpoint tidak boleh membuat atau memangkas backup nyata.'
        );
    }

    public function test_guru_and_siswa_cannot_reach_admin_only_actions(): void
    {
        $guru = $this->guruUser;
        $siswaUser = $this->user('siswauji2');

        foreach ([
            ['post', '/master/tahun'],
            ['post', '/master/kelas'],
            ['post', '/cbt/jenis'],
            ['post', '/users/guru'],
            ['post', '/setting/sekolah'],
        ] as [$method, $uri]) {
            $this->actingAs($guru)->{$method}($uri)->assertForbidden();
            $this->actingAs($siswaUser)->{$method}($uri)->assertForbidden();
        }
    }

    private function excelWithRows(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $content;
    }

    private function uploadedBinary(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'impor').'-'.$name;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * Dompdf menulis teks sebagai UTF-16BE di dalam content stream terkompresi.
     * Helper ini mengekstrak stream agar isi dokumen bisa diperiksa tanpa biner eksternal.
     */
    private function pdfContains(string $pdf, string $needle): bool
    {
        if (str_contains($pdf, $needle)) {
            return true;
        }

        $utf16 = mb_convert_encoding($needle, 'UTF-16BE', 'UTF-8');
        $streams = '';

        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }

            $streams .= $decoded === false ? $stream : $decoded;
        }

        return str_contains($streams, $needle) || str_contains($streams, $utf16);
    }

    public function test_jadwal_dengan_pilih_acak_mengambil_soal_dari_seluruh_kumpulan(): void
    {
        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'ACAK-POOL',
            'bank_nama' => 'Bank Kumpulan Acak',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'tampil_pg' => 1,
            'bobot_pg' => 100,
        ]);

        foreach ([1, 2, 3, 4] as $nomor) {
            DB::table('cbt_soal')->insert([
                'bank_id' => $bankId,
                'mapel_id' => $this->mapel->id_mapel,
                'jenis' => 1,
                'nomor_soal' => $nomor,
                'soal' => '<p>Soal '.$nomor.'</p>',
                'deskripsi' => '',
                'opsi_a' => 'Salah',
                'opsi_b' => 'Benar',
                'jawaban' => 'B',
                'tampilkan' => 1,
                'kesulitan' => 1,
            ]);
        }

        $jadwalId = $this->makeJadwal($bankId);
        DB::table('cbt_jadwal')->where('id_jadwal', $jadwalId)->update(['pilih_acak' => 1]);

        $dipilih = [];

        for ($i = 0; $i < 6; $i++) {
            $username = 'siswaacak'.$i;
            $user = $this->user($username);
            $user->groups()->attach(Group::where('name', 'siswa')->value('id'));

            $siswa = Siswa::create([
                'nisn' => '2028'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'nis' => '2028'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama' => 'Siswa Acak '.$i,
                'username' => $username,
                'password' => 'rahasia123',
                'kelas_awal' => $this->levelId,
                'nik' => '',
                'warga_negara' => 'WNI',
                'uid' => 'uji-acak-'.$i,
            ]);

            DB::table('kelas_siswa')->insert([
                'id_kelas_siswa' => 900 + $i,
                'id_tp' => $this->tahun->id_tp,
                'id_smt' => $this->semester->id_smt,
                'id_siswa' => $siswa->id_siswa,
                'id_kelas' => $this->kelas->id_kelas,
            ]);

            $this->actingAs($user)->post('/ujian/'.$jadwalId.'/mulai');

            $dipilih[] = (int) DB::table('cbt_soal_siswa')
                ->where('id_jadwal', $jadwalId)
                ->where('id_siswa', $siswa->id_siswa)
                ->value('id_soal');
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($dipilih)),
            'Soal yang diambil harus bervariasi antar siswa, bukan selalu soal pertama.'
        );
    }

    private function makeJadwal(int $bankId): int
    {
        return DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 90,
            'status' => 1,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);
    }

    private function makeBank(): int
    {
        return DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-SIM',
            'bank_nama' => 'Bank Simulasi',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);
    }

    private function sim(string $method, string $uri, array $payload = []): TestResponse
    {
        $response = $this->actingAs($this->admin)->{$method}($uri, $payload);
        $bag = session('errors');
        $errors = $bag instanceof MessageBag ? $bag->all() : (array) ($bag ?? []);

        $this->assertSame([], $errors, "Validasi gagal pada {$method} {$uri}: ".json_encode($errors, JSON_UNESCAPED_UNICODE));
        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "HTTP {$response->getStatusCode()} pada {$method} {$uri}: ".($response->exception?->getMessage() ?? '')
        );

        return $response;
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
