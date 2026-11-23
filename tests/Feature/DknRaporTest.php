<?php

namespace Tests\Feature;

use App\Models\Ekstra;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasEkstra;
use App\Models\Mapel;
use App\Models\RaporCatatanWali;
use App\Models\RaporNilaiAkhir;
use App\Models\RaporNilaiEkstra;
use App\Models\RaporNilaiSikap;
use App\Models\RaporPrestasi;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DknRaporTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private User $otherGuruUser;

    private User $siswaUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private Mapel $mulok;

    private Ekstra $ekstra;

    private Siswa $ani;

    private Siswa $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('admindkn', 'admin-dkn@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('gurudkn', 'guru-dkn@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $wali = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '199000001', 'nama_guru' => 'Wali DKN']);

        $this->otherGuruUser = $this->user('gurulaindkn', 'guru-lain-dkn@example.test');
        $this->otherGuruUser->groups()->attach($guruGroup->id);
        Guru::create(['id_user' => $this->otherGuruUser->id, 'nip' => '199000002', 'nama_guru' => 'Guru Lain']);

        $this->siswaUser = $this->user('siswadkn', 'siswa-dkn@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
            'guru_id' => $wali->id_guru,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'kelompok' => '-', 'urutan' => 1]);
        $this->mulok = Mapel::create(['nama_mapel' => 'Bahasa Sunda', 'kode' => 'SDR', 'kelompok' => 'MULOK', 'urutan' => 2]);
        $this->ekstra = Ekstra::create(['nama_ekstra' => 'Pramuka', 'kode_ekstra' => 'PRM']);

        KelasEkstra::create([
            'id_kelas_ekstra' => 'ekstra-'.$this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_kelas' => $this->kelas->id_kelas,
            'ekstra' => serialize([
                [
                    'id_ekstra' => $this->ekstra->id_ekstra,
                    'nama_ekstra' => $this->ekstra->nama_ekstra,
                    'kelas_ekstra' => [['kelas' => $this->kelas->id_kelas]],
                ],
            ]),
        ]);

        $this->ani = $this->siswa('Ani Lestari', '2028001', '2028000001', 1);
        $this->budi = $this->siswa('Budi Hartono', '2028002', '2028000002', 2);

        $this->nilai($this->ani, $this->mapel, 90, 'A', 1);
        $this->nilai($this->ani, $this->mulok, 70, '', 2);
        $this->nilai($this->budi, $this->mapel, 80, 'B', 3);
        $this->nilai($this->budi, $this->mulok, 95, 'A', 4);

        RaporNilaiSikap::create([
            'id_nilai_sikap' => 1,
            'id_siswa' => $this->ani->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 1,
            'nilai' => serialize(['predikat' => 'B']),
        ]);

        RaporNilaiSikap::create([
            'id_nilai_sikap' => 2,
            'id_siswa' => $this->ani->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'jenis' => 2,
            'nilai' => serialize(['predikat' => 'A']),
        ]);

        RaporCatatanWali::create([
            'id_catatan_wali' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->ani->id_siswa,
            'nilai' => serialize(['s' => 1, 'i' => 2, 'a' => 0]),
            'deskripsi' => 'Catatan.',
        ]);

        RaporNilaiEkstra::create([
            'id_nilai_ekstra' => 1,
            'id_ekstra' => $this->ekstra->id_ekstra,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->ani->id_siswa,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nilai' => 85,
            'predikat' => 'B',
        ]);
    }

    public function test_admin_can_open_dkn_page_and_pdf(): void
    {
        $this->actingAs($this->admin)
            ->get('/rapor/dkn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/DknIndex')
                ->has('classes', 1)
                ->where('classes.0.nama_kelas', 'X IPA 1'));

        $response = $this->actingAs($this->admin)->get('/rapor/dkn/'.$this->kelas->id_kelas);

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rapor/Dkn')
            ->where('kelas.nama_kelas', 'X IPA 1')
            ->has('mapels.reguler', 1)
            ->has('mapels.mulok', 1)
            ->has('ekstra', 1)
            ->has('rows', 2)
            ->where('rows.0.nama', 'Ani Lestari')
            ->where('rows.1.nama', 'Budi Hartono'));

        $props = $response->viewData('page')['props'];
        $mtk = (string) $this->mapel->id_mapel;
        $sunda = (string) $this->mulok->id_mapel;

        $this->assertSame(90, $props['rows'][0]['nilai'][$mtk]['nilai']);
        $this->assertSame('A', $props['rows'][0]['nilai'][$mtk]['predikat']);
        $this->assertNotSame('', $props['rows'][0]['nilai'][$sunda]['predikat'], 'Predikat dihitung dari KKM bila kosong.');
        $this->assertSame(160, $props['rows'][0]['jumlah']);
        $this->assertSame(80.0, (float) $props['rows'][0]['rata_rata']);
        $this->assertSame('B', $props['rows'][0]['sikap_spiritual']);
        $this->assertSame('A', $props['rows'][0]['sikap_sosial']);
        $this->assertSame(1, $props['rows'][0]['absensi']['s']);
        $this->assertSame(2, $props['rows'][0]['absensi']['i']);
        $this->assertSame(85, $props['rows'][0]['ekstra'][(string) $this->ekstra->id_ekstra]['nilai']);
        $this->assertSame(2, $props['rows'][0]['ranking']);
        $this->assertSame(1, $props['rows'][1]['ranking']);

        $pdf = $this->actingAs($this->admin)->get('/rapor/dkn/'.$this->kelas->id_kelas.'/pdf');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_saved_ranking_is_used_when_available(): void
    {
        RaporPrestasi::create([
            'id_ranking' => 1,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->ani->id_siswa,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'ranking' => 3,
            'deskripsi' => '-',
            'p1' => '',
            'p1_desk' => '',
            'p2' => '',
            'p2_desk' => '',
            'p3' => '',
            'p3_desk' => '',
        ]);

        $response = $this->actingAs($this->admin)->get('/rapor/dkn/'.$this->kelas->id_kelas);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Rapor/Dkn')
            ->where('rows.0.ranking', 3));
    }

    public function test_wali_can_open_own_class_but_other_teacher_and_student_cannot(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/rapor/dkn/'.$this->kelas->id_kelas)
            ->assertOk();

        $this->actingAs($this->guruUser)
            ->get('/rapor/dkn')
            ->assertInertia(fn (Assert $page) => $page->has('classes', 1));

        $this->actingAs($this->otherGuruUser)
            ->get('/rapor/dkn/'.$this->kelas->id_kelas)
            ->assertForbidden();

        $this->actingAs($this->otherGuruUser)
            ->get('/rapor/dkn/'.$this->kelas->id_kelas.'/pdf')
            ->assertForbidden();

        $this->actingAs($this->otherGuruUser)
            ->get('/rapor/dkn')
            ->assertInertia(fn (Assert $page) => $page->has('classes', 0));

        $this->actingAs($this->siswaUser)->get('/rapor/dkn')->assertForbidden();
    }

    private function nilai(Siswa $siswa, Mapel $mapel, int $nilai, string $predikat, int $id): void
    {
        RaporNilaiAkhir::create([
            'id_nilai_akhir' => $id,
            'id_mapel' => $mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $siswa->id_siswa,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nilai' => $nilai,
            'akhir' => $nilai,
            'predikat' => $predikat,
        ]);
    }

    private function siswa(string $nama, string $nis, string $nisn, int $sequence): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => $nama,
            'username' => 'dkn'.$sequence,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-dkn-'.$sequence,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $sequence,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        return $siswa;
    }

    private function user(string $username, string $email): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $email,
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
    }
}
