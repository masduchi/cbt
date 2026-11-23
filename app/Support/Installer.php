<?php

namespace App\Support;

use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Installer
{
    /**
     * Penanda bahwa wizard instalasi sudah selesai.
     */
    public static function marker(): string
    {
        return (string) config('installer.marker', storage_path('app/installed'));
    }

    public static function marked(): bool
    {
        return is_file(self::marker()) || Storage::disk('local')->exists('installed');
    }

    public static function isInstalled(): bool
    {
        if (self::marked()) {
            return true;
        }

        try {
            return self::adminReady() && self::settingReady() && self::periodReady();
        } catch (Throwable) {
            return false;
        }
    }

    public static function periodReady(): bool
    {
        return Schema::hasTable('master_tp')
            && DB::table('master_tp')->where('active', 1)->exists();
    }

    public static function markInstalled(): void
    {
        Storage::disk('local')->put('installed', 'installed at '.now()->toDateTimeString());
    }

    public static function databaseReady(): bool
    {
        try {
            return Schema::hasTable('users')
                && Schema::hasTable('setting')
                && Schema::hasTable('groups');
        } catch (Throwable) {
            return false;
        }
    }

    public static function adminReady(): bool
    {
        if (! self::databaseReady()) {
            return false;
        }

        return User::query()
            ->whereHas('groups', fn ($query) => $query->where('name', 'admin'))
            ->exists();
    }

    public static function settingReady(): bool
    {
        return self::databaseReady() && Setting::query()->exists();
    }

    public static function currentStep(): int
    {
        if (! self::databaseReady()) {
            return 1;
        }

        if (! self::adminReady()) {
            return 2;
        }

        if (! self::settingReady()) {
            return 3;
        }

        return 4;
    }

    /**
     * Grup akses dasar yang dipakai middleware role.
     */
    public static function ensureGroups(): void
    {
        $groups = [
            'admin' => 'Administrator',
            'guru' => 'Pembuat Soal dan ujian',
            'siswa' => 'Peserta Ujian',
        ];

        foreach ($groups as $name => $description) {
            Group::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }

    /**
     * Aktifkan tahun pelajaran berjalan dan semester ganjil bila belum ada.
     */
    public static function ensureActivePeriod(): void
    {
        if (! Schema::hasTable('master_tp') || ! Schema::hasTable('master_smt')) {
            return;
        }

        $tahun = date('Y').'/'.(date('Y') + 1);
        $existing = DB::table('master_tp')->where('tahun', $tahun)->first();

        if (! $existing) {
            DB::table('master_tp')->update(['active' => 0]);
            DB::table('master_tp')->insert(['tahun' => $tahun, 'active' => 1]);
        } elseif ((int) $existing->active !== 1) {
            DB::table('master_tp')->update(['active' => 0]);
            DB::table('master_tp')->where('id_tp', $existing->id_tp)->update(['active' => 1]);
        }

        if (! DB::table('master_smt')->where('active', 1)->exists()) {
            $first = DB::table('master_smt')->orderBy('id_smt')->value('id_smt');

            if ($first !== null) {
                DB::table('master_smt')->where('id_smt', $first)->update(['active' => 1]);
            }
        }
    }
}
