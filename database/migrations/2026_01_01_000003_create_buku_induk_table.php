<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_induk', function (Blueprint $table) {
            $table->integer('id_siswa', true);
            $table->string('uid', 50);
            $table->string('rombel_awal', 50)->nullable();
            $table->string('nama_panggilan', 50)->nullable();
            $table->string('bahasa', 50)->nullable();
            $table->integer('jml_saudara_kandung')->default('0');
            $table->integer('jml_saudara_tiri')->default('0');
            $table->integer('jml_saudara_angkat')->default('0');
            $table->integer('yatim')->default('0')->comment('0=ada orang-tua, 1=yatim, 2=yatim piatu');
            $table->string('tinggal_bersama', 1)->default('1')->comment('1=orang-tua, 2=saudara, 3=wali, 4=asrama/pesantren, 5=kost, 6=lainnya');
            $table->string('jarak', 10)->nullable();
            $table->string('gol_darah', 4)->nullable();
            $table->longText('penyakit')->nullable();
            $table->string('kelainan_fisik', 100)->nullable();
            $table->longText('kegemaran')->nullable();
            $table->longText('beasiswa')->nullable();
            $table->string('no_ijazah_sebelumnya', 50)->nullable();
            $table->string('tahun_lulus_sebelumnya', 10)->nullable();
            $table->string('pindahan_dari', 100)->nullable();
            $table->string('alasan_kepindahan', 200)->nullable();
            $table->string('agama_ayah', 20)->nullable();
            $table->string('tempat_lahir_ayah', 50)->nullable();
            $table->string('wn_ayah', 50)->nullable();
            $table->string('penghasilan_ayah', 50)->nullable();
            $table->string('hidup_meninggal_ayah', 50)->nullable();
            $table->string('agama_ibu', 50)->nullable();
            $table->string('tempat_lahir_ibu', 50)->nullable();
            $table->string('wn_ibu', 50)->nullable();
            $table->string('penghasilan_ibu', 50)->nullable();
            $table->string('hidup_meninggal_ibu', 50)->nullable();
            $table->string('tempat_lahir_wali', 50)->nullable();
            $table->string('agama_wali', 20)->nullable();
            $table->string('wn_wali', 50)->nullable();
            $table->string('penghasilan_wali', 10)->nullable();
            $table->integer('status')->nullable()->default('1')->comment('1= aktif, 2=lulus, 3=pindah, 4=keluar');
            $table->integer('tahun_lulus')->nullable();
            $table->string('no_ijazah', 50)->nullable();
            $table->string('kelas_akhir', 50)->nullable();
            $table->string('lanjut_ke', 50)->nullable();
            $table->string('pindah_ke', 100)->nullable();
            $table->string('alasan_pindah', 100)->nullable();
            $table->string('tgl_pindah', 20)->nullable();
            $table->string('bekerja_di', 100)->nullable();
            $table->longText('catatan_penting')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_induk');
    }
};
