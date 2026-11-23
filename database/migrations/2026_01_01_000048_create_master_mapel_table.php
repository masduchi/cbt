<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_mapel', function (Blueprint $table) {
            $table->integer('id_mapel', true);
            $table->string('nama_mapel', 50);
            $table->string('kode', 20)->nullable();
            $table->string('kelompok', 5)->default('-');
            $table->integer('bobot_p')->default('0');
            $table->integer('bobot_k')->default('0');
            $table->integer('jenjang')->default('0');
            $table->integer('urutan');
            $table->integer('status')->default('1');
            $table->integer('deletable')->default('1');
            $table->integer('urutan_tampil')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_mapel');
    }
};
