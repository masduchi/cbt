<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaporAdminSetting extends Model
{
    protected $table = 'rapor_admin_setting';

    protected $primaryKey = 'id_setting';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_setting',
        'id_tp',
        'id_smt',
        'kkm_tunggal',
        'kkm',
        'bobot_ph',
        'bobot_pts',
        'bobot_pas',
        'bobot_absen',
        'tgl_rapor_akhir',
        'tgl_rapor_kelas_akhir',
        'tgl_rapor_pts',
        'nip_kepsek',
        'nip_walikelas',
    ];
}
