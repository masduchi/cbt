<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNaik extends Model
{
    protected $table = 'rapor_naik';

    protected $primaryKey = 'id_naik';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_naik',
        'id_tp',
        'id_smt',
        'id_siswa',
        'naik',
    ];
}
