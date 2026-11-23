<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_guru', function (Blueprint $table) {
            $table->integer('id_level', true);
            $table->string('level', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_guru');
    }
};
