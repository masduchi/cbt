<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID turunan rapor lain yang juga dibentuk dari gabungan beberapa kolom
     * (siswa, kelas, mapel, tahun pelajaran, semester). Kolom INT akan overflow
     * begitu gabungannya melewati 2.147.483.647, mis. "4446132401".
     */
    private array $autoIncrementColumns = [
        'rapor_nilai_sikap' => 'id_nilai_sikap',
        'rapor_fisik' => 'id_fisik',
        'rapor_data_fisik' => 'id_fisik',
        'rapor_kikd' => 'id_kikd',
        'rapor_prestasi' => 'id_ranking',
        'rapor_catatan_wali' => 'id_catatan_wali',
    ];

    private array $plainColumns = [
        'rapor_naik' => 'id_naik',
        'cbt_nomor_peserta' => 'id_nomor',
    ];

    public function up(): void
    {
        $this->resize('BIGINT', true);
        $this->resize('BIGINT', false);
    }

    public function down(): void
    {
        $this->resize('INT', true);
        $this->resize('INT', false);
    }

    private function resize(string $type, bool $autoIncrement): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columns = $autoIncrement ? $this->autoIncrementColumns : $this->plainColumns;

        foreach ($columns as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $suffix = $autoIncrement ? ' NOT NULL AUTO_INCREMENT' : ' NOT NULL';

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type}{$suffix}");
        }
    }
};
