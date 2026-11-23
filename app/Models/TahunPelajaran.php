<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahunPelajaran extends Model
{
    protected $table = 'master_tp';

    protected $primaryKey = 'id_tp';

    public $timestamps = false;

    protected $fillable = [
        'tahun',
        'active',
    ];
}
