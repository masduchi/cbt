<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_data_catatan', function (Blueprint $table) {
            $table->integer('id_catatan', true);
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('id_kelas')->nullable();
            $table->integer('jenis')->comment('1=desk absensi, 2=desk catatan, 3=desk ranking');
            $table->integer('kode');
            $table->string('deskripsi', 150);
            $table->string('rank', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_data_catatan');
    }
};
