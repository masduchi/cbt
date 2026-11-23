<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KartuUjianGlobalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelasA;

    private Kelas $kelasB;

    private Siswa $ahmad;

    private Siswa $budi;

    private Siswa $citra;

    private int $jenisPas;

    private int $jenisUas;

    private int $jadwalMatematika;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->user('adminkartu');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('gurukartu');
        $this->guru->groups()->attach($guruGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelasA = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->kelasB = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
        ]);

        $matematika = DB::table('master_mapel')->insertGetId([
            'nama_mapel' => 'Matematika',
            'kode' => 'MTK',
            'urutan' => 1,
        ]);

        $biologi = DB::table('master_mapel')->insertGetId([
            'nama_mapel' => 'Biologi',
            'kode' => 'BIO',
            'urutan' => 2,
        ]);

        $this->jenisPas = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir Semester',
            'kode_jenis' => 'PAS',
        ]);

        $this->jenisUas = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Ujian Akhir Sekolah',
            'kode_jenis' => 'UAS',
        ]);

        $this->jadwalMatematika = $this->jadwal(
            $this->bank('Matematika', $matematika, $this->jenisPas, (string) $this->kelasA->id_kelas.','.$this->kelasB->id_kelas),
            $this->jenisPas,
            '2026-11-10 08:00:00',
            '2026-11-10 10:00:00',
        );

        $this->jadwal(
            $this->bank('Biologi', $biologi, $this->jenisPas, (string) $this->kelasA->id_kelas),
            $this->jenisPas,
            '2026-11-11 08:00:00',
            '2026-11-11 10:00:00',
        );

        $kimia = DB::table('master_mapel')->insertGetId([
            'nama_mapel' => 'Kimia',
            'kode' => 'KIM',
            'urutan' => 3,
        ]);

        $this->jadwal(
            $this->bank('Kimia', $kimia, $this->jenisUas, (string) $this->kelasA->id_kelas),
            $this->jenisUas,
            '2026-11-12 08:00:00',
            '2026-11-12 10:00:00',
        );

        $ruangId = DB::table('cbt_ruang')->insertGetId([
            'nama_ruang' => 'R-01',
            'kode_ruang' => 'R01',
        ]);

        $sesiId = DB::table('cbt_sesi')->insertGetId([
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '08:00:00',
            'waktu_akhir' => '10:00:00',
            'aktif' => 1,
        ]);

        DB::table('cbt_kelas_ruang')->insert([
            'id_kelas_ruang' => 'KR-A',
            'id_kelas' => $this->kelasA->id_kelas,
            'id_ruang' => $ruangId,
            'id_sesi' => $sesiId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);

        $this->ahmad = $this->siswa('2027001', 'Ahmad Fauzi', 'ahmadkartu', $this->kelasA, 1);
        $this->budi = $this->siswa('2027002', 'Budi Santoso', 'budikartu', $this->kelasA, 2);
        $this->citra = $this->siswa('2027003', 'Citra Lestari', 'citrakartu', $this->kelasB, 3);

        DB::table('cbt_nomor_peserta')->insert([
            'id_nomor' => 1,
            'id_siswa' => $this->ahmad->id_siswa,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nomor_peserta' => '0001',
        ]);
    }

    public function test_kartu_global_memuat_semua_mapel_per_siswa(): void
    {
        $this->actingAs($this->admin)
            ->get('/cbt/kartu-global')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/KartuGlobal')
                ->has('kartu', 3)
                ->where('ringkasan.siswa', 3)
                ->where('ringkasan.mapel', 3)
                ->where('ringkasan.tanpa_nomor', 2)
                ->where('ringkasan.tanpa_ruang', 1)
                ->where('kartu.0.nama', 'Ahmad Fauzi')
                ->where('kartu.0.nomor_peserta', '0001')
                ->where('kartu.0.ruang', 'R-01')
                ->where('kartu.0.sesi', 'Sesi 1')
                ->where('kartu.0.jumlah_mapel', 3)
                ->where('kartu.0.ujian.0.mapel', 'Matematika')
                ->where('kartu.0.ujian.1.mapel', 'Biologi')
                ->where('kartu.0.ujian.2.mapel', 'Kimia')
                ->where('kartu.0.verifikasi.svg', fn ($svg) => str_starts_with((string) $svg, '<svg'))
                ->where('kartu.2.nama', 'Citra Lestari')
                ->where('kartu.2.jumlah_mapel', 1)
                ->where('kartu.2.ruang', null));

        $kode = DB::table('dokumen_verifikasi')->where('jenis', 'kartu-global')->pluck('kode');

        $this->assertCount(3, $kode);
        $this->assertDatabaseHas('dokumen_verifikasi', [
            'jenis' => 'kartu-global',
            'ref' => $this->ahmad->id_siswa.'-'.$this->tahun->id_tp.'-'.$this->semester->id_smt,
        ]);
    }

    public function test_filter_kelas_dan_jenis_membatasi_mapel_yang_dicetak(): void
    {
        $this->actingAs($this->admin)
            ->get('/cbt/kartu-global?kelas='.$this->kelasA->id_kelas)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('kartu', 2)
                ->where('filters.kelas', $this->kelasA->id_kelas));

        $this->actingAs($this->admin)
            ->get('/cbt/kartu-global?jenis='.$this->jenisUas)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('kartu', 2)
                ->where('ringkasan.mapel', 1)
                ->where('kartu.0.jumlah_mapel', 1)
                ->where('kartu.0.ujian.0.mapel', 'Kimia')
                ->where('filters.jenis', $this->jenisUas));
    }

    public function test_cetak_ulang_kartu_global_memakai_kode_yang_sama(): void
    {
        $this->actingAs($this->admin)->get('/cbt/kartu-global')->assertOk();

        $sebelum = DB::table('dokumen_verifikasi')
            ->where('jenis', 'kartu-global')
            ->orderBy('id')
            ->pluck('kode', 'ref')
            ->all();

        $this->actingAs($this->admin)->get('/cbt/kartu-global')->assertOk();

        $sesudah = DB::table('dokumen_verifikasi')
            ->where('jenis', 'kartu-global')
            ->orderBy('id')
            ->pluck('kode', 'ref')
            ->all();

        $this->assertSame($sebelum, $sesudah);
        $this->assertCount(3, $sesudah);
    }

    public function test_guru_tidak_dapat_mencetak_kartu_global(): void
    {
        $this->actingAs($this->guru)
            ->get('/cbt/kartu-global')
            ->assertForbidden();
    }

    public function test_kartu_per_jadwal_memakai_kode_verifikasi_per_siswa(): void
    {
        $url = '/cbt/jadwal/'.$this->jadwalMatematika.'/kartu';

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/Kartu')
                ->has('peserta', 3)
                ->where('peserta.0.nama', 'Ahmad Fauzi')
                ->where('peserta.0.verifikasi.kode', fn ($kode) => is_string($kode) && strlen($kode) === 10)
                ->where('peserta.0.verifikasi.svg', fn ($svg) => str_starts_with((string) $svg, '<svg'))
                ->where('peserta.0.verifikasi.url', fn ($url) => str_contains((string) $url, '/verifikasi/')));

        $sebelum = DB::table('dokumen_verifikasi')
            ->where('jenis', 'kartu-peserta-siswa')
            ->orderBy('id')
            ->pluck('kode', 'ref')
            ->all();

        $this->assertCount(3, $sebelum);
        $this->assertArrayHasKey($this->jadwalMatematika.'-'.$this->ahmad->id_siswa, $sebelum);

        // Cetak ulang memakai kode yang sama, tanpa menerbitkan kode ganda.
        $this->actingAs($this->admin)->get($url)->assertOk();

        $this->assertSame($sebelum, DB::table('dokumen_verifikasi')
            ->where('jenis', 'kartu-peserta-siswa')
            ->orderBy('id')
            ->pluck('kode', 'ref')
            ->all());
    }

    public function test_kartu_peserta_dapat_dicari_dan_dipilih_per_siswa(): void
    {
        $url = '/cbt/jadwal/'.$this->jadwalMatematika.'/kartu';

        $this->actingAs($this->admin)->get($url.'?q=Budi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/Kartu')
                ->has('peserta', 1)
                ->where('peserta.0.nama', 'Budi Santoso')
                ->where('filters.q', 'Budi'));

        $this->actingAs($this->admin)->get($url.'?pilih[]='.$this->ahmad->id_siswa)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Cetak/Kartu')
                ->has('peserta', 1)
                ->where('peserta.0.nama', 'Ahmad Fauzi'));

        $this->actingAs($this->admin)->get($url.'?q=Tidak Ada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('peserta', 0));
    }

    public function test_pdf_kartu_peserta_hanya_mencetak_siswa_terpilih_atau_hasil_pencarian(): void
    {
        $url = '/cbt/jadwal/'.$this->jadwalMatematika.'/kartu/pdf';

        $pdf = (string) $this->actingAs($this->admin)->get($url.'?pilih[]='.$this->ahmad->id_siswa)->assertOk()->getContent();
        $this->assertTrue($this->pdfContains($pdf, 'Ahmad'), 'Siswa terpilih tidak tercetak.');
        $this->assertFalse($this->pdfContains($pdf, 'Budi'), 'Siswa yang tidak dipilih tidak boleh tercetak.');

        $pdf = (string) $this->actingAs($this->admin)->get($url.'?q=Budi')->assertOk()->getContent();
        $this->assertTrue($this->pdfContains($pdf, 'Budi'));
        $this->assertFalse($this->pdfContains($pdf, 'Ahmad'));
    }

    public function test_pdf_kartu_peserta_memakai_kop_dan_logo_sekolah(): void
    {
        $this->siapkanKopDanLogo();

        $response = $this->actingAs($this->admin)->get('/cbt/jadwal/'.$this->jadwalMatematika.'/kartu/pdf');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $pdf = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue($this->pdfContains($pdf, 'PEMERINTAH'), 'Kop tidak tercetak pada PDF kartu.');
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'Logo sekolah tidak disematkan pada PDF kartu.');

        $kode = DB::table('dokumen_verifikasi')->where('jenis', 'kartu-peserta-siswa')->pluck('kode');

        $this->assertCount(3, $kode);

        foreach ($kode as $satu) {
            $this->assertTrue($this->pdfContains($pdf, (string) $satu), 'Kode verifikasi '.$satu.' tidak tercetak pada PDF kartu.');
        }
    }

    public function test_pdf_kartu_global_memakai_kop_logo_dan_filter_kelas(): void
    {
        $this->siapkanKopDanLogo();

        $response = $this->actingAs($this->admin)
            ->get('/cbt/kartu-global/pdf?kelas='.$this->kelasA->id_kelas);

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $pdf = (string) $response->getContent();

        $this->assertTrue($this->pdfContains($pdf, 'PEMERINTAH'));
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertGreaterThanOrEqual(3, substr_count($pdf, '/Subtype /Image'), 'Dua logo dan QR harus tersemat sebagai gambar.');

        // Filter kelas hanya menerbitkan kode untuk siswa kelas tersebut.
        $refs = DB::table('dokumen_verifikasi')->where('jenis', 'kartu-global')->pluck('ref');
        $this->assertCount(2, $refs);
        $this->assertTrue($refs->contains(fn ($ref) => str_starts_with((string) $ref, (string) $this->ahmad->id_siswa.'-')));
        $this->assertTrue($refs->contains(fn ($ref) => str_starts_with((string) $ref, (string) $this->budi->id_siswa.'-')));
        $this->assertFalse($refs->contains(fn ($ref) => str_starts_with((string) $ref, (string) $this->citra->id_siswa.'-')));

        $kode = (string) DB::table('dokumen_verifikasi')
            ->where('jenis', 'kartu-global')
            ->where('ref', 'like', $this->ahmad->id_siswa.'-%')
            ->value('kode');

        $this->assertTrue($this->pdfContains($pdf, $kode), 'Kode verifikasi siswa tidak tercetak pada PDF kartu global.');
        $this->assertFalse($this->pdfContains($pdf, 'Matematika'), 'Jadwal mapel tidak lagi dicetak pada kartu global.');
        $this->assertFalse($this->pdfContains($pdf, 'Biologi'), 'Jadwal mapel tidak lagi dicetak pada kartu global.');
    }

    public function test_pdf_kartu_global_ditolak_untuk_guru(): void
    {
        $this->actingAs($this->guru)
            ->get('/cbt/kartu-global/pdf')
            ->assertForbidden();
    }

    private function siapkanKopDanLogo(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('logos/kiri.png', $this->png());

        Setting::create([
            'sekolah' => 'SMA Negeri 1 Contoh',
            'alamat' => 'Jl. Merdeka 1',
            'kota' => 'Bandung',
            'logo_kiri' => 'logos/kiri.png',
        ]);

        DB::table('cbt_kop_kartu')->insert([
            'id_set_kartu' => 1,
            'header_1' => 'PEMERINTAH PROVINSI JAWA BARAT',
            'header_2' => 'DINAS PENDIDIKAN',
            'header_3' => 'SMA NEGERI 1 CONTOH',
        ]);
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
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

    private function jadwal(int $bankId, int $jenisId, string $mulai, string $selesai): int
    {
        return DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => $mulai,
            'tgl_selesai' => $selesai,
            'durasi_ujian' => 120,
            'status' => 1,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 0,
            'jam_ke' => 0,
        ]);
    }

    private function bank(string $nama, int $mapelId, int $jenisId, string $kelas): int
    {
        return DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-'.strtoupper(substr($nama, 0, 3)).'-'.$jenisId,
            'bank_nama' => $nama,
            'bank_mapel_id' => $mapelId,
            'bank_jenis_id' => $jenisId,
            'bank_level' => 10,
            'bank_kelas' => $kelas,
            'bank_guru_id' => 0,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);
    }

    public function test_nomor_peserta_string_kosong_dihitung_belum_punya_nomor(): void
    {
        DB::table('cbt_nomor_peserta')->insert([
            'id_nomor' => 2,
            'id_siswa' => $this->budi->id_siswa,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nomor_peserta' => '',
        ]);

        $this->actingAs($this->admin)
            ->get('/cbt/kartu-global')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ringkasan.tanpa_nomor', 2));
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

    private function siswa(string $nis, string $nama, string $username, Kelas $kelas, int $pivotId): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => '006'.substr($nis, -7),
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
            'id_kelas' => $kelas->id_kelas,
        ]);

        return $siswa;
    }
}
