<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KoreksiPenilai extends Model
{
    protected $table = 'cbt_koreksi_penilai';

    protected $primaryKey = 'id_penilai';

    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'id_guru',
    ];

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'id_guru');
    }
}
