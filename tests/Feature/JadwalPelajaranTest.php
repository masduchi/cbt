<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JadwalPelajaranTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'adminjadwal',
            'password' => 'rahasia123',
            'email' => 'admin-jadwal@example.test',
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
    }

    public function test_index_shows_default_kbm_and_empty_template(): void
    {
        $this->actingAs($this->admin)
            ->get('/elearning/jadwal-pelajaran')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/JadwalPelajaran')
                ->where('kelasId', $this->kelas->id_kelas)
                ->where('kbm.jam_pel', 40)
                ->where('kbm.jml_mapel', 8)
                ->has('template', 0)
                ->has('mapels', 1));
    }

    public function test_admin_can_save_kbm_and_subject_template(): void
    {
        $payload = [
            'kelas' => $this->kelas->id_kelas,
            'jam_pel' => 45,
            'jam_mulai' => '07:15',
            'jml_mapel' => 3,
            'istirahat' => [
                ['jam_ke' => 2, 'durasi' => 15],
                ['jam_ke' => null, 'durasi' => null],
                ['jam_ke' => null, 'durasi' => null],
                ['jam_ke' => null, 'durasi' => null],
            ],
            'template' => [
                ['jam_ke' => 1, 'mapel' => ['1' => $this->mapel->id_mapel, '2' => null, '3' => null, '4' => null, '5' => null, '6' => null]],
                ['jam_ke' => 2, 'mapel' => ['1' => null, '2' => $this->mapel->id_mapel, '3' => null, '4' => null, '5' => null, '6' => null]],
                ['jam_ke' => 3, 'mapel' => ['1' => null, '2' => null, '3' => null, '4' => null, '5' => null, '6' => null]],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/elearning/jadwal-pelajaran', $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $kbm = DB::table('kelas_jadwal_kbm')->first();
        $this->assertSame((int) ($this->tahun->id_tp.$this->semester->id_smt.$this->kelas->id_kelas), (int) $kbm->id_kbm);
        $this->assertSame(45, (int) $kbm->kbm_jam_pel);
        $this->assertSame('07:15', $kbm->kbm_jam_mulai);
        $this->assertSame(3, (int) $kbm->kbm_jml_mapel_hari);
        $this->assertSame([['ist' => 2, 'dur' => 15]], unserialize($kbm->istirahat));

        $rows = DB::table('kelas_jadwal_mapel')->orderBy('id_hari')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(
            (int) ($this->tahun->id_tp.$this->semester->id_smt.$this->kelas->id_kelas.'1'.'1'),
            (int) $rows[0]->id_jadwal
        );
        $this->assertSame(1, (int) $rows[0]->jam_ke);
        $this->assertSame($this->mapel->id_mapel, (int) $rows[0]->id_mapel);

        $this->actingAs($this->admin)
            ->get("/elearning/jadwal-pelajaran?kelas={$this->kelas->id_kelas}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('kbm.jam_pel', 45)
                ->has('template', 2));

        $payload['jml_mapel'] = 2;
        $payload['template'] = [
            ['jam_ke' => 1, 'mapel' => ['1' => null, '2' => null, '3' => $this->mapel->id_mapel, '4' => null, '5' => null, '6' => null]],
            ['jam_ke' => 2, 'mapel' => ['1' => null, '2' => null, '3' => null, '4' => null, '5' => null, '6' => null]],
        ];

        $this->actingAs($this->admin)->post('/elearning/jadwal-pelajaran', $payload)->assertRedirect();

        $this->assertDatabaseCount('kelas_jadwal_mapel', 1);
        $this->assertDatabaseHas('kelas_jadwal_mapel', [
            'id_kelas' => $this->kelas->id_kelas,
            'id_hari' => 3,
            'jam_ke' => 1,
            'id_mapel' => $this->mapel->id_mapel,
        ]);
    }
}
