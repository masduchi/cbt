<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HasilUjianTersedia extends Notification implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        public string $namaSiswa,
        public string $namaUjian,
        public string $jenisUjian,
        public float $nilaiAkhir,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Notifikasi on-demand hanya punya alamat email, bukan akun, sehingga
        // kanal database tidak bisa disimpan untuknya.
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ujian = $this->namaUjian;

        if ($this->jenisUjian !== '') {
            $ujian .= ' ('.$this->jenisUjian.')';
        }

        return (new MailMessage)
            ->subject('Nilai ujian tersedia: '.$this->namaUjian)
            ->greeting('Halo '.$this->namaSiswa.',')
            ->line('Nilai ujian **'.$ujian.'** sudah selesai dikoreksi dan tersedia.')
            ->line('Nilai akhir: **'.$this->nilaiAkhir.'**')
            ->line('Buka portal CBT untuk melihat rincian nilai ujian Anda.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'nama_siswa' => $this->namaSiswa,
            'nama_ujian' => $this->namaUjian,
            'jenis_ujian' => $this->jenisUjian,
            'nilai_akhir' => $this->nilaiAkhir,
        ];
    }
}
