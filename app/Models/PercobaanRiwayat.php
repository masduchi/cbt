<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PercobaanRiwayat extends Model
{
    protected $table = 'cbt_percobaan_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_siswa',
        'id_jadwal_induk',
        'percobaan',
        'total',
        'komponen',
        'jawaban',
        'alasan',
        'mulai',
        'selesai',
        'lama_ujian',
        'time_create',
    ];

    protected $casts = [
        'komponen' => 'array',
        'jawaban' => 'array',
        'mulai' => 'datetime',
        'selesai' => 'datetime',
        'time_create' => 'datetime',
        'total' => 'float',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }
}
