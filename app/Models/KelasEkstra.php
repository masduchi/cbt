<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasEkstra extends Model
{
    protected $table = 'kelas_ekstra';

    protected $primaryKey = 'id_kelas_ekstra';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kelas_ekstra',
        'id_tp',
        'id_smt',
        'id_kelas',
        'ekstra',
    ];
}
