<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_soal', function (Blueprint $table) {
            $table->integer('id_soal', true);
            $table->integer('bank_id')->nullable();
            $table->integer('mapel_id')->nullable()->default('0');
            $table->integer('jenis')->comment('1=ganda, 2=ganda kompleks, 3=menjodohkan, 4=isian singkat, 5=uraian');
            $table->integer('nomor_soal')->nullable()->default('0');
            $table->string('file', 255)->nullable();
            $table->longText('file1')->nullable();
            $table->string('tipe_file', 50)->nullable();
            $table->longText('soal')->nullable();
            $table->longText('opsi_a')->nullable();
            $table->longText('opsi_b')->nullable();
            $table->longText('opsi_c')->nullable();
            $table->longText('opsi_d')->nullable();
            $table->longText('opsi_e')->nullable();
            $table->string('file_a', 255)->nullable();
            $table->string('file_b', 255)->nullable();
            $table->string('file_c', 255)->nullable();
            $table->string('file_d', 255)->nullable();
            $table->string('file_e', 255)->nullable();
            $table->longText('jawaban')->nullable();
            $table->integer('created_on')->nullable();
            $table->integer('updated_on')->nullable();
            $table->integer('tampilkan')->default('0');
            $table->longText('deskripsi');
            $table->integer('kesulitan')->default('1')->comment('tingkat kesulitan 1-10');
            $table->integer('timer')->default('0')->comment('0=tidak, 1=ya');
            $table->integer('timer_menit')->default('0');
            $table->index(['bank_id'], 'id_bank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_soal');
    }
};
