<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_profile', function (Blueprint $table) {
            $table->integer('id_user', true);
            $table->mediumText('nama_lengkap');
            $table->mediumText('jabatan')->nullable();
            $table->integer('level_access')->default('0');
            $table->mediumText('foto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_profile');
    }
};
