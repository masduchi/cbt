<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_materi', function (Blueprint $table) {
            $table->string('id_log', 50);
            $table->dateTime('log_time')->useCurrent();
            $table->integer('id_siswa')->nullable();
            $table->integer('jam_ke');
            $table->string('id_materi', 50);
            $table->integer('id_mapel')->nullable();
            $table->integer('log_type');
            $table->mediumText('log_desc');
            $table->longText('text')->nullable();
            $table->longText('file')->nullable();
            $table->string('nilai', 3)->nullable();
            $table->longText('catatan')->nullable();
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
            $table->string('finish_time', 20)->nullable();
            $table->primary(['id_log']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_materi');
    }
};
