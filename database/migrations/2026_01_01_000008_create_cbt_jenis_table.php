<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_jenis', function (Blueprint $table) {
            $table->integer('id_jenis', true);
            $table->string('nama_jenis', 50);
            $table->string('kode_jenis', 10);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_jenis');
    }
};
