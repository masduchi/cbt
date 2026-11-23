<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `cbt_jadwal.id_tp` dan `id_smt` dibuat char(2) pada skema lama, sedangkan
     * `master_tp.id_tp` dan `master_smt.id_smt` adalah integer. Begitu id tahun
     * pelajaran atau semester mencapai 100, jadwal ujian gagal disimpan
     * ("Data too long for column 'id_tp'/'id_smt'").
     */
    public function up(): void
    {
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->integer('id_tp')->change();
            $table->integer('id_smt')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->char('id_tp', 2)->change();
            $table->char('id_smt', 2)->change();
        });
    }
};
