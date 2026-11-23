<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_kehadiran', function (Blueprint $table) {
            $table->unsignedInteger('id_kehadiran', true);
            $table->unsignedInteger('id_jadwal');
            $table->unsignedInteger('id_siswa');
            $table->string('id_ruang', 50)->nullable();
            $table->string('id_sesi', 50)->nullable();
            $table->string('kode', 100)->nullable();
            $table->string('metode', 20)->default('kode');
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('waktu')->useCurrent();

            $table->unique(['id_jadwal', 'id_siswa']);
            $table->index(['id_jadwal', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_kehadiran');
    }
};
