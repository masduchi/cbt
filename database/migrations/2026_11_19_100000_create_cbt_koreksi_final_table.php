<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai final ditetapkan admin terpisah dari skor korektor agar tidak ikut
     * terhitung dalam rata-rata maupun selisih antar korektor. Snapshot rubrik
     * disimpan bersama koreksi supaya riwayat tetap terbaca bila rubrik berubah.
     */
    public function up(): void
    {
        Schema::create('cbt_koreksi_final', function (Blueprint $table) {
            $table->string('id_soal_siswa', 15)->primary();
            $table->decimal('skor', 6, 2)->default(0);
            $table->unsignedInteger('id_user');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::table('cbt_koreksi', function (Blueprint $table) {
            $table->json('detail_rubrik')->nullable()->after('detail');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_koreksi', function (Blueprint $table) {
            $table->dropColumn('detail_rubrik');
        });

        Schema::dropIfExists('cbt_koreksi_final');
    }
};
