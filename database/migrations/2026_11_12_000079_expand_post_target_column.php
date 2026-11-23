<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post', function (Blueprint $table) {
            $table->string('kepada', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('post', function (Blueprint $table) {
            $table->string('kepada', 50)->change();
        });
    }
};
