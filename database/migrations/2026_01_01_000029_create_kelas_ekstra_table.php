<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_ekstra', function (Blueprint $table) {
            $table->string('id_kelas_ekstra', 50);
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_kelas')->nullable();
            $table->longText('ekstra');
            $table->primary(['id_kelas_ekstra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_ekstra');
    }
};
