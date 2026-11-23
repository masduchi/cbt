<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuInduk extends Model
{
    protected $table = 'buku_induk';

    protected $primaryKey = 'id_siswa';

    public $timestamps = false;

    protected $fillable = [
        'id_siswa',
        'uid',
        'rombel_awal',
        'nama_panggilan',
        'bahasa',
        'jml_saudara_kandung',
        'jml_saudara_tiri',
        'jml_saudara_angkat',
        'yatim',
        'tinggal_bersama',
        'jarak',
        'gol_darah',
        'penyakit',
        'kelainan_fisik',
        'kegemaran',
        'beasiswa',
        'no_ijazah_sebelumnya',
        'tahun_lulus_sebelumnya',
        'pindahan_dari',
        'alasan_kepindahan',
        'agama_ayah',
        'tempat_lahir_ayah',
        'wn_ayah',
        'penghasilan_ayah',
        'hidup_meninggal_ayah',
        'agama_ibu',
        'tempat_lahir_ibu',
        'wn_ibu',
        'penghasilan_ibu',
        'hidup_meninggal_ibu',
        'tempat_lahir_wali',
        'agama_wali',
        'wn_wali',
        'penghasilan_wali',
        'status',
        'tahun_lulus',
        'no_ijazah',
        'kelas_akhir',
        'lanjut_ke',
        'pindah_ke',
        'alasan_pindah',
        'tgl_pindah',
        'bekerja_di',
        'catatan_penting',
    ];
}
