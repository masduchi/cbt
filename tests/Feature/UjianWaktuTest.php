<?php

namespace Tests\Feature;

use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use App\Support\UjianWaktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UjianWaktuTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_penuh_mengembalikan_waktu_ke_nol(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 10:00:00'));

        $durasi = DurasiSiswa::create([
            'id_durasi' => 1,
            'id_siswa' => 1,
            'id_jadwal' => 1,
            'status' => 1,
            'lama_ujian' => '00:30:00',
            'mulai' => now()->subMinutes(30),
            'reset' => 1,
        ]);

        (new UjianWaktu)->sentuh($durasi);

        $this->assertSame(0, (int) $durasi->reset);
        $this->assertSame('00:00:00', $durasi->lama_ujian);
        $this->assertSame(now()->getTimestamp(), Carbon::parse($durasi->mulai)->getTimestamp());
    }

    public function test_reset_penambahan_waktu_mempertahankan_sisa_waktu(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 10:00:00'));

        $durasi = DurasiSiswa::create([
            'id_durasi' => 2,
            'id_siswa' => 1,
            'id_jadwal' => 1,
            'status' => 1,
            'lama_ujian' => '00:20:00',
            'mulai' => now()->subMinutes(20),
            'reset' => 2,
        ]);

        (new UjianWaktu)->sentuh($durasi);

        $this->assertSame(0, (int) $durasi->reset);
        $this->assertSame('00:20:00', $durasi->lama_ujian);
    }

    public function test_sisa_detik_mengikuti_mulai_dan_durasi(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 10:00:00'));

        $jadwal = new Jadwal;
        $jadwal->durasi_ujian = 30;

        $waktu = new UjianWaktu;

        $this->assertSame(1800, $waktu->sisaDetik($jadwal, null));

        $durasi = new DurasiSiswa(['mulai' => now()->subMinutes(10)]);

        $this->assertSame(1200, $waktu->sisaDetik($jadwal, $durasi));

        $durasi->mulai = now()->subMinutes(40);

        $this->assertSame(0, $waktu->sisaDetik($jadwal, $durasi));
    }
}
