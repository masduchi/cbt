<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_catatan_wali', function (Blueprint $table) {
            $table->integer('id_catatan_wali', true);
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('id_kelas')->nullable();
            $table->integer('id_siswa')->nullable();
            $table->longText('nilai')->nullable();
            $table->longText('deskripsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_catatan_wali');
    }
};
