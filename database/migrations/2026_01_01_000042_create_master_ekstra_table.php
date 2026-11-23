<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_ekstra', function (Blueprint $table) {
            $table->integer('id_ekstra', true);
            $table->string('nama_ekstra', 100);
            $table->string('kode_ekstra', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_ekstra');
    }
};
