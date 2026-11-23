<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jurusan extends Model
{
    protected $table = 'master_jurusan';

    protected $primaryKey = 'id_jurusan';

    public $timestamps = false;

    protected $fillable = [
        'nama_jurusan',
        'kode_jurusan',
        'mapel_peminatan',
        'status',
        'deletable',
    ];

    public function mapels(): BelongsToMany
    {
        return $this->belongsToMany(Mapel::class, 'jurusan_mapel', 'id_jurusan', 'id_mapel');
    }
}
