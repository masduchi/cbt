<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaliKelasSiswaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private Guru $guru;

    private User $otherGuruUser;

    private User $siswaUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Kelas $otherKelas;

    private Siswa $siswaA;

    private Siswa $siswaB;

    private Siswa $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('adminwali', 'admin-wali@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('guruwali', 'guru-wali@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198800001', 'nama_guru' => 'Guru Wali']);

        $this->otherGuruUser = $this->user('gurulain', 'guru-lain@example.test');
        $this->otherGuruUser->groups()->attach($guruGroup->id);
        $otherGuru = Guru::create(['id_user' => $this->otherGuruUser->id, 'nip' => '198800002', 'nama_guru' => 'Guru Lain']);

        $this->siswaUser = $this->user('siswawali', 'siswa-wali@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
            'guru_id' => $this->guru->id_guru,
        ]);

        $this->otherKelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
            'guru_id' => $otherGuru->id_guru,
        ]);

        $this->siswaA = $this->siswa('Ahmad Fauzi', '2027001', '2027000001', $this->kelas, 1);
        $this->siswaB = $this->siswa('Budi Santoso', '2027002', '2027000002', $this->kelas, 2);
        $this->outsider = $this->siswa('Citra Lestari', '2027003', '2027000003', $this->otherKelas, 3);
    }

    public function test_wali_sees_only_own_class_students(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/wali/siswa')
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Siswa')
                ->has('classes', 1)
                ->where('selected.nama_kelas', 'X IPA 1')
                ->has('students', 2)
                ->where('students.0.nama', 'Ahmad Fauzi')
                ->where('students.1.nama', 'Budi Santoso'));
    }

    public function test_wali_can_update_student_in_own_class(): void
    {
        $this->actingAs($this->guruUser)
            ->post("/wali/siswa/{$this->siswaA->id_siswa}", [
                'id_kelas' => $this->kelas->id_kelas,
                'nisn' => '2027000001',
                'nis' => '2027001',
                'nama' => 'Ahmad Fauzi Rahman',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-05-01',
                'agama' => 'Islam',
                'alamat' => 'Jl. Merdeka 1',
                'hp' => '081200000001',
                'nama_ayah' => 'Abdullah',
                'nama_ibu' => 'Siti Aminah',
                'nama_wali' => 'Abdullah',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $siswa = $this->siswaA->fresh();
        $this->assertSame('Ahmad Fauzi Rahman', $siswa->nama);
        $this->assertSame('Bandung', $siswa->tempat_lahir);
        $this->assertSame('Abdullah', $siswa->nama_ayah);
    }

    public function test_wali_cannot_update_student_outside_class(): void
    {
        $this->actingAs($this->guruUser)
            ->post("/wali/siswa/{$this->outsider->id_siswa}", [
                'id_kelas' => $this->otherKelas->id_kelas,
                'nisn' => '2027000003',
                'nis' => '2027003',
                'nama' => 'Citra Diubah',
            ])
            ->assertForbidden();

        $this->assertSame('Citra Lestari', $this->outsider->fresh()->nama);
    }

    public function test_wali_cannot_claim_student_through_other_class(): void
    {
        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 20,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->outsider->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        $this->actingAs($this->guruUser)
            ->post("/wali/siswa/{$this->outsider->id_siswa}", [
                'id_kelas' => $this->otherKelas->id_kelas,
                'nisn' => '2027000003',
                'nis' => '2027003',
                'nama' => 'Citra Pindah',
            ])
            ->assertForbidden();

        $this->assertSame('Citra Lestari', $this->outsider->fresh()->nama);
    }

    public function test_wali_can_upload_and_delete_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->guruUser)
            ->post("/wali/siswa/{$this->siswaA->id_siswa}/foto", [
                'foto' => UploadedFile::fake()->image('siswa.jpg', 400, 400),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = $this->siswaA->fresh()->foto;
        $this->assertNotNull($path);
        $this->assertNotSame('siswa.png', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->guruUser)
            ->delete("/wali/siswa/{$this->siswaA->id_siswa}/foto")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($this->siswaA->fresh()->foto);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_other_roles_are_handled_by_ownership(): void
    {
        $this->actingAs($this->otherGuruUser)
            ->get('/wali/siswa')
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.nama_kelas', 'X IPA 2')
                ->has('students', 1));

        $this->actingAs($this->otherGuruUser)
            ->post("/wali/siswa/{$this->siswaA->id_siswa}/foto", [
                'foto' => UploadedFile::fake()->image('siswa.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post("/wali/siswa/{$this->siswaA->id_siswa}", [
                'id_kelas' => $this->kelas->id_kelas,
                'nisn' => '2027000001',
                'nis' => '2027001',
                'nama' => 'Ahmad Admin Edit',
            ])
            ->assertRedirect();

        $this->assertSame('Ahmad Admin Edit', $this->siswaA->fresh()->nama);

        $this->actingAs($this->siswaUser)->get('/wali/siswa')->assertForbidden();
    }

    private function siswa(string $nama, string $nis, string $nisn, Kelas $kelas, int $sequence): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => $nama,
            'username' => 'wali'.$sequence,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-wali-'.$sequence,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $sequence,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $kelas->id_kelas,
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
