<?php

namespace App\Support;

class PengirimanEmail
{
    /**
     * Transport yang hanya menulis pesan ke log atau memori sehingga tidak ada
     * email yang benar-benar sampai ke siswa.
     */
    private const TRANSPORT_TANPA_PENGIRIMAN = ['log', 'array'];

    /**
     * Mailer dianggap dapat mengirim hanya bila seluruh jalur transportnya
     * berakhir di transport pengiriman sungguhan.
     */
    public static function dapatMengirim(?string $mailer = null): bool
    {
        return self::mailerDapatMengirim($mailer ?? (string) config('mail.default'));
    }

    private static function mailerDapatMengirim(string $nama): bool
    {
        $mailer = config('mail.mailers.'.$nama);

        if (! is_array($mailer)) {
            return false;
        }

        $transport = strtolower(trim((string) ($mailer['transport'] ?? '')));

        if ($transport === '' || in_array($transport, self::TRANSPORT_TANPA_PENGIRIMAN, true)) {
            return false;
        }

        // Failover/roundrobin bisa diam-diam jatuh ke log, jadi baru dipercaya
        // bila semua mailer anaknya benar-benar mengirim.
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $anak = $mailer['mailers'] ?? [];

            if (! is_array($anak) || $anak === []) {
                return false;
            }

            foreach ($anak as $namaAnak) {
                if (! self::mailerDapatMengirim((string) $namaAnak)) {
                    return false;
                }
            }
        }

        return true;
    }
}
