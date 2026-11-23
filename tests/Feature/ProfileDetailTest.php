<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileDetailTest extends TestCase
{
    use RefreshDatabase;

    private Group $guruGroup;

    private Group $siswaGroup;

    private Group $adminGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $this->siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $this->adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
    }

    public function test_guru_sees_own_details_prefilled(): void
    {
        $user = $this->user('guruprofil', 'guru-profil@example.test', $this->guruGroup);
        Guru::create([
            'id_user' => $user->id,
            'nip' => '198700001',
            'nama_guru' => 'Ibu Sari',
            'jenis_kelamin' => 'P',
            'no_hp' => '081200001',
        ]);

        $this->actingAs($user)
            ->get('/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profil/Index')
                ->where('profile.details.type', 'guru')
                ->where('profile.details.fields.nama_guru', 'Ibu Sari')
                ->where('profile.details.fields.nip', '198700001')
                ->where('profile.details.fields.jenis_kelamin', 'P'));
    }

    public function test_guru_updates_own_record_and_account_email(): void
    {
        $user = $this->user('guruprofil', 'guru-profil@example.test', $this->guruGroup);
        $guru = Guru::create(['id_user' => $user->id, 'nip' => '198700001', 'nama_guru' => 'Ibu Sari']);

        $this->actingAs($user)
            ->post('/profil', [
                'nama_guru' => 'Ibu Sari Wulandari',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Bandung',
                'tgl_lahir' => '1990-05-17',
                'agama' => 'Islam',
                'no_hp' => '0812345678901',
                'email' => 'sari@example.test',
                'alamat_jalan' => 'Jalan Merdeka 10',
                'kode_pos' => '40111',
                'kewarganegaraan' => 'WNI',
                'status_nikah' => 'Menikah',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $guru->refresh();
        $this->assertSame('Ibu Sari Wulandari', $guru->nama_guru);
        $this->assertSame('Bandung', $guru->tempat_lahir);
        $this->assertSame('1990-05-17', $guru->tgl_lahir);
        $this->assertSame('0812345678901', $guru->no_hp);
        $this->assertSame('sari@example.test', $user->refresh()->email);

        $profile = UserProfile::where('id_user', $user->id)->firstOrFail();
        $this->assertSame('Ibu Sari Wulandari', $profile->nama_lengkap);
    }

    public function test_siswa_updates_own_record_and_photo(): void
    {
        Storage::fake('public');

        $user = $this->user('siswaprofil', 'siswa-profil@example.test', $this->siswaGroup);
        $siswa = Siswa::create([
            'nisn' => '2028000001',
            'nis' => '2028001',
            'nama' => 'Ahmad Awal',
            'username' => 'siswaprofil',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-profil-1',
            'foto' => 'siswa.png',
        ]);

        $this->actingAs($user)
            ->get('/profil')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.details.type', 'siswa')
                ->where('profile.details.fields.nama', 'Ahmad Awal')
                ->where('profile.details.fields.nis', '2028001'));

        $this->actingAs($user)
            ->post('/profil', [
                'nama' => 'Ahmad Fauzan',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Surabaya',
                'tanggal_lahir' => '2009-02-03',
                'agama' => 'Islam',
                'hp' => '08521111',
                'email' => 'ahmad@example.test',
                'alamat' => 'Jalan Melati 5',
                'rt' => '02',
                'rw' => '04',
                'kelurahan' => 'Sukamaju',
                'kecamatan' => 'Cilodong',
                'kabupaten' => 'Depok',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '16415',
                'photo' => UploadedFile::fake()->image('foto.png', 120, 120),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $siswa->refresh();
        $this->assertSame('Ahmad Fauzan', $siswa->nama);
        $this->assertSame('2009-02-03', $siswa->tanggal_lahir);
        $this->assertSame('16415', (string) $siswa->kode_pos);
        $this->assertStringStartsWith('profil/siswa/', $siswa->foto);
        Storage::disk('public')->assertExists($siswa->foto);

        $profile = UserProfile::where('id_user', $user->id)->firstOrFail();
        $this->assertSame('Ahmad Fauzan', $profile->nama_lengkap);
        $this->assertSame($siswa->foto, $profile->foto);
    }

    public function test_guru_profile_update_is_validated(): void
    {
        $user = $this->user('guruprofil', 'guru-profil@example.test', $this->guruGroup);
        Guru::create(['id_user' => $user->id, 'nip' => '198700001', 'nama_guru' => 'Ibu Sari']);

        $this->actingAs($user)
            ->post('/profil', [
                'nama_guru' => '',
                'email' => 'bukan-email',
                'kode_pos' => 'abc',
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['nama_guru', 'email', 'kode_pos', 'photo']);

        $this->assertSame('Ibu Sari', Guru::where('id_user', $user->id)->value('nama_guru'));
    }

    public function test_admin_cannot_edit_self_service_details(): void
    {
        $user = $this->user('adminprofil', 'admin-profil@example.test', $this->adminGroup);

        $this->actingAs($user)
            ->get('/profil')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profil/Index')
                ->where('profile.details', null));

        $this->actingAs($user)->post('/profil', ['nama_guru' => 'Admin'])->assertForbidden();
    }

    private function user(string $username, string $email, Group $group): User
    {
        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $email,
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);

        $user->groups()->attach($group->id);

        return $user;
    }
}
