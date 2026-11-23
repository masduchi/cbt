<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_nilai', function (Blueprint $table) {
            $table->integer('id_nilai');
            $table->integer('pg_benar')->nullable()->default('0');
            $table->string('pg_nilai', 10)->nullable()->default('0');
            $table->string('essai_nilai', 10)->nullable()->default('0');
            $table->integer('id_siswa')->nullable();
            $table->integer('id_jadwal')->nullable();
            $table->string('kompleks_nilai', 10)->nullable()->default('0');
            $table->string('jodohkan_nilai', 10)->nullable()->default('0');
            $table->string('isian_nilai', 10)->nullable()->default('0');
            $table->integer('dikoreksi')->default('0');
            $table->dateTime('time_create')->nullable()->useCurrentOnUpdate();
            $table->primary(['id_nilai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_nilai');
    }
};
