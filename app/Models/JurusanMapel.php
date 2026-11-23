<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurusanMapel extends Model
{
    protected $table = 'jurusan_mapel';

    protected $primaryKey = 'id_jurusan_mapel';

    public $timestamps = false;

    protected $fillable = [
        'id_jurusan',
        'id_mapel',
    ];
}
