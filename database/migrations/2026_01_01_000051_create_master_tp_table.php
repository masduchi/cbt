<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_tp', function (Blueprint $table) {
            $table->integer('id_tp', true);
            $table->string('tahun', 20);
            $table->integer('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_tp');
    }
};
