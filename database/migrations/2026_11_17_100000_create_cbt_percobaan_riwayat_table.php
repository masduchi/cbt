<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan salinan percobaan sebelum jawaban/nilai dihapus saat reset
     * otomatis, izin mengulang, atau penghapusan jadwal remedial.
     */
    public function up(): void
    {
        Schema::create('cbt_percobaan_riwayat', function (Blueprint $table) {
            $table->id();
            $table->integer('id_jadwal');
            $table->integer('id_siswa');
            $table->integer('id_jadwal_induk')->nullable();
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->decimal('total', 8, 2)->nullable();
            $table->json('komponen')->nullable();
            $table->json('jawaban')->nullable();
            $table->string('alasan', 40);
            $table->timestamp('mulai')->nullable();
            $table->timestamp('selesai')->nullable();
            $table->string('lama_ujian', 8)->nullable();
            $table->timestamp('time_create')->nullable();

            $table->index(['id_jadwal', 'id_siswa'], 'cbt_percobaan_jadwal_siswa_index');
            $table->index('id_siswa', 'cbt_percobaan_siswa_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_percobaan_riwayat');
    }
};
