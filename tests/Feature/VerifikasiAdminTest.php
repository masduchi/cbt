<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Support\VerifikasiDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerifikasiAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminverifikasi');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('guruverifikasi');
        $this->guru->groups()->attach($guruGroup->id);
    }

    public function test_admin_melihat_daftar_kode_verifikasi_dengan_filter(): void
    {
        $rapor = VerifikasiDokumen::terbitkan('rapor', '1-2-3-4-akhir', [
            'siswa' => 'Ahmad Fauzi',
            'kelas' => 'X IPA 1',
        ]);
        $kartu = VerifikasiDokumen::terbitkan('kartu-peserta', '9', [
            'ujian' => 'Matematika',
        ]);
        DB::table('dokumen_verifikasi')->where('kode', $kartu)->update(['revoked_at' => now()]);

        $this->actingAs($this->admin)
            ->get('/setting/verifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Verifikasi')
                ->has('dokumen.data', 2)
                ->where('statistik.total', 2)
                ->where('statistik.aktif', 1)
                ->where('statistik.dicabut', 1)
                ->where('dokumen.data.0.kode', $kartu)
                ->where('dokumen.data.0.jenis_label', 'Kartu peserta ujian')
                ->where('dokumen.data.0.aktif', false)
                ->where('dokumen.data.1.kode', $rapor)
                ->where('dokumen.data.1.jenis_label', 'Rapor')
                ->where('dokumen.data.1.aktif', true)
                ->where('dokumen.data.1.diperbarui', fn ($nilai) => is_string($nilai) && $nilai !== '-')
                ->where('dokumen.data.1.url', VerifikasiDokumen::url($rapor)));

        $this->actingAs($this->admin)
            ->get('/setting/verifikasi?status=dicabut')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.kode', $kartu)
                ->where('filters.status', 'dicabut'));

        $this->actingAs($this->admin)
            ->get('/setting/verifikasi?jenis=rapor&q='.$rapor)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dokumen.data', 1)
                ->where('dokumen.data.0.kode', $rapor));
    }

    public function test_admin_mencabut_kode_dan_audit_tercatat(): void
    {
        $kode = VerifikasiDokumen::terbitkan('buku-induk', '77', ['siswa' => 'Ahmad Fauzi']);

        $this->actingAs($this->admin)
            ->post('/setting/verifikasi/'.$kode.'/cabut')
            ->assertRedirect('/setting/verifikasi')
            ->assertSessionHas('success');

        $row = DB::table('dokumen_verifikasi')->where('kode', $kode)->first();
        $this->assertNotNull($row->revoked_at);

        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'log_desc' => 'verifikasi.cabut: Kode '.$kode.' (buku-induk: 77) dicabut',
        ]);

        $this->get('/verifikasi/'.$kode)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));
    }

    public function test_pencabutan_membebaskan_kunci_agar_kode_baru_dapat_diterbitkan(): void
    {
        $lama = VerifikasiDokumen::terbitkan('rapor', '5-6-akhir', ['siswa' => 'Ahmad Fauzi']);

        $this->actingAs($this->admin)
            ->post('/setting/verifikasi/'.$lama.'/cabut')
            ->assertRedirect();

        $this->assertNull(DB::table('dokumen_verifikasi')->where('kode', $lama)->value('kunci_aktif'));

        $baru = VerifikasiDokumen::terbitkan('rapor', '5-6-akhir', ['siswa' => 'Ahmad Fauzi']);

        $this->assertNotSame($lama, $baru);
        $this->assertSame('rapor|5-6-akhir', DB::table('dokumen_verifikasi')->where('kode', $baru)->value('kunci_aktif'));
        $this->assertSame(1, DB::table('dokumen_verifikasi')
            ->where('jenis', 'rapor')
            ->where('ref', '5-6-akhir')
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_pencabutan_ulang_tidak_mengubah_waktu_dan_tidak_menggandakan_audit(): void
    {
        $kode = VerifikasiDokumen::terbitkan('rapor', '1-2-3-4-akhir');

        $this->actingAs($this->admin)->post('/setting/verifikasi/'.$kode.'/cabut')->assertRedirect();
        $pertama = DB::table('dokumen_verifikasi')->where('kode', $kode)->value('revoked_at');

        $this->actingAs($this->admin)
            ->post('/setting/verifikasi/'.$kode.'/cabut')
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame($pertama, DB::table('dokumen_verifikasi')->where('kode', $kode)->value('revoked_at'));
        $this->assertSame(1, DB::table('log')->where('log_desc', 'like', 'verifikasi.cabut%')->count());
    }

    public function test_kode_tidak_dikenal_dan_akses_guru_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/setting/verifikasi/TIDAKADA/cabut')
            ->assertNotFound();

        $this->actingAs($this->guru)->get('/setting/verifikasi')->assertForbidden();

        $kode = VerifikasiDokumen::terbitkan('kartu-peserta', '5');
        $this->actingAs($this->guru)->post('/setting/verifikasi/'.$kode.'/cabut')->assertForbidden();

        $this->assertNull(DB::table('dokumen_verifikasi')->where('kode', $kode)->value('revoked_at'));
    }

    private function user(string $username): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
    }
}
