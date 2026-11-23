<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasJadwalMateri extends Model
{
    protected $table = 'kelas_jadwal_materi';

    protected $primaryKey = 'id_kjm';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_kjm',
        'id_tp',
        'id_smt',
        'id_materi',
        'id_mapel',
        'id_kelas',
        'jadwal_materi',
        'jenis',
    ];

    public function materi(): BelongsTo
    {
        return $this->belongsTo(KelasMateri::class, 'id_materi', 'id_materi');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'id_mapel', 'id_mapel');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id_kelas');
    }
}
