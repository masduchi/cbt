<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengawas extends Model
{
    protected $table = 'cbt_pengawas';

    protected $primaryKey = 'id_pengawas';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_tp',
        'id_smt',
        'id_ruang',
        'id_sesi',
        'id_guru',
    ];
}
