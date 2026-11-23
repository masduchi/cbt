<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_kelas', function (Blueprint $table) {
            $table->integer('id_level');
            $table->string('level', 2)->nullable();
            $table->primary(['id_level']);
            $table->index(['id_level'], 'index_id_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_kelas');
    }
};
