<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_siswa', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt', 'id_kelas'], 'kelas_siswa_periode_kelas_idx');
            $table->index(['id_tp', 'id_smt', 'id_siswa'], 'kelas_siswa_periode_siswa_idx');
        });

        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->index(['id_jadwal', 'id_siswa'], 'cbt_nilai_jadwal_siswa_idx');
            $table->index(['id_jadwal', 'dikoreksi'], 'cbt_nilai_jadwal_koreksi_idx');
            $table->index(['id_siswa'], 'cbt_nilai_siswa_idx');
        });

        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->index(['id_jadwal', 'id_siswa'], 'cbt_soal_siswa_jadwal_siswa_idx');
        });

        Schema::table('cbt_durasi_siswa', function (Blueprint $table) {
            $table->index(['id_jadwal', 'status'], 'cbt_durasi_jadwal_status_idx');
        });

        foreach (['harian' => 'id_nilai_harian', 'pts' => 'id_nilai_pts', 'akhir' => 'id_nilai_akhir'] as $suffix => $primary) {
            Schema::table("rapor_nilai_{$suffix}", function (Blueprint $table) use ($suffix) {
                $table->index(['id_tp', 'id_smt', 'id_kelas', 'id_mapel'], "rapor_{$suffix}_periode_idx");
                $table->index(['id_siswa', 'id_tp', 'id_smt'], "rapor_{$suffix}_siswa_idx");
            });
        }

        Schema::table('master_kelas', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt'], 'master_kelas_periode_idx');
        });

        Schema::table('cbt_bank_soal', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt'], 'cbt_bank_periode_idx');
        });

        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt', 'status'], 'cbt_jadwal_periode_status_idx');
        });

        Schema::table('kelas_materi', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt'], 'kelas_materi_periode_idx');
        });

        Schema::table('rapor_kkm', function (Blueprint $table) {
            $table->index(['id_tp', 'id_smt', 'id_kelas'], 'rapor_kkm_periode_kelas_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kelas_siswa', function (Blueprint $table) {
            $table->dropIndex('kelas_siswa_periode_kelas_idx');
            $table->dropIndex('kelas_siswa_periode_siswa_idx');
        });

        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->dropIndex('cbt_nilai_jadwal_siswa_idx');
            $table->dropIndex('cbt_nilai_jadwal_koreksi_idx');
            $table->dropIndex('cbt_nilai_siswa_idx');
        });

        Schema::table('cbt_soal_siswa', function (Blueprint $table) {
            $table->dropIndex('cbt_soal_siswa_jadwal_siswa_idx');
        });

        Schema::table('cbt_durasi_siswa', function (Blueprint $table) {
            $table->dropIndex('cbt_durasi_jadwal_status_idx');
        });

        foreach (['harian', 'pts', 'akhir'] as $suffix) {
            Schema::table("rapor_nilai_{$suffix}", function (Blueprint $table) use ($suffix) {
                $table->dropIndex("rapor_{$suffix}_periode_idx");
                $table->dropIndex("rapor_{$suffix}_siswa_idx");
            });
        }

        Schema::table('master_kelas', function (Blueprint $table) {
            $table->dropIndex('master_kelas_periode_idx');
        });

        Schema::table('cbt_bank_soal', function (Blueprint $table) {
            $table->dropIndex('cbt_bank_periode_idx');
        });

        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->dropIndex('cbt_jadwal_periode_status_idx');
        });

        Schema::table('kelas_materi', function (Blueprint $table) {
            $table->dropIndex('kelas_materi_periode_idx');
        });

        Schema::table('rapor_kkm', function (Blueprint $table) {
            $table->dropIndex('rapor_kkm_periode_kelas_idx');
        });
    }
};
