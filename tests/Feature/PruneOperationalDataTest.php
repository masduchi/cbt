<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_menghapus_data_lama_dan_mempertahankan_yang_baru(): void
    {
        $lama = now()->subDays(40)->getTimestamp();
        $baru = now()->getTimestamp();

        DB::table('login_attempts')->insert([
            ['ip_address' => '10.0.0.1', 'login' => 'lama', 'time' => $lama],
            ['ip_address' => '10.0.0.2', 'login' => 'baru', 'time' => $baru],
        ]);

        DB::table('sessions')->insert([
            ['id' => 'sesi-lama', 'user_id' => null, 'ip_address' => '10.0.0.1', 'user_agent' => 'uji', 'payload' => 'x', 'last_activity' => $lama],
            ['id' => 'sesi-baru', 'user_id' => null, 'ip_address' => '10.0.0.2', 'user_agent' => 'uji', 'payload' => 'x', 'last_activity' => $baru],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'gagal',
            'failed_at' => now()->subDays(40),
        ]);

        $this->artisan('cbt:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('login_attempts', ['login' => 'lama']);
        $this->assertDatabaseHas('login_attempts', ['login' => 'baru']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lama']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-baru']);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
