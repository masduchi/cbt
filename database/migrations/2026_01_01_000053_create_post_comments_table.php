<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_comments', function (Blueprint $table) {
            $table->integer('id_comment', true);
            $table->integer('id_post')->nullable();
            $table->integer('dari')->nullable();
            $table->integer('dari_group')->nullable();
            $table->longText('text');
            $table->dateTime('tanggal')->useCurrent();
            $table->dateTime('updated')->useCurrent()->useCurrentOnUpdate();
            $table->string('type', 1)->default('1')->comment('1:pengumuman, 2:materi, 3:tugas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
    }
};
