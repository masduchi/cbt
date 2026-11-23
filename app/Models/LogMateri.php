<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogMateri extends Model
{
    protected $table = 'log_materi';

    protected $primaryKey = 'id_log';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_log',
        'log_time',
        'id_siswa',
        'jam_ke',
        'id_materi',
        'id_mapel',
        'log_type',
        'log_desc',
        'text',
        'file',
        'nilai',
        'catatan',
        'dinilai_oleh',
        'dinilai_at',
        'address',
        'agent',
        'device',
        'finish_time',
    ];
}
