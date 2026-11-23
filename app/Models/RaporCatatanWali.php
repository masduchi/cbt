<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporCatatanWali extends Model
{
    protected $table = 'rapor_catatan_wali';

    protected $primaryKey = 'id_catatan_wali';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_catatan_wali',
        'id_tp',
        'id_smt',
        'id_kelas',
        'id_siswa',
        'nilai',
        'deskripsi',
    ];
}
