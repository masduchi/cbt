<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_smt', function (Blueprint $table) {
            $table->integer('id_smt', true);
            $table->string('smt', 10);
            $table->string('nama_smt', 10);
            $table->integer('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_smt');
    }
};
