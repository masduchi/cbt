<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_naik', function (Blueprint $table) {
            $table->integer('id_naik');
            $table->integer('id_tp');
            $table->integer('id_smt');
            $table->integer('id_siswa');
            $table->integer('naik');
            $table->primary(['id_naik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_naik');
    }
};
