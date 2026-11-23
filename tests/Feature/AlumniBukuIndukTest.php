<?php

namespace Tests\Feature;

use App\Models\BukuInduk;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlumniBukuIndukTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminalumni', 'admin-alumni@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('gurualumni', 'guru-alumni@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198700009', 'nama_guru' => 'Guru Alumni']);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 12, 'level' => '12']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'XII IPA 1',
            'level_id' => 12,
        ]);

        $this->siswa = $this->siswa('Ahmad Fauzi', '2027001', '2027000001', 'ahmadalumni');
    }

    public function test_admin_generates_alumni_from_class(): void
    {
        $this->actingAs($this->admin)
            ->post('/alumni/generate', [
                'kelas' => $this->kelas->id_kelas,
                'status' => 2,
                'tahun_lulus' => 2027,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = BukuInduk::where('id_siswa', $this->siswa->id_siswa)->firstOrFail();
        $this->assertSame(2, (int) $record->status);
        $this->assertSame(2027, (int) $record->tahun_lulus);
        $this->assertSame('XII IPA 1', $record->kelas_akhir);
        $this->assertSame($this->siswa->uid, $record->uid);

        $this->actingAs($this->admin)
            ->get('/alumni')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alumni/Index')
                ->has('alumni.data', 1)
                ->where('alumni.data.0.nama', 'Ahmad Fauzi')
                ->where('alumni.data.0.status_label', 'Lulus')
                ->where('alumni.data.0.kelas_akhir', 'XII IPA 1')
                ->where('stats.lulus', 1)
                ->where('stats.total', 1));
    }

    public function test_admin_updates_alumni_and_cancels_status(): void
    {
        DB::table('buku_induk')->insert([
            'id_siswa' => $this->siswa->id_siswa,
            'uid' => $this->siswa->uid,
            'status' => 2,
            'tahun_lulus' => 2027,
            'kelas_akhir' => 'XII IPA 1',
        ]);

        $this->actingAs($this->admin)
            ->put('/alumni/'.$this->siswa->id_siswa, [
                'status' => 3,
                'tahun_lulus' => 2027,
                'kelas_akhir' => 'XII IPA 1',
                'pindah_ke' => 'SMA Negeri 2',
                'alasan_pindah' => 'Mengikuti orang tua',
                'tgl_pindah' => '2026-11-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = BukuInduk::where('id_siswa', $this->siswa->id_siswa)->firstOrFail();
        $this->assertSame(3, (int) $record->status);
        $this->assertSame('SMA Negeri 2', $record->pindah_ke);
        $this->assertSame('2026-11-01', $record->tgl_pindah);

        $this->actingAs($this->admin)
            ->get('/alumni?status=3')
            ->assertInertia(fn (Assert $page) => $page
                ->has('alumni.data', 1)
                ->where('alumni.data.0.status_label', 'Pindah')
                ->where('alumni.data.0.pindah_ke', 'SMA Negeri 2'));

        $this->actingAs($this->admin)
            ->delete('/alumni/'.$this->siswa->id_siswa)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, (int) BukuInduk::where('id_siswa', $this->siswa->id_siswa)->value('status'));

        $this->actingAs($this->admin)
            ->get('/alumni')
            ->assertInertia(fn (Assert $page) => $page
                ->has('alumni.data', 0)
                ->where('stats.total', 0));
    }

    public function test_admin_lists_and_saves_buku_induk(): void
    {
        $this->actingAs($this->admin)
            ->get('/master/buku-induk')
            ->assertInertia(fn (Assert $page) => $page
                ->component('BukuInduk/Index')
                ->has('siswa.data', 1)
                ->where('siswa.data.0.nama', 'Ahmad Fauzi')
                ->where('siswa.data.0.status_label', 'Belum diisi')
                ->where('stats.kosong', 1)
                ->where('stats.terisi', 0));

        $this->actingAs($this->admin)
            ->get('/master/buku-induk/'.$this->siswa->id_siswa)
            ->assertInertia(fn (Assert $page) => $page
                ->component('BukuInduk/Form')
                ->where('siswa.nama', 'Ahmad Fauzi')
                ->where('buku.status', 1));

        $this->actingAs($this->admin)
            ->post('/master/buku-induk/'.$this->siswa->id_siswa, [
                'nama_panggilan' => 'Ahmad',
                'jml_saudara_kandung' => 2,
                'tinggal_bersama' => '1',
                'yatim' => 0,
                'status' => 1,
                'kelas_akhir' => 'XII IPA 1',
            ])
            ->assertRedirect(route('master.buku-induk.index'))
            ->assertSessionHas('success');

        $record = BukuInduk::where('id_siswa', $this->siswa->id_siswa)->firstOrFail();
        $this->assertSame('Ahmad', $record->nama_panggilan);
        $this->assertSame(2, (int) $record->jml_saudara_kandung);
        $this->assertSame('XII IPA 1', $record->kelas_akhir);
    }

    public function test_buku_induk_pdf_is_downloaded(): void
    {
        DB::table('buku_induk')->insert([
            'id_siswa' => $this->siswa->id_siswa,
            'uid' => $this->siswa->uid,
            'status' => 2,
            'tahun_lulus' => 2027,
            'kelas_akhir' => 'XII IPA 1',
        ]);

        $response = $this->actingAs($this->admin)->get('/master/buku-induk/'.$this->siswa->id_siswa.'/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guru_cannot_access_alumni_or_buku_induk(): void
    {
        $this->actingAs($this->guruUser)->get('/alumni')->assertForbidden();
        $this->actingAs($this->guruUser)->get('/master/buku-induk')->assertForbidden();
        $this->actingAs($this->guruUser)->get('/master/buku-induk/'.$this->siswa->id_siswa)->assertForbidden();
    }

    private function siswa(string $nama, string $nis, string $nisn, string $username): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => $nama,
            'username' => $username,
            'password' => 'rahasia123',
            'kelas_awal' => 12,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-'.$username,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
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
