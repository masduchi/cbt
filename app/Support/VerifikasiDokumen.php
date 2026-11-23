<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VerifikasiDokumen
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Satu dokumen cetak memakai satu kode verifikasi yang stabil selama isinya
     * tidak berubah. Bila ringkasan berubah, kode lama dicabut agar QR yang
     * sudah beredar tidak lagi menampilkan data basi, lalu kode baru diterbitkan.
     *
     * Kolom kunci_aktif berindeks unik menjaga agar cetak bersamaan tidak
     * menghasilkan dua kode aktif untuk pasangan jenis+ref yang sama.
     *
     * @param  array<string, string|int|float|null>  $ringkasan
     */
    public static function terbitkan(string $jenis, string $ref, array $ringkasan = []): string
    {
        $ringkasanJson = $ringkasan === [] ? null : json_encode($ringkasan, JSON_UNESCAPED_UNICODE);
        $kunci = $jenis.'|'.$ref;

        // Baris yang sudah dicabut tidak boleh memegang kunci aktif, termasuk
        // data lama yang dicabut langsung di database.
        DB::table('dokumen_verifikasi')
            ->where('kunci_aktif', $kunci)
            ->whereNotNull('revoked_at')
            ->update(['kunci_aktif' => null]);

        for ($percobaan = 0; $percobaan < 3; $percobaan++) {
            $aktif = DB::table('dokumen_verifikasi')->where('kunci_aktif', $kunci)->first();

            if ($aktif && self::ringkasanSama($aktif->ringkasan, $ringkasanJson)) {
                return (string) $aktif->kode;
            }

            $kode = self::kodeUnik();

            try {
                DB::transaction(function () use ($aktif, $kode, $jenis, $ref, $kunci, $ringkasanJson): void {
                    if ($aktif) {
                        DB::table('dokumen_verifikasi')
                            ->where('id', $aktif->id)
                            ->whereNull('revoked_at')
                            ->update([
                                'revoked_at' => now(),
                                'kunci_aktif' => null,
                                'updated_at' => now(),
                            ]);
                    }

                    DB::table('dokumen_verifikasi')->insert([
                        'kode' => $kode,
                        'jenis' => $jenis,
                        'ref' => $ref,
                        'kunci_aktif' => $kunci,
                        'ringkasan' => $ringkasanJson,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                return $kode;
            } catch (UniqueConstraintViolationException) {
                // Proses cetak lain lebih dulu menerbitkan kode aktif. Ulangi
                // agar kode yang menang dipakai, bukan membuat duplikat.
                continue;
            }
        }

        $kode = DB::table('dokumen_verifikasi')->where('kunci_aktif', $kunci)->value('kode');

        if ($kode !== null) {
            return (string) $kode;
        }

        throw new RuntimeException('Gagal menerbitkan kode verifikasi unik untuk '.$kunci.'.');
    }

    public static function url(string $kode): string
    {
        return route('verifikasi.show', ['kode' => $kode]);
    }

    /**
     * Data QR siap tempel pada dokumen cetak.
     *
     * @return array{kode: string, url: string, svg: string}
     */
    public static function qr(string $kode, int $ukuran = 2): array
    {
        $url = self::url($kode);

        try {
            $svg = QrCode::svg($url, $ukuran, 2);
        } catch (Throwable) {
            $svg = '';
        }

        return ['kode' => $kode, 'url' => $url, 'svg' => $svg];
    }

    public static function temukan(string $kode): ?object
    {
        return DB::table('dokumen_verifikasi')
            ->where('kode', strtoupper($kode))
            ->first();
    }

    private static function ringkasanSama(?string $lama, ?string $baru): bool
    {
        return json_decode((string) $lama, true) == json_decode((string) $baru, true);
    }

    private static function kodeUnik(): string
    {
        do {
            $kode = '';

            for ($i = 0; $i < 10; $i++) {
                $kode .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (DB::table('dokumen_verifikasi')->where('kode', $kode)->exists());

        return $kode;
    }
}
