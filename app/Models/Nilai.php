<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nilai extends Model
{
    protected $table = 'cbt_nilai';

    protected $primaryKey = 'id_nilai';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai',
        'pg_benar',
        'pg_nilai',
        'essai_nilai',
        'id_siswa',
        'id_jadwal',
        'kompleks_nilai',
        'jodohkan_nilai',
        'isian_nilai',
        'dikoreksi',
        'hasil_notified_at',
        'hasil_notify_failed_at',
        'hasil_notify_error',
        'time_create',
    ];

    protected $casts = [
        'hasil_notified_at' => 'datetime',
        'hasil_notify_failed_at' => 'datetime',
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
