<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesiSiswa extends Model
{
    protected $table = 'cbt_sesi_siswa';

    protected $primaryKey = 'siswa_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'kelas_id',
        'ruang_id',
        'sesi_id',
        'tp_id',
        'smt_id',
    ];
}
