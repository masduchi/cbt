<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurusan_mapel', function (Blueprint $table) {
            $table->integer('id_jurusan_mapel', true);
            $table->integer('id_jurusan');
            $table->integer('id_mapel');
            $table->unique(['id_jurusan', 'id_mapel']);
        });

        DB::table('master_jurusan')
            ->whereNotNull('mapel_peminatan')
            ->orderBy('id_jurusan')
            ->each(function ($row) {
                $ids = array_unique(array_filter(array_map('intval', explode(',', (string) $row->mapel_peminatan))));

                foreach ($ids as $id) {
                    if ($id > 0) {
                        DB::table('jurusan_mapel')->insertOrIgnore([
                            'id_jurusan' => (int) $row->id_jurusan,
                            'id_mapel' => $id,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurusan_mapel');
    }
};
