<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNilaiPts extends Model
{
    protected $table = 'rapor_nilai_pts';

    protected $primaryKey = 'id_nilai_pts';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai_pts',
        'id_mapel',
        'id_kelas',
        'id_siswa',
        'id_tp',
        'id_smt',
        'nilai',
        'predikat',
    ];
}
