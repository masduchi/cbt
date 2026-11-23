<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CbtStatusStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruLain;

    private Jadwal $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('admin', 'Admin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruLain = $this->user('guru_lain', 'Guru Lain');
        $this->guruLain->groups()->attach($guruGroup->id);

        $tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $kelas = Kelas::create([
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTR', 'urutan' => 1]);

        $jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir',
            'kode_jenis' => 'PA',
        ]);

        $bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-SSE',
            'bank_nama' => 'Bank SSE',
            'bank_mapel_id' => $mapel->id_mapel,
            'bank_jenis_id' => $jenisId,
            'bank_guru_id' => 99,
            'bank_level' => 10,
            'bank_kelas' => (string) $kelas->id_kelas,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'opsi' => 4,
            'kkm' => 0,
            'tampil_pg' => 0,
            'bobot_pg' => 0,
        ]);

        $this->jadwal = Jadwal::create([
            'id_bank' => $bankId,
            'id_jenis' => $jenisId,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'tgl_mulai' => '2026-11-20T08:00',
            'tgl_selesai' => '2026-11-20T10:00',
            'durasi_ujian' => 90,
            'status' => 1,
            'remedial' => 0,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);

        $siswa = Siswa::create([
            'nisn' => '2029000101',
            'nis' => '20290101',
            'nama' => 'Siswa Pantau',
            'username' => 'siswapantau',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswa-pantau',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $kelas->id_kelas,
        ]);
    }

    public function test_stream_mengirim_snapshot_peserta(): void
    {
        config(['cbt.realtime.enabled' => true, 'cbt.realtime.interval' => 0, 'cbt.realtime.ticks' => 1]);

        $response = $this->actingAs($this->admin)->get('/cbt/status/'.$this->jadwal->id_jadwal.'/stream');

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('content-type'));

        $body = $response->streamedContent();

        $this->assertStringContainsString('event: snapshot', $body);
        $this->assertStringContainsString('Siswa Pantau', $body);

        $payload = $this->payload($body);
        $this->assertSame('Siswa Pantau', $payload['rows'][0]['nama']);
        $this->assertSame(0, $payload['rows'][0]['status']);
    }

    public function test_stream_menolak_guru_yang_bukan_pemilik_bank(): void
    {
        config(['cbt.realtime.enabled' => true, 'cbt.realtime.interval' => 0, 'cbt.realtime.ticks' => 1]);

        $this->actingAs($this->guruLain)
            ->get('/cbt/status/'.$this->jadwal->id_jadwal.'/stream')
            ->assertForbidden();
    }

    public function test_stream_memakai_polling_saat_realtime_dimatikan(): void
    {
        config(['cbt.realtime.enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/cbt/status/'.$this->jadwal->id_jadwal.'/stream')
            ->assertNoContent();
    }

    /**
     * Ambil bagian data: dari keluaran SSE.
     *
     * @return array<string, mixed>
     */
    private function payload(string $body): array
    {
        preg_match('/data: (.*)/', $body, $matches);

        return json_decode($matches[1] ?? '[]', true) ?: [];
    }

    private function user(string $username, string $nama): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => $nama,
            'last_name' => '',
        ]);
    }
}
