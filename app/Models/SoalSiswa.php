<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoalSiswa extends Model
{
    protected $table = 'cbt_soal_siswa';

    protected $primaryKey = 'id_soal_siswa';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id_bank',
        'id_jadwal',
        'id_soal',
        'id_siswa',
        'jenis_soal',
        'no_soal_alias',
        'opsi_alias_a',
        'opsi_alias_b',
        'opsi_alias_c',
        'opsi_alias_d',
        'opsi_alias_e',
        'jawaban_alias',
        'jawaban_siswa',
        'jawaban_benar',
        'point_essai',
        'soal_end',
        'point_soal',
        'nilai_koreksi',
        'nilai_otomatis',
        'time_create',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'id_soal', 'id_soal');
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }

    public function koreksi(): HasMany
    {
        return $this->hasMany(Koreksi::class, 'id_soal_siswa', 'id_soal_siswa');
    }
}
