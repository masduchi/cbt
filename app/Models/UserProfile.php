<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $table = 'users_profile';

    protected $primaryKey = 'id_user';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nama_lengkap',
        'jabatan',
        'level_access',
        'foto',
    ];
}
