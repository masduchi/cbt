<?php

namespace Tests\Feature;

use App\Models\Ekstra;
use App\Models\Group;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RaporCatatanWali;
use App\Models\RaporFisik;
use App\Models\RaporNilaiEkstra;
use App\Models\RaporNilaiHarian;
use App\Models\RaporNilaiSikap;
use App\Models\RaporPrestasi;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Support\Rapor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RaporImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private Siswa $siswa;

    private Ekstra $ekstra;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'adminimpor',
            'password' => 'rahasia123',
            'email' => 'admin-impor@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $this->admin->groups()->attach($adminGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'status' => 1]);

        $this->siswa = Siswa::create([
            'nisn' => '0061111111',
            'nis' => '2026111',
            'nama' => 'Ahmad Fauzi',
            'username' => 'ahmadimpor',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-impor',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        $this->ekstra = Ekstra::create(['nama_ekstra' => 'Pramuka', 'kode_ekstra' => 'PRM']);
    }

    public function test_import_nilai_harian_computes_average_and_predikat(): void
    {
        $csv = "nisn,nis,nama,p1,p2,k1,k2\n"
            .'0061111111,2026111,Ahmad Fauzi,80,90,90,98'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/nilai/harian/{$this->mapel->id_mapel}/{$this->kelas->id_kelas}/import", [
                'file' => UploadedFile::fake()->createWithContent('harian.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('import_errors');

        $row = RaporNilaiHarian::firstOrFail();
        $this->assertSame(80, (int) $row->p1);
        $this->assertSame(98, (int) $row->k2);
        $this->assertSame(85, (int) $row->p_rata_rata);
        $this->assertSame(94, (int) $row->k_rata_rata);
        $this->assertSame('A', $row->k_predikat);
    }

    public function test_import_sikap_and_catatan(): void
    {
        $csv = "nisn,nama,predikat_spiritual,deskripsi_spiritual,predikat_sosial,deskripsi_sosial\n"
            .'0061111111,Ahmad Fauzi,A,Sangat baik,B,Baik'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/import/sikap", [
                'file' => UploadedFile::fake()->createWithContent('sikap.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, RaporNilaiSikap::count());
        $this->assertSame('A', Rapor::unserialize(
            RaporNilaiSikap::where('jenis', 1)->firstOrFail()->nilai
        )['predikat']);

        $csv = "nisn,nama,sakit,izin,alpa,catatan\n"
            .'0061111111,Ahmad Fauzi,2,1,0,Rajin dan disiplin'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/import/catatan", [
                'file' => UploadedFile::fake()->createWithContent('catatan.csv', $csv),
            ])
            ->assertRedirect();

        $catatan = RaporCatatanWali::firstOrFail();
        $this->assertSame('Rajin dan disiplin', $catatan->deskripsi);
        $this->assertSame(['s' => 2, 'i' => 1, 'a' => 0], Rapor::unserialize($catatan->nilai));
    }

    public function test_import_prestasi_fisik_and_ekstra(): void
    {
        $csv = "nisn,nama,ranking,deskripsi,p1,p1_desk\n"
            .'0061111111,Ahmad Fauzi,1,Peringkat satu,Lomba OSN,Juara 1'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/import/prestasi", [
                'file' => UploadedFile::fake()->createWithContent('prestasi.csv', $csv),
            ])
            ->assertRedirect();

        $prestasi = RaporPrestasi::firstOrFail();
        $this->assertSame(1, (int) $prestasi->ranking);
        $this->assertSame('Juara 1', $prestasi->p1_desk);

        $csv = "nisn,nama,tinggi,berat,telinga,mata,gigi,lain\n"
            .'0061111111,Ahmad Fauzi,165,55,Normal,Normal,Normal,-'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/import/fisik", [
                'file' => UploadedFile::fake()->createWithContent('fisik.csv', $csv),
            ])
            ->assertRedirect();

        $fisik = RaporFisik::firstOrFail();
        $this->assertSame(165, (int) $fisik->tinggi);
        $this->assertSame('Normal', Rapor::unserialize($fisik->kondisi)['mata']);

        $csv = "nisn,nama,nilai,predikat,deskripsi\n"
            .'0061111111,Ahmad Fauzi,90,A,Aktif dan teladan'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/import/ekstra", [
                'file' => UploadedFile::fake()->createWithContent('ekstra.csv', $csv),
                'id_ekstra' => $this->ekstra->id_ekstra,
            ])
            ->assertRedirect();

        $nilai = RaporNilaiEkstra::firstOrFail();
        $this->assertSame(90, (int) $nilai->nilai);
        $this->assertSame('A', $nilai->predikat);
    }

    public function test_unknown_student_is_reported_and_skipped(): void
    {
        $csv = "nisn,nama,p1,p2\n"
            .'0099999999,Bukan Siswa,80,90'."\n";

        $this->actingAs($this->admin)
            ->post("/rapor/nilai/harian/{$this->mapel->id_mapel}/{$this->kelas->id_kelas}/import", [
                'file' => UploadedFile::fake()->createWithContent('harian.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_errors');

        $this->assertSame(0, RaporNilaiHarian::count());
    }

    public function test_templates_can_be_downloaded(): void
    {
        $this->actingAs($this->admin)
            ->get("/rapor/nilai/harian/{$this->mapel->id_mapel}/{$this->kelas->id_kelas}/template")
            ->assertOk();

        foreach (['sikap', 'catatan', 'prestasi', 'fisik'] as $jenis) {
            $this->actingAs($this->admin)
                ->get("/rapor/kelas/{$this->kelas->id_kelas}/template/{$jenis}")
                ->assertOk();
        }

        $this->actingAs($this->admin)
            ->get("/rapor/kelas/{$this->kelas->id_kelas}/template/ekstra?ekstra={$this->ekstra->id_ekstra}")
            ->assertOk();
    }
}
