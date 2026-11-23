<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemedialPeserta extends Model
{
    protected $table = 'cbt_remedial_peserta';

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_siswa',
        'id_jadwal_induk',
        'percobaan',
        'max_percobaan',
        'time_create',
    ];

    protected function casts(): array
    {
        return [
            'percobaan' => 'integer',
            'max_percobaan' => 'integer',
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }
}
