<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporKkm extends Model
{
    protected $table = 'rapor_kkm';

    protected $primaryKey = 'id_kkm';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kkm',
        'kkm',
        'bobot_ph',
        'bobot_pts',
        'bobot_pas',
        'bobot_absen',
        'beban_jam',
        'id_tp',
        'id_smt',
        'jenis',
        'id_kelas',
        'id_mapel',
    ];
}
