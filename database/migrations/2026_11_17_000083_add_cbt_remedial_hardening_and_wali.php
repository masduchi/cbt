<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah penanda jadwal remedial, penguncian sesi/perangkat ujian,
     * peserta remedial, kisi-kisi perakitan soal, dan tautan akun wali murid.
     */
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('cbt_jadwal'));
        $idBankIndexes = $indexes->filter(fn (array $index) => ($index['columns'] ?? []) === ['id_bank']);
        $idBankUnique = $idBankIndexes->first(fn (array $index) => (bool) ($index['unique'] ?? false));
        $idBankPlain = $idBankIndexes->contains(fn (array $index) => ! ($index['unique'] ?? false));

        // Skema lama memasang indeks unik pada id_bank sehingga satu bank soal
        // hanya boleh dipakai satu jadwal. Jadwal remedial butuh bank yang sama,
        // jadi indeks unik diganti indeks biasa. Indeks biasa dibuat lebih dulu
        // agar foreign key tetap punya penopang saat indeks unik dilepas.
        if ($idBankUnique && ! $idBankPlain) {
            Schema::table('cbt_jadwal', function (Blueprint $table) {
                $table->index('id_bank', 'cbt_jadwal_id_bank_index');
            });
        }

        if ($idBankUnique) {
            Schema::table('cbt_jadwal', function (Blueprint $table) use ($idBankUnique) {
                $table->dropUnique((string) $idBankUnique['name']);
            });
        }

        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->tinyInteger('remedial')->default(0)->after('status');
            $table->integer('id_jadwal_induk')->nullable()->after('remedial');

            $table->index('id_jadwal_induk', 'cbt_jadwal_induk_index');
        });

        Schema::table('cbt_durasi_siswa', function (Blueprint $table) {
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
        });

        Schema::create('cbt_remedial_peserta', function (Blueprint $table) {
            $table->id();
            $table->integer('id_jadwal');
            $table->integer('id_siswa');
            $table->integer('id_jadwal_induk');
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->unsignedTinyInteger('max_percobaan')->default(2);
            $table->timestamp('time_create')->nullable();

            $table->unique(['id_jadwal', 'id_siswa'], 'cbt_remedial_unik');
            $table->index(['id_jadwal_induk', 'id_siswa'], 'cbt_remedial_induk_index');
        });

        Schema::create('wali_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_user');
            $table->integer('id_siswa');
            $table->timestamp('time_create')->nullable();

            $table->unique(['id_user', 'id_siswa'], 'wali_siswa_unik');
            $table->index('id_siswa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wali_siswa');

        Schema::dropIfExists('cbt_remedial_peserta');

        Schema::table('cbt_durasi_siswa', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'ip_address']);
        });

        Schema::table('cbt_jadwal', function (Blueprint $table) {
            $table->dropIndex('cbt_jadwal_induk_index');
            $table->dropColumn(['remedial', 'id_jadwal_induk']);
        });

        $indexes = collect(Schema::getIndexes('cbt_jadwal'));
        $punyaUnik = $indexes->contains(
            fn (array $index) => ($index['columns'] ?? []) === ['id_bank'] && (bool) ($index['unique'] ?? false)
        );

        // Indeks unik hanya bisa dipasang ulang bila tidak ada bank yang sudah
        // dipakai dua jadwal (mis. jadwal remedial yang menunjuk bank yang sama).
        $adaDuplikat = DB::table('cbt_jadwal')
            ->select('id_bank')
            ->groupBy('id_bank')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($adaDuplikat && ! $punyaUnik) {
            // Rollback tidak boleh menghapus data atau menggagalkan migrasi.
            // Indeks biasa dipertahankan dan kondisi ini dilaporkan agar
            // operator bisa menggabungkan jadwal duplikat lalu mengulang.
            Log::warning('Rollback cbt_jadwal: indeks unik id_bank tidak dipasang karena ada bank dipakai lebih dari satu jadwal.', [
                'id_bank' => DB::table('cbt_jadwal')
                    ->select('id_bank')
                    ->groupBy('id_bank')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('id_bank')
                    ->all(),
            ]);
        }

        if (! $punyaUnik && ! $adaDuplikat) {
            // Indeks unik dipasang lebih dulu agar foreign key tetap punya
            // penopang, baru indeks biasa dilepas.
            Schema::table('cbt_jadwal', function (Blueprint $table) {
                $table->unique('id_bank', 'id_bank_soal');
            });

            if ($indexes->contains(fn (array $index) => ($index['name'] ?? '') === 'cbt_jadwal_id_bank_index')) {
                Schema::table('cbt_jadwal', function (Blueprint $table) {
                    $table->dropIndex('cbt_jadwal_id_bank_index');
                });
            }
        }
    }
};
