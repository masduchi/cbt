<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use SoftDeletes;

    protected $table = 'master_kelas';

    protected $primaryKey = 'id_kelas';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'nama_kelas',
        'kode_kelas',
        'jurusan_id',
        'level_id',
        'guru_id',
        'siswa_id',
        'jumlah_siswa',
        'set_siswa',
    ];

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'kelas_siswa', 'id_kelas', 'id_siswa')
            ->withPivot(['id_tp', 'id_smt']);
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guru_id', 'id_guru');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id_jurusan');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelKelas::class, 'level_id', 'id_level');
    }

    public function tahun(): BelongsTo
    {
        return $this->belongsTo(TahunPelajaran::class, 'id_tp', 'id_tp');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_smt', 'id_smt');
    }
}
