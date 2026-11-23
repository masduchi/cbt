<?php

namespace Tests\Feature;

use App\Models\BankSoal;
use App\Models\DurasiSiswa;
use App\Models\Group;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UjianJamKeTest extends TestCase
{
    use RefreshDatabase;

    private User $siswaUser;

    private Siswa $siswa;

    private Kelas $kelas;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Jadwal $jam1;

    private Jadwal $jam2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 09:00:00');

        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '2027000001',
            'nis' => '2027001',
            'nama' => 'Ahmad Fauzi',
            'username' => 'siswa-jam',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-jam-1',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        $this->siswaUser = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'siswa-jam',
            'password' => 'rahasia123',
            'email' => 'siswa-jam@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->jam1 = $this->jadwal('Matematika', 1, '2026-11-12');
        $this->jam2 = $this->jadwal('Bahasa Indonesia', 2, '2026-11-12');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_second_slot_is_locked_until_the_first_is_finished(): void
    {
        $this->actingAs($this->siswaUser)
            ->get('/ujian')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Siswa/Ujian/Index')
                ->has('exams', 2)
                ->where('exams.0.jam_ke', 1)
                ->where('exams.0.locked', false)
                ->where('exams.1.jam_ke', 2)
                ->where('exams.1.locked', true)
                ->where('exams.1.lock_reason', 'Selesaikan ujian jam ke-1 terlebih dahulu.'));
    }

    public function test_student_cannot_open_or_start_locked_slot(): void
    {
        $this->actingAs($this->siswaUser)
            ->get("/ujian/{$this->jam2->id_jadwal}")
            ->assertRedirect('/ujian')
            ->assertSessionHas('error');

        $this->actingAs($this->siswaUser)
            ->post("/ujian/{$this->jam2->id_jadwal}/mulai")
            ->assertRedirect('/ujian')
            ->assertSessionHas('error');

        $this->assertSame(0, DurasiSiswa::count());
    }

    public function test_first_slot_can_be_opened(): void
    {
        $this->actingAs($this->siswaUser)
            ->get("/ujian/{$this->jam1->id_jadwal}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Siswa/Ujian/Show'));
    }

    public function test_second_slot_unlocks_after_first_slot_finishes(): void
    {
        DurasiSiswa::create([
            'id_durasi' => $this->siswa->id_siswa.'0'.$this->jam1->id_jadwal.'1',
            'id_siswa' => $this->siswa->id_siswa,
            'id_jadwal' => $this->jam1->id_jadwal,
            'status' => 2,
            'lama_ujian' => '00:30:00',
            'mulai' => '2026-11-12 08:00:00',
            'selesai' => '2026-11-12 08:30:00',
            'reset' => 0,
            'time_create' => now(),
        ]);

        $this->actingAs($this->siswaUser)
            ->get('/ujian')
            ->assertInertia(fn (Assert $page) => $page
                ->where('exams.1.jam_ke', 2)
                ->where('exams.1.locked', false));

        $this->actingAs($this->siswaUser)
            ->get("/ujian/{$this->jam2->id_jadwal}")
            ->assertOk();
    }

    public function test_earlier_slot_on_a_different_day_does_not_lock(): void
    {
        Carbon::setTestNow('2026-11-13 09:00:00');
        $this->jam2->update(['tgl_mulai' => '2026-11-13', 'tgl_selesai' => '2026-11-13']);

        $this->actingAs($this->siswaUser)
            ->get('/ujian')
            ->assertInertia(fn (Assert $page) => $page
                ->has('exams', 1)
                ->where('exams.0.jam_ke', 2)
                ->where('exams.0.locked', false));
    }

    private function jadwal(string $nama, int $jamKe, string $tanggal): Jadwal
    {
        $bank = BankSoal::create([
            'bank_kode' => 'BANK-'.$jamKe,
            'bank_level' => '10',
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_nama' => $nama,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'status' => 1,
        ]);

        return Jadwal::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_bank' => $bank->id_bank,
            'tgl_mulai' => $tanggal,
            'tgl_selesai' => $tanggal,
            'durasi_ujian' => 60,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'hasil_tampil' => 1,
            'token' => 0,
            'status' => 1,
            'ulang' => 0,
            'reset_login' => 0,
            'jam_ke' => $jamKe,
            'time_create' => now(),
        ]);
    }
}
