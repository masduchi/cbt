<?php

namespace App\Support;

use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use Illuminate\Support\Carbon;

/**
 * Perhitungan waktu ujian: sisa waktu, waktu berjalan, dan penerapan reset dari
 * pengawas. Dipisahkan dari controller agar aturan waktu dapat diuji sendiri.
 */
class UjianWaktu
{
    /**
     * Terapkan permintaan reset pengawas, lalu segarkan lama ujian berjalan.
     */
    public function sentuh(DurasiSiswa $durasi): void
    {
        $reset = (int) $durasi->reset;

        if ($reset === 1) {
            $durasi->lama_ujian = '00:00:00';
            $durasi->mulai = now();
            $durasi->reset = 0;
        } elseif ($reset === 2) {
            $parts = array_map('intval', explode(':', (string) $durasi->lama_ujian));
            $seconds = ($parts[0] ?? 0) * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
            $durasi->mulai = now()->subSeconds($seconds);
            $durasi->reset = 0;
        }

        $durasi->lama_ujian = gmdate('H:i:s', min($this->terlampaui($durasi), 359999));
        $durasi->save();
    }

    /**
     * Detik yang sudah berjalan sejak peserta mulai mengerjakan.
     */
    public function terlampaui(DurasiSiswa $durasi): int
    {
        if (! $durasi->mulai) {
            return 0;
        }

        return max(0, Carbon::parse($durasi->mulai)->diffInSeconds(now()));
    }

    /**
     * Sisa waktu ujian dalam detik; tanpa percobaan aktif memakai durasi penuh.
     */
    public function sisaDetik(Jadwal $jadwal, ?DurasiSiswa $durasi): int
    {
        if (! $durasi || ! $durasi->mulai) {
            return (int) $jadwal->durasi_ujian * 60;
        }

        return max(0, (int) $jadwal->durasi_ujian * 60 - $this->terlampaui($durasi));
    }
}
