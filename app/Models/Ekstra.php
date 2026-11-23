<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ekstra extends Model
{
    protected $table = 'master_ekstra';

    protected $primaryKey = 'id_ekstra';

    public $timestamps = false;

    protected $fillable = [
        'nama_ekstra',
        'kode_ekstra',
    ];
}
