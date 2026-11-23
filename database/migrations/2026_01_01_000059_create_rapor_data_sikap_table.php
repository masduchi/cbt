<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_data_sikap', function (Blueprint $table) {
            $table->integer('id_sikap', true);
            $table->integer('id_tp')->default('0');
            $table->integer('id_smt')->default('0');
            $table->integer('id_kelas')->nullable();
            $table->integer('jenis')->comment('1=spiritual, 2=sosial');
            $table->integer('kode');
            $table->string('sikap', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_data_sikap');
    }
};
