<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rekap extends Model
{
    protected $table = 'cbt_rekap';

    protected $primaryKey = 'id_rekap';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'tp',
        'id_smt',
        'smt',
        'id_jadwal',
        'id_jenis',
        'kode_jenis',
        'id_bank',
        'bank_kelas',
        'bank_kode',
        'bank_level',
        'id_mapel',
        'nama_mapel',
        'kode',
        'tgl_mulai',
        'tgl_selesai',
        'tampil_pg',
        'jawaban_pg',
        'tampil_esai',
        'jawaban_esai',
        'bobot_pg',
        'bobot_esai',
        'id_guru',
        'nama_guru',
        'nama_kelas',
        'soal_kompleks',
        'soal_jodohkan',
        'soal_isian',
        'soal_essai',
    ];
}
