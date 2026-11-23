<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSetting extends Model
{
    protected $table = 'api_setting';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'auto_sync',
        'edit_profile_siswa',
        'edit_profile_guru',
    ];

    protected function casts(): array
    {
        return [
            'auto_sync' => 'boolean',
            'edit_profile_siswa' => 'boolean',
            'edit_profile_guru' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $setting = self::query()->orderBy('id')->first();

        if ($setting) {
            return $setting;
        }

        $setting = new self;
        $setting->fill([
            'auto_sync' => 0,
            'edit_profile_siswa' => 0,
            'edit_profile_guru' => 0,
        ]);
        $setting->save();

        return $setting;
    }
}
