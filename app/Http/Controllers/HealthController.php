<?php

namespace App\Http\Controllers;

use App\Support\BackupOffsite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $queue = $this->queueStatus();
        $notifikasi = $this->notificationStatus();

        $backup = $this->latestBackup();
        $staleAfterDays = (int) config('backup.retention_days', 14) + 1;
        $backupStale = $backup === null || $backup['age_hours'] > $staleAfterDays * 24;

        $offsite = $this->offsiteStatus($backup);
        $heartbeat = Cache::get('monitor:scheduler_heartbeat');
        $heartbeatAt = is_numeric($heartbeat) ? (int) $heartbeat : null;
        $schedulerOk = ! app()->environment('production')
            || ($heartbeatAt !== null && $heartbeatAt >= now()->subMinutes(10)->getTimestamp());

        $checks = [
            'database' => $this->databaseOk(),
            'storage' => File::isWritable(storage_path()),
            'cache' => File::isWritable(storage_path('framework/cache')),
            'queue' => $queue['ok'],
            'notifikasi' => $notifikasi['ok'],
            // Backup yang tidak ada atau belum lolos verifikasi sama berbahayanya
            // dengan backup basi: pemulihan saat insiden belum tentu bisa dilakukan.
            'backup' => ! $backupStale && (bool) ($backup['verified'] ?? false),
            'backup_offsite' => $offsite['ok'],
            'scheduler' => $schedulerOk,
            'konfigurasi' => $this->productionConfigOk(),
        ];

        $healthy = ! in_array(false, $checks, true);

        if (! $this->tokenValid($request)) {
            return response()->json([
                'status' => $healthy ? 'ok' : 'degraded',
                'time' => now()->toIso8601String(),
            ], $healthy ? 200 : 503);
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'latest_backup' => $backup['name'] ?? null,
            'backup_age_hours' => $backup['age_hours'] ?? null,
            'backup_stale' => $backupStale,
            'backup_verified' => (bool) ($backup['verified'] ?? false),
            'backup_offsite' => $offsite,
            'scheduler_heartbeat_at' => $heartbeatAt ? Carbon::createFromTimestamp($heartbeatAt)->toIso8601String() : null,
            'queue' => $queue,
            'notifikasi' => $notifikasi,
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * Bila HEALTH_TOKEN diatur, detail operasional hanya diberikan kepada
     * pemanggil yang membawa token. Tanpa token, pemantau uptime tetap
     * menerima status ringkas agar tidak ada informasi internal yang terbuka.
     */
    private function tokenValid(Request $request): bool
    {
        $token = (string) config('monitoring.health_token');

        if ($token === '') {
            return true;
        }

        $provided = (string) ($request->header('X-Health-Token') ?: $request->query('token', ''));

        return $provided !== '' && hash_equals($token, $provided);
    }

    /**
     * @param  array{name: string, age_hours: int, verified: bool}|null  $backup
     * @return array{configured: bool, ok: bool, name: string|null}
     */
    private function offsiteStatus(?array $backup): array
    {
        if (! BackupOffsite::configured()) {
            return ['configured' => false, 'ok' => true, 'name' => null];
        }

        return [
            'configured' => true,
            'ok' => $backup !== null && BackupOffsite::exists($backup['name']),
            'name' => $backup['name'] ?? null,
        ];
    }

    /**
     * Antrean berbasis database hanya diproses bila ada worker. Tanpa
     * pemeriksaan ini, job menumpuk diam-diam dan baru ketahuan saat gagal.
     *
     * @return array{driver: string, pending: int, failed: int, backlog_limit: int, ok: bool}
     */
    private function queueStatus(): array
    {
        $driver = (string) config('queue.default');
        $limit = 50;

        if ($driver !== 'database') {
            return ['driver' => $driver, 'pending' => 0, 'failed' => 0, 'backlog_limit' => $limit, 'ok' => true];
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return ['driver' => $driver, 'pending' => 0, 'failed' => 0, 'backlog_limit' => $limit, 'ok' => false];
        }

        return [
            'driver' => $driver,
            'pending' => $pending,
            'failed' => $failed,
            'backlog_limit' => $limit,
            'ok' => $pending <= $limit && $failed === 0,
        ];
    }

    /**
     * Notifikasi yang gagal permanen tidak selalu tampil di failed_jobs setelah
     * job dibersihkan, sehingga penandanya ikut dipantau di sini.
     *
     * @return array{gagal: int, ok: bool}
     */
    private function notificationStatus(): array
    {
        try {
            $gagal = DB::table('cbt_nilai')->whereNotNull('hasil_notify_failed_at')->count();
        } catch (Throwable) {
            return ['gagal' => 0, 'ok' => false];
        }

        return ['gagal' => $gagal, 'ok' => $gagal === 0];
    }

    private function databaseOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Di produksi, antrean "sync" menjalankan pekerjaan di dalam request dan
     * mailer "log" membuang notifikasi tanpa jejak. Keduanya lolos tanpa
     * peringatan bila hanya diperiksa pada saat konfigurasi ditulis.
     */
    private function productionConfigOk(): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        return (string) config('queue.default') !== 'sync'
            && (string) config('mail.default') !== 'log';
    }

    /**
     * @return array{name: string, age_hours: int, verified: bool}|null
     */
    private function latestBackup(): ?array
    {
        $directories = File::directories(storage_path('app/backups'));

        if ($directories === []) {
            return null;
        }

        usort($directories, fn (string $a, string $b) => File::lastModified($b) <=> File::lastModified($a));

        $latest = $directories[0];

        $manifest = $latest.DIRECTORY_SEPARATOR.'verify.json';
        $verified = false;

        if (File::exists($manifest)) {
            $data = json_decode((string) File::get($manifest), true);
            $verified = is_array($data) && ! empty($data['database']);
        }

        return [
            'name' => basename($latest),
            'age_hours' => (int) floor((time() - File::lastModified($latest)) / 3600),
            'verified' => $verified,
        ];
    }
}
