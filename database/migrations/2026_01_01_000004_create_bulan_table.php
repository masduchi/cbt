<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulan', function (Blueprint $table) {
            $table->integer('id_bln', true);
            $table->string('nama_bln', 25);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulan');
    }
};
