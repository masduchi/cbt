<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasJadwalMapel extends Model
{
    protected $table = 'kelas_jadwal_mapel';

    protected $primaryKey = 'id_jadwal';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_kelas',
        'id_hari',
        'jam_ke',
        'id_mapel',
    ];

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'id_mapel', 'id_mapel');
    }
}
