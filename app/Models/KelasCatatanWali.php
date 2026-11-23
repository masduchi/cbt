<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasCatatanWali extends Model
{
    protected $table = 'kelas_catatan_wali';

    protected $primaryKey = 'id_catatan';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'type',
        'level',
        'tgl',
        'id_siswa',
        'id_kelas',
        'text',
        'readed',
        'reading',
        'jml',
    ];
}
