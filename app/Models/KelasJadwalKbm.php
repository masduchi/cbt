<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasJadwalKbm extends Model
{
    protected $table = 'kelas_jadwal_kbm';

    protected $primaryKey = 'id_kbm';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'kbm_jam_pel',
        'kbm_jam_mulai',
        'kbm_jml_mapel_hari',
        'istirahat',
    ];
}
