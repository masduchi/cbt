<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasRuang extends Model
{
    protected $table = 'cbt_kelas_ruang';

    protected $primaryKey = 'id_kelas_ruang';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kelas',
        'id_ruang',
        'id_sesi',
        'id_tp',
        'id_smt',
        'set_siswa',
    ];
}
