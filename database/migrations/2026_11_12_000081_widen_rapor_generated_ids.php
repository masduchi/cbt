<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID rapor lama dibentuk dari gabungan beberapa kolom sehingga bisa melewati
     * batas signed INT ketika id mapel/kelas/siswa sudah dua digit ke atas.
     */
    private array $columns = [
        'rapor_nilai_harian' => 'id_nilai_harian',
        'rapor_nilai_pts' => 'id_nilai_pts',
        'rapor_nilai_akhir' => 'id_nilai_akhir',
        'rapor_nilai_ekstra' => 'id_nilai_ekstra',
        'rapor_kkm' => 'id_kkm',
    ];

    public function up(): void
    {
        $this->resize('BIGINT');
    }

    public function down(): void
    {
        $this->resize('INT');
    }

    private function resize(string $type): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NOT NULL AUTO_INCREMENT");
        }
    }
};
