<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari', function (Blueprint $table) {
            $table->integer('id_hri', true);
            $table->string('nama_hri', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari');
    }
};
