<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_verifikasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('jenis', 40);
            $table->string('ref', 100);
            $table->json('ringkasan')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_verifikasi');
    }
};
