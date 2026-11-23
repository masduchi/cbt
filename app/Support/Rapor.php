<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\JabatanGuru;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\RaporAdminSetting;
use App\Models\RaporKkm;
use App\Models\RaporKunci;
use App\Models\User;

class Rapor
{
    public static function period(): array
    {
        return Period::current();
    }

    public static function guruId(User $user): ?int
    {
        $id = Guru::where('id_user', $user->id)->value('id_guru');

        return $id ? (int) $id : null;
    }

    public static function setting(int $tp, int $smt): ?RaporAdminSetting
    {
        return RaporAdminSetting::where('id_tp', $tp)->where('id_smt', $smt)->first();
    }

    public static function jabatan(?int $guruId, int $tp, int $smt): ?JabatanGuru
    {
        if (! $guruId) {
            return null;
        }

        return JabatanGuru::where('id_guru', $guruId)->where('id_tp', $tp)->where('id_smt', $smt)->first();
    }

    public static function unserialize(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $result = @unserialize($value, ['allowed_classes' => false]);

        if (is_array($result)) {
            return $result;
        }

        $json = json_decode($value, true);

        return is_array($json) ? $json : [];
    }

    /**
     * @return array<int, array{id_mapel: int, nama_mapel: string, kelas: array<int, int>}>
     */
    public static function mapelKelas(?string $serialized): array
    {
        $rows = [];

        foreach (self::unserialize($serialized) as $row) {
            $row = (array) $row;
            $kelas = [];

            foreach ((array) ($row['kelas_mapel'] ?? []) as $item) {
                $item = (array) $item;
                if (isset($item['kelas'])) {
                    $kelas[] = (int) $item['kelas'];
                }
            }

            $rows[] = [
                'id_mapel' => (int) ($row['id_mapel'] ?? 0),
                'nama_mapel' => (string) ($row['nama_mapel'] ?? ''),
                'kelas' => array_values(array_unique($kelas)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{id_ekstra: int, nama_ekstra: string, kelas: array<int, int>}>
     */
    public static function ekstraKelas(?string $serialized): array
    {
        $rows = [];

        foreach (self::unserialize($serialized) as $row) {
            $row = (array) $row;
            $kelas = [];

            foreach ((array) ($row['kelas_ekstra'] ?? []) as $item) {
                $item = (array) $item;
                if (isset($item['kelas'])) {
                    $kelas[] = (int) $item['kelas'];
                }
            }

            $rows[] = [
                'id_ekstra' => (int) ($row['id_ekstra'] ?? 0),
                'nama_ekstra' => (string) ($row['nama_ekstra'] ?? ''),
                'kelas' => array_values(array_unique($kelas)),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{id_mapel: int, nama_mapel: string, kelas: array<int, int>}>
     */
    public static function assignments(?int $guruId, int $tp, int $smt): array
    {
        $jabatan = self::jabatan($guruId, $tp, $smt);

        if (! $jabatan) {
            return [];
        }

        $names = Mapel::pluck('nama_mapel', 'id_mapel');
        $rows = self::mapelKelas($jabatan->mapel_kelas);

        foreach ($rows as &$row) {
            $row['nama_mapel'] = $names[$row['id_mapel']] ?? $row['nama_mapel'];
        }

        return $rows;
    }

    /**
     * Flat list of mapel/kelas pairs for the current period.
     */
    public static function allAssignments(int $tp, int $smt): array
    {
        $guruNames = Guru::pluck('nama_guru', 'id_guru');
        $kelasNames = Kelas::where('id_tp', $tp)->where('id_smt', $smt)->pluck('nama_kelas', 'id_kelas');
        $mapelNames = Mapel::pluck('nama_mapel', 'id_mapel');
        $rows = [];

        foreach (JabatanGuru::where('id_tp', $tp)->where('id_smt', $smt)->get() as $jabatan) {
            foreach (self::mapelKelas($jabatan->mapel_kelas) as $mapel) {
                foreach ($mapel['kelas'] as $kelas) {
                    $rows[] = [
                        'id_guru' => (int) $jabatan->id_guru,
                        'nama_guru' => $guruNames[$jabatan->id_guru] ?? '',
                        'id_mapel' => $mapel['id_mapel'],
                        'nama_mapel' => $mapelNames[$mapel['id_mapel']] ?? $mapel['nama_mapel'],
                        'id_kelas' => $kelas,
                        'nama_kelas' => $kelasNames[$kelas] ?? ('Kelas '.$kelas),
                    ];
                }
            }
        }

        return $rows;
    }

    public static function lock(int $kelas, int $tp, int $smt): ?RaporKunci
    {
        return RaporKunci::where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->where('id_kelas', $kelas)
            ->whereNotNull('locked_at')
            ->first();
    }

    public static function ensureUnlocked(int $kelas, int $tp, int $smt): void
    {
        if (self::lock($kelas, $tp, $smt)) {
            abort(423, 'Rapor kelas ini sudah dikunci. Buka kunci terlebih dahulu untuk mengubah nilai.');
        }
    }

    public static function hasMapelAccess(User $user, ?int $guruId, int $mapel, int $kelas, int $tp, int $smt): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        foreach (self::assignments($guruId, $tp, $smt) as $row) {
            if ($row['id_mapel'] === $mapel && in_array($kelas, $row['kelas'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function hasKelasAccess(User $user, ?int $guruId, int $kelas, int $tp, int $smt): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $guruId) {
            return false;
        }

        $kelasModel = Kelas::where('id_kelas', $kelas)
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->first();

        if ($kelasModel && (int) $kelasModel->guru_id === $guruId) {
            return true;
        }

        $jabatan = self::jabatan($guruId, $tp, $smt);

        return $jabatan && (int) $jabatan->id_kelas === $kelas;
    }

    public static function thresholds(int $kkm): array
    {
        $c = max(0, min(100, $kkm));
        $csd = (int) floor($c + (100 - $c) / 3);
        $b = $csd + 1;
        $bsd = (int) floor($b + (100 - $b) / 2);

        return ['c' => $c, 'csd' => $csd, 'b' => $b, 'bsd' => $bsd];
    }

    public static function predikat($nilai, int $kkm): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        $n = (int) round((float) $nilai);
        $t = self::thresholds($kkm);

        if ($n > $t['bsd']) {
            return 'A';
        }

        if ($n > $t['csd']) {
            return 'B';
        }

        if ($n > $t['c'] - 1) {
            return 'C';
        }

        return 'D';
    }

    public static function rata(array $values): ?int
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));

        if (count($values) < 2) {
            return null;
        }

        return (int) round(array_sum($values) / count($values));
    }

    public static function akhir($nhar, $npts, $npas, array $bobot): ?int
    {
        if (($nhar === null || $nhar === '') && ($npts === null || $npts === '') && ($npas === null || $npas === '')) {
            return null;
        }

        $ph = (int) ($bobot['bobot_ph'] ?? 0);
        $pts = (int) ($bobot['bobot_pts'] ?? 0);
        $pas = (int) ($bobot['bobot_pas'] ?? 0);

        return (int) round(
            ((float) $nhar * $ph / 100) + ((float) $npts * $pts / 100) + ((float) $npas * $pas / 100)
        );
    }

    /**
     * KKM and weight configuration for one mapel/kelas.
     */
    public static function kkm(int $mapel, int $kelas, int $tp, int $smt, ?RaporAdminSetting $setting): array
    {
        $default = [
            'kkm' => 75,
            'bobot_ph' => 40,
            'bobot_pts' => 30,
            'bobot_pas' => 30,
            'bobot_absen' => 10,
            'beban_jam' => 0,
        ];

        if ($setting && (int) $setting->kkm_tunggal === 1) {
            return [
                'kkm' => (int) ($setting->kkm ?? $default['kkm']),
                'bobot_ph' => (int) ($setting->bobot_ph ?? $default['bobot_ph']),
                'bobot_pts' => (int) ($setting->bobot_pts ?? $default['bobot_pts']),
                'bobot_pas' => (int) ($setting->bobot_pas ?? $default['bobot_pas']),
                'bobot_absen' => (int) ($setting->bobot_absen ?? $default['bobot_absen']),
                'beban_jam' => 0,
            ];
        }

        $row = RaporKkm::where('id_kkm', $mapel.$kelas.$tp.$smt.'1')->first();

        if (! $row) {
            return $default;
        }

        return [
            'kkm' => (int) ($row->kkm ?? $default['kkm']),
            'bobot_ph' => (int) ($row->bobot_ph ?? $default['bobot_ph']),
            'bobot_pts' => (int) ($row->bobot_pts ?? $default['bobot_pts']),
            'bobot_pas' => (int) ($row->bobot_pas ?? $default['bobot_pas']),
            'bobot_absen' => (int) ($row->bobot_absen ?? $default['bobot_absen']),
            'beban_jam' => (int) ($row->beban_jam ?? 0),
        ];
    }

    public static function deskripsi($nilai, string $predikat, array $materi = []): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        $phrase = match ($predikat) {
            'A' => 'Sangat baik',
            'B' => 'Baik',
            'C' => 'Cukup',
            'D' => 'Perlu bimbingan',
            default => '',
        };

        $materi = array_values(array_filter(array_map(
            fn ($item) => trim(strip_tags((string) $item)),
            $materi
        )));

        if ($materi && $phrase) {
            $materi = array_slice($materi, 0, 2);
            $last = array_pop($materi);
            $list = $materi ? implode(', ', $materi).' dan '.$last : $last;

            return "{$phrase} dalam {$list}.";
        }

        return $predikat === ''
            ? "Memperoleh nilai rata-rata {$nilai}."
            : "Memperoleh nilai rata-rata {$nilai} dengan predikat {$predikat}.";
    }

    /**
     * NISN tersimpan sebagai angka ZEROFILL (mis. 0061234567), sedangkan berkas
     * Excel sering memuat 61234567. Kunci pencarian dinormalisasi tanpa nol depan.
     */
    public static function nisnKey(?string $value): string
    {
        $value = trim((string) $value);
        $trimmed = ltrim($value, '0');

        return $trimmed === '' ? $value : $trimmed;
    }

    /**
     * Cari id siswa dari NISN/NIS pada indeks hasil studentIndex().
     */
    public static function studentIdByNomor(array $index, string $nisn, string $nis): ?int
    {
        foreach ([$nisn, $nis] as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            if (isset($index[$value])) {
                return (int) $index[$value];
            }

            $key = self::nisnKey($value);

            if ($key !== '' && isset($index[$key])) {
                return (int) $index[$key];
            }
        }

        return null;
    }

    public static function serialize(array $value): string
    {
        return serialize($value);
    }
}
