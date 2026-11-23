<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Guru extends Model
{
    use SoftDeletes;

    protected $table = 'master_guru';

    protected $primaryKey = 'id_guru';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nip',
        'nama_guru',
        'email',
        'kode_guru',
        'username',
        'password',
        'no_ktp',
        'tempat_lahir',
        'tgl_lahir',
        'jenis_kelamin',
        'agama',
        'no_hp',
        'alamat_jalan',
        'rt_rw',
        'dusun',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'kewarganegaraan',
        'nuptk',
        'jenis_ptk',
        'tgs_tambahan',
        'status_pegawai',
        'status_aktif',
        'status_nikah',
        'tmt',
        'keahlian_isyarat',
        'npwp',
        'foto',
    ];

    protected $hidden = ['password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function bankSoal(): HasMany
    {
        return $this->hasMany(BankSoal::class, 'bank_guru_id', 'id_guru');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }
}
