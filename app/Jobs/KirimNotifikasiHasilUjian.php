<?php

namespace App\Jobs;

use App\Models\Jadwal;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Notifications\HasilUjianTersedia;
use App\Support\PengirimanEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class KirimNotifikasiHasilUjian implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Jeda antar percobaan (detik) agar SMTP yang sedang down tidak dihujani
     * tiga percobaan berurutan dalam hitungan milidetik.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $nilaiId,
        public string $email,
        public string $namaSiswa,
        public string $namaUjian,
        public string $jenisUjian,
        public float $nilaiAkhir,
    ) {}

    /**
     * Antre ulang notifikasi yang sebelumnya gagal permanen, misalnya karena
     * SMTP sedang tidak dapat dihubungi. Dipakai tombol halaman hasil dan
     * perintah artisan cbt:notifikasi-ulang.
     */
    public static function ulangGagal(?int $jadwalId = null): int
    {
        $query = Nilai::query()
            ->whereNotNull('hasil_notify_failed_at')
            ->whereNotNull('id_siswa')
            ->whereNotNull('id_jadwal')
            ->when($jadwalId !== null, fn ($builder) => $builder->where('id_jadwal', $jadwalId));

        $jumlah = 0;

        foreach ($query->cursor() as $nilai) {
            $siswa = Siswa::find($nilai->id_siswa);
            $jadwal = Jadwal::with(['bank:id_bank,bank_nama', 'jenis:id_jenis,nama_jenis'])->find($nilai->id_jadwal);

            if (! $siswa || ! $jadwal) {
                continue;
            }

            $email = trim((string) ($siswa->email ?? ''));

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }

            $total = (float) $nilai->pg_nilai
                + (float) $nilai->kompleks_nilai
                + (float) $nilai->jodohkan_nilai
                + (float) $nilai->isian_nilai
                + (float) $nilai->essai_nilai;

            self::dispatch(
                (int) $nilai->id_nilai,
                $email,
                (string) $siswa->nama,
                (string) ($jadwal->bank?->bank_nama ?? 'Ujian'),
                (string) ($jadwal->jenis?->nama_jenis ?? ''),
                round($total, 2),
            );

            $jumlah++;
        }

        return $jumlah;
    }

    /**
     * Kanal database selalu diisi lebih dulu sehingga siswa tanpa email tetap
     * menerima notifikasi. Email hanya dikirim bila alamatnya tersedia dan
     * mailer benar-benar mengirim; kegagalannya tidak menghapus notifikasi in-app.
     */
    public function handle(): void
    {
        $nilai = Nilai::find($this->nilaiId);

        if (! $nilai) {
            return;
        }

        $this->simpanKePusatNotifikasi($nilai);
        $this->kirimEmail();
    }

    /**
     * hasil_notified_at menandai hasil sudah tersedia di pusat notifikasi siswa,
     * bukan bahwa email berhasil dikirim. Idempoten agar percobaan ulang karena
     * email gagal tidak menggandakan notifikasi.
     */
    private function simpanKePusatNotifikasi(Nilai $nilai): void
    {
        if ($nilai->hasil_notified_at !== null) {
            return;
        }

        $siswa = Siswa::find($nilai->id_siswa);

        if (! $siswa) {
            return;
        }

        Notification::sendNow($siswa, $this->pesan(), ['database']);

        Nilai::where('id_nilai', $this->nilaiId)->update(['hasil_notified_at' => now()]);
        $nilai->hasil_notified_at = now();
    }

    private function kirimEmail(): void
    {
        if ($this->email === '' || ! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->bersihkanPenandaGagal();

            return;
        }

        if (! PengirimanEmail::dapatMengirim()) {
            $mailer = (string) config('mail.default');

            throw new RuntimeException(sprintf(
                'Mailer "%s" (transport %s) tidak mengirim email; notifikasi email belum boleh ditandai terkirim.',
                $mailer,
                (string) config('mail.mailers.'.$mailer.'.transport', 'tidak dikenal'),
            ));
        }

        Notification::route('mail', $this->email)->notifyNow($this->pesan());

        $this->bersihkanPenandaGagal();
    }

    private function pesan(): HasilUjianTersedia
    {
        return new HasilUjianTersedia(
            $this->namaSiswa,
            $this->namaUjian,
            $this->jenisUjian,
            $this->nilaiAkhir,
        );
    }

    private function bersihkanPenandaGagal(): void
    {
        Nilai::where('id_nilai', $this->nilaiId)->update([
            'hasil_notify_failed_at' => null,
            'hasil_notify_error' => null,
        ]);
    }

    /**
     * Job yang habis percobaannya menyimpan penanda gagal permanen supaya
     * email yang tidak pernah sampai tetap terlihat dan bisa ditindaklanjuti.
     */
    public function failed(?Throwable $e): void
    {
        $pesan = Str::limit(trim((string) $e?->getMessage()), 1000, '');

        Nilai::where('id_nilai', $this->nilaiId)->update([
            'hasil_notify_failed_at' => now(),
            'hasil_notify_error' => $pesan !== '' ? $pesan : 'Kegagalan tanpa pesan',
        ]);

        Log::warning('Notifikasi hasil ujian gagal dikirim', [
            'id_nilai' => $this->nilaiId,
            'message' => $e?->getMessage(),
        ]);
    }
}
