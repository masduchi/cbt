<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Koreksi;
use App\Models\KoreksiFinal;
use App\Models\KoreksiPenilai;
use App\Models\SoalRubrik;
use App\Models\SoalSiswa;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Nilai esai bisa diberikan oleh beberapa korektor. Setiap korektor menyimpan
 * skornya sendiri di cbt_koreksi; nilai akhir adalah rata-rata seluruh skor.
 * Admin dapat menetapkan nilai final terpisah yang mengalahkan rata-rata.
 */
class KoreksiEsai
{
    /** @return Collection<int, int> id_guru yang ditugaskan pada jadwal */
    public static function guruIds(Jadwal $jadwal): Collection
    {
        return KoreksiPenilai::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_guru');
    }

    /** @return Collection<int, int> id_user para korektor yang ditugaskan */
    public static function userIds(Jadwal $jadwal): Collection
    {
        $guruIds = self::guruIds($jadwal);

        if ($guruIds->isEmpty()) {
            return collect();
        }

        return Guru::whereIn('id_guru', $guruIds)->pluck('id_user')->filter()->values();
    }

    /**
     * Jumlah korektor yang harus menilai sebelum jawaban dianggap selesai.
     * Tanpa penugasan, pemilik bank soal (atau admin) menjadi satu-satunya korektor.
     */
    public static function wajib(Jadwal $jadwal): int
    {
        return max(1, self::userIds($jadwal)->count());
    }

    /**
     * Apakah pengguna boleh memberi skor koreksi (bukan sekadar final) pada jadwal.
     * Bila ada penugasan, hanya guru yang ditugaskan yang boleh menilai.
     */
    public static function bolehMenilai(Jadwal $jadwal, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);

        if ($guruId <= 0) {
            return false;
        }

        $penugasan = self::guruIds($jadwal);

        if ($penugasan->isNotEmpty()) {
            return $penugasan->contains($guruId);
        }

        return (int) $jadwal->bank?->bank_guru_id === $guruId;
    }

    /**
     * @return array{skor: float|null, jumlah: int, wajib: int, lengkap: bool, final: bool, selisih: float|null, perlu_tinjau: bool}
     */
    public static function agregat(SoalSiswa $row, ?int $wajib = null): array
    {
        $rows = Koreksi::where('id_soal_siswa', $row->id_soal_siswa)
            ->orderByDesc('updated_at')
            ->get();

        $final = KoreksiFinal::find($row->id_soal_siswa);

        return self::ringkas($rows, (float) $row->point_soal, $wajib ?? 1, $final);
    }

    /**
     * Menghitung nilai akhir dari kumpulan koreksi yang sudah dimuat, agar
     * daftar panjang tidak menimbulkan kueri berulang per jawaban.
     *
     * @param  Collection<int, Koreksi>  $rows
     * @return array{skor: float|null, jumlah: int, wajib: int, lengkap: bool, final: bool, selisih: float|null, perlu_tinjau: bool}
     */
    public static function ringkas(Collection $rows, float $maks, int $wajib, ?KoreksiFinal $final = null): array
    {
        if ($final) {
            $rows = $rows
                ->reject(fn (Koreksi $row) => (int) $row->id_user === (int) $final->id_user)
                ->values();
        }

        $selisih = $rows->count() > 1 ? round((float) $rows->max('skor') - (float) $rows->min('skor'), 2) : null;
        $batas = $maks > 0
            ? $maks * ((int) config('cbt.koreksi.selisih_persen', 15)) / 100
            : 0.0;

        $skor = $final
            ? (float) $final->skor
            : ($rows->isNotEmpty() ? round((float) $rows->avg('skor'), 2) : null);

        return [
            'skor' => $skor,
            'jumlah' => $rows->count(),
            'wajib' => $wajib,
            'lengkap' => $final !== null || $rows->count() >= $wajib,
            'final' => $final !== null,
            'selisih' => $selisih,
            'perlu_tinjau' => $final === null && $selisih !== null && $batas > 0 && $selisih >= $batas,
        ];
    }

    /**
     * Menyimpan skor seorang korektor lalu memperbarui nilai efektif jawaban.
     *
     * @param  array<int, float>  $rincian  skor per id_rubrik
     * @param  array<int, array{id_rubrik: int, kriteria: string, skor_maks: float}>  $snapshot
     */
    public static function simpan(
        SoalSiswa $row,
        User $user,
        float $skor,
        ?string $catatan = null,
        array $rincian = [],
        array $snapshot = [],
        ?int $wajib = null,
    ): Koreksi {
        $maks = (float) $row->point_soal;
        $skor = round(max(0, $maks > 0 ? min($skor, $maks) : $skor), 2);

        $koreksi = Koreksi::updateOrCreate(
            ['id_soal_siswa' => $row->id_soal_siswa, 'id_user' => $user->id],
            [
                'skor' => $skor,
                'catatan' => $catatan,
                'detail' => $rincian ?: null,
                'detail_rubrik' => $snapshot ?: null,
            ]
        );

        self::sinkron($row, $wajib);

        return $koreksi;
    }

    /** Menetapkan nilai final admin; tidak dihitung sebagai skor korektor. */
    public static function simpanFinal(
        SoalSiswa $row,
        User $user,
        float $skor,
        ?string $catatan = null,
        ?int $wajib = null,
    ): KoreksiFinal {
        $maks = (float) $row->point_soal;
        $skor = round(max(0, $maks > 0 ? min($skor, $maks) : $skor), 2);

        $final = KoreksiFinal::updateOrCreate(
            ['id_soal_siswa' => $row->id_soal_siswa],
            [
                'skor' => $skor,
                'id_user' => $user->id,
                'catatan' => $catatan,
            ]
        );

        self::sinkron($row, $wajib);

        return $final;
    }

    /** Menghapus nilai final sehingga rata-rata korektor berlaku kembali. */
    public static function hapusFinal(SoalSiswa $row, ?int $wajib = null): void
    {
        KoreksiFinal::where('id_soal_siswa', $row->id_soal_siswa)->delete();

        self::sinkron($row, $wajib);
    }

    /** Menulis ulang nilai_koreksi & status selesai pada baris jawaban siswa. */
    public static function sinkron(SoalSiswa $row, ?int $wajib = null): array
    {
        $agregat = self::agregat($row, $wajib);

        $row->nilai_koreksi = (string) round((float) ($agregat['skor'] ?? 0), 2);
        $row->nilai_otomatis = $agregat['lengkap'] ? 1 : 0;
        $row->save();

        return $agregat;
    }

    /**
     * Skor total rubrik diskalakan ke bobot soal sehingga rubrik boleh memakai
     * angka bebas (mis. 1-4 per kriteria) tanpa terikat bobot bank soal.
     *
     * @param  array<int, mixed>  $rincian  skor per id_rubrik
     * @param  Collection<int, SoalRubrik>  $rubrik
     */
    public static function skorRubrik(array $rincian, Collection $rubrik, float $maksSoal): float
    {
        $maksRubrik = (float) $rubrik->sum(fn (SoalRubrik $item) => (float) $item->skor_maks);

        if ($maksRubrik <= 0) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($rubrik as $item) {
            $nilai = $rincian[$item->id_rubrik] ?? $rincian[(string) $item->id_rubrik] ?? 0;
            $nilai = max(0, (float) $nilai);
            $total += min($nilai, (float) $item->skor_maks);
        }

        return round($total / $maksRubrik * max(0, $maksSoal), 2);
    }
}
