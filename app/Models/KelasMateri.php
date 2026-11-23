<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasMateri extends Model
{
    protected $table = 'kelas_materi';

    protected $primaryKey = 'id_materi';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'kode_materi',
        'id_guru',
        'materi_kelas',
        'id_mapel',
        'kode_mapel',
        'judul_materi',
        'isi_materi',
        'file',
        'link_file',
        'tgl_mulai',
        'tenggat',
        'created_on',
        'updated_on',
        'status',
        'youtube',
        'jenis',
    ];

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'id_mapel', 'id_mapel');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'id_guru');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(KelasJadwalMateri::class, 'id_materi', 'id_materi');
    }
}
