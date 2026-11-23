<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_token', function (Blueprint $table) {
            $table->integer('id_api', true);
            $table->dateTime('timestamp')->useCurrent();
            $table->integer('id_user');
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
            $table->mediumText('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_token');
    }
};
