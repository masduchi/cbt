<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Soal extends Model
{
    protected $table = 'cbt_soal';

    protected $primaryKey = 'id_soal';

    public $timestamps = false;

    protected $fillable = [
        'bank_id',
        'mapel_id',
        'jenis',
        'nomor_soal',
        'file',
        'file1',
        'tipe_file',
        'soal',
        'opsi_a',
        'opsi_b',
        'opsi_c',
        'opsi_d',
        'opsi_e',
        'file_a',
        'file_b',
        'file_c',
        'file_d',
        'file_e',
        'jawaban',
        'created_on',
        'updated_on',
        'tampilkan',
        'deskripsi',
        'kesulitan',
        'timer',
        'timer_menit',
    ];

    public function rubrik(): HasMany
    {
        return $this->hasMany(SoalRubrik::class, 'id_soal', 'id_soal')->orderBy('urutan');
    }
}
