<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KoreksiFinal extends Model
{
    protected $table = 'cbt_koreksi_final';

    protected $primaryKey = 'id_soal_siswa';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_soal_siswa',
        'skor',
        'id_user',
        'catatan',
    ];

    protected $casts = [
        'skor' => 'float',
    ];

    public function soalSiswa(): BelongsTo
    {
        return $this->belongsTo(SoalSiswa::class, 'id_soal_siswa', 'id_soal_siswa');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
