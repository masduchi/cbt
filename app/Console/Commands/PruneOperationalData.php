<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneOperationalData extends Command
{
    protected $signature = 'cbt:prune {--days=30 : Umur data operasional yang disimpan}';

    protected $description = 'Menghapus percobaan login, sesi, dan job gagal yang sudah lama';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = now()->subDays($days)->getTimestamp();

        $attempts = Schema::hasTable('login_attempts')
            ? DB::table('login_attempts')->where('time', '<', $limit)->delete()
            : 0;

        $sessions = Schema::hasTable('sessions')
            ? DB::table('sessions')->where('last_activity', '<', $limit)->delete()
            : 0;

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '<', now()->subDays($days))->delete()
            : 0;

        $this->info("Dihapus: {$attempts} percobaan login, {$sessions} sesi, {$failedJobs} job gagal.");

        return self::SUCCESS;
    }
}
