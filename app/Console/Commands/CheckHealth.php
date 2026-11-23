<?php

namespace App\Console\Commands;

use App\Support\AlertNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckHealth extends Command
{
    protected $signature = 'monitor:health';

    protected $description = 'Memeriksa endpoint /health dan mengirim peringatan bila tidak sehat';

    public function handle(): int
    {
        $url = (string) config('monitoring.health_url');

        if ($url === '') {
            $this->error('HEALTH_URL belum diatur.');

            return self::INVALID;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
        } catch (Throwable $e) {
            AlertNotifier::critical('health:unreachable', 'Health check tidak bisa dihubungi: '.$url, [
                'error' => $e->getMessage(),
            ]);

            $this->error('Tidak bisa menghubungi '.$url);

            return self::FAILURE;
        }

        $status = $response->json('status');

        if ($response->successful() && $status === 'ok') {
            $this->info('Sehat: '.$url);

            return self::SUCCESS;
        }

        AlertNotifier::critical(
            'health:'.$status,
            sprintf('Aplikasi tidak sehat (HTTP %d) pada %s', $response->status(), $url),
            ['payload' => $response->json()],
        );

        $this->error(sprintf('Tidak sehat: HTTP %d', $response->status()));

        return self::FAILURE;
    }
}
