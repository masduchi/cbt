<?php

namespace Tests\Feature;

use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Support\VerifikasiDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CbtKehadiranTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private User $guruLainUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private BankSoal $bank;

    private Jadwal $jadwal;

    private Siswa $andi;

    private Siswa $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = DB::table('groups')->insertGetId(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = DB::table('groups')->insertGetId(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminkeh');
        $this->admin->groups()->attach($adminGroup);

        $this->guruUser = $this->user('gurukeh');
        $this->guruUser->groups()->attach($guruGroup);

        $this->guruLainUser = $this->user('gurulainkeh');
        $this->guruLainUser->groups()->attach($guruGroup);

        $guru = Guru::create(['id_user' => $this->guruUser->id, 'nama_guru' => 'Guru Kehadiran', 'username' => 'gurukeh', 'nip' => '199001']);
        Guru::create(['id_user' => $this->guruLainUser->id, 'nama_guru' => 'Guru Lain', 'username' => 'gurulainkeh', 'nip' => '199002']);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1]);

        $jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir Semester',
            'kode_jenis' => 'PAS',
        ]);

        $this->bank = BankSoal::create([
            'bank_kode' => 'KEH-1',
            'bank_nama' => 'Bank Kehadiran',
            'bank_mapel_id' => $mapel->id_mapel,
            'bank_jenis_id' => $jenisId,
            'bank_level' => '10',
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'bank_guru_id' => $guru->id_guru,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->jadwal = Jadwal::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_bank' => $this->bank->id_bank,
            'id_jenis' => $jenisId,
            'tgl_mulai' => '2026-11-14 07:30:00',
            'tgl_selesai' => '2026-11-14 09:30:00',
            'durasi_ujian' => 90,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'token' => 0,
            'status' => 1,
            'hasil_tampil' => 1,
            'ulang' => 0,
            'reset_login' => 0,
            'jam_ke' => 0,
        ]);

        $this->andi = $this->siswa('2028000001', 'Andi Pratama', 'andikeh', 1);
        $this->budi = $this->siswa('2028000002', 'Budi Santoso', 'budikeh', 2);

        DB::table('cbt_nomor_peserta')->insert([
            ['id_nomor' => 1, 'id_siswa' => $this->andi->id_siswa, 'id_tp' => $this->tahun->id_tp, 'id_smt' => $this->semester->id_smt, 'nomor_peserta' => 'HADIR-01'],
            ['id_nomor' => 2, 'id_siswa' => $this->budi->id_siswa, 'id_tp' => $this->tahun->id_tp, 'id_smt' => $this->semester->id_smt, 'nomor_peserta' => 'HADIR-02'],
        ]);
    }

    public function test_daftar_kehadiran_memuat_peserta_jadwal(): void
    {
        $this->actingAs($this->admin)
            ->get('/cbt/kehadiran')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Kehadiran')
                ->has('peserta', 2)
                ->where('ringkasan.hadir', 0)
                ->where('ringkasan.belum', 2)
                ->where('peserta.0.nama', 'Andi Pratama')
                ->where('peserta.1.nama', 'Budi Santoso'));
    }

    public function test_peserta_ditandai_hadir_lewat_nomor_peserta_dan_nis(): void
    {
        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => 'HADIR-01'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_kehadiran', [
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->andi->id_siswa,
        ]);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => '2028000002'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_kehadiran', [
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->budi->id_siswa,
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/kehadiran')
            ->assertInertia(fn (Assert $page) => $page
                ->where('ringkasan.hadir', 2)
                ->where('peserta.0.hadir', true));
    }

    public function test_kode_asing_ditolak_tanpa_mencatat_kehadiran(): void
    {
        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => 'TIDAK-ADA'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cbt_kehadiran', 0);
    }

    public function test_pindai_qr_kartu_global_menandai_siswa_hadir(): void
    {
        $kode = VerifikasiDokumen::terbitkan('kartu-global', implode('-', [
            $this->andi->id_siswa,
            $this->tahun->id_tp,
            $this->semester->id_smt,
        ]), ['siswa' => 'Andi Pratama']);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($kode)])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_kehadiran', [
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->andi->id_siswa,
            'kode' => $kode,
            'metode' => 'qr',
        ]);
    }

    public function test_pindai_qr_kartu_per_jadwal_menandai_siswa_hadir(): void
    {
        $kode = VerifikasiDokumen::terbitkan('kartu-peserta-siswa', $this->jadwal->id_jadwal.'-'.$this->budi->id_siswa);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($kode)])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_kehadiran', [
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->budi->id_siswa,
            'kode' => $kode,
            'metode' => 'qr',
        ]);
    }

    public function test_qr_kartu_dari_jadwal_lain_ditolak(): void
    {
        $kode = VerifikasiDokumen::terbitkan('kartu-peserta-siswa', ($this->jadwal->id_jadwal + 1).'-'.$this->andi->id_siswa);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($kode)])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'jadwal ujian lain'));

        $this->assertDatabaseCount('cbt_kehadiran', 0);
    }

    public function test_qr_kartu_lama_dan_jenis_dokumen_lain_dijelaskan(): void
    {
        $lama = VerifikasiDokumen::terbitkan('kartu-peserta', (string) $this->jadwal->id_jadwal);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($lama)])
            ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'nomor peserta'));

        $rapor = VerifikasiDokumen::terbitkan('rapor', implode('-', [
            $this->andi->id_siswa,
            $this->tahun->id_tp,
            $this->semester->id_smt,
            'akhir',
        ]));

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($rapor)])
            ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'bukan kartu peserta'));

        $this->assertDatabaseCount('cbt_kehadiran', 0);
    }

    public function test_qr_dicabut_atau_bukan_peserta_jadwal_ditolak(): void
    {
        $kode = VerifikasiDokumen::terbitkan('kartu-global', implode('-', [
            $this->andi->id_siswa,
            $this->tahun->id_tp,
            $this->semester->id_smt,
        ]));

        DB::table('dokumen_verifikasi')->where('kode', $kode)->update(['revoked_at' => now()]);

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($kode)])
            ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'dicabut'));

        $asing = VerifikasiDokumen::terbitkan('kartu-global', implode('-', [
            999999,
            $this->tahun->id_tp,
            $this->semester->id_smt,
        ]));

        $this->actingAs($this->admin)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => VerifikasiDokumen::url($asing)])
            ->assertSessionHas('error', fn ($pesan) => str_contains((string) $pesan, 'bukan peserta'));

        $this->assertDatabaseCount('cbt_kehadiran', 0);
    }

    public function test_guru_lain_tidak_boleh_menandai_kehadiran(): void
    {
        $this->actingAs($this->guruLainUser)
            ->post('/cbt/kehadiran/'.$this->jadwal->id_jadwal, ['kode' => 'HADIR-01'])
            ->assertForbidden();

        $this->assertDatabaseCount('cbt_kehadiran', 0);
    }

    public function test_kehadiran_dapat_dibatalkan(): void
    {
        Kehadiran::create([
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->andi->id_siswa,
            'kode' => 'HADIR-01',
            'metode' => 'kode',
            'id_user' => $this->admin->id,
            'waktu' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete('/cbt/kehadiran/'.$this->jadwal->id_jadwal.'/'.$this->andi->id_siswa)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cbt_kehadiran', [
            'id_jadwal' => $this->jadwal->id_jadwal,
            'id_siswa' => $this->andi->id_siswa,
        ]);
    }

    private function siswa(string $nis, string $nama, string $username, int $pivotId): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => $nis,
            'nis' => $nis,
            'nama' => $nama,
            'username' => $username,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-'.$username,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $pivotId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        return $siswa;
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
}
