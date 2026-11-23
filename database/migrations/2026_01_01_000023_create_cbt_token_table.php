<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_token', function (Blueprint $table) {
            $table->string('token', 6);
            $table->integer('auto');
            $table->integer('id_token', true);
            $table->integer('jarak')->default('0');
            $table->string('updated', 20)->nullable()->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_token');
    }
};
