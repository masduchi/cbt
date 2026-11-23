<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporDataCatatan extends Model
{
    protected $table = 'rapor_data_catatan';

    protected $primaryKey = 'id_catatan';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'jenis',
        'kode',
        'deskripsi',
        'rank',
    ];
}
