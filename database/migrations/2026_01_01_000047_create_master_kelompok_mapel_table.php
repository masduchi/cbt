<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_kelompok_mapel', function (Blueprint $table) {
            $table->integer('id_kel_mapel', true);
            $table->string('kode_kel_mapel', 10)->nullable();
            $table->string('nama_kel_mapel', 100)->nullable();
            $table->string('kategori', 20)->nullable();
            $table->integer('id_parent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kelompok_mapel');
    }
};
