<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'log';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = [
        'log_time',
        'id_user',
        'id_group',
        'name_group',
        'log_type',
        'log_desc',
        'address',
        'agent',
        'device',
    ];
}
