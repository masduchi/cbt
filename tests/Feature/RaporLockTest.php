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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RaporLockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kelas $kelas;

    private Mapel $mapel;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'adminrapor',
            'password' => 'rahasia123',
            'email' => 'admin@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $this->admin->groups()->attach($adminGroup->id);

        $tp = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $smt = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $tp->id_tp,
            'id_smt' => $smt->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'urutan' => 1, 'status' => 1]);

        $this->siswa = Siswa::create([
            'nisn' => '0061234567',
            'nis' => '2026001',
            'nama' => 'Ahmad Fauzi',
            'username' => 'ahmad.fauzi',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-kunci',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $tp->id_tp,
            'id_smt' => $smt->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_admin_can_lock_and_unlock_rapor_with_audit_log(): void
    {
        $this->actingAs($this->admin)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/kunci")
            ->assertRedirect();

        $this->assertDatabaseHas('rapor_kunci', [
            'id_kelas' => $this->kelas->id_kelas,
            'locked_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'log_type' => 2,
            'log_desc' => 'rapor.lock: Rapor kelas X IPA 1 dikunci',
        ]);

        $this->actingAs($this->admin)
            ->delete("/rapor/kelas/{$this->kelas->id_kelas}/kunci")
            ->assertRedirect();

        $this->assertDatabaseHas('rapor_kunci', [
            'id_kelas' => $this->kelas->id_kelas,
            'locked_by' => null,
            'locked_at' => null,
        ]);
        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'log_desc' => 'rapor.unlock: Rapor kelas X IPA 1 dibuka kuncinya',
        ]);
    }

    public function test_locked_rapor_rejects_nilai_changes_until_unlocked(): void
    {
        $payload = ['rows' => [['id_siswa' => $this->siswa->id_siswa, 'nilai' => 80]]];
        $url = "/rapor/nilai/pts/{$this->mapel->id_mapel}/{$this->kelas->id_kelas}";

        $this->actingAs($this->admin)->post($url, $payload)->assertRedirect();
        $this->assertDatabaseHas('rapor_nilai_pts', ['id_siswa' => $this->siswa->id_siswa, 'nilai' => 80]);

        $this->actingAs($this->admin)->post("/rapor/kelas/{$this->kelas->id_kelas}/kunci");

        $payload['rows'][0]['nilai'] = 95;
        $this->actingAs($this->admin)->post($url, $payload)->assertStatus(423);
        $this->assertDatabaseHas('rapor_nilai_pts', ['id_siswa' => $this->siswa->id_siswa, 'nilai' => 80]);

        $this->actingAs($this->admin)->delete("/rapor/kelas/{$this->kelas->id_kelas}/kunci");
        $this->actingAs($this->admin)->post($url, $payload)->assertRedirect();
        $this->assertDatabaseHas('rapor_nilai_pts', ['id_siswa' => $this->siswa->id_siswa, 'nilai' => 95]);
    }

    public function test_guru_cannot_lock_rapor(): void
    {
        $guruGroup = Group::where('name', 'guru')->first();
        $guru = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'gururapor',
            'password' => 'rahasia123',
            'email' => 'guru@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $guru->groups()->attach($guruGroup->id);
        $profile = Guru::create(['id_user' => $guru->id, 'nip' => '198700001', 'nama_guru' => 'Guru Rapor']);
        $this->kelas->update(['guru_id' => $profile->id_guru]);

        $this->actingAs($guru)
            ->post("/rapor/kelas/{$this->kelas->id_kelas}/kunci")
            ->assertStatus(403);

        $this->assertDatabaseCount('rapor_kunci', 0);
    }

    public function test_pts_rapor_print_uses_pts_scores(): void
    {
        DB::table('rapor_nilai_pts')->insert([
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'id_siswa' => $this->siswa->id_siswa,
            'id_tp' => TahunPelajaran::where('active', 1)->value('id_tp'),
            'id_smt' => Semester::where('active', 1)->value('id_smt'),
            'nilai' => 88,
            'predikat' => 'B',
        ]);

        $this->actingAs($this->admin)
            ->get("/rapor/cetak/{$this->kelas->id_kelas}/{$this->siswa->id_siswa}?jenis=pts")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/PrintRapor')
                ->where('jenis', 'pts')
                ->where('judul', 'Laporan Hasil Penilaian Tengah Semester')
                ->has('nilai', 1)
                ->where('nilai.0.nama_mapel', 'Matematika')
                ->where('nilai.0.nilai', 88)
                ->where('nilai.0.predikat', 'B'));

        $this->actingAs($this->admin)
            ->get("/rapor/cetak/{$this->kelas->id_kelas}/{$this->siswa->id_siswa}/pdf?jenis=pts")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
