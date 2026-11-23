<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $table = 'master_smt';

    protected $primaryKey = 'id_smt';

    public $timestamps = false;

    protected $fillable = [
        'smt',
        'nama_smt',
        'active',
    ];
}
