<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            // 1 = ambil soal secara acak dari seluruh kumpulan, bukan hanya
            // mengacak urutan soal pertama yang dipilih.
            $table->tinyInteger('pilih_acak')->default(0)->after('acak_opsi');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->dropColumn('pilih_acak');
        });
    }
};
