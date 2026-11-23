<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporKikd extends Model
{
    protected $table = 'rapor_kikd';

    protected $primaryKey = 'id_kikd';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kikd',
        'id_mapel_kelas',
        'aspek',
        'id_tp',
        'id_smt',
        'materi_kikd',
    ];
}
