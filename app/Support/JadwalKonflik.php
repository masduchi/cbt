<?php

namespace App\Support;

use App\Models\BankSoal;
use App\Models\Jadwal;

class JadwalKonflik
{
    /**
     * Cari jadwal lain pada periode yang sama yang waktunya bertumpang tindih
     * dan memakai kelas yang sama atau dibuat oleh guru yang sama.
     *
     * Hasil dipakai sebagai peringatan, bukan alasan menolak penyimpanan.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{id_jadwal: int, nama: string, kelas: array<int, int>, guru: bool}>
     */
    public static function cari(array $data, ?int $kecuali = null): array
    {
        $mulai = (string) ($data['tgl_mulai'] ?? '');
        $selesai = (string) ($data['tgl_selesai'] ?? '');

        if ($mulai === '' || $selesai === '' || blank($data['id_tp'] ?? null) || blank($data['id_smt'] ?? null)) {
            return [];
        }

        $bank = BankSoal::find((int) ($data['id_bank'] ?? 0));
        $kelas = array_values(array_filter(array_map('intval', explode(',', (string) $bank?->bank_kelas))));
        $guruId = (int) ($bank?->bank_guru_id ?? 0);

        return Jadwal::with('bank:id_bank,bank_nama,bank_kelas,bank_guru_id')
            ->where('id_tp', $data['id_tp'])
            ->where('id_smt', $data['id_smt'])
            ->when($kecuali !== null, fn ($query) => $query->where('id_jadwal', '<>', $kecuali))
            ->where('tgl_mulai', '<', $selesai)
            ->where('tgl_selesai', '>', $mulai)
            ->get()
            ->map(function (Jadwal $jadwal) use ($kelas, $guruId) {
                $kelasLain = array_values(array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas))));
                $irisan = array_values(array_intersect($kelas, $kelasLain));
                $guruSama = $guruId > 0 && (int) $jadwal->bank?->bank_guru_id === $guruId;

                if ($irisan === [] && ! $guruSama) {
                    return null;
                }

                return [
                    'id_jadwal' => (int) $jadwal->id_jadwal,
                    'nama' => (string) ($jadwal->bank?->bank_nama ?? '-'),
                    'kelas' => $irisan,
                    'guru' => $guruSama,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
