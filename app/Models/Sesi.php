<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sesi extends Model
{
    protected $table = 'cbt_sesi';

    protected $primaryKey = 'id_sesi';

    public $timestamps = false;

    protected $fillable = [
        'nama_sesi',
        'kode_sesi',
        'waktu_mulai',
        'waktu_akhir',
        'aktif',
    ];

    public function siswaSesi(): HasMany
    {
        return $this->hasMany(SesiSiswa::class, 'sesi_id', 'id_sesi');
    }
}
