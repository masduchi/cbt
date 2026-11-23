<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlertNotifier
{
    /**
     * Kirim peringatan ke webhook. Payload memuat "text" (Slack) dan "content"
     * (Discord) sekaligus supaya satu URL bisa dipakai keduanya.
     *
     * @param  array<string, mixed>  $context
     */
    public static function send(string $message, array $context = []): void
    {
        $url = (string) config('monitoring.alert_webhook');

        if ($url === '') {
            Log::warning('Peringatan tanpa webhook: '.$message, $context);

            return;
        }

        try {
            Http::timeout(5)->post($url, [
                'text' => $message,
                'content' => $message,
                'context' => $context,
            ]);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim peringatan', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Kirim peringatan yang dibatasi laju: kunci yang sama hanya dikirim sekali
     * per rentang waktu tertentu.
     *
     * @param  array<string, mixed>  $context
     */
    public static function critical(string $key, string $message, array $context = []): void
    {
        $minutes = max(1, (int) config('monitoring.throttle_minutes', 10));

        if (! Cache::add('alert:'.$key, true, now()->addMinutes($minutes))) {
            return;
        }

        self::send($message, $context);
    }
}
