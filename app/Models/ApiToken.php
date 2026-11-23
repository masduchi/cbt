<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $table = 'api_token';

    protected $primaryKey = 'id_api';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'address',
        'agent',
        'device',
        'token',
    ];

    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
