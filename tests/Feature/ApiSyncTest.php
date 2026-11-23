<?php

namespace Tests\Feature;

use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\Group;
use App\Models\Guru;
use App\Models\JabatanGuru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private User $siswaUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private Jurusan $jurusan;

    private Siswa $siswa;

    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('apiadmin', 'api-admin@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('apiguru', 'api-guru@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '197700001', 'nama_guru' => 'Guru API']);

        $this->siswaUser = $this->user('apisiswa', 'api-siswa@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X API 1',
            'level_id' => 10,
            'guru_id' => $this->guru->id_guru,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Informatika', 'kode' => 'INF', 'kelompok' => 'MIPA', 'urutan' => 1]);
        $this->jurusan = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $this->jurusan->mapels()->sync([$this->mapel->id_mapel]);

        JabatanGuru::create([
            'id_jabatan_guru' => 'jabatan-api',
            'id_guru' => $this->guru->id_guru,
            'id_jabatan' => 1,
            'id_kelas' => $this->kelas->id_kelas,
            'mapel_kelas' => serialize([
                [
                    'id_mapel' => $this->mapel->id_mapel,
                    'nama_mapel' => $this->mapel->nama_mapel,
                    'kelas_mapel' => [['kelas' => $this->kelas->id_kelas]],
                ],
            ]),
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '2029000001',
            'nis' => '2029001',
            'nama' => 'Siswa API',
            'username' => 'apisiswa',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-api-1',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_token_is_issued_with_hashed_storage(): void
    {
        $response = $this->postJson('/api/auth/token', [
            'username' => 'apisiswa',
            'password' => 'rahasia123',
            'device' => 'Android',
        ]);

        $response->assertOk()->assertJsonPath('user.role', 'siswa');
        $plain = $response->json('token');

        $this->assertIsString($plain);
        $this->assertSame(64, strlen($plain));

        $row = ApiToken::firstOrFail();
        $this->assertSame(hash('sha256', $plain), $row->token);
        $this->assertNotSame($plain, $row->token);
        $this->assertSame('Android', $row->device);

        $this->postJson('/api/auth/token', [
            'username' => 'apisiswa',
            'password' => 'salah',
        ])->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_sync_requires_token_and_returns_snapshot(): void
    {
        $this->getJson('/api/sync')->assertUnauthorized();

        $token = $this->token($this->admin);

        $response = $this->getJson('/api/sync', ['Authorization' => 'Bearer '.$token]);

        $response->assertOk()
            ->assertJsonPath('periode.tahun', '2026/2027')
            ->assertJsonPath('periode.semester', 'Ganjil')
            ->assertJsonPath('auto_sync', false)
            ->assertJsonPath('profile_edit.siswa', false)
            ->assertJsonPath('kelas.0.nama_kelas', 'X API 1')
            ->assertJsonPath('kelas.0.wali', 'Guru API')
            ->assertJsonPath('jurusan.0.mapel', [$this->mapel->id_mapel])
            ->assertJsonPath('siswa.0.nama', 'Siswa API')
            ->assertJsonPath('siswa.0.kelas', [$this->kelas->id_kelas])
            ->assertJsonPath('guru.0.mapel.0.id_mapel', $this->mapel->id_mapel);
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->token($this->siswaUser);

        $this->postJson('/api/auth/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertDatabaseCount('api_token', 0);
        $this->getJson('/api/sync', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_profile_update_is_gated_by_api_setting(): void
    {
        $token = $this->token($this->siswaUser);
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->putJson('/api/profil', ['nama' => 'Nama Baru'], $headers)->assertForbidden();

        $this->assertSame('Siswa API', $this->siswa->refresh()->nama);

        ApiSetting::current()->update(['edit_profile_siswa' => true]);

        $this->putJson('/api/profil', [
            'nama' => 'Siswa Diperbarui',
            'hp' => '081200009999',
            'email' => 'baru@example.test',
            'alamat' => 'Jalan Baru 1',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('nama', 'Siswa Diperbarui');

        $this->assertSame('Siswa Diperbarui', $this->siswa->refresh()->nama);
        $this->assertSame('081200009999', $this->siswa->hp);
        $this->assertSame('baru@example.test', $this->siswaUser->refresh()->email);

        $profile = UserProfile::where('id_user', $this->siswaUser->id)->firstOrFail();
        $this->assertSame('Siswa Diperbarui', $profile->nama_lengkap);

        $guruToken = $this->token($this->guruUser);
        $guruHeaders = ['Authorization' => 'Bearer '.$guruToken];

        $this->putJson('/api/profil', ['nama_guru' => 'Guru Baru'], $guruHeaders)->assertForbidden();

        ApiSetting::current()->update(['edit_profile_guru' => true]);

        $this->putJson('/api/profil', [
            'nama_guru' => 'Guru Baru',
            'no_hp' => '08137777',
        ], $guruHeaders)->assertOk();

        $this->assertSame('Guru Baru', $this->guru->refresh()->nama_guru);

        $adminToken = $this->token($this->admin);
        $this->putJson('/api/profil', ['nama_guru' => 'X'], ['Authorization' => 'Bearer '.$adminToken])->assertForbidden();
    }

    public function test_admin_manages_api_settings_and_tokens_from_web_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/setting/api')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Api')
                ->where('setting.auto_sync', false)
                ->has('tokens', 0)
                ->where('endpoints.sync', url('/api/sync')));

        $this->actingAs($this->admin)
            ->post('/setting/api', [
                'auto_sync' => true,
                'edit_profile_siswa' => true,
                'edit_profile_guru' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = ApiSetting::current();
        $this->assertTrue($setting->auto_sync);
        $this->assertTrue($setting->edit_profile_siswa);
        $this->assertFalse($setting->edit_profile_guru);

        $this->token($this->siswaUser);
        $tokenRow = ApiToken::firstOrFail();

        $this->actingAs($this->admin)
            ->delete('/setting/api/token/'.$tokenRow->id_api)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('api_token', 0);

        $this->actingAs($this->guruUser)->get('/setting/api')->assertForbidden();
    }

    public function test_sync_conflicts_when_period_is_missing(): void
    {
        TahunPelajaran::query()->update(['active' => 0]);
        Semester::query()->update(['active' => 0]);

        $token = $this->token($this->admin);

        $this->getJson('/api/sync', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Tahun pelajaran atau semester aktif belum diatur.');
    }

    private function token(User $user): string
    {
        $plain = Str::random(64);

        ApiToken::create([
            'id_user' => $user->id,
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'phpunit',
            'token' => hash('sha256', $plain),
        ]);

        return $plain;
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
