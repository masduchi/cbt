<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_jurusan', function (Blueprint $table) {
            $table->integer('id_jurusan', true);
            $table->string('nama_jurusan', 30);
            $table->string('kode_jurusan', 10)->nullable();
            $table->longText('mapel_peminatan')->nullable();
            $table->integer('status')->default('1');
            $table->integer('deletable')->default('1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_jurusan');
    }
};
