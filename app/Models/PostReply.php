<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostReply extends Model
{
    protected $table = 'post_reply';

    protected $primaryKey = 'id_reply';

    public $timestamps = false;

    protected $fillable = [
        'id_comment',
        'dari',
        'dari_group',
        'text',
        'tanggal',
        'updated',
        'type',
    ];
}
