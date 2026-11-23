<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/backups/uji-verify-'.uniqid());
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_pemulihan_menolak_nama_di_luar_folder_backup(): void
    {
        $this->artisan('backup:restore', ['name' => '../.env', '--force' => true])
            ->expectsOutputToContain('Folder backup tidak ditemukan')
            ->assertExitCode(1);
    }

    public function test_pemulihan_menolak_backup_tanpa_dump_database(): void
    {
        $this->artisan('backup:restore', [
            'name' => basename($this->directory),
            '--force' => true,
        ])
            ->expectsOutputToContain('tidak layak dipulihkan')
            ->assertExitCode(1);
    }

    public function test_perintah_verifikasi_menerima_dump_valid_dan_menulis_manifest(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'database.sql.gz', gzencode("CREATE TABLE contoh (id int);\n"));

        $this->artisan('backup:verify', ['path' => $this->directory])->assertExitCode(0);

        $manifest = json_decode((string) File::get($this->directory.DIRECTORY_SEPARATOR.'verify.json'), true);

        $this->assertIsArray($manifest);
        $this->assertSame('database.sql.gz', $manifest['database']);
        $this->assertGreaterThan(0, $manifest['bytes']);
    }

    public function test_perintah_verifikasi_menolak_dump_rusak(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'database.sql.gz', gzencode('bukan dump database'));

        $this->artisan('backup:verify', ['path' => $this->directory])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'verify.json');
    }

    public function test_health_melaporkan_backup_terverifikasi(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'database.sql.gz', gzencode("CREATE TABLE contoh (id int);\n"));
        File::put(
            $this->directory.DIRECTORY_SEPARATOR.'verify.json',
            json_encode(['database' => 'database.sql.gz'])
        );

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('backup_verified', true);
    }
}
