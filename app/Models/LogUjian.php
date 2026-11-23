<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogUjian extends Model
{
    protected $table = 'log_ujian';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = [
        'log_time',
        'id_siswa',
        'id_jadwal',
        'log_type',
        'log_desc',
        'kode',
        'address',
        'agent',
        'device',
        'reset',
        'finish_time',
    ];
}
