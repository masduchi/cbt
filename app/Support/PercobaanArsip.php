<?php

namespace App\Support;

use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use App\Models\Nilai;
use App\Models\PercobaanRiwayat;
use App\Models\SoalSiswa;
use Illuminate\Support\Facades\DB;

class PercobaanArsip
{
    /**
     * Salin jawaban, nilai, dan durasi peserta sebelum datanya dihapus.
     *
     * Riwayat hanya disimpan bila peserta benar-benar punya jejak percobaan,
     * sehingga reset pada peserta yang belum mulai tidak membuat baris kosong.
     *
     * @param  array<int, int>  $idsSiswa
     */
    public static function simpan(Jadwal $jadwal, array $idsSiswa, string $alasan): int
    {
        $idsSiswa = array_values(array_unique(array_map('intval', $idsSiswa)));

        if ($idsSiswa === []) {
            return 0;
        }

        $nilai = Nilai::where('id_jadwal', $jadwal->id_jadwal)
            ->whereIn('id_siswa', $idsSiswa)
            ->get()
            ->keyBy('id_siswa');

        $durasi = DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->whereIn('id_siswa', $idsSiswa)
            ->get()
            ->keyBy('id_siswa');

        $jawaban = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->whereIn('id_siswa', $idsSiswa)
            ->get()
            ->groupBy('id_siswa');

        $percobaan = DB::table('cbt_remedial_peserta')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereIn('id_siswa', $idsSiswa)
            ->pluck('percobaan', 'id_siswa');

        $tersimpan = 0;

        foreach ($idsSiswa as $idSiswa) {
            $skor = $nilai->get($idSiswa);
            $sesi = $durasi->get($idSiswa);
            $daftarJawaban = $jawaban->get($idSiswa);

            if (! $skor && ! $sesi && ! $daftarJawaban) {
                continue;
            }

            $komponen = $skor ? [
                'pg' => (float) $skor->pg_nilai,
                'kompleks' => (float) $skor->kompleks_nilai,
                'jodohkan' => (float) $skor->jodohkan_nilai,
                'isian' => (float) $skor->isian_nilai,
                'esai' => (float) $skor->essai_nilai,
            ] : null;

            PercobaanRiwayat::create([
                'id_jadwal' => $jadwal->id_jadwal,
                'id_siswa' => $idSiswa,
                'id_jadwal_induk' => $jadwal->id_jadwal_induk,
                'percobaan' => (int) ($percobaan[$idSiswa] ?? 0),
                'total' => $komponen !== null ? round(array_sum($komponen), 2) : null,
                'komponen' => $komponen,
                'jawaban' => $daftarJawaban?->map(fn (SoalSiswa $soal) => [
                    'id_soal' => (int) $soal->id_soal,
                    'jenis' => (int) $soal->jenis_soal,
                    'nomor' => (int) $soal->no_soal_alias,
                    'jawaban' => $soal->jawaban_siswa,
                    'benar' => $soal->jawaban_benar,
                    'point' => (int) $soal->point_soal,
                    'nilai_otomatis' => (int) $soal->nilai_otomatis,
                    'nilai_koreksi' => (float) $soal->nilai_koreksi,
                ])->values()->all(),
                'alasan' => $alasan,
                'mulai' => $sesi?->mulai,
                'selesai' => $sesi?->selesai,
                'lama_ujian' => $sesi?->lama_ujian,
                'time_create' => now(),
            ]);

            $tersimpan++;
        }

        return $tersimpan;
    }
}
