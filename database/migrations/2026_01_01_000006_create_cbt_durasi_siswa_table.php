<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_durasi_siswa', function (Blueprint $table) {
            $table->integer('id_durasi');
            $table->integer('id_siswa')->nullable();
            $table->integer('id_jadwal')->nullable();
            $table->integer('status')->default('0')->comment('0=belum ujian, 1=sedang ujian, 2=sudah ujian');
            $table->time('lama_ujian')->nullable();
            $table->string('mulai', 22)->nullable();
            $table->string('selesai', 22)->nullable();
            $table->integer('reset')->default('0')->comment('0=tidak, 1=reset dari 0, 2=reset dari sisa waktu, 3=ulangi semua');
            $table->dateTime('time_create')->nullable()->useCurrentOnUpdate();
            $table->primary(['id_durasi']);
            $table->index(['id_durasi'], 'Cbt_index_id_durasi');
            $table->index(['id_siswa'], 'id_siswa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_durasi_siswa');
    }
};
