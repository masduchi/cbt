<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel cbt_kisi tidak pernah dibaca aplikasi: soal terpilih sudah ditandai
     * pada kolom tampilkan dan aksinya tercatat di log audit.
     */
    public function up(): void
    {
        Schema::dropIfExists('cbt_kisi');
    }

    public function down(): void
    {
        Schema::create('cbt_kisi', function (Blueprint $table) {
            $table->id();
            $table->integer('id_bank');
            $table->unsignedInteger('id_user')->nullable();
            $table->string('nama', 150);
            $table->json('komposisi');
            $table->timestamp('time_create')->nullable();

            $table->index('id_bank');
        });
    }
};
