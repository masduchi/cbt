<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        Group::create(['name' => 'guru', 'description' => 'Guru']);

        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'adminimpor',
            'password' => 'rahasia123',
            'email' => 'admin@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $user->groups()->attach($admin->id);

        return $user;
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('data.csv', $content);
    }

    public function test_guru_import_creates_profile_and_login_account(): void
    {
        $csv = "nip,nama_guru,jenis_kelamin,email,username,password,no_hp\n"
            ."198703152010011001,Budi Santoso,L,budi@example.test,budi.santoso,,081234567890\n"
            ."198805202011012002,Sari Dewi,P,,sari.dewi,rahasia123,081298765432\n";

        $response = $this->actingAs($this->admin())
            ->post('/master/guru/import', ['file' => $this->csv($csv)]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Impor selesai: 2 guru ditambahkan.');
        $this->assertDatabaseHas('master_guru', ['nip' => '198703152010011001', 'nama_guru' => 'Budi Santoso']);
        $this->assertDatabaseHas('users', ['username' => 'budi.santoso', 'must_change_password' => 1]);
        $this->assertDatabaseHas('users', ['username' => 'sari.dewi', 'must_change_password' => 0]);
        $this->assertSame(2, User::whereIn('username', ['budi.santoso', 'sari.dewi'])->count());
        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.guru.import: Impor guru: 2 ditambahkan, 0 baris dilewati',
        ]);
    }

    public function test_guru_import_skips_duplicate_rows_and_reports_them(): void
    {
        $csv = "nip,nama_guru,username\n"
            ."111,Budi,budi\n"
            ."111,Budi Lagi,budi.lagi\n"
            ."222,Sari,sari\n";

        $response = $this->actingAs($this->admin())
            ->post('/master/guru/import', ['file' => $this->csv($csv)]);

        $response->assertRedirect();
        $response->assertSessionHas('import_errors');
        $this->assertSame(2, Guru::count());
        $this->assertArrayHasKey('import_errors', session()->all());
        $this->assertStringContainsString('NIP atau username ganda', session('import_errors')[0]);
    }

    public function test_mapel_import_supports_semicolon_csv_and_skips_duplicates(): void
    {
        Mapel::create(['nama_mapel' => 'Matematika', 'urutan' => 1, 'status' => 1]);

        $csv = "nama_mapel;kode;kelompok;bobot_p;bobot_k;urutan;status\n"
            ."Matematika;MTK;A;1;1;;1\n"
            ."Bahasa Indonesia;BIN;A;1;2;;1\n";

        $response = $this->actingAs($this->admin())
            ->post('/master/mapel/import', ['file' => $this->csv($csv)]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Impor selesai: 1 mata pelajaran ditambahkan. 1 baris dilewati.');
        $this->assertSame(2, Mapel::count());
        $this->assertDatabaseHas('master_mapel', ['nama_mapel' => 'Bahasa Indonesia', 'kode' => 'BIN', 'bobot_k' => 2]);
        $this->assertSame(2, (int) Mapel::where('nama_mapel', 'Bahasa Indonesia')->value('urutan'));
        $this->assertDatabaseHas('log', [
            'log_desc' => 'master.mapel.import: Impor mata pelajaran: 1 ditambahkan, 1 baris dilewati',
        ]);
    }

    public function test_template_downloads_are_available_for_admin(): void
    {
        $admin = $this->admin();

        foreach (['/master/siswa/import-template', '/master/guru/import-template', '/master/mapel/import-template'] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();
            $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        }
    }

    public function test_pts_import_matches_students_and_reports_unknown_rows(): void
    {
        $admin = $this->admin();

        $tp = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $smt = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);
        $kelas = Kelas::create(['id_tp' => $tp->id_tp, 'id_smt' => $smt->id_smt, 'nama_kelas' => 'X IPA 1', 'level_id' => 10]);
        $mapel = Mapel::create(['nama_mapel' => 'Matematika', 'urutan' => 1, 'status' => 1]);

        $siswa = Siswa::create([
            'nisn' => '0061234567',
            'nis' => '2026001',
            'nama' => 'Ahmad Fauzi',
            'username' => 'ahmad.fauzi',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-1',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $tp->id_tp,
            'id_smt' => $smt->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $kelas->id_kelas,
        ]);

        $csv = "nisn,nis,nama,nilai\n"
            ."0061234567,2026001,Ahmad Fauzi,88\n"
            ."0099999999,,Siswa Lain,70\n"
            ."0061234567,2026001,Ahmad Fauzi,120\n";

        $response = $this->actingAs($admin)
            ->post("/rapor/nilai/pts/{$mapel->id_mapel}/{$kelas->id_kelas}/import", ['file' => $this->csv($csv)]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Impor selesai: 1 nilai PTS disimpan. 2 baris dilewati.');
        $this->assertDatabaseHas('rapor_nilai_pts', [
            'id_siswa' => $siswa->id_siswa,
            'id_mapel' => $mapel->id_mapel,
            'id_kelas' => $kelas->id_kelas,
            'nilai' => 88,
            'predikat' => 'B',
        ]);
        $errors = session('import_errors');
        $this->assertStringContainsString('tidak ditemukan di kelas ini', $errors[0]);
        $this->assertStringContainsString('angka 0 sampai 100', $errors[1]);
    }
}
