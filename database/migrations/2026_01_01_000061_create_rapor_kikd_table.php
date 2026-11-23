<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_kikd', function (Blueprint $table) {
            $table->integer('id_kikd', true);
            $table->integer('id_mapel_kelas')->nullable();
            $table->integer('aspek');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->longText('materi_kikd');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_kikd');
    }
};
