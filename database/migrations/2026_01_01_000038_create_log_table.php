<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log', function (Blueprint $table) {
            $table->integer('id_log', true);
            $table->dateTime('log_time')->useCurrent();
            $table->integer('id_user');
            $table->integer('id_group');
            $table->mediumText('name_group');
            $table->integer('log_type');
            $table->mediumText('log_desc');
            $table->mediumText('address');
            $table->mediumText('agent');
            $table->mediumText('device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log');
    }
};
