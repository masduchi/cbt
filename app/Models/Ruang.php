<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruang extends Model
{
    protected $table = 'cbt_ruang';

    protected $primaryKey = 'id_ruang';

    public $timestamps = false;

    protected $fillable = [
        'nama_ruang',
        'kode_ruang',
    ];

    public function siswaSesi(): HasMany
    {
        return $this->hasMany(SesiSiswa::class, 'ruang_id', 'id_ruang');
    }
}
