<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Jurusan;
use App\Models\Mapel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JurusanMapelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private Mapel $mipa;

    private Mapel $ips;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminjurusan', 'admin-jurusan@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('gurujurusan', 'guru-jurusan@example.test');
        $this->guru->groups()->attach($guruGroup->id);

        $this->mipa = Mapel::create(['nama_mapel' => 'Matematika Lanjut', 'kode' => 'MTK-L', 'kelompok' => 'MIPA', 'urutan' => 1]);
        $this->ips = Mapel::create(['nama_mapel' => 'Sosiologi', 'kode' => 'SOS', 'kelompok' => 'IPS', 'urutan' => 2]);
    }

    public function test_admin_creates_jurusan_with_subject_relations(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/jurusan', [
                'nama_jurusan' => 'MIPA',
                'kode_jurusan' => 'MIPA',
                'mapel' => [$this->mipa->id_mapel, $this->ips->id_mapel, $this->mipa->id_mapel],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $jurusan = Jurusan::where('nama_jurusan', 'MIPA')->firstOrFail();

        $this->assertSame(
            [$this->mipa->id_mapel, $this->ips->id_mapel],
            $jurusan->mapels()->orderBy('id_mapel')->pluck('master_mapel.id_mapel')->all()
        );
        $this->assertDatabaseCount('jurusan_mapel', 2);
        $this->assertSame($this->mipa->id_mapel.','.$this->ips->id_mapel, $jurusan->mapel_peminatan);
    }

    public function test_index_exposes_related_subjects(): void
    {
        $jurusan = Jurusan::create(['nama_jurusan' => 'IPS', 'kode_jurusan' => 'IPS', 'mapel_peminatan' => (string) $this->ips->id_mapel]);
        $jurusan->mapels()->sync([$this->ips->id_mapel]);

        $this->actingAs($this->admin)
            ->get('/master/jurusan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Jurusan')
                ->has('jurusan', 1)
                ->where('jurusan.0.mapel_ids', [$this->ips->id_mapel])
                ->where('jurusan.0.mapel_names', ['Sosiologi'])
                ->has('mapel', 2));
    }

    public function test_update_replaces_subject_relations(): void
    {
        $jurusan = Jurusan::create(['nama_jurusan' => 'IPA', 'mapel_peminatan' => '']);
        $jurusan->mapels()->sync([$this->mipa->id_mapel, $this->ips->id_mapel]);

        $this->actingAs($this->admin)
            ->put('/master/jurusan/'.$jurusan->id_jurusan, [
                'nama_jurusan' => 'IPA',
                'mapel' => [$this->ips->id_mapel],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->ips->id_mapel], $jurusan->mapels()->pluck('master_mapel.id_mapel')->all());
        $this->assertSame((string) $this->ips->id_mapel, $jurusan->refresh()->mapel_peminatan);
    }

    public function test_delete_removes_subject_relations(): void
    {
        $jurusan = Jurusan::create(['nama_jurusan' => 'Bahasa']);
        $jurusan->mapels()->sync([$this->mipa->id_mapel]);

        $this->actingAs($this->admin)
            ->delete('/master/jurusan/'.$jurusan->id_jurusan)
            ->assertRedirect();

        $this->assertDatabaseMissing('master_jurusan', ['id_jurusan' => $jurusan->id_jurusan]);
        $this->assertDatabaseCount('jurusan_mapel', 0);
    }

    public function test_invalid_subject_is_rejected_and_guru_is_forbidden(): void
    {
        $this->actingAs($this->admin)
            ->post('/master/jurusan', [
                'nama_jurusan' => 'Salah',
                'mapel' => [999999],
            ])
            ->assertSessionHasErrors('mapel.0');

        $this->actingAs($this->guru)->get('/master/jurusan')->assertForbidden();
        $this->actingAs($this->guru)->post('/master/jurusan', ['nama_jurusan' => 'Baru'])->assertForbidden();
    }

    private function user(string $username, string $email): User
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

        return $user;
    }
}
