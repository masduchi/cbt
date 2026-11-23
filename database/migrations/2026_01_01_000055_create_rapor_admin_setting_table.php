<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_admin_setting', function (Blueprint $table) {
            $table->integer('id_setting', true);
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('kkm_tunggal')->default('0');
            $table->integer('kkm')->nullable();
            $table->integer('bobot_ph')->nullable();
            $table->integer('bobot_pts')->nullable();
            $table->integer('bobot_pas')->nullable();
            $table->integer('bobot_absen')->nullable();
            $table->string('tgl_rapor_akhir', 100)->nullable();
            $table->string('tgl_rapor_kelas_akhir', 100)->nullable();
            $table->string('tgl_rapor_pts', 100)->nullable();
            $table->integer('nip_kepsek')->nullable()->default('0');
            $table->integer('nip_walikelas')->nullable()->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_admin_setting');
    }
};
