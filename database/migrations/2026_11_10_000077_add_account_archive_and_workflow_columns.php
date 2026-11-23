<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_siswa', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('master_kelas', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('master_guru', fn (Blueprint $table) => $table->softDeletes());

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('active');
        });

        Schema::table('kelas_materi', function (Blueprint $table) {
            $table->dateTime('tenggat')->nullable()->after('tgl_mulai');
        });

        Schema::table('log_materi', function (Blueprint $table) {
            $table->unsignedInteger('dinilai_oleh')->nullable()->after('nilai');
            $table->dateTime('dinilai_at')->nullable()->after('dinilai_oleh');
        });

        Schema::create('rapor_kunci', function (Blueprint $table) {
            $table->increments('id_kunci');
            $table->unsignedInteger('id_tp');
            $table->unsignedInteger('id_smt');
            $table->unsignedInteger('id_kelas');
            $table->unsignedInteger('locked_by')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unique(['id_tp', 'id_smt', 'id_kelas'], 'rapor_kunci_periode_kelas_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_kunci');

        Schema::table('log_materi', function (Blueprint $table) {
            $table->dropColumn(['dinilai_oleh', 'dinilai_at']);
        });

        Schema::table('kelas_materi', fn (Blueprint $table) => $table->dropColumn('tenggat'));

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));

        Schema::table('master_guru', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('master_kelas', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('master_siswa', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
