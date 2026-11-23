<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanGuru extends Model
{
    protected $table = 'jabatan_guru';

    protected $primaryKey = 'id_jabatan_guru';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_jabatan_guru',
        'id_guru',
        'id_jabatan',
        'id_kelas',
        'mapel_kelas',
        'ekstra_kelas',
        'id_tp',
        'id_smt',
    ];
}
