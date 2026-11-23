<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_hari_efektif', function (Blueprint $table) {
            $table->integer('id_hari_efektif', true);
            $table->integer('jml_hari_efektif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_hari_efektif');
    }
};
