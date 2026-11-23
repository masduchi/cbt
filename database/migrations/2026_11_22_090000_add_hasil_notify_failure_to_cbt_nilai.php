<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->timestamp('hasil_notify_failed_at')->nullable()->after('hasil_notified_at');
            $table->text('hasil_notify_error')->nullable()->after('hasil_notify_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_nilai', function (Blueprint $table) {
            $table->dropColumn(['hasil_notify_failed_at', 'hasil_notify_error']);
        });
    }
};
