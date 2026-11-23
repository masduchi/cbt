<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasSiswa extends Model
{
    protected $table = 'kelas_siswa';

    protected $primaryKey = 'id_kelas_siswa';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_siswa',
        'id_kelas',
    ];
}
