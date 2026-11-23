<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\HasilUjianTersedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class NotifikasiHasilTest extends TestCase
{
    public function test_notifikasi_hasil_ujian_diantrikan_dan_memakai_kanal_database(): void
    {
        $notification = new HasilUjianTersedia('Siswa A', 'Ujian Akhir', 'PAS', 88.5);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail', 'database'], $notification->via(new User));
        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable));

        $this->assertSame([
            'nama_siswa' => 'Siswa A',
            'nama_ujian' => 'Ujian Akhir',
            'jenis_ujian' => 'PAS',
            'nilai_akhir' => 88.5,
        ], $notification->toArray(new User));
    }
}
