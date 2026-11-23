<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->string('ip_address', 45);
            $table->string('username', 100)->nullable();
            $table->string('password', 255);
            $table->string('email', 254)->nullable();
            $table->string('activation_selector', 255)->nullable();
            $table->string('activation_code', 255)->nullable();
            $table->string('forgotten_password_selector', 255)->nullable();
            $table->string('forgotten_password_code', 255)->nullable();
            $table->unsignedInteger('forgotten_password_time')->nullable();
            $table->string('remember_selector', 255)->nullable();
            $table->string('remember_code', 255)->nullable();
            $table->unsignedInteger('created_on');
            $table->unsignedInteger('last_login')->nullable();
            $table->unsignedTinyInteger('active')->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->rememberToken();
            $table->unique(['username'], 'username_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
