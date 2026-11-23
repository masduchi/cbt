<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_hot_query_paths_have_composite_indexes(): void
    {
        $expected = [
            'kelas_siswa' => ['kelas_siswa_periode_kelas_idx', 'kelas_siswa_periode_siswa_idx'],
            'cbt_nilai' => ['cbt_nilai_jadwal_siswa_idx', 'cbt_nilai_jadwal_koreksi_idx'],
            'cbt_soal_siswa' => ['cbt_soal_siswa_jadwal_siswa_idx'],
            'cbt_durasi_siswa' => ['cbt_durasi_jadwal_status_idx'],
            'rapor_nilai_harian' => ['rapor_harian_periode_idx', 'rapor_harian_siswa_idx'],
            'rapor_nilai_pts' => ['rapor_pts_periode_idx'],
            'rapor_nilai_akhir' => ['rapor_akhir_periode_idx'],
            'cbt_jadwal' => ['cbt_jadwal_periode_status_idx'],
        ];

        foreach ($expected as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), "Index {$index} tidak ada pada tabel {$table}.");
            }
        }
    }
}
