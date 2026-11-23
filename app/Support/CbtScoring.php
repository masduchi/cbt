<?php

namespace App\Support;

use App\Jobs\KirimNotifikasiHasilUjian;
use App\Models\BankSoal;
use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\Nilai;
use App\Models\NomorPeserta;
use App\Models\RekapNilai;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use Illuminate\Support\Facades\Log;
use Throwable;

class CbtScoring
{
    public static function hitung(Jadwal $jadwal, int $siswaId): array
    {
        $jadwal->loadMissing('bank');
        $bank = $jadwal->bank ?: BankSoal::find($jadwal->id_bank);
        $rows = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswaId)
            ->get()
            ->groupBy('jenis_soal');

        $pgBenar = 0;
        foreach ($rows->get(1, collect()) as $row) {
            if (self::pgBenar($row)) {
                $pgBenar++;
            }
        }

        $kompleks = 0.0;
        foreach ($rows->get(2, collect()) as $row) {
            $correct = self::letters($row->jawaban_benar);
            $answer = self::letters($row->jawaban_siswa);
            if ($correct) {
                $benar = count(array_intersect($answer, $correct));
                $salah = count(array_diff($answer, $correct));

                $kompleks += max(0, ($benar - $salah) / count($correct));
            }
        }

        $jodohkan = 0.0;
        foreach ($rows->get(3, collect()) as $row) {
            $pairs = self::pairs($row->jawaban_benar);
            if (! $pairs) {
                continue;
            }
            $answer = json_decode((string) $row->jawaban_siswa, true);
            $answer = is_array($answer) ? $answer : [];
            $benar = 0;
            foreach ($pairs as $index => $_) {
                if (isset($answer[$index]) && $answer[$index] !== null && (int) $answer[$index] === $index) {
                    $benar++;
                }
            }
            $jodohkan += $benar / count($pairs);
        }

        $isianBenar = 0;
        foreach ($rows->get(4, collect()) as $row) {
            $correct = mb_strtolower(trim((string) $row->jawaban_benar));
            $answer = mb_strtolower(trim((string) $row->jawaban_siswa));
            if ($correct !== '' && $answer === $correct) {
                $isianBenar++;
            }
        }

        $esaiRows = $rows->get(5, collect());
        $essaiNilai = 0.0;
        $terkoreksi = 0;
        foreach ($esaiRows as $row) {
            $essaiNilai += (float) $row->nilai_koreksi;
            if ((int) $row->nilai_otomatis === 1) {
                $terkoreksi++;
            }
        }

