<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoalRubrik extends Model
{
    protected $table = 'cbt_soal_rubrik';

    protected $primaryKey = 'id_rubrik';

    public $timestamps = false;

    protected $fillable = [
        'id_soal',
        'urutan',
        'kriteria',
        'skor_maks',
    ];

    protected $casts = [
        'skor_maks' => 'float',
    ];

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'id_soal', 'id_soal');
    }
}
