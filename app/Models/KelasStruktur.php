<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelasStruktur extends Model
{
    protected $table = 'kelas_struktur';

    protected $primaryKey = 'id_kelas';

    public $timestamps = false;

    protected $fillable = [
        'id_kelas',
        'ketua',
        'wakil_ketua',
        'sekretaris_1',
        'sekretaris_2',
        'bendahara_1',
        'bendahara_2',
        'sie_ekstrakurikuler',
        'sie_upacara',
        'sie_olahraga',
        'sie_keagamaan',
        'sie_keamanan',
        'sie_ketertiban',
        'sie_kebersihan',
        'sie_keindahan',
        'sie_kesehatan',
        'sie_kekeluargaan',
        'sie_humas',
    ];
}
