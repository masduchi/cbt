<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'post';

    protected $primaryKey = 'id_post';

    public $timestamps = false;

    protected $fillable = [
        'dari',
        'dari_group',
        'kepada',
        'text',
        'tanggal',
        'updated',
    ];
}
