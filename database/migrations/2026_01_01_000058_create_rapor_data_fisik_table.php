<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_data_fisik', function (Blueprint $table) {
            $table->integer('id_fisik', true);
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('id_kelas')->nullable();
            $table->integer('jenis')->comment('1=pendengaran, 2=penglihatan, 3=gigi, 4=lain-lain');
            $table->integer('kode');
            $table->longText('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_data_fisik');
    }
};
