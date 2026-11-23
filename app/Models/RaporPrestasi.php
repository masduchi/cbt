<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporPrestasi extends Model
{
    protected $table = 'rapor_prestasi';

    protected $primaryKey = 'id_ranking';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_ranking',
        'id_kelas',
        'id_siswa',
        'id_tp',
        'id_smt',
        'ranking',
        'deskripsi',
        'p1',
        'p1_desk',
        'p2',
        'p2_desk',
        'p3',
        'p3_desk',
    ];
}
