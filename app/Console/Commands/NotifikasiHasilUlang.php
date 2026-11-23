<?php

namespace App\Console\Commands;

use App\Jobs\KirimNotifikasiHasilUjian;
use Illuminate\Console\Command;

class NotifikasiHasilUlang extends Command
{
    protected $signature = 'cbt:notifikasi-ulang
                            {--jadwal= : Batasi ke satu ID jadwal ujian}';

    protected $description = 'Antre ulang notifikasi hasil ujian yang gagal permanen';

    public function handle(): int
    {
        $jadwal = $this->option('jadwal');
        $jadwalId = $jadwal !== null && $jadwal !== '' ? (int) $jadwal : null;

        $jumlah = KirimNotifikasiHasilUjian::ulangGagal($jadwalId);

        if ($jumlah === 0) {
            $this->info('Tidak ada notifikasi hasil ujian yang perlu dikirim ulang.');

            return self::SUCCESS;
        }

        $this->info($jumlah.' notifikasi hasil ujian diantrekan ulang.');

        return self::SUCCESS;
    }
}
