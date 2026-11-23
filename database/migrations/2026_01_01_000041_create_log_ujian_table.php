<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_ujian', function (Blueprint $table) {
            $table->integer('id_log', true);
            $table->dateTime('log_time')->useCurrent();
            $table->integer('id_siswa')->nullable();
            $table->integer('id_jadwal')->nullable();
            $table->integer('log_type');
            $table->mediumText('log_desc');
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
            $table->integer('reset')->comment('0=tidak reset, 1=reset');
            $table->string('finish_time', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_ujian');
    }
};
