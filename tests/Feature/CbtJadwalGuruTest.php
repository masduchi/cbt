<?php

namespace Tests\Feature;

use App\Models\BankSoal;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CbtJadwalGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    private Guru $guru;

    private User $otherGuruUser;

    private User $siswaUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private BankSoal $bankMilik;

    private BankSoal $bankLain;

    private Jadwal $jadwalMilik;

    private Jadwal $jadwalLain;

    private int $jenisId;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $admin = $this->user('adminjadwal', 'admin-jadwal@example.test');
        $admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('gurujadwal', 'guru-jadwal@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198900001', 'nama_guru' => 'Guru Jadwal']);

        $this->otherGuruUser = $this->user('gurulainjadwal', 'guru-lain-jadwal@example.test');
        $this->otherGuruUser->groups()->attach($guruGroup->id);
        $otherGuru = Guru::create(['id_user' => $this->otherGuruUser->id, 'nip' => '198900002', 'nama_guru' => 'Guru Lain Jadwal']);

        $this->siswaUser = $this->user('siswajadwal', 'siswa-jadwal@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1]);

        $this->jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Tengah Semester',
            'kode_jenis' => 'PTS',
        ]);

        $this->bankMilik = $this->bank('BANK-GURU-1', $this->guru->id_guru);
        $this->bankLain = $this->bank('BANK-GURU-2', $otherGuru->id_guru);

        $this->jadwalMilik = $this->jadwal($this->bankMilik);
        $this->jadwalLain = $this->jadwal($this->bankLain);
    }

    public function test_guru_sees_only_his_own_schedules_and_banks(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/cbt/jadwal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Jadwal/Index')
                ->has('jadwal', 1)
                ->where('jadwal.0.id_jadwal', $this->jadwalMilik->id_jadwal)
                ->has('bankOptions', 1)
                ->where('bankOptions.0.id_bank', $this->bankMilik->id_bank));
    }

    public function test_guru_can_create_a_schedule_for_his_own_bank(): void
    {
        $bank = $this->bank('BANK-GURU-BARU', $this->guru->id_guru);

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal', $this->payload(['id_bank' => $bank->id_bank]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_jadwal', [
            'id_bank' => $bank->id_bank,
            'durasi_ujian' => 90,
            'status' => 1,
        ]);
    }

    public function test_guru_cannot_create_a_schedule_for_another_guru_bank(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal', $this->payload(['id_bank' => $this->bankLain->id_bank]))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'id_bank' => 'Anda hanya dapat membuat jadwal untuk bank soal milik Anda sendiri.',
            ]);

        $this->assertSame(1, Jadwal::where('id_bank', $this->bankLain->id_bank)->count());
    }

    public function test_guru_can_update_toggle_and_delete_his_own_schedule(): void
    {
        $this->actingAs($this->guruUser)
            ->put('/cbt/jadwal/'.$this->jadwalMilik->id_jadwal, $this->payload([
                'id_bank' => $this->bankMilik->id_bank,
                'durasi_ujian' => 120,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_jadwal', [
            'id_jadwal' => $this->jadwalMilik->id_jadwal,
            'durasi_ujian' => 120,
        ]);

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal/'.$this->jadwalMilik->id_jadwal.'/status')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, (int) Jadwal::find($this->jadwalMilik->id_jadwal)->status);

        $this->actingAs($this->guruUser)
            ->delete('/cbt/jadwal/'.$this->jadwalMilik->id_jadwal)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cbt_jadwal', ['id_jadwal' => $this->jadwalMilik->id_jadwal]);
    }

    public function test_guru_cannot_update_toggle_or_delete_another_guru_schedule(): void
    {
        $id = $this->jadwalLain->id_jadwal;

        $this->actingAs($this->guruUser)
            ->put('/cbt/jadwal/'.$id, $this->payload(['id_bank' => $this->bankLain->id_bank, 'durasi_ujian' => 5]))
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal/'.$id.'/status')
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->delete('/cbt/jadwal/'.$id)
            ->assertForbidden();

        $this->assertDatabaseHas('cbt_jadwal', ['id_jadwal' => $id, 'durasi_ujian' => 90]);
    }

    public function test_guru_bulk_delete_skips_another_guru_schedule(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal/hapus-terpilih', [
                'ids' => [$this->jadwalMilik->id_jadwal, $this->jadwalLain->id_jadwal],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cbt_jadwal', ['id_jadwal' => $this->jadwalMilik->id_jadwal]);
        $this->assertDatabaseHas('cbt_jadwal', ['id_jadwal' => $this->jadwalLain->id_jadwal]);
    }

    public function test_siswa_cannot_open_or_manage_the_schedule_page(): void
    {
        $this->actingAs($this->siswaUser)->get('/cbt/jadwal')->assertForbidden();

        $this->actingAs($this->siswaUser)
            ->post('/cbt/jadwal', $this->payload(['id_bank' => $this->bankMilik->id_bank]))
            ->assertForbidden();

        $this->assertSame(1, Jadwal::where('id_bank', $this->bankMilik->id_bank)->count());
    }

    public function test_jadwal_tidak_dapat_diaktifkan_saat_komposisi_soal_belum_lengkap(): void
    {
        $bank = BankSoal::create([
            'bank_kode' => 'BANK-BELUM-SIAP',
            'bank_nama' => 'Bank Belum Siap',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => '10',
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_guru_id' => $this->guru->id_guru,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tampil_pg' => 2,
            'bobot_pg' => 100,
        ]);

        $soal = [
            'bank_id' => $bank->id_bank,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'soal' => '<p>Soal</p>',
            'deskripsi' => '',
            'opsi_a' => 'A',
            'opsi_b' => 'B',
            'jawaban' => 'A',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ];

        DB::table('cbt_soal')->insert([$soal + ['nomor_soal' => 1]]);

        $jadwal = $this->jadwal($bank);
        $jadwal->update(['status' => 0]);

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal/'.$jadwal->id_jadwal.'/status')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, (int) $jadwal->fresh()->status);

        DB::table('cbt_soal')->insert([$soal + ['nomor_soal' => 2]]);

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal/'.$jadwal->id_jadwal.'/status')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, (int) $jadwal->fresh()->status);
    }

    public function test_jadwal_menyimpan_pilihan_ambil_soal_acak(): void
    {
        $bank = $this->bank('BANK-ACAK', $this->guru->id_guru);

        $this->actingAs($this->guruUser)
            ->post('/cbt/jadwal', $this->payload(['id_bank' => $bank->id_bank, 'pilih_acak' => 1]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_jadwal', [
            'id_bank' => $bank->id_bank,
            'pilih_acak' => 1,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id_bank' => $this->bankMilik->id_bank,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20 07:30:00',
            'tgl_selesai' => '2026-11-20 09:30:00',
            'durasi_ujian' => 90,
            'acak_soal' => 1,
            'acak_opsi' => 0,
            'token' => 1,
            'status' => 1,
        ], $overrides);
    }

    private function bank(string $kode, int $guruId): BankSoal
    {
        return BankSoal::create([
            'bank_kode' => $kode,
            'bank_nama' => 'Bank '.$kode,
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => '10',
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_guru_id' => $guruId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);
    }

    private function jadwal(BankSoal $bank): Jadwal
    {
        return Jadwal::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_bank' => $bank->id_bank,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-14 07:30:00',
            'tgl_selesai' => '2026-11-14 09:30:00',
            'durasi_ujian' => 90,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'token' => 1,
            'status' => 1,
            'hasil_tampil' => 1,
            'ulang' => 0,
            'reset_login' => 0,
            'jam_ke' => 0,
        ]);
    }

    private function user(string $username, string $email): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $email,
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
    }
}
