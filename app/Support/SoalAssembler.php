<?php

namespace App\Support;

use App\Models\BankSoal;
use App\Models\Soal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SoalAssembler
{
    /**
     * Tingkat kesulitan soal disimpan 1–10; kelompok mudah/sedang/sulit
     * mengikuti rentang yang dipakai guru saat menulis soal.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const BUCKETS = [
        'mudah' => [1, 3],
        'sedang' => [4, 7],
        'sulit' => [8, 10],
    ];

    /**
     * @var array<int, string>
     */
    private const COLUMNS = [
        1 => 'tampil_pg',
        2 => 'tampil_kompleks',
        3 => 'tampil_jodohkan',
        4 => 'tampil_isian',
        5 => 'tampil_esai',
    ];

    /**
     * @param  array<int|string, array<string, int>>  $komposisi
     * @return array<string, mixed>
     */
    public static function assemble(BankSoal $bank, array $komposisi): array
    {
        $rencana = [];
        $kurang = [];

        foreach (self::COLUMNS as $jenis => $column) {
            $target = $komposisi[$jenis] ?? $komposisi[(string) $jenis] ?? null;

            if (! is_array($target)) {
                continue;
            }

            $butuh = max(0, (int) ($target['mudah'] ?? 0))
                + max(0, (int) ($target['sedang'] ?? 0))
                + max(0, (int) ($target['sulit'] ?? 0));

            if ($butuh === 0) {
                continue;
            }

            $pool = Soal::where('bank_id', $bank->id_bank)->where('jenis', $jenis)->get(['id_soal', 'kesulitan']);
            $dipilih = [];

            foreach (self::BUCKETS as $label => [$min, $max]) {
                $perlu = max(0, (int) ($target[$label] ?? 0));

                if ($perlu === 0) {
                    continue;
                }

                $kandidat = $pool
                    ->filter(fn (Soal $soal) => (int) $soal->kesulitan >= $min && (int) $soal->kesulitan <= $max)
                    ->shuffle()
                    ->take($perlu);

                if ($kandidat->count() < $perlu) {
                    $kurang[] = sprintf(
                        'Soal jenis %d tingkat %s kurang %d butir.',
                        $jenis,
                        $label,
                        $perlu - $kandidat->count()
                    );
                }

                $dipilih = array_merge($dipilih, $kandidat->pluck('id_soal')->all());
            }

            $rencana[$jenis] = array_values(array_unique($dipilih));
        }

        if ($kurang !== []) {
            throw ValidationException::withMessages(['komposisi' => $kurang]);
        }

        if ($rencana === []) {
            throw ValidationException::withMessages(['komposisi' => 'Isi jumlah soal yang ingin dirakit minimal satu jenis.']);
        }

        DB::transaction(function () use ($bank, $rencana) {
            $updates = [];

            foreach ($rencana as $jenis => $ids) {
                Soal::where('bank_id', $bank->id_bank)->where('jenis', $jenis)->update(['tampilkan' => 0]);
                Soal::whereIn('id_soal', $ids)->update(['tampilkan' => 1]);
                $updates[self::COLUMNS[$jenis]] = count($ids);
            }

            $bank->update($updates);
            BankSoalCounter::sync($bank->refresh());
        });

        return [
            'dipilih' => array_map(fn (array $ids) => count($ids), $rencana),
            'total' => array_sum(array_map(fn (array $ids) => count($ids), $rencana)),
        ];
    }
}
