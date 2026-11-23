<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KopKartu extends Model
{
    protected $table = 'cbt_kop_kartu';

    protected $primaryKey = 'id_set_kartu';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'header_1',
        'header_2',
        'header_3',
        'header_4',
        'tanggal',
    ];
}
