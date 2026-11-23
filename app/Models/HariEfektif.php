<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HariEfektif extends Model
{
    protected $table = 'master_hari_efektif';

    protected $primaryKey = 'id_hari_efektif';

    public $timestamps = false;

    protected $fillable = [
        'jml_hari_efektif',
    ];
}
