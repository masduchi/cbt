<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNilaiAkhir extends Model
{
    protected $table = 'rapor_nilai_akhir';

    protected $primaryKey = 'id_nilai_akhir';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai_akhir',
        'id_mapel',
        'id_kelas',
        'id_siswa',
        'id_tp',
        'id_smt',
        'nilai',
        'akhir',
        'predikat',
    ];
}
