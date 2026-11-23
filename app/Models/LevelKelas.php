<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelKelas extends Model
{
    protected $table = 'level_kelas';

    protected $primaryKey = 'id_level';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'level',
    ];
}
