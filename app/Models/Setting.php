<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'setting';

    protected $primaryKey = 'id_setting';

    public $timestamps = false;

    protected $fillable = [
        'kode_sekolah',
        'sekolah',
        'npsn',
        'nss',
        'jenjang',
        'kepsek',
        'nip',
        'tanda_tangan',
        'alamat',
        'desa',
        'kecamatan',
        'kota',
        'provinsi',
        'kode_pos',
        'telp',
        'fax',
        'web',
        'email',
        'nama_aplikasi',
        'logo_kanan',
        'logo_kiri',
        'versi',
        'ip_server',
        'waktu',
        'server',
        'id_server',
        'sekolah_id',
        'db_versi',
        'satuan_pendidikan',
    ];
}
