<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporDataSikap extends Model
{
    protected $table = 'rapor_data_sikap';

    protected $primaryKey = 'id_sikap';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'jenis',
        'kode',
        'sikap',
    ];
}
