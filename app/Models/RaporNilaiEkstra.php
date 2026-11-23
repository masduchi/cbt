<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporNilaiEkstra extends Model
{
    protected $table = 'rapor_nilai_ekstra';

    protected $primaryKey = 'id_nilai_ekstra';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_nilai_ekstra',
        'id_ekstra',
        'id_kelas',
        'id_siswa',
        'id_tp',
        'id_smt',
        'nilai',
        'predikat',
        'deskripsi',
    ];
}
