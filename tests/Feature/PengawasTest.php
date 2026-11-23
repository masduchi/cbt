<?php

namespace Tests\Feature;

use App\Models\BankSoal;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Ruang;
use App\Models\Semester;
use App\Models\Sesi;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PengawasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Jadwal $jadwal;

    private Ruang $ruang;

    private Sesi $sesi;

    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'adminpengawas',
            'password' => 'rahasia123',
            'email' => 'admin-pengawas@example.test',
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

        $mapel = Mapel::create(['nama_mapel' => 'Matematika', 'urutan' => 1, 'status' => 1]);

        $bank = BankSoal::create([
            'bank_kode' => 'BANK-PENGAWAS',
            'bank_level' => '10',
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_mapel_id' => $mapel->id_mapel,
            'bank_nama' => 'Matematika PTS',
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->jadwal = Jadwal::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_bank' => $bank->id_bank,
            'tgl_mulai' => '2026-11-12 07:30:00',
            'tgl_selesai' => '2026-11-12 09:30:00',
            'durasi_ujian' => 90,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'hasil_tampil' => 1,
            'token' => 0,
            'status' => 1,
            'ulang' => 0,
            'reset_login' => 0,
        ]);

        $this->ruang = Ruang::create(['nama_ruang' => 'Ruang 1', 'kode_ruang' => 'R1']);
        $this->sesi = Sesi::create([
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '07:30:00',
            'waktu_akhir' => '09:30:00',
            'aktif' => 1,
        ]);

        $this->guru = Guru::create(['nip' => '198700001', 'nama_guru' => 'Guru Pengawas']);

        DB::table('cbt_kelas_ruang')->insert([
            'id_kelas_ruang' => $this->kelas->id_kelas.$this->tahun->id_tp.$this->semester->id_smt,
            'id_kelas' => $this->kelas->id_kelas,
            'id_ruang' => $this->ruang->id_ruang,
            'id_sesi' => $this->sesi->id_sesi,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'set_siswa' => 1,
        ]);

        $siswa = Siswa::create([
            'nisn' => '0061234567',
            'nis' => '2026001',
            'nama' => 'Ahmad Fauzi',
            'username' => 'ahmad.pengawas',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-pengawas',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_index_lists_ruang_sesi_combos_for_selected_jadwal(): void
    {
        $this->actingAs($this->admin)
            ->get('/cbt/pengawas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Pengawas')
                ->where('selected', $this->jadwal->id_jadwal)
                ->has('options', 1)
                ->has('combos', 1)
                ->where('combos.0.nama_ruang', 'Ruang 1')
                ->where('combos.0.nama_sesi', 'Sesi 1')
                ->where('combos.0.peserta', 1)
                ->has('combos.0.id_guru', 0)
                ->has('guru', 1));
    }

    public function test_admin_can_assign_and_clear_pengawas(): void
    {
        $this->actingAs($this->admin)
            ->post('/cbt/pengawas', [
                'rows' => [[
                    'id_jadwal' => $this->jadwal->id_jadwal,
                    'id_ruang' => $this->ruang->id_ruang,
                    'id_sesi' => $this->sesi->id_sesi,
                    'id_guru' => [$this->guru->id_guru],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_pengawas', [
            'id_pengawas' => $this->tahun->id_tp.$this->semester->id_smt.$this->jadwal->id_jadwal.$this->ruang->id_ruang.$this->sesi->id_sesi,
            'id_jadwal' => (string) $this->jadwal->id_jadwal,
            'id_ruang' => (string) $this->ruang->id_ruang,
            'id_sesi' => (string) $this->sesi->id_sesi,
            'id_guru' => (string) $this->guru->id_guru,
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/pengawas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('combos.0.id_guru.0', $this->guru->id_guru));

        $this->actingAs($this->admin)
            ->post('/cbt/pengawas', [
                'rows' => [[
                    'id_jadwal' => $this->jadwal->id_jadwal,
                    'id_ruang' => $this->ruang->id_ruang,
                    'id_sesi' => $this->sesi->id_sesi,
                    'id_guru' => [],
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('cbt_pengawas', 0);
    }

    public function test_guru_cannot_access_pengawas_management(): void
    {
        $guruGroup = Group::where('name', 'guru')->first();
        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'gurupengawas',
            'password' => 'rahasia123',
            'email' => 'guru-pengawas@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $user->groups()->attach($guruGroup->id);

        $this->actingAs($user)->get('/cbt/pengawas')->assertStatus(403);
        $this->actingAs($user)->post('/cbt/pengawas', ['rows' => []])->assertStatus(403);
    }
}
