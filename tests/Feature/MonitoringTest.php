<?php

namespace Tests\Feature;

use App\Support\AlertNotifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    private const WEBHOOK = 'https://hooks.example.test/alert';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_alert_is_posted_to_configured_webhook(): void
    {
        config(['monitoring.alert_webhook' => self::WEBHOOK]);
        Http::fake();

        AlertNotifier::send('Backup gagal', ['path' => '/tmp/backup']);

        Http::assertSent(function ($request) {
            return $request->url() === self::WEBHOOK
                && $request['text'] === 'Backup gagal'
                && $request['context'] === ['path' => '/tmp/backup'];
        });
    }

    public function test_critical_alert_is_throttled_per_key(): void
    {
        config(['monitoring.alert_webhook' => self::WEBHOOK, 'monitoring.throttle_minutes' => 10]);
        Http::fake();

        AlertNotifier::critical('error:abc', 'Error pertama');
        AlertNotifier::critical('error:abc', 'Error kedua');
        AlertNotifier::critical('error:lain', 'Error kunci lain');

        Http::assertSentCount(2);
    }

    public function test_alert_without_webhook_only_writes_log(): void
    {
        config(['monitoring.alert_webhook' => null]);
        Http::fake();
        Log::spy();

        AlertNotifier::send('Tanpa webhook');

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_health_command_passes_when_application_is_healthy(): void
    {
        config(['monitoring.health_url' => 'https://cbt.example.test/health', 'monitoring.alert_webhook' => self::WEBHOOK]);
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->artisan('monitor:health')->assertExitCode(0);

        Http::assertNotSent(fn ($request) => $request->url() === self::WEBHOOK);
    }

    public function test_health_command_alerts_when_application_is_degraded(): void
    {
        config(['monitoring.health_url' => 'https://cbt.example.test/health', 'monitoring.alert_webhook' => self::WEBHOOK]);
        Http::fake([
            'cbt.example.test/*' => Http::response(['status' => 'degraded', 'checks' => ['database' => false]], 503),
            '*' => Http::response([], 200),
        ]);

        $this->artisan('monitor:health')->assertExitCode(1);

        Http::assertSent(fn ($request) => $request->url() === self::WEBHOOK);
    }

    public function test_health_command_alerts_when_endpoint_is_unreachable(): void
    {
        config(['monitoring.health_url' => 'https://cbt.example.test/health', 'monitoring.alert_webhook' => self::WEBHOOK]);
        Http::fake([
            'cbt.example.test/*' => function () {
                throw new ConnectionException('Connection refused');
            },
            '*' => Http::response([], 200),
        ]);

        $this->artisan('monitor:health')->assertExitCode(1);

        Http::assertSent(fn ($request) => $request->url() === self::WEBHOOK);
    }
}
