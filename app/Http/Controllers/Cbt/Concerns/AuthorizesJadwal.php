<?php

namespace App\Http\Controllers\Cbt\Concerns;

use App\Models\Guru;
use App\Models\Jadwal;

trait AuthorizesJadwal
{
    /**
     * Admin boleh membuka semua jadwal; guru hanya jadwal dari bank soalnya.
     * Bila $mustRemedial diisi, tipe jadwal juga harus cocok.
     */
    protected function authorizeJadwal($user, int $id, ?bool $mustRemedial = null): Jadwal
    {
        $jadwal = Jadwal::with(['bank', 'jenis'])->findOrFail($id);

        if (! $user->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
            abort_unless((int) $jadwal->bank?->bank_guru_id === $guruId, 403);
        }

        if ($mustRemedial !== null) {
            abort_unless((int) $jadwal->remedial === ($mustRemedial ? 1 : 0), 404);
        }

        return $jadwal;
    }
}
