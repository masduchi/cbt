<?php

namespace App\Support;

use App\Models\Jadwal;
use App\Models\Siswa;
use App\Models\Soal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membekukan pilihan soal untuk satu peserta saat ujian dimulai. Snapshot
 * menyimpan urutan, opsi teracak, dan bobot per soal sehingga perubahan bank
 * soal setelah ujian berjalan tidak mengubah isi ujian peserta.
 */
class SnapshotSoal
{
    public function buat(Jadwal $jadwal, Siswa $siswa, int $durasiId): void
    {
        $bank = $jadwal->bank;
        $limits = [
            1 => (int) $bank->tampil_pg,
            2 => (int) $bank->tampil_kompleks,
            3 => (int) $bank->tampil_jodohkan,
            4 => (int) $bank->tampil_isian,
            5 => (int) $bank->tampil_esai,
        ];
        $bobot = [
            1 => (int) $bank->bobot_pg,
            2 => (int) $bank->bobot_kompleks,
            3 => (int) $bank->bobot_jodohkan,
            4 => (int) $bank->bobot_isian,
            5 => (int) $bank->bobot_esai,
        ];
        $tampil = [
            1 => (int) $bank->tampil_pg,
            2 => (int) $bank->tampil_kompleks,
            3 => (int) $bank->tampil_jodohkan,
            4 => (int) $bank->tampil_isian,
            5 => (int) $bank->tampil_esai,
        ];

        $questions = collect();

        foreach ($limits as $jenis => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $query = Soal::where('bank_id', $bank->id_bank)
                ->where('jenis', $jenis)
                ->where('tampilkan', 1);

            // Bila kumpulan soal lebih banyak dari yang ditampilkan, ambil sampel
            // acak per siswa agar dua peserta tidak selalu menerima soal yang sama.
            $selected = (int) $jadwal->pilih_acak === 1
                ? $query->inRandomOrder()->limit($limit)->get()
                : $query->orderBy('nomor_soal')->limit($limit)->get();

            if ($selected->count() < $limit) {
                throw new RuntimeException('Komposisi soal pada bank belum lengkap.');
            }

            $questions = $questions->merge($selected);
        }

        if ($questions->isEmpty()) {
            throw new RuntimeException('Bank soal belum memiliki soal yang dipilih.');
        }

        $total = $questions->count();
        $aliases = range(1, $total);

        if ((int) $jadwal->acak_soal === 1) {
            shuffle($aliases);
        }

        $rows = [];
        $letters = ['a', 'b', 'c', 'd', 'e'];

        foreach ($questions->values() as $index => $soal) {
            $jenis = (int) $soal->jenis;
            $alias = $aliases[$index];
            $point = $tampil[$jenis] > 0 ? round($bobot[$jenis] / $tampil[$jenis], 2) : 0;
            $row = [
                'id_soal_siswa' => $durasiId.str_pad((string) $bank->id_bank, 3, '0', STR_PAD_LEFT).str_pad((string) $alias, 3, '0', STR_PAD_LEFT),
                'id_bank' => $bank->id_bank,
                'id_jadwal' => $jadwal->id_jadwal,
                'id_soal' => $soal->id_soal,
                'id_siswa' => $siswa->id_siswa,
                'jenis_soal' => $jenis,
                'no_soal_alias' => $alias,
                'opsi_alias_a' => null,
                'opsi_alias_b' => null,
                'opsi_alias_c' => null,
                'opsi_alias_d' => null,
                'opsi_alias_e' => null,
                'jawaban_alias' => null,
                'jawaban_benar' => (string) $soal->jawaban,
                'point_essai' => 0,
                'soal_end' => $alias === $total ? 1 : 0,
                'point_soal' => (string) $point,
                'nilai_koreksi' => '0',
                'nilai_otomatis' => 0,
                'time_create' => now(),
            ];

            if ($jenis === 1) {
                $order = ['A', 'B', 'C', 'D', 'E'];
                if ((int) $jadwal->acak_opsi === 1) {
                    shuffle($order);
                }
                foreach ($letters as $position => $letter) {
                    $row['opsi_alias_'.$letter] = $order[$position] ?? '';
                }
                $row['jawaban_benar'] = strtoupper(trim((string) $soal->jawaban));
            } elseif ($jenis === 2) {
                $correct = CbtScoring::letters((string) $soal->jawaban);
                $row['jawaban_benar'] = json_encode($correct);
            } elseif ($jenis === 3) {
                $pairs = CbtScoring::pairs((string) $soal->jawaban);
                $order = range(0, max(0, count($pairs) - 1));
                shuffle($order);
                $row['jawaban_benar'] = json_encode(['pairs' => $pairs]);
                $row['jawaban_alias'] = json_encode($order);
            }

            $rows[] = $row;
        }

        DB::table('cbt_soal_siswa')->insert($rows);
    }
}
