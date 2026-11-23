<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DurasiSiswa extends Model
{
    protected $table = 'cbt_durasi_siswa';

    protected $primaryKey = 'id_durasi';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_durasi',
        'id_siswa',
        'id_jadwal',
        'status',
        'lama_ujian',
        'mulai',
        'selesai',
        'reset',
        'session_id',
        'ip_address',
        'time_create',
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
