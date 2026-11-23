<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_catatan_mapel', function (Blueprint $table) {
            $table->integer('id_catatan', true);
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('type');
            $table->integer('id_siswa')->nullable();
            $table->integer('id_mapel')->nullable();
            $table->integer('id_kelas')->nullable();
            $table->integer('id_guru')->nullable();
            $table->string('level', 1)->default('0');
            $table->dateTime('tgl')->useCurrent();
            $table->mediumText('text');
            $table->string('readed', 22)->default('0');
            $table->longText('reading')->nullable()->comment('array id_siswa yang membaca');
            $table->integer('jml')->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_catatan_mapel');
    }
};
