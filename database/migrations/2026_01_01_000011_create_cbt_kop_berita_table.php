<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_kop_berita', function (Blueprint $table) {
            $table->integer('id_kop');
            $table->string('header_1', 100)->nullable();
            $table->string('header_2', 100)->nullable();
            $table->string('header_3', 100)->nullable();
            $table->string('header_4', 100)->nullable();
            $table->primary(['id_kop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_kop_berita');
    }
};
