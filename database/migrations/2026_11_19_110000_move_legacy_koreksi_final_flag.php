<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelum ada tabel cbt_koreksi_final, nilai final disimpan sebagai baris
     * cbt_koreksi dengan flag final = 1 sehingga ikut terhitung dalam rata-rata
     * korektor. Pindahkan baris lama ke tabel final agar tidak lagi dihitung.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('cbt_koreksi', 'final')) {
            return;
        }

        DB::table('cbt_koreksi')
            ->where('final', 1)
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $ada = DB::table('cbt_koreksi_final')
                        ->where('id_soal_siswa', $row->id_soal_siswa)
                        ->exists();

                    if ($ada) {
                        continue;
                    }

                    DB::table('cbt_koreksi_final')->insert([
                        'id_soal_siswa' => $row->id_soal_siswa,
                        'skor' => $row->skor,
                        'id_user' => $row->id_user,
                        'catatan' => $row->catatan,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }, 'id_koreksi');

        Schema::table('cbt_koreksi', function (Blueprint $table) {
            $table->dropColumn('final');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbt_koreksi', 'final')) {
            return;
        }

        Schema::table('cbt_koreksi', function (Blueprint $table) {
            $table->boolean('final')->default(false);
        });
    }
};
