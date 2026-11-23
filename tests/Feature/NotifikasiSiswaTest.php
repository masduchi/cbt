<?php

namespace Tests\Feature;

use App\Jobs\KirimNotifikasiHasilUjian;
use App\Models\Group;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\HasilUjianTersedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotifikasiSiswaTest extends TestCase
{
    use RefreshDatabase;

    private User $userAhmad;

    private User $userBudi;

    private User $guru;

    private Siswa $ahmad;

    private Siswa $budi;

    private Nilai $nilaiAhmad;

    protected function setUp(): void
    {
        parent::setUp();

        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->userAhmad = $this->user('ahmadnotif');
        $this->userAhmad->groups()->attach($siswaGroup->id);

        $this->userBudi = $this->user('budinotif');
        $this->userBudi->groups()->attach($siswaGroup->id);

        $this->guru = $this->user('gurunotif');
        $this->guru->groups()->attach($guruGroup->id);

        // Ahmad sengaja tidak punya email.
        $this->ahmad = $this->siswa('2027001', 'Ahmad Fauzi', 'ahmadnotif', '');
        $this->budi = $this->siswa('2027002', 'Budi Santoso', 'budinotif', 'budi@example.test');

        $this->nilaiAhmad = Nilai::create([
            'id_nilai' => 5001,
            'id_siswa' => $this->ahmad->id_siswa,
            'id_jadwal' => 1,
        ]);
    }

    public function test_job_menyimpan_notifikasi_database_untuk_siswa_tanpa_email(): void
    {
        $job = new KirimNotifikasiHasilUjian(
            (int) $this->nilaiAhmad->id_nilai,
            '',
            'Ahmad Fauzi',
            'PAS Matematika',
            'PAS',
            88.5,
        );

        $job->handle();

        $notifikasi = $this->ahmad->notifications()->sole();

        $this->assertNull($notifikasi->read_at);
        $this->assertSame('PAS Matematika', $notifikasi->data['nama_ujian']);
        $this->assertSame('PAS', $notifikasi->data['jenis_ujian']);
        $this->assertSame(88.5, (float) $notifikasi->data['nilai_akhir']);

        $this->assertNotNull(
            $this->nilaiAhmad->fresh()->hasil_notified_at,
            'Siswa tanpa email harus mendapat penanda status kirim dari kanal database.'
        );
    }

    public function test_siswa_hanya_melihat_notifikasi_miliknya(): void
    {
        $this->kirimUntukAhmad();
        Notification::sendNow($this->budi, new HasilUjianTersedia('Budi Santoso', 'UAS Biologi', 'UAS', 70.0), ['database']);

        $this->actingAs($this->userAhmad)
            ->get('/siswa/notifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Siswa/Notifikasi')
                ->where('student.nama', 'Ahmad Fauzi')
                ->has('notifikasi', 1)
                ->where('notifikasi.0.ujian', 'PAS Matematika')
                ->where('notifikasi.0.dibaca', false)
                ->where('ringkasan.total', 1)
                ->where('ringkasan.belum', 1));
    }

    public function test_siswa_tidak_dapat_menandai_notifikasi_siswa_lain(): void
    {
        $this->kirimUntukAhmad();
        Notification::sendNow($this->budi, new HasilUjianTersedia('Budi Santoso', 'UAS Biologi', 'UAS', 70.0), ['database']);

        $notifikasiBudi = $this->budi->notifications()->sole();

        $this->actingAs($this->userAhmad)
            ->post('/siswa/notifikasi/'.$notifikasiBudi->id.'/baca')
            ->assertNotFound();

        $this->assertNull($notifikasiBudi->fresh()->read_at);

        $notifikasiAhmad = $this->ahmad->notifications()->sole();

        $this->actingAs($this->userAhmad)
            ->post('/siswa/notifikasi/'.$notifikasiAhmad->id.'/baca')
            ->assertRedirect();

        $this->assertNotNull($notifikasiAhmad->fresh()->read_at);
        $this->assertNull($notifikasiBudi->fresh()->read_at);
    }

    public function test_tandai_semua_hanya_berlaku_untuk_notifikasi_siswa_sendiri(): void
    {
        $this->kirimUntukAhmad();

        Notification::sendNow($this->ahmad, new HasilUjianTersedia('Ahmad Fauzi', 'UAS Kimia', 'UAS', 80.0), ['database']);
        Notification::sendNow($this->budi, new HasilUjianTersedia('Budi Santoso', 'UAS Biologi', 'UAS', 70.0), ['database']);

        $this->actingAs($this->userAhmad)
            ->post('/siswa/notifikasi/baca-semua')
            ->assertRedirect();

        $this->assertSame(0, $this->ahmad->unreadNotifications()->count());
        $this->assertSame(1, $this->budi->unreadNotifications()->count());
    }

    public function test_guru_tidak_dapat_membuka_pusat_notifikasi_siswa(): void
    {
        $this->actingAs($this->guru)
            ->get('/siswa/notifikasi')
            ->assertForbidden();
    }

    private function kirimUntukAhmad(): void
    {
        (new KirimNotifikasiHasilUjian(
            (int) $this->nilaiAhmad->id_nilai,
            '',
            'Ahmad Fauzi',
            'PAS Matematika',
            'PAS',
            88.5,
        ))->handle();
    }

    private function user(string $username): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
    }

    private function siswa(string $nis, string $nama, string $username, string $email): Siswa
    {
        return Siswa::create([
            'nisn' => '006'.substr($nis, -7),
            'nis' => $nis,
            'nama' => $nama,
            'username' => $username,
            'email' => $email,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-'.$username,
        ]);
    }
}
