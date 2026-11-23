<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jadwal extends Model
{
    protected $table = 'cbt_jadwal';

    protected $primaryKey = 'id_jadwal';

    public $timestamps = false;

    protected $fillable = [
        'id_tp',
        'id_smt',
        'id_bank',
        'id_jenis',
        'tgl_mulai',
        'tgl_selesai',
        'durasi_ujian',
        'pengawas',
        'acak_soal',
        'acak_opsi',
        'pilih_acak',
        'hasil_tampil',
        'token',
        'status',
        'remedial',
        'id_jadwal_induk',
        'ulang',
        'reset_login',
        'rekap',
        'jam_ke',
        'jarak',
        'time_create',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankSoal::class, 'id_bank', 'id_bank');
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisUjian::class, 'id_jenis', 'id_jenis');
    }

    public function durasi(): HasMany
    {
        return $this->hasMany(DurasiSiswa::class, 'id_jadwal', 'id_jadwal');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'id_jadwal', 'id_jadwal');
    }

    public function pengawas(): HasMany
    {
        return $this->hasMany(Pengawas::class, 'id_jadwal', 'id_jadwal');
    }

    public function remedialPeserta(): HasMany
    {
        return $this->hasMany(RemedialPeserta::class, 'id_jadwal', 'id_jadwal');
    }

    public function isRemedial(): bool
    {
        return (int) $this->remedial === 1;
    }

    /**
     * Hanya jadwal ujian utama; sesi remedial selalu turunan jadwal induk.
     */
    public function scopeInduk($query)
    {
        return $query->where('remedial', 0);
    }
}
