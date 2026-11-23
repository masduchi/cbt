<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNilaiHarian extends Model
{
    protected $table = 'rapor_nilai_harian';

    protected $primaryKey = 'id_nilai_harian';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai_harian',
        'id_siswa',
        'id_mapel',
        'id_kelas',
        'id_tp',
        'id_smt',
        'p1',
        'p2',
        'p3',
        'p4',
        'p5',
        'p6',
        'p7',
        'p8',
        'p_rata_rata',
        'p_predikat',
        'p_deskripsi',
        'k1',
        'k2',
        'k3',
        'k4',
        'k5',
        'k6',
        'k7',
        'k8',
        'k_rata_rata',
        'k_predikat',
        'k_deskripsi',
        'jml',
    ];
}
