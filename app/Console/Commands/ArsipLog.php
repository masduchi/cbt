<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ArsipLog extends Command
{
    protected $signature = 'cbt:arsip-log {--before= : Batas tanggal (Y-m-d), default 6 bulan lalu} {--batch=1000 : Jumlah baris per batch}';

    protected $description = 'Memindahkan log ujian dan log audit lama ke tabel arsip';

    /**
     * Default enam bulan: cukup untuk investigasi satu semester tanpa membuat
     * tabel aktif tumbuh selamanya.
     */
    public function handle(): int
    {
        try {
            $cutoff = $this->option('before')
                ? Carbon::parse((string) $this->option('before'))->startOfDay()
                : now()->subMonths(6)->startOfDay();
        } catch (Throwable) {
            $this->error('Format tanggal tidak valid. Gunakan Y-m-d.');

            return self::FAILURE;
        }

        if ($cutoff->isFuture()) {
            $this->error('Batas tanggal tidak boleh di masa depan.');

            return self::FAILURE;
        }

        $batch = max(100, (int) $this->option('batch'));
        $total = 0;

        foreach ([['log_ujian', 'log_ujian_arsip'], ['log', 'log_arsip']] as [$source, $archive]) {
            $total += $this->pindahkan($source, $archive, $cutoff, $batch);
        }

        $this->info($total.' baris log dipindahkan ke arsip (sebelum '.$cutoff->toDateString().').');

        return self::SUCCESS;
    }

    private function pindahkan(string $source, string $archive, Carbon $cutoff, int $batch): int
    {
        if (! Schema::hasTable($source) || ! Schema::hasTable($archive)) {
            return 0;
        }

        $columns = Schema::getColumnListing($source);
        $total = 0;

        do {
            $ids = DB::table($source)
                ->where('log_time', '<', $cutoff)
                ->orderBy('id_log')
                ->limit($batch)
                ->pluck('id_log');

            if ($ids->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($source, $archive, $columns, $ids) {
                DB::table($archive)->insertUsing(
                    $columns,
                    DB::table($source)->whereIn('id_log', $ids)->select($columns)
                );

                DB::table($source)->whereIn('id_log', $ids)->delete();
            });

            $total += $ids->count();
        } while ($ids->count() === $batch);

        return $total;
    }
}
