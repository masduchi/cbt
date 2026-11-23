<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KopAbsensi extends Model
{
    protected $table = 'cbt_kop_absensi';

    protected $primaryKey = 'id_kop';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'header_1',
        'header_2',
        'header_3',
        'header_4',
        'proktor',
        'pengawas_1',
        'pengawas_2',
    ];
}
