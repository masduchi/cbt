<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KopBerita extends Model
{
    protected $table = 'cbt_kop_berita';

    protected $primaryKey = 'id_kop';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'header_1',
        'header_2',
        'header_3',
        'header_4',
    ];
}
