<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->timestamp('hasil_notified_at')->nullable()->after('dikoreksi');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->dropColumn('hasil_notified_at');
        });
    }
};
