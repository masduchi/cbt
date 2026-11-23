<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisUjian extends Model
{
    protected $table = 'cbt_jenis';

    protected $primaryKey = 'id_jenis';

    public $timestamps = false;

    protected $fillable = [
        'nama_jenis',
        'kode_jenis',
    ];

    public function bankSoal(): HasMany
    {
        return $this->hasMany(BankSoal::class, 'bank_jenis_id', 'id_jenis');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'id_jenis', 'id_jenis');
    }
}
