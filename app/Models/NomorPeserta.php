<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NomorPeserta extends Model
{
    protected $table = 'cbt_nomor_peserta';

    protected $primaryKey = 'id_nomor';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_siswa',
        'id_tp',
        'id_smt',
        'nomor_peserta',
    ];
}
