<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CetakVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kelas $kelas;

    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'admincetak',
            'password' => 'rahasia123',
            'email' => 'admincetak@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
        ]);
        $this->admin->groups()->attach($adminGroup->id);

        $tp = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $smt = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $tp->id_tp,
            'id_smt' => $smt->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '0061234567',
            'nis' => '2026001',
            'nama' => 'Ahmad Fauzi',
            'username' => 'ahmad.cetak',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-cetak-verifikasi',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $tp->id_tp,
            'id_smt' => $smt->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_cetak_rapor_menempelkan_qr_verifikasi_yang_stabil(): void
    {
        $url = '/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa;

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/PrintRapor')
                ->where('verifikasi.kode', fn ($kode) => is_string($kode) && strlen($kode) === 10)
                ->where('verifikasi.url', fn ($tautan) => str_contains((string) $tautan, '/verifikasi/'))
                ->where('verifikasi.svg', fn ($svg) => str_starts_with((string) $svg, '<svg')));

        $row = DB::table('dokumen_verifikasi')->where('jenis', 'rapor')->sole();
        $this->assertStringContainsString((string) $this->siswa->id_siswa, (string) $row->ref);
        $this->assertStringContainsString((string) $this->kelas->id_kelas, (string) $row->ref);

        $ringkasan = json_decode((string) $row->ringkasan, true);
        $this->assertSame('Ahmad Fauzi', $ringkasan['siswa']);
        $this->assertSame('2026001', $ringkasan['nis']);
        $this->assertSame('X IPA 1', $ringkasan['kelas']);
        $this->assertSame('Akhir Semester', $ringkasan['jenis']);

        $pdf = $this->actingAs($this->admin)->get($url.'/pdf');

        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue($this->pdfContains((string) $pdf->getContent(), (string) $row->kode), 'Kode verifikasi tidak tercetak pada PDF rapor.');

        // Cetak ulang memakai kode yang sama, tidak menerbitkan kode baru.
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertSame(1, DB::table('dokumen_verifikasi')->where('jenis', 'rapor')->count());
    }

    public function test_cetak_ulang_rapor_dengan_isi_berubah_menerbitkan_kode_baru(): void
    {
        $url = '/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa;

        $this->actingAs($this->admin)->get($url)->assertOk();
        $lama = DB::table('dokumen_verifikasi')->where('jenis', 'rapor')->sole();

        $this->siswa->update(['nama' => 'Ahmad Fauzi Ramadhan']);

        $this->actingAs($this->admin)->get($url)->assertOk();

        $aktif = DB::table('dokumen_verifikasi')
            ->where('jenis', 'rapor')
            ->whereNull('revoked_at')
            ->sole();

        $this->assertNotSame($lama->kode, $aktif->kode, 'Isi yang berubah harus menerbitkan kode baru.');
        $this->assertNotNull(
            DB::table('dokumen_verifikasi')->where('kode', $lama->kode)->value('revoked_at'),
            'Kode lama harus dicabut agar QR lama tidak menampilkan ringkasan basi.'
        );

        $ringkasan = json_decode((string) $aktif->ringkasan, true);
        $this->assertSame('Ahmad Fauzi Ramadhan', $ringkasan['siswa']);

        $this->get('/verifikasi/'.$lama->kode)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));

        $this->get('/verifikasi/'.$aktif->kode)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('valid', true)
                ->where('ringkasan.siswa', 'Ahmad Fauzi Ramadhan')
                ->where('diperbarui', fn ($nilai) => is_string($nilai) && $nilai !== ''));
    }

    public function test_rapor_pts_memakai_kode_verifikasi_terpisah(): void
    {
        $url = '/rapor/cetak/'.$this->kelas->id_kelas.'/'.$this->siswa->id_siswa;

        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url.'?jenis=pts')->assertOk();

        $refs = DB::table('dokumen_verifikasi')->where('jenis', 'rapor')->pluck('ref');

        $this->assertCount(2, $refs);
        $this->assertTrue($refs->contains(fn ($ref) => str_ends_with((string) $ref, '-akhir')));
        $this->assertTrue($refs->contains(fn ($ref) => str_ends_with((string) $ref, '-pts')));
    }

    public function test_cetak_buku_induk_menempelkan_qr_verifikasi(): void
    {
        DB::table('buku_induk')->insert([
            'id_siswa' => $this->siswa->id_siswa,
            'uid' => $this->siswa->uid,
            'status' => 2,
            'tahun_lulus' => 2027,
            'kelas_akhir' => 'XII IPA 1',
        ]);

        $url = '/master/buku-induk/'.$this->siswa->id_siswa.'/pdf';
        $pdf = $this->actingAs($this->admin)->get($url);

        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $row = DB::table('dokumen_verifikasi')->where('jenis', 'buku-induk')->sole();
        $this->assertSame((string) $this->siswa->id_siswa, (string) $row->ref);

        $ringkasan = json_decode((string) $row->ringkasan, true);
        $this->assertSame('Ahmad Fauzi', $ringkasan['siswa']);
        $this->assertSame('X IPA 1', $ringkasan['kelas']);
        $this->assertSame('Lulus', $ringkasan['status']);

        $this->assertTrue($this->pdfContains((string) $pdf->getContent(), (string) $row->kode), 'Kode verifikasi tidak tercetak pada PDF buku induk.');

        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertSame(1, DB::table('dokumen_verifikasi')->where('jenis', 'buku-induk')->count());
    }

    public function test_cetak_ulang_buku_induk_dengan_status_berubah_menerbitkan_kode_baru(): void
    {
        DB::table('buku_induk')->insert([
            'id_siswa' => $this->siswa->id_siswa,
            'uid' => $this->siswa->uid,
            'status' => 2,
        ]);

        $url = '/master/buku-induk/'.$this->siswa->id_siswa.'/pdf';

        $this->actingAs($this->admin)->get($url)->assertOk();
        $lama = DB::table('dokumen_verifikasi')->where('jenis', 'buku-induk')->sole();

        DB::table('buku_induk')->where('id_siswa', $this->siswa->id_siswa)->update(['status' => 3]);

        $this->actingAs($this->admin)->get($url)->assertOk();

        $aktif = DB::table('dokumen_verifikasi')
            ->where('jenis', 'buku-induk')
            ->whereNull('revoked_at')
            ->sole();

        $this->assertNotSame($lama->kode, $aktif->kode);
        $this->assertNotNull(DB::table('dokumen_verifikasi')->where('kode', $lama->kode)->value('revoked_at'));

        $ringkasan = json_decode((string) $aktif->ringkasan, true);
        $this->assertSame('Pindah', $ringkasan['status']);
    }

    private function pdfContains(string $pdf, string $needle): bool
    {
        if (str_contains($pdf, $needle)) {
            return true;
        }

        $utf16 = mb_convert_encoding($needle, 'UTF-16BE', 'UTF-8');
        $streams = '';

        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }

            $streams .= $decoded === false ? $stream : $decoded;
        }

        return str_contains($streams, $needle) || str_contains($streams, $utf16);
    }
}
