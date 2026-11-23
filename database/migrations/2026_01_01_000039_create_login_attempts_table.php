<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->string('ip_address', 45);
            $table->string('login', 100);
            $table->unsignedInteger('time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
