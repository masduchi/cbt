<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Jejak aktivitas sesi ujian per peserta.
 *
 * Pelanggaran sesi ganda hanya dilaporkan bila dua sesi benar-benar aktif
 * bersamaan, yaitu sesi lain masih mengirim aktivitas setelah sesi ini pertama
 * kali terlihat. Perpindahan perangkat biasa (browser restart, sesi kedaluwarsa,
 * atau jaringan lab berganti) tidak dianggap pelanggaran.
 */
class UjianSessionGuard
{
    /** Jendela waktu dua sesi dianggap aktif bersamaan (detik). */
    public const OVERLAP_SECONDS = 180;

    /** Lama jejak aktivitas sesi disimpan di cache (menit). */
    public const TTL_MINUTES = 30;

    /** Jarak minimal pembaruan jejak sesi (detik). */
    private const TOUCH_SECONDS = 30;

    /**
     * Catat aktivitas sesi dan kembalikan sesi lain yang masih aktif bersamaan.
     *
     * @return array{ip: string, at: int, detik: int}|null
     */
    public static function record(int $jadwalId, int $siswaId, string $sessionId, string $ip, ?int $now = null): ?array
    {
        $now ??= now()->getTimestamp();
        $key = self::key($jadwalId, $siswaId);
        $sessions = Cache::get($key, []);

        if (! is_array($sessions)) {
            $sessions = [];
        }

        $sessions = array_filter(
            $sessions,
            fn ($row) => is_array($row) && $now - (int) ($row['at'] ?? 0) <= self::OVERLAP_SECONDS * 2
        );

        $current = $sessions[$sessionId] ?? ['first' => $now, 'at' => $now, 'ip' => $ip];
        $current['at'] = $now;
        $current['ip'] = $ip;

        $lain = null;

        foreach ($sessions as $id => $row) {
            if ($id === $sessionId) {
                continue;
            }

            // Sesi lain aktif setelah sesi ini muncul: keduanya hidup bersamaan.
            if ((int) ($row['at'] ?? 0) > (int) ($current['first'] ?? $now)) {
                $lain = $row;
            }
        }

        $sebelumnya = (int) ($sessions[$sessionId]['at'] ?? 0);
        $sessions[$sessionId] = $current;

        // Guard berjalan pada setiap simpan jawaban; jendela overlap tiga menit
        // cukup diperbarui tiap 30 detik agar tidak menulis cache terus-menerus.
        if ($lain !== null || $now - $sebelumnya >= self::TOUCH_SECONDS) {
            Cache::put($key, $sessions, now()->addMinutes(self::TTL_MINUTES));
        }

        if ($lain === null) {
            return null;
        }

        return [
            'ip' => (string) ($lain['ip'] ?? ''),
            'at' => (int) ($lain['at'] ?? $now),
            'detik' => max(0, $now - (int) ($lain['at'] ?? $now)),
        ];
    }

    /**
     * Hapus jejak sesi, mis. saat peserta memulai percobaan baru.
     */
    public static function forget(int $jadwalId, int $siswaId): void
    {
        Cache::forget(self::key($jadwalId, $siswaId));
    }

    private static function key(int $jadwalId, int $siswaId): string
    {
        return sprintf('ujian-sesi:%s:%s', $jadwalId, $siswaId);
    }
}
