<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankSoal extends Model
{
    protected $table = 'cbt_bank_soal';

    protected $primaryKey = 'id_bank';

    public $timestamps = false;

    protected $fillable = [
        'bank_jenis_id',
        'bank_kode',
        'bank_level',
        'bank_kelas',
        'bank_mapel_id',
        'bank_jurusan_id',
        'bank_guru_id',
        'bank_nama',
        'kkm',
        'jml_soal',
        'jml_esai',
        'tampil_pg',
        'tampil_esai',
        'bobot_pg',
        'bobot_esai',
        'opsi',
        'date',
        'status',
        'soal_agama',
        'id_tp',
        'id_smt',
        'deskripsi',
        'jml_kompleks',
        'tampil_kompleks',
        'bobot_kompleks',
        'jml_jodohkan',
        'tampil_jodohkan',
        'bobot_jodohkan',
        'jml_isian',
        'tampil_isian',
        'bobot_isian',
        'status_soal',
    ];

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class, 'bank_id', 'id_bank')->orderBy('nomor_soal');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'bank_mapel_id', 'id_mapel');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'bank_guru_id', 'id_guru');
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisUjian::class, 'bank_jenis_id', 'id_jenis');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'bank_jurusan_id', 'id_jurusan');
    }
}
