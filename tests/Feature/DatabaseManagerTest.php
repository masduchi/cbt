<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatabaseManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('dbadmin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('dbguru');
        $this->guru->groups()->attach($guruGroup->id);
    }

    public function test_halaman_database_memperingatkan_konfigurasi_produksi_tidak_aman(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['queue.default' => 'sync', 'mail.default' => 'log']);

        $this->actingAs($this->admin)
            ->get('/setting/database')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Database')
                ->has('productionWarnings', 2));
    }

    public function test_admin_sees_migration_table_and_backup_status(): void
    {
        $this->user('dbsiswa');

        $response = $this->actingAs($this->admin)->get('/setting/database')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Setting/Database')
            ->has('driver')
            ->has('migrations')
            ->has('tables')
            ->has('totals')
            ->has('backups'));

        $props = $response->viewData('page')['props'];

        $this->assertSame(DB::getDriverName(), $props['driver']);
        $this->assertGreaterThan(0, $props['migrations']['total']);
        $this->assertSame($props['migrations']['total'], $props['migrations']['applied']);
        $this->assertSame([], $props['migrations']['pending']);

        $tables = collect($props['tables'])->keyBy('name');

        $this->assertTrue($tables->has('users'));
        $this->assertTrue($tables->has('migrations'));
        $this->assertSame(3, $tables['users']['rows']);
        $this->assertArrayHasKey('size', $tables['users']);
        $this->assertSame(array_sum(array_column($props['tables'], 'rows')), $props['totals']['rows']);
        $this->assertSame($tables->count(), count($props['tables']));
    }

    public function test_backup_endpoint_runs_command_and_reports_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('backup:run')->andReturn(0);

        $this->actingAs($this->admin)
            ->post('/setting/database/backup')
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_backup_endpoint_reports_failure(): void
    {
        Artisan::shouldReceive('call')->once()->with('backup:run')->andReturn(1);

        $this->actingAs($this->admin)
            ->post('/setting/database/backup')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_restore_ditolak_bila_konfirmasi_nama_backup_tidak_cocok(): void
    {
        $name = 'uji-restore-'.uniqid();
        $directory = storage_path('app/backups/'.$name);
        File::ensureDirectoryExists($directory);

        Artisan::shouldReceive('call')->never();

        try {
            $this->actingAs($this->admin)
                ->post('/setting/database/backup/'.$name.'/restore', ['confirm' => 'nama-lain'])
                ->assertRedirect()
                ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'Konfirmasi tidak cocok'));

            $this->actingAs($this->admin)
                ->post('/setting/database/backup/'.$name.'/restore', [])
                ->assertSessionHasErrors('confirm');

            $this->assertDirectoryExists($directory);
            $this->assertDatabaseMissing('log', [
                'log_desc' => 'database.restore: Database dipulihkan dari backup '.$name,
            ]);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_restore_dengan_konfirmasi_benar_menjalankan_perintah(): void
    {
        $name = 'uji-restore-'.uniqid();
        $directory = storage_path('app/backups/'.$name);
        File::ensureDirectoryExists($directory);

        try {
            // Backup kosong ditolak perintah, tetapi konfirmasi yang benar sudah
            // lolos guard sehingga pemulihan benar-benar dicoba.
            $this->actingAs($this->admin)
                ->post('/setting/database/backup/'.$name.'/restore', ['confirm' => $name])
                ->assertRedirect()
                ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'Pemulihan gagal'));

            $this->assertDatabaseMissing('log', [
                'log_desc' => 'database.restore: Database dipulihkan dari backup '.$name,
            ]);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_non_admin_cannot_access_database_manager(): void
    {
        $this->actingAs($this->guru)->get('/setting/database')->assertForbidden();
        $this->actingAs($this->guru)->post('/setting/database/backup')->assertForbidden();
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
