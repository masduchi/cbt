<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelompokMapel extends Model
{
    protected $table = 'master_kelompok_mapel';

    protected $primaryKey = 'id_kel_mapel';

    public $timestamps = false;

    protected $fillable = [
        'kode_kel_mapel',
        'nama_kel_mapel',
        'kategori',
        'id_parent',
    ];
}
