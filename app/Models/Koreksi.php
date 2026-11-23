<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Koreksi extends Model
{
    protected $table = 'cbt_koreksi';

    protected $primaryKey = 'id_koreksi';

    protected $fillable = [
        'id_soal_siswa',
        'id_user',
        'skor',
        'catatan',
        'detail',
        'detail_rubrik',
    ];

    protected $casts = [
        'skor' => 'float',
        'detail' => 'array',
        'detail_rubrik' => 'array',
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
