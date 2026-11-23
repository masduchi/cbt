<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_siswa', function (Blueprint $table) {
            $table->integer('id_siswa', true);
            $table->unsignedInteger('nisn');
            $table->string('nis', 20);
            $table->string('nama', 50);
            $table->string('jenis_kelamin', 1)->nullable();
            $table->string('username', 50);
            $table->mediumText('password');
            $table->integer('kelas_awal');
            $table->string('tahun_masuk', 30)->nullable();
            $table->string('sekolah_asal', 100)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->string('tanggal_lahir', 30)->nullable();
            $table->string('agama', 10)->nullable();
            $table->string('hp', 15)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('foto', 255)->nullable()->default('siswa.png');
            $table->integer('anak_ke')->nullable();
            $table->string('status_keluarga', 1)->nullable();
            $table->longText('alamat')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kabupaten', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->integer('kode_pos')->nullable();
            $table->string('nama_ayah', 150)->nullable();
            $table->string('tgl_lahir_ayah', 50)->nullable();
            $table->string('pendidikan_ayah', 50)->nullable();
            $table->string('pekerjaan_ayah', 100)->nullable();
            $table->string('nohp_ayah', 20)->nullable();
            $table->longText('alamat_ayah')->nullable();
            $table->string('nama_ibu', 50)->nullable();
            $table->string('tgl_lahir_ibu', 50)->nullable();
            $table->string('pendidikan_ibu', 50)->nullable();
            $table->string('pekerjaan_ibu', 100)->nullable();
            $table->string('nohp_ibu', 20)->nullable();
            $table->longText('alamat_ibu')->nullable();
            $table->string('nama_wali', 150)->nullable();
            $table->string('tgl_lahir_wali', 50)->nullable();
            $table->string('pendidikan_wali', 50)->nullable();
            $table->string('pekerjaan_wali', 100)->nullable();
            $table->string('nohp_wali', 20)->nullable();
            $table->longText('alamat_wali')->nullable();
            $table->string('nik', 30);
            $table->string('warga_negara', 20);
            $table->string('uid', 255);
            $table->primary(['id_siswa', 'uid', 'nisn', 'nis']);
            $table->unique(['id_siswa'], 'Id_siswa_idx');
            $table->unique(['uid'], 'uid_idx');
            $table->unique(['nisn'], 'nisn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_siswa');
    }
};
