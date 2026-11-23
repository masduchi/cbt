<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($this->backupDir);
    }

    protected function tearDown(): void
    {
        foreach (File::directories($this->backupDir) as $directory) {
            File::deleteDirectory($directory);
        }

        File::deleteDirectory(storage_path('app/uji-offsite'));

        parent::tearDown();
    }

    public function test_health_endpoint_reports_core_checks(): void
    {
        $this->simpanBackup('20260101-010000');

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.storage', true)
            ->assertJsonPath('checks.cache', true)
            ->assertJsonPath('checks.notifikasi', true)
            ->assertJsonPath('checks.backup', true)
            ->assertJsonPath('checks.konfigurasi', true)
            ->assertJsonPath('backup_verified', true)
            ->assertJsonPath('notifikasi.gagal', 0);
    }

    public function test_health_endpoint_flags_missing_backup(): void
    {
        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('backup_stale', true)
            ->assertJsonPath('backup_verified', false)
            ->assertJsonPath('checks.backup', false);
    }

    public function test_health_endpoint_flags_unverified_backup(): void
    {
        $this->simpanBackup('20260102-010000', verified: false);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('backup_stale', false)
            ->assertJsonPath('backup_verified', false)
            ->assertJsonPath('checks.backup', false);
    }

    public function test_health_endpoint_flags_unsafe_production_config(): void
    {
        $this->simpanBackup('20260103-010000');
        $this->app->detectEnvironment(fn () => 'production');
        config(['queue.default' => 'sync', 'mail.default' => 'log']);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('environment', 'production')
            ->assertJsonPath('checks.konfigurasi', false);
    }

    public function test_health_endpoint_accepts_safe_production_config(): void
    {
        $this->simpanBackup('20260104-010000');
        $this->app->detectEnvironment(fn () => 'production');
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);
        Cache::put('monitor:scheduler_heartbeat', now()->getTimestamp(), now()->addMinutes(15));

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.konfigurasi', true)
            ->assertJsonPath('checks.scheduler', true);
    }

    public function test_health_endpoint_flags_missing_scheduler_heartbeat(): void
    {
        $this->simpanBackup('20260107-010000');
        $this->app->detectEnvironment(fn () => 'production');
        config(['queue.default' => 'database', 'mail.default' => 'smtp']);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('checks.scheduler', false)
            ->assertJsonPath('scheduler_heartbeat_at', null);
    }

    public function test_health_endpoint_menyembunyikan_detail_tanpa_token(): void
    {
        $this->simpanBackup('20260106-010000');
        config(['monitoring.health_token' => 'rahasia-health']);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissingPath('checks')
            ->assertJsonMissingPath('latest_backup');

        $this->withHeader('X-Health-Token', 'rahasia-health')
            ->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.backup', true)
            ->assertJsonPath('latest_backup', '20260106-010000');
    }

    public function test_health_endpoint_menandai_backup_offsite_hilang(): void
    {
        $this->simpanBackup('20260108-010000');
        $offsite = storage_path('app/uji-offsite');
        File::ensureDirectoryExists($offsite);
        config(['backup.offsite_path' => $offsite]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('checks.backup_offsite', false);

        File::ensureDirectoryExists($offsite.DIRECTORY_SEPARATOR.'20260108-010000');

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.backup_offsite', true);
    }

    public function test_health_endpoint_reports_empty_database_queue(): void
    {
        $this->simpanBackup('20260105-010000');
        config(['queue.default' => 'database']);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('checks.queue', true)
            ->assertJsonPath('queue.driver', 'database')
            ->assertJsonPath('queue.pending', 0)
            ->assertJsonPath('queue.failed', 0);
    }

    public function test_health_endpoint_flags_failed_jobs(): void
    {
        config(['queue.default' => 'database']);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Job gagal',
            'failed_at' => now(),
        ]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue', false)
            ->assertJsonPath('queue.failed', 1);
    }

    public function test_health_endpoint_flags_permanently_failed_result_notifications(): void
    {
        DB::table('cbt_nilai')->insert([
            'id_nilai' => 1,
            'hasil_notify_failed_at' => now(),
            'hasil_notify_error' => 'Mailer "log" tidak mengirim email',
        ]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.notifikasi', false)
            ->assertJsonPath('notifikasi.gagal', 1);
    }

    public function test_health_endpoint_flags_queue_backlog(): void
    {
        config(['queue.default' => 'database']);

        $rows = [];
        $now = time();

        for ($i = 1; $i <= 51; $i++) {
            $rows[] = [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ];
        }

        DB::table('jobs')->insert($rows);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('checks.queue', false)
            ->assertJsonPath('queue.pending', 51);
    }

    public function test_health_endpoint_flags_stale_backup(): void
    {
        $path = $this->backupDir.DIRECTORY_SEPARATOR.'20200101-010000';
        File::ensureDirectoryExists($path);

        $stale = time() - ((int) config('backup.retention_days', 14) + 3) * 86400;
        touch($path, $stale);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('backup_stale', true)
            ->assertJsonPath('checks.backup', false);
    }

    public function test_health_endpoint_accepts_fresh_backup(): void
    {
        $this->simpanBackup('20260101-010000');

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('backup_stale', false)
            ->assertJsonPath('backup_verified', true)
            ->assertJsonPath('latest_backup', '20260101-010000');
    }

    private function simpanBackup(string $name, bool $verified = true): void
    {
        $path = $this->backupDir.DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($path);
        touch($path, time() - 3600);

        if ($verified) {
            File::put($path.DIRECTORY_SEPARATOR.'verify.json', json_encode(['database' => true]));
        }
    }
}
