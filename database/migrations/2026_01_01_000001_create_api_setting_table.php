<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_setting', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('auto_sync')->default('0');
            $table->integer('edit_profile_siswa')->default('0');
            $table->integer('edit_profile_guru')->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_setting');
    }
};
