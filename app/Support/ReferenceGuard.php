<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ReferenceGuard
{
    private const SISWA = [
        'kelas_siswa' => 'riwayat kelas',
        'cbt_nilai' => 'nilai CBT',
        'cbt_soal_siswa' => 'jawaban ujian',
        'cbt_durasi_siswa' => 'durasi ujian',
        'cbt_rekap_nilai' => 'rekap nilai',
        'cbt_nomor_peserta' => 'nomor peserta',
        'log_ujian' => 'log ujian',
        'log_materi' => 'aktivitas e-learning',
        'buku_induk' => 'buku induk',
        'rapor_nilai_akhir' => 'nilai rapor',
        'rapor_nilai_harian' => 'nilai harian',
        'rapor_nilai_pts' => 'nilai PTS',
        'rapor_nilai_ekstra' => 'nilai ekstrakurikuler',
        'rapor_nilai_sikap' => 'nilai sikap',
        'rapor_fisik' => 'data fisik',
        'rapor_prestasi' => 'prestasi',
        'rapor_naik' => 'kenaikan kelas',
        'rapor_catatan_wali' => 'catatan wali',
    ];

    private const KELAS = [
        'kelas_siswa' => 'anggota kelas',
        'kelas_jadwal_mapel' => 'jadwal mapel',
        'kelas_jadwal_materi' => 'jadwal materi',
        'kelas_jadwal_kbm' => 'jadwal KBM',
        'kelas_ekstra' => 'ekstrakurikuler kelas',
        'kelas_struktur' => 'struktur kelas',
        'kelas_catatan_mapel' => 'catatan mapel',
        'kelas_catatan_wali' => 'catatan wali',
        'cbt_kelas_ruang' => 'ruang ujian',
        'cbt_rekap_nilai' => 'rekap nilai',
        'jabatan_guru' => 'jabatan guru',
        'rapor_nilai_akhir' => 'nilai rapor',
        'rapor_nilai_harian' => 'nilai harian',
        'rapor_nilai_pts' => 'nilai PTS',
        'rapor_nilai_ekstra' => 'nilai ekstrakurikuler',
        'rapor_nilai_sikap' => 'nilai sikap',
        'rapor_kkm' => 'KKM',
        'rapor_data_sikap' => 'data sikap',
        'rapor_data_fisik' => 'data fisik',
        'rapor_data_catatan' => 'catatan rapor',
        'rapor_prestasi' => 'prestasi',
        'rapor_fisik' => 'data fisik',
        'rapor_catatan_wali' => 'catatan wali',
        'rapor_kunci' => 'kunci rapor',
    ];

    /**
     * Sebagian tabel memakai nama kolom relasi yang berbeda, sehingga tiap
     * entri dapat menyebut kolomnya sendiri: [label, kolom].
     */
    private const GURU = [
        'master_kelas' => ['wali kelas', 'guru_id'],
        'kelas_materi' => ['materi e-learning', 'id_guru'],
        'kelas_catatan_mapel' => ['catatan mapel', 'id_guru'],
        'cbt_bank_soal' => ['bank soal', 'bank_guru_id'],
        'cbt_pengawas' => ['pengawas ujian', 'id_guru'],
        'cbt_rekap' => ['rekap ujian', 'id_guru'],
        'cbt_rekap_nilai' => ['rekap nilai', 'id_guru'],
        'jabatan_guru' => ['jabatan guru', 'id_guru'],
    ];

    private const TAHUN = [
        'master_kelas' => 'kelas',
        'kelas_siswa' => 'anggota kelas',
        'kelas_materi' => 'materi e-learning',
        'kelas_jadwal_mapel' => 'jadwal mapel',
        'kelas_jadwal_materi' => 'jadwal materi',
        'kelas_catatan_mapel' => 'catatan mapel',
        'kelas_catatan_wali' => 'catatan wali',
        'kelas_ekstra' => 'ekstrakurikuler kelas',
        'jabatan_guru' => 'jabatan guru',
        'cbt_jadwal' => 'jadwal ujian',
        'cbt_bank_soal' => 'bank soal',
        'cbt_kelas_ruang' => 'ruang ujian',
        'cbt_nomor_peserta' => 'nomor peserta',
        'cbt_pengawas' => 'pengawas ujian',
        'cbt_rekap' => 'rekap ujian',
        'cbt_rekap_nilai' => 'rekap nilai',
        'rapor_kkm' => 'KKM',
        'rapor_nilai_akhir' => 'nilai rapor',
        'rapor_naik' => 'kenaikan kelas',
    ];

    private const MAPEL = [
        'cbt_bank_soal' => ['bank soal', 'bank_mapel_id'],
        'cbt_soal' => ['butir soal', 'mapel_id'],
        'kelas_jadwal_mapel' => ['jadwal mapel', 'id_mapel'],
        'kelas_jadwal_materi' => ['jadwal materi', 'id_mapel'],
        'kelas_materi' => ['materi e-learning', 'id_mapel'],
        'kelas_catatan_mapel' => ['catatan mapel', 'id_mapel'],
        'cbt_rekap' => ['rekap ujian', 'id_mapel'],
        'cbt_rekap_nilai' => ['rekap nilai', 'id_mapel'],
        'log_materi' => ['aktivitas e-learning', 'id_mapel'],
        'rapor_kkm' => ['KKM', 'id_mapel'],
        'rapor_nilai_akhir' => ['nilai rapor', 'id_mapel'],
        'rapor_nilai_harian' => ['nilai harian', 'id_mapel'],
        'rapor_nilai_pts' => ['nilai PTS', 'id_mapel'],
    ];

    public static function siswa(int $id): array
    {
        return self::check(self::SISWA, 'id_siswa', $id);
    }

    public static function kelas(int $id): array
    {
        return self::check(self::KELAS, 'id_kelas', $id);
    }

    public static function guru(int $id): array
    {
        return self::check(self::GURU, 'id_guru', $id);
    }

    public static function mapel(int $id): array
    {
        return self::check(self::MAPEL, 'id_mapel', $id);
    }

    public static function tahun(int $id): array
    {
        return self::check(self::TAHUN, 'id_tp', $id);
    }

    private static function check(array $tables, string $column, int $id): array
    {
        $used = [];

        foreach ($tables as $table => $meta) {
            [$label, $columnName] = is_array($meta) ? $meta : [$meta, $column];

            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            if (! DB::getSchemaBuilder()->hasColumn($table, $columnName)) {
                continue;
            }

            if (DB::table($table)->where($columnName, $id)->exists()) {
                $used[] = $label;
            }
        }

        return array_values(array_unique($used));
    }
}
