<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->envPath = storage_path('framework/testing/installer.env');
        file_put_contents($this->envPath, "APP_NAME=CBT\nDB_DATABASE=lama\n");

        config(['installer.env_path' => $this->envPath]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_wizard_opens_with_administrator_step_when_schema_is_ready(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Install/Index')
                ->where('step', 2)
                ->where('databaseReady', true)
                ->where('admin', null));
    }

    public function test_administrator_step_validates_password_and_creates_admin(): void
    {
        $payload = [
            'nama_lengkap' => 'Admin Sekolah',
            'username' => 'adminbaru',
            'email' => 'admin@sekolah.test',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $this->post('/install/administrator', $payload)->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());

        $payload['password'] = 'rahasia123';
        $payload['password_confirmation'] = 'rahasia123';

        $this->post('/install/administrator', $payload)->assertSessionHas('success');

        $admin = User::where('username', 'adminbaru')->firstOrFail();

        $this->assertSame(1, (int) $admin->active);
        $this->assertSame('Admin Sekolah', $admin->nama);
        $this->assertSame('admin', $admin->role);
        $this->assertDatabaseHas('groups', ['name' => 'guru']);
        $this->assertDatabaseHas('groups', ['name' => 'siswa']);
    }

    public function test_admin_step_runs_only_once(): void
    {
        $this->post('/install/administrator', [
            'nama_lengkap' => 'Admin Sekolah',
            'username' => 'adminbaru',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHas('success');

        $this->post('/install/administrator', [
            'nama_lengkap' => 'Admin Lain',
            'username' => 'adminlain',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHas('success');

        $this->assertSame(1, User::count());
    }

    public function test_school_step_requires_administrator_first(): void
    {
        $this->post('/install/sekolah', ['sekolah' => 'SMA Contoh'])
            ->assertRedirect('/install')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('setting', 0);
    }

    public function test_full_installation_flow_marks_app_installed(): void
    {
        $this->post('/install/administrator', [
            'nama_lengkap' => 'Admin Sekolah',
            'username' => 'adminbaru',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHas('success');

        $this->post('/install/sekolah', [
            'nama_aplikasi' => 'CBT Sekolah',
            'sekolah' => 'SMA Negeri 2 Contoh',
            'npsn' => '12345678',
            'kepsek' => 'Kepala Sekolah',
            'jenjang' => 3,
            'satuan_pendidikan' => 1,
            'alamat' => 'Jl. Pendidikan 2',
            'desa' => 'Desa Contoh',
            'kecamatan' => 'Kecamatan Contoh',
            'kota' => 'Kota Contoh',
            'provinsi' => 'Provinsi Contoh',
            'telp' => '021123456',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('setting', [
            'sekolah' => 'SMA Negeri 2 Contoh',
            'satuan_pendidikan' => 1,
            'jenjang' => 3,
        ]);

        $this->post('/install/selesai')->assertRedirect('/login');
        Storage::disk('local')->assertExists('installed');

        $this->get('/install')->assertRedirect('/login');

        $this->post('/login', [
            'identity' => 'adminbaru',
            'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');
    }

    public function test_finish_requires_admin_and_school_data(): void
    {
        $this->post('/install/selesai')
            ->assertRedirect('/install')
            ->assertSessionHas('error');

        Storage::disk('local')->assertMissing('installed');
    }

    public function test_reference_seeder_provides_baseline_rows(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->assertDatabaseHas('groups', ['name' => 'admin']);
        $this->assertTrue(DB::table('level_kelas')->exists());
        $this->assertTrue(DB::table('master_tp')->exists());
        $this->assertTrue(DB::table('master_smt')->exists());
        $this->assertTrue(DB::table('cbt_jenis')->exists());
    }

    public function test_database_step_reports_connection_failure_without_touching_env(): void
    {
        $this->post('/install/database', [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'cbt_instalasi',
            'username' => 'root',
            'password' => '',
        ])->assertSessionHasErrors('database');

        $this->assertStringContainsString('DB_DATABASE=lama', (string) file_get_contents($this->envPath));
    }

    public function test_database_step_validates_name_and_port(): void
    {
        $this->post('/install/database', [
            'host' => '',
            'port' => 99999,
            'database' => 'nama database',
            'username' => '',
        ])->assertSessionHasErrors(['host', 'port', 'database', 'username']);
    }

    public function test_wizard_is_not_reachable_after_installation_marker_exists(): void
    {
        Storage::disk('local')->put('installed', 'done');

        $this->get('/install')->assertRedirect('/login');
    }
}
