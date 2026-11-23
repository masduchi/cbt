<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode pelanggaran dipisahkan dari deskripsi agar pengawas dapat membedakan
     * jenis pelanggaran tanpa menebak dari teks bebas.
     */
    public function up(): void
    {
        Schema::table('log_ujian', function (Blueprint $table) {
            $table->string('kode', 40)->nullable()->after('log_desc');
        });
    }

    public function down(): void
    {
        Schema::table('log_ujian', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
    }
};
