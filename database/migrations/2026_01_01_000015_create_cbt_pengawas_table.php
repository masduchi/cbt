<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_pengawas', function (Blueprint $table) {
            $table->string('id_pengawas', 50);
            $table->string('id_jadwal', 50);
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->string('id_ruang', 50);
            $table->string('id_sesi', 50);
            $table->string('id_guru', 50);
            $table->primary(['id_pengawas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_pengawas');
    }
};
