<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->foreign('id_bank', 'id_bank_soal')->references('id_bank')->on('cbt_bank_soal');
        });
        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->foreign('id_jenis', 'id_jns_idx_ifc')->references('id_jenis')->on('cbt_jenis');
        });
        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->foreign('id_bank', 'id_bank_fc')->references('id_bank')->on('cbt_bank_soal');
        });
        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->foreign('id_jadwal', 'id_jadwal_fc')->references('id_jadwal')->on('cbt_jadwal');
        });
        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->foreign('id_siswa', 'Id_siswa_fc')->references('id_siswa')->on('master_siswa');
        });
        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->foreign('id_soal', 'id_soal_fc')->references('id_soal')->on('cbt_soal');
        });
        Schema::table('master_kelas', function (Blueprint $table) {
            $table->foreign('level_id', 'key_id_cek')->references('id_level')->on('level_kelas');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_jadwal', fn (Blueprint $table) => $table->dropForeign('id_bank_soal'));
        Schema::table('cbt_jadwal', fn (Blueprint $table) => $table->dropForeign('id_jns_idx_ifc'));
        Schema::table('cbt_soal_siswa', fn (Blueprint $table) => $table->dropForeign('id_bank_fc'));
        Schema::table('cbt_soal_siswa', fn (Blueprint $table) => $table->dropForeign('id_jadwal_fc'));
        Schema::table('cbt_soal_siswa', fn (Blueprint $table) => $table->dropForeign('Id_siswa_fc'));
        Schema::table('cbt_soal_siswa', fn (Blueprint $table) => $table->dropForeign('id_soal_fc'));
        Schema::table('master_kelas', fn (Blueprint $table) => $table->dropForeign('key_id_cek'));
    }
};
