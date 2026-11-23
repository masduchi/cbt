<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_ruang', function (Blueprint $table) {
            $table->integer('id_ruang', true);
            $table->string('nama_ruang', 50);
            $table->string('kode_ruang', 10);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_ruang');
    }
};
