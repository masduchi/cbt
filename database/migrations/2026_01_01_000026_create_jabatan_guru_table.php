<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan_guru', function (Blueprint $table) {
            $table->string('id_jabatan_guru', 50);
            $table->integer('id_guru')->nullable();
            $table->integer('id_jabatan');
            $table->integer('id_kelas')->nullable()->default('0');
            $table->longText('mapel_kelas')->nullable();
            $table->longText('ekstra_kelas')->nullable();
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->primary(['id_jabatan_guru']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_guru');
    }
};
