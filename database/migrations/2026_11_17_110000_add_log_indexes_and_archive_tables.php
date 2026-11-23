<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mempercepat query monitor/audit dan menyiapkan tabel arsip agar log
     * lama bisa dipindahkan tanpa kehilangan jejak.
     */
    public function up(): void
    {
        Schema::table('log_ujian', function (Blueprint $table) {
            $table->index(['id_jadwal', 'id_siswa'], 'log_ujian_jadwal_siswa_idx');
            $table->index('log_time', 'log_ujian_time_idx');
            $table->index('kode', 'log_ujian_kode_idx');
        });

        Schema::table('log', function (Blueprint $table) {
            $table->index('log_time', 'log_time_idx');
            $table->index('id_user', 'log_user_idx');
        });

        Schema::create('log_ujian_arsip', function (Blueprint $table) {
            $table->integer('id_log')->primary();
            $table->dateTime('log_time')->index();
            $table->integer('id_siswa')->nullable()->index();
            $table->integer('id_jadwal')->nullable()->index();
            $table->integer('log_type');
            $table->mediumText('log_desc');
            $table->string('kode', 40)->nullable()->index();
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
            $table->integer('reset');
            $table->string('finish_time', 20)->nullable();
        });

        Schema::create('log_arsip', function (Blueprint $table) {
            $table->integer('id_log')->primary();
            $table->dateTime('log_time')->index();
            $table->integer('id_user')->index();
            $table->integer('id_group');
            $table->mediumText('name_group');
            $table->integer('log_type');
            $table->mediumText('log_desc');
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_arsip');
        Schema::dropIfExists('log_ujian_arsip');

        Schema::table('log', function (Blueprint $table) {
            $table->dropIndex('log_time_idx');
            $table->dropIndex('log_user_idx');
        });

        Schema::table('log_ujian', function (Blueprint $table) {
            $table->dropIndex('log_ujian_jadwal_siswa_idx');
            $table->dropIndex('log_ujian_time_idx');
            $table->dropIndex('log_ujian_kode_idx');
        });
    }
};
