<?php

namespace App\Support;

use App\Models\BankSoal;
use App\Models\Soal;

class BankSoalCounter
{
    public static function sync(BankSoal $bank): void
    {
        $counts = Soal::where('bank_id', $bank->id_bank)
            ->selectRaw('jenis, COUNT(*) as jumlah, SUM(tampilkan) as dipilih')
            ->groupBy('jenis')
            ->get()
            ->keyBy('jenis');

        $map = [1 => 'soal', 2 => 'kompleks', 3 => 'jodohkan', 4 => 'isian', 5 => 'esai'];
        $selectedColumn = [1 => 'tampil_pg', 2 => 'tampil_kompleks', 3 => 'tampil_jodohkan', 4 => 'tampil_isian', 5 => 'tampil_esai'];

        $updates = [];
        $complete = true;

        foreach ($map as $jenis => $key) {
            $updates['jml_'.$key] = (int) ($counts[$jenis]->jumlah ?? 0);
            $selected = (int) ($counts[$jenis]->dipilih ?? 0);
            if ($selected < (int) $bank->{$selectedColumn[$jenis]}) {
                $complete = false;
            }
        }

        $totalPlanned = (int) $bank->tampil_pg + (int) $bank->tampil_kompleks + (int) $bank->tampil_jodohkan + (int) $bank->tampil_isian + (int) $bank->tampil_esai;
        $updates['status_soal'] = $complete && $totalPlanned > 0 ? 1 : 0;

        $bank->update($updates);
    }
}
