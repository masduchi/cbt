<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporFisik extends Model
{
    protected $table = 'rapor_fisik';

    protected $primaryKey = 'id_fisik';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_fisik',
        'id_kelas',
        'id_siswa',
        'id_tp',
        'id_smt',
        'kondisi',
        'tinggi',
        'berat',
    ];
}
