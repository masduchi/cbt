<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunningText extends Model
{
    protected $table = 'running_text';

    protected $primaryKey = 'id_text';

    public $timestamps = false;

    protected $fillable = [
        'text',
    ];
}
