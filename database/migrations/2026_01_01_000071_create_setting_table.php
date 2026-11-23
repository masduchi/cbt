<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting', function (Blueprint $table) {
            $table->integer('id_setting', true);
            $table->string('kode_sekolah', 10)->nullable();
            $table->string('sekolah', 50)->nullable();
            $table->string('npsn', 10)->nullable();
            $table->string('nss', 20)->nullable();
            $table->integer('jenjang')->nullable();
            $table->string('kepsek', 50)->nullable();
            $table->string('nip', 30)->nullable();
            $table->longText('tanda_tangan')->nullable();
            $table->longText('alamat')->nullable();
            $table->string('desa', 100)->nullable();
            $table->string('kecamatan', 50)->nullable();
            $table->string('kota', 30)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->integer('kode_pos')->nullable();
            $table->string('telp', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('web', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('nama_aplikasi', 100)->nullable();
            $table->longText('logo_kanan')->nullable();
            $table->longText('logo_kiri')->nullable();
            $table->string('versi', 10)->nullable();
            $table->string('ip_server', 100)->nullable();
            $table->string('waktu', 50)->nullable();
            $table->string('server', 50)->nullable();
            $table->string('id_server', 50)->nullable();
            $table->string('sekolah_id', 50)->nullable();
            $table->string('db_versi', 10)->nullable();
            $table->string('satuan_pendidikan', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting');
    }
};
