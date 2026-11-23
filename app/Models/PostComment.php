<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $table = 'post_comments';

    protected $primaryKey = 'id_comment';

    public $timestamps = false;

    protected $fillable = [
        'id_post',
        'dari',
        'dari_group',
        'text',
        'tanggal',
        'updated',
        'type',
    ];
}