        return [
            'pg_benar' => $pgBenar,
            'pg_nilai' => self::skala($pgBenar, $bank?->tampil_pg, $bank?->bobot_pg),
            'kompleks_nilai' => self::skala($kompleks, $bank?->tampil_kompleks, $bank?->bobot_kompleks),
            'jodohkan_nilai' => self::skala($jodohkan, $bank?->tampil_jodohkan, $bank?->bobot_jodohkan),
            'isian_nilai' => self::skala($isianBenar, $bank?->tampil_isian, $bank?->bobot_isian),
            'essai_nilai' => round($essaiNilai, 2),
            'dikoreksi' => $esaiRows->isEmpty() || $terkoreksi === $esaiRows->count(),
        ];
    }

    public static function simpan(Jadwal $jadwal, int $siswaId): Nilai
    {
        $hasil = self::hitung($jadwal, $siswaId);
        $id = self::nilaiId($siswaId, (int) $jadwal->id_jadwal);

        $nilai = Nilai::find($id) ?: new Nilai([
            'id_nilai' => $id,
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwal->id_jadwal,
        ]);

        $sebelumnyaDikoreksi = (int) ($nilai->dikoreksi ?? 0);

        $nilai->fill($hasil);
        $nilai->dikoreksi = $hasil['dikoreksi'] ? 1 : 0;
        $nilai->time_create = now();
        $nilai->save();

        self::rekap($jadwal, $siswaId, $nilai);

        if ($sebelumnyaDikoreksi === 0 && (int) $nilai->dikoreksi === 1) {
            self::kirimNotifikasiHasil($jadwal, $siswaId, $nilai);
        }

        return $nilai;
    }

    /**
     * Mengantrekan email satu kali saat nilai ujian pertama kali dinyatakan selesai.
     * Kegagalan pengiriman tidak boleh menggagalkan penyimpanan nilai.
     */
    private static function kirimNotifikasiHasil(Jadwal $jadwal, int $siswaId, Nilai $nilai): void
    {
        if ((int) $jadwal->hasil_tampil !== 1 || $nilai->hasil_notified_at !== null) {
            return;
        }

        $siswa = Siswa::find($siswaId);

        if (! $siswa) {
            return;
        }

        $email = trim((string) ($siswa->email ?? ''));

        // Email tidak wajib: job tetap berjalan untuk mengisi kanal database.
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $jadwal->loadMissing(['bank:id_bank,bank_nama', 'jenis:id_jenis,nama_jenis']);

        $total = (float) $nilai->pg_nilai
            + (float) $nilai->kompleks_nilai
            + (float) $nilai->jodohkan_nilai
            + (float) $nilai->isian_nilai
            + (float) $nilai->essai_nilai;

        try {
            // Penanda hasil_notified_at diisi oleh job setelah notifikasi tersimpan.
            KirimNotifikasiHasilUjian::dispatch(
                (int) $nilai->id_nilai,
                $email,
                (string) $siswa->nama,
                (string) ($jadwal->bank?->bank_nama ?? 'Ujian'),
                (string) ($jadwal->jenis?->nama_jenis ?? ''),
                round($total, 2),
            );
        } catch (Throwable $e) {
            Log::warning('Notifikasi hasil ujian gagal diantrekan', [
                'id_siswa' => $siswaId,
                'id_jadwal' => $jadwal->id_jadwal,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function rekap(Jadwal $jadwal, int $siswaId, ?Nilai $nilai = null): void
    {
        $jadwal->loadMissing(['bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis']);

        $nilai ??= Nilai::find(self::nilaiId($siswaId, (int) $jadwal->id_jadwal));

        if (! $nilai) {
            return;
        }

        $bank = $jadwal->bank ?: BankSoal::find($jadwal->id_bank);
        $siswa = Siswa::find($siswaId);
        $durasi = DurasiSiswa::where('id_siswa', $siswaId)->where('id_jadwal', $jadwal->id_jadwal)->first();
        $kelasSiswa = KelasSiswa::where('id_siswa', $siswaId)
            ->where('id_tp', $jadwal->id_tp)
            ->where('id_smt', $jadwal->id_smt)
            ->first();
        $kelas = $kelasSiswa ? Kelas::find($kelasSiswa->id_kelas) : null;
        $tp = TahunPelajaran::find($jadwal->id_tp);
        $smt = Semester::find($jadwal->id_smt);
        $nomor = NomorPeserta::where('id_siswa', $siswaId)
            ->where('id_tp', $jadwal->id_tp)
            ->where('id_smt', $jadwal->id_smt)
            ->value('nomor_peserta');

        $jawaban = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswaId)
            ->orderBy('no_soal_alias')
            ->get();

        $detail = fn (int $jenis, string $bobotField, string $nilaiField) => [
            'bobot' => (int) ($bank?->{$bobotField} ?? 0),
            'nilai' => (float) $nilai->{$nilaiField},
            'jawaban' => $jawaban->where('jenis_soal', $jenis)
                ->map(fn (SoalSiswa $row) => [
                    'no_soal' => (int) $row->no_soal_alias,
                    'id_soal' => (int) $row->id_soal,
                    'jawab' => $row->jawaban_siswa,
                    'koreksi' => (float) $row->nilai_koreksi,
                ])
                ->values()
                ->all(),
        ];

        RekapNilai::updateOrCreate(
            ['id_jadwal' => $jadwal->id_jadwal, 'id_siswa' => $siswaId],
            [
                'id_tp' => (int) $jadwal->id_tp,
                'tp' => $tp?->tahun ?? '',
                'id_smt' => (int) $jadwal->id_smt,
                'smt' => $smt?->smt ?? '',
                'id_jenis' => (int) $jadwal->id_jenis,
                'kode_jenis' => $jadwal->jenis?->kode_jenis ?? '',
                'id_bank' => (int) $jadwal->id_bank,
                'id_mapel' => (int) ($bank?->bank_mapel_id ?? 0),
                'id_siswa' => $siswaId,
                'id_kelas' => (int) ($kelas?->id_kelas ?? 0),
                'kelas' => $kelas?->nama_kelas ?? '',
                'mulai' => (string) ($durasi?->mulai ?? ''),
                'selesai' => (string) ($durasi?->selesai ?? ''),
                'durasi' => (string) ($durasi?->lama_ujian ?? ''),
                'bobot_pg' => (int) ($bank?->bobot_pg ?? 0),
                'jawaban_pg' => json_encode($detail(1, 'bobot_pg', 'pg_nilai')),
                'nilai_pg' => (string) $nilai->pg_nilai,
                'bobot_esai' => (int) ($bank?->bobot_esai ?? 0),
                'jawaban_esai' => json_encode($detail(5, 'bobot_esai', 'essai_nilai')),
                'nilai_esai' => (string) $nilai->essai_nilai,
                'id_guru' => $bank?->bank_guru_id,
                'nama_siswa' => $siswa?->nama,
                'no_peserta' => $nomor,
                'soal_kompleks' => json_encode($detail(2, 'bobot_kompleks', 'kompleks_nilai')),
                'soal_jodohkan' => json_encode($detail(3, 'bobot_jodohkan', 'jodohkan_nilai')),
                'soal_isian' => json_encode($detail(4, 'bobot_isian', 'isian_nilai')),
                'soal_essai' => json_encode($detail(5, 'bobot_esai', 'essai_nilai')),
                'time_create' => now(),
            ]
        );
    }

    public static function nilaiId(int $siswaId, int $jadwalId): int
    {
        return $siswaId * 100000 + $jadwalId;
    }

    public static function durasiId(int $siswaId, int $jadwalId): int
    {
        return $siswaId * 100000 + $jadwalId;
    }

    public static function pgBenar(SoalSiswa $row): bool
    {
        $answer = strtoupper(trim((string) $row->jawaban_siswa));
        $correct = strtoupper(trim((string) $row->jawaban_benar));

        return $answer !== '' && $answer === $correct;
    }

    public static function letters(?string $raw): array
    {
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            $value = strtoupper(trim((string) $raw));

            return $value === '' ? [] : [$value];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => strtoupper(trim((string) $value)),
            $decoded
        ), fn ($value) => $value !== '')));
    }

    public static function pairs(?string $raw): array
    {
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['pairs']) && is_array($decoded['pairs'])) {
            return array_values($decoded['pairs']);
        }

        return array_is_list($decoded) ? $decoded : [];
    }

    private static function skala(float $benar, $tampil, $bobot): float
    {
        $tampil = (int) $tampil;
        $bobot = (int) $bobot;

        if ($tampil <= 0 || $bobot <= 0) {
            return 0.0;
        }

        return round($benar / $tampil * $bobot, 2);
    }
}
