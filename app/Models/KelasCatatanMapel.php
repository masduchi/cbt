<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasCatatanMapel extends Model
{
    protected $table = 'kelas_catatan_mapel';

    protected $primaryKey = 'id_catatan';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'type',
        'id_siswa',
        'id_mapel',
        'id_kelas',
        'id_guru',
        'level',
        'tgl',
        'text',
        'readed',
        'reading',
        'jml',
    ];
}
