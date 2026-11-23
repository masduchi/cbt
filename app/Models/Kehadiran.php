<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kehadiran extends Model
{
    protected $table = 'cbt_kehadiran';

    protected $primaryKey = 'id_kehadiran';

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_siswa',
        'id_ruang',
        'id_sesi',
        'kode',
        'metode',
        'id_user',
        'waktu',
    ];

    protected $casts = [
        'waktu' => 'datetime',
    ];
}
