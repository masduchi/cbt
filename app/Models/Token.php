<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Token extends Model
{
    protected $table = 'cbt_token';

    protected $primaryKey = 'id_token';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'auto',
        'jarak',
        'updated',
    ];

    /**
     * Kolom `updated` berasal dari aplikasi lama berupa string bebas, sehingga
     * nilai rusak seperti '0000-00-00 00:00:00' harus dianggap belum pernah diubah.
     */
    public function updatedAt(): ?Carbon
    {
        if ($this->updated === null || trim((string) $this->updated) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($this->updated);
        } catch (\Throwable) {
            return null;
        }

        return $date->year >= 1900 ? $date : null;
    }

    /**
     * Token hanya kedaluwarsa bila pembaruan otomatis aktif. Dengan mode
     * manual, token tetap berlaku sampai pengawas membuat token baru.
     */
    public function expiresAt(): ?Carbon
    {
        if ((int) $this->auto !== 1 || (int) $this->jarak <= 0) {
            return null;
        }

        return $this->updatedAt()?->addMinutes((int) $this->jarak);
    }

    public function kedaluwarsa(): bool
    {
        return $this->expiresAt()?->isPast() ?? false;
    }
}
