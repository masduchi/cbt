<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_guru', function (Blueprint $table) {
            $table->integer('id_guru', true);
            $table->integer('id_user')->nullable();
            $table->char('nip', 30);
            $table->string('nama_guru', 50);
            $table->string('email', 254)->nullable();
            $table->string('kode_guru', 6)->nullable();
            $table->string('username', 50)->nullable();
            $table->longText('password')->nullable();
            $table->string('no_ktp', 16)->nullable();
            $table->string('tempat_lahir', 30)->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->string('jenis_kelamin', 10)->nullable();
            $table->string('agama', 10)->nullable();
            $table->string('no_hp', 13)->nullable();
            $table->string('alamat_jalan', 255)->nullable();
            $table->string('rt_rw', 8)->nullable();
            $table->string('dusun', 50)->nullable();
            $table->string('kelurahan', 50)->nullable();
            $table->string('kecamatan', 30)->nullable();
            $table->string('kabupaten', 30)->nullable();
            $table->string('provinsi', 30)->nullable();
            $table->integer('kode_pos')->nullable();
            $table->string('kewarganegaraan', 10)->nullable();
            $table->string('nuptk', 20)->nullable();
            $table->string('jenis_ptk', 50)->nullable();
            $table->string('tgs_tambahan', 50)->nullable();
            $table->string('status_pegawai', 50)->nullable();
            $table->string('status_aktif', 20)->nullable();
            $table->string('status_nikah', 20)->nullable();
            $table->date('tmt')->nullable();
            $table->string('keahlian_isyarat', 10)->nullable();
            $table->string('npwp', 16)->nullable();
            $table->longText('foto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_guru');
    }
};
