<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_verifikasi', function (Blueprint $table) {
            $table->string('kunci_aktif', 160)->nullable()->after('ref');
        });

        // Hanya kode aktif yang memegang kunci; baris dicabut dibiarkan NULL
        // sehingga beberapa riwayat pencabutan tetap boleh ada.
        $aktifTerlihat = [];

        DB::table('dokumen_verifikasi')
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get(['id', 'jenis', 'ref'])
            ->each(function (object $row) use (&$aktifTerlihat): void {
                $kunci = $row->jenis.'|'.$row->ref;

                if (isset($aktifTerlihat[$kunci])) {
                    // Duplikat lama: pertahankan yang terbaru saja.
                    DB::table('dokumen_verifikasi')->where('id', $row->id)->update([
                        'revoked_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $aktifTerlihat[$kunci] = true;

                DB::table('dokumen_verifikasi')->where('id', $row->id)->update([
                    'kunci_aktif' => $kunci,
                ]);
            });

        Schema::table('dokumen_verifikasi', function (Blueprint $table) {
            $table->unique('kunci_aktif');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_verifikasi', function (Blueprint $table) {
            $table->dropUnique(['kunci_aktif']);
            $table->dropColumn('kunci_aktif');
        });
    }
};
