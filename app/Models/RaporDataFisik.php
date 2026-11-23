<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporDataFisik extends Model
{
    protected $table = 'rapor_data_fisik';

    protected $primaryKey = 'id_fisik';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'jenis',
        'kode',
        'deskripsi',
    ];
}
