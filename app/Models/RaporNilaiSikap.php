<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNilaiSikap extends Model
{
    protected $table = 'rapor_nilai_sikap';

    protected $primaryKey = 'id_nilai_sikap';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai_sikap',
        'id_siswa',
        'id_kelas',
        'id_tp',
        'id_smt',
        'jenis',
        'nilai',
        'deskripsi',
    ];
}
