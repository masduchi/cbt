<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Siswa extends Model
{
    use Notifiable;
    use SoftDeletes;

    protected $table = 'master_siswa';

    protected $primaryKey = 'id_siswa';

    public $timestamps = false;

    protected $fillable = [
        'nisn',
        'nis',
        'nama',
        'jenis_kelamin',
        'username',
        'password',
        'kelas_awal',
        'tahun_masuk',
        'sekolah_asal',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'hp',
        'email',
        'foto',
        'anak_ke',
        'status_keluarga',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'nama_ayah',
        'tgl_lahir_ayah',
        'pendidikan_ayah',
        'pekerjaan_ayah',
        'nohp_ayah',
        'alamat_ayah',
        'nama_ibu',
        'tgl_lahir_ibu',
        'pendidikan_ibu',
        'pekerjaan_ibu',
        'nohp_ibu',
        'alamat_ibu',
        'nama_wali',
        'tgl_lahir_wali',
        'pendidikan_wali',
        'pekerjaan_wali',
        'nohp_wali',
        'alamat_wali',
        'nik',
        'warga_negara',
        'uid',
    ];

    protected $hidden = ['password'];

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_siswa', 'id_siswa', 'id_kelas')
            ->withPivot(['id_tp', 'id_smt']);
    }

    public function soalSiswa(): HasMany
    {
        return $this->hasMany(SoalSiswa::class, 'id_siswa');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'id_siswa');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }
}
