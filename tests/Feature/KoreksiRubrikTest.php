<?php

namespace Tests\Feature;

use App\Jobs\KirimNotifikasiHasilUjian;
use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Koreksi;
use App\Models\KoreksiFinal;
use App\Models\KoreksiPenilai;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\SoalRubrik;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Notifications\HasilUjianTersedia;
use App\Support\CbtScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class KoreksiRubrikTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruOwnerUser;

    private User $guruKeduaUser;

    private User $guruLuarUser;

    private Guru $guruOwner;

    private Guru $guruKedua;

    private Guru $guruLuar;

    private Siswa $siswa;

    private int $bankId;

    private int $jenisId;

    private int $levelId = 1;

    private int $jadwalId;

    private Soal $soal;

    private SoalRubrik $kriteriaIsi;

    private SoalRubrik $kriteriaBahasa;

    private SoalSiswa $jawaban;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->admin = $this->makeUser('admin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruOwnerUser = $this->makeUser('guruowner');
        $this->guruOwnerUser->groups()->attach($guruGroup->id);

        $this->guruKeduaUser = $this->makeUser('gurukedua');
        $this->guruKeduaUser->groups()->attach($guruGroup->id);

        $this->guruLuarUser = $this->makeUser('guruluar');
        $this->guruLuarUser->groups()->attach($guruGroup->id);

        $tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => $this->levelId, 'level' => '10']);

        $kelas = Kelas::create([
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => $this->levelId,
        ]);

        $mapel = Mapel::create(['nama_mapel' => 'Bahasa Indonesia', 'kode' => 'BIN', 'urutan' => 1]);

        $this->siswa = Siswa::create([
            'nisn' => '2029000102',
            'nis' => '2029002',
            'nama' => 'Siswa Esai',
            'username' => 'siswaesai',
            'email' => 'siswaesai@example.test',
            'password' => 'rahasia123',
            'kelas_awal' => $this->levelId,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-siswaesai',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $kelas->id_kelas,
        ]);

        $this->jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir Semester',
            'kode_jenis' => 'PAS',
        ]);

        $this->guruOwner = Guru::create([
            'id_user' => $this->guruOwnerUser->id,
            'nama_guru' => 'Guru Pemilik',
            'username' => 'guruowner',
            'nip' => '198501012010011001',
        ]);

        $this->guruKedua = Guru::create([
            'id_user' => $this->guruKeduaUser->id,
            'nama_guru' => 'Guru Kedua',
            'username' => 'gurukedua',
            'nip' => '198501012010011002',
        ]);

        $this->guruLuar = Guru::create([
            'id_user' => $this->guruLuarUser->id,
            'nama_guru' => 'Guru Luar',
            'username' => 'guruluar',
            'nip' => '198501012010011003',
        ]);

        $this->bankId = DB::table('cbt_bank_soal')->insertGetId([
            'bank_kode' => 'BANK-ESAI',
            'bank_nama' => 'Bank Esai',
            'bank_mapel_id' => $mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => $this->levelId,
            'bank_kelas' => (string) $kelas->id_kelas,
            'bank_guru_id' => $this->guruOwner->id_guru,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'tampil_esai' => 1,
            'bobot_esai' => 40,
        ]);

        $this->jadwalId = DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $this->bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 90,
            'status' => 1,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);

        $this->soal = Soal::create([
            'bank_id' => $this->bankId,
            'mapel_id' => $mapel->id_mapel,
            'jenis' => 5,
            'nomor_soal' => 1,
            'soal' => 'Jelaskan penyebab banjir di kota besar.',
            'jawaban' => '',
            'deskripsi' => '',
            'tampilkan' => 1,
            'created_on' => time(),
            'updated_on' => time(),
        ]);

        $this->kriteriaIsi = SoalRubrik::create([
            'id_soal' => $this->soal->id_soal,
            'urutan' => 1,
            'kriteria' => 'Ketepatan isi',
            'skor_maks' => 4,
        ]);

        $this->kriteriaBahasa = SoalRubrik::create([
            'id_soal' => $this->soal->id_soal,
            'urutan' => 2,
            'kriteria' => 'Keruntutan bahasa',
            'skor_maks' => 6,
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => '9001001',
            'id_bank' => $this->bankId,
            'id_jadwal' => $this->jadwalId,
            'id_soal' => $this->soal->id_soal,
            'id_siswa' => $this->siswa->id_siswa,
            'jenis_soal' => 5,
            'no_soal_alias' => 1,
            'jawaban_siswa' => 'Banjir terjadi karena drainase buruk dan sampah.',
            'point_soal' => '40',
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 0,
        ]);

        $this->jawaban = SoalSiswa::where('id_soal_siswa', '9001001')->firstOrFail();
    }

    public function test_guru_pemilik_menyimpan_skor_esai_tunggal(): void
    {
        $response = $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        );

        $response->assertRedirect()->assertSessionHas('success');

        $koreksi = Koreksi::where('id_soal_siswa', $this->jawaban->id_soal_siswa)->sole();
        $this->assertSame(30.0, $koreksi->skor);
        $this->assertSame($this->guruOwnerUser->id, $koreksi->id_user);

        $this->jawaban->refresh();
        $this->assertSame('30', $this->jawaban->nilai_koreksi);
        $this->assertSame(1, (int) $this->jawaban->nilai_otomatis);

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertNotNull($nilai);
        $this->assertSame(30.0, (float) $nilai->essai_nilai);
        $this->assertSame(1, (int) $nilai->dikoreksi);
    }

    public function test_skor_rubrik_diskalakan_ke_bobot_soal(): void
    {
        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 0],
                'rincian' => [
                    $this->jawaban->id_soal_siswa => [
                        $this->kriteriaIsi->id_rubrik => 2,
                        $this->kriteriaBahasa->id_rubrik => 3,
                    ],
                ],
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        // 5 dari total rubrik 10, diskalakan ke bobot soal 40 menjadi 20.
        $koreksi = Koreksi::where('id_soal_siswa', $this->jawaban->id_soal_siswa)->sole();
        $this->assertSame(20.0, $koreksi->skor);
        $this->assertSame(2.0, (float) $koreksi->detail[$this->kriteriaIsi->id_rubrik]);

        $this->jawaban->refresh();
        $this->assertSame('20', $this->jawaban->nilai_koreksi);
    }

    public function test_nilai_akhir_rata_rata_menunggu_semua_korektor(): void
    {
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        $this->jawaban->refresh();
        $this->assertSame('30', $this->jawaban->nilai_koreksi);
        $this->assertSame(0, (int) $this->jawaban->nilai_otomatis, 'Satu korektor belum melengkapi jawaban.');

        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        $this->jawaban->refresh();
        $this->assertSame('20', $this->jawaban->nilai_koreksi);
        $this->assertSame(1, (int) $this->jawaban->nilai_otomatis);

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertSame(20.0, (float) $nilai->essai_nilai);
        $this->assertSame(1, (int) $nilai->dikoreksi);
    }

    public function test_selisih_besar_ditandai_perlu_ditinjau(): void
    {
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();
        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Koreksi/Show')
                ->where('jawaban.0.agregat.selisih', 20)
                ->where('jawaban.0.agregat.perlu_tinjau', true)
                ->where('jawaban.0.agregat.skor', 20)
                ->where('wajib', 2)
            );

        $this->actingAs($this->admin)->get('/cbt/koreksi?jadwal='.$this->jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Koreksi/Index')
                ->where('ringkasan.perlu_tinjau', 1)
                ->where('students.0.wajib', 2)
            );
    }

    public function test_admin_menetapkan_nilai_final_mengalahkan_rata_rata(): void
    {
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            ['nilai' => [$this->jawaban->id_soal_siswa => 30]]
        );
        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            ['nilai' => [$this->jawaban->id_soal_siswa => 10]]
        );

        $this->actingAs($this->admin)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 25],
                'final' => [$this->jawaban->id_soal_siswa => 1],
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cbt_koreksi', [
            'id_soal_siswa' => $this->jawaban->id_soal_siswa,
            'id_user' => $this->admin->id,
        ]);

        $final = KoreksiFinal::findOrFail($this->jawaban->id_soal_siswa);
        $this->assertSame(25.0, $final->skor);
        $this->assertSame($this->admin->id, $final->id_user);

        $this->jawaban->refresh();
        $this->assertSame('25', $this->jawaban->nilai_koreksi);
        $this->assertSame(1, (int) $this->jawaban->nilai_otomatis);

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertSame(25.0, (float) $nilai->essai_nilai);
    }

    public function test_guru_tanpa_tugas_tidak_boleh_mengakses_koreksi(): void
    {
        $this->actingAs($this->guruLuarUser)
            ->get('/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa)
            ->assertForbidden();

        $this->actingAs($this->guruLuarUser)
            ->post('/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa, [
                'nilai' => [$this->jawaban->id_soal_siswa => 40],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('cbt_koreksi', 0);
    }

    public function test_hanya_admin_yang_dapat_menugaskan_korektor(): void
    {
        $this->actingAs($this->guruOwnerUser)
            ->post('/cbt/koreksi/'.$this->jadwalId.'/penilai', ['guru' => [$this->guruKedua->id_guru]])
            ->assertForbidden();

        $this->assertDatabaseCount('cbt_koreksi_penilai', 0);

        $this->actingAs($this->admin)
            ->post('/cbt/koreksi/'.$this->jadwalId.'/penilai', ['guru' => [$this->guruKedua->id_guru]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cbt_koreksi_penilai', [
            'id_jadwal' => $this->jadwalId,
            'id_guru' => $this->guruKedua->id_guru,
        ]);

        $this->actingAs($this->guruKeduaUser)
            ->get('/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa)
            ->assertOk();

        $this->actingAs($this->guruKeduaUser)
            ->get('/cbt/koreksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected', $this->jadwalId));
    }

    public function test_penyimpanan_dan_pembaruan_rubrik_soal_esai(): void
    {
        $this->actingAs($this->guruOwnerUser)->post('/cbt/bank-soal/'.$this->bankId.'/soal', [
            'jenis' => 5,
            'soal' => 'Uraikan dampak sampah plastik bagi ekosistem laut.',
            'deskripsi' => '',
            'jawaban' => 'Rambu-rambu jawaban.',
            'kesulitan' => 2,
            'timer' => 0,
            'timer_menit' => 0,
            'rubrik' => [
                ['kriteria' => 'Ketepatan isi', 'skor_maks' => 4],
                ['kriteria' => 'Keruntutan bahasa', 'skor_maks' => 6],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $baru = Soal::where('bank_id', $this->bankId)
            ->where('soal', 'like', '%sampah plastik%')
            ->sole();

        $rubrik = SoalRubrik::where('id_soal', $baru->id_soal)->orderBy('urutan')->get();
        $this->assertCount(2, $rubrik);
        $this->assertSame('Ketepatan isi', $rubrik[0]->kriteria);
        $this->assertSame(4.0, (float) $rubrik[0]->skor_maks);
        $this->assertSame('Keruntutan bahasa', $rubrik[1]->kriteria);

        // Perbarui: pertahankan satu kriteria, ubah kriteria lain, hapus sisanya.
        $this->actingAs($this->guruOwnerUser)->post('/cbt/bank-soal/'.$this->bankId.'/soal/'.$baru->id_soal, [
            'jenis' => 5,
            'soal' => 'Uraikan dampak sampah plastik bagi ekosistem laut.',
            'deskripsi' => '',
            'jawaban' => 'Rambu-rambu jawaban.',
            'kesulitan' => 2,
            'timer' => 0,
            'timer_menit' => 0,
            'rubrik' => [
                [
                    'id_rubrik' => $rubrik[0]->id_rubrik,
                    'kriteria' => 'Ketepatan isi (diperbarui)',
                    'skor_maks' => 5,
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tersisa = SoalRubrik::where('id_soal', $baru->id_soal)->get();
        $this->assertCount(1, $tersisa);
        $this->assertSame('Ketepatan isi (diperbarui)', $tersisa[0]->kriteria);
        $this->assertSame(5.0, (float) $tersisa[0]->skor_maks);
        $this->assertSame(1, (int) $tersisa[0]->urutan);
    }

    public function test_halaman_soal_menyertakan_rubrik(): void
    {
        $this->actingAs($this->guruOwnerUser)
            ->get('/cbt/bank-soal/'.$this->bankId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/BankSoal/Show')
                ->has('soals.0.rubrik', 2)
                ->where('soals.0.rubrik.0.kriteria', 'Ketepatan isi')
                ->where('soals.0.rubrik.1.skor_maks', 6)
            );
    }

    public function test_rubrik_wajib_diisi_dan_tidak_fallback_ke_nilai_manual(): void
    {
        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            ['nilai' => [$this->jawaban->id_soal_siswa => 30]]
        )->assertSessionHasErrors(['rincian.'.$this->jawaban->id_soal_siswa]);

        $this->assertDatabaseCount('cbt_koreksi', 0);

        // Kriteria yang tidak dikirim dihitung 0, bukan memakai nilai manual.
        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 40],
                'rincian' => [$this->jawaban->id_soal_siswa => [
                    $this->kriteriaIsi->id_rubrik => 4,
                ]],
            ]
        )->assertSessionHasNoErrors();

        $koreksi = Koreksi::where('id_soal_siswa', $this->jawaban->id_soal_siswa)->sole();
        // 4 dari 10 skor rubrik, diskalakan ke bobot 40 = 16.
        $this->assertSame(16.0, $koreksi->skor);
    }

    public function test_pemilik_bank_tidak_menilai_saat_ada_penugasan(): void
    {
        $this->tugaskanKorektor($this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            ['nilai' => [$this->jawaban->id_soal_siswa => 30]]
        )->assertForbidden();

        $this->assertDatabaseCount('cbt_koreksi', 0);
    }

    public function test_guru_terhapus_tidak_dapat_ditugaskan(): void
    {
        $this->guruKedua->delete();

        $this->actingAs($this->admin)
            ->post('/cbt/koreksi/'.$this->jadwalId.'/penilai', ['guru' => [$this->guruKedua->id_guru]])
            ->assertSessionHasErrors(['guru.0']);

        $this->assertDatabaseCount('cbt_koreksi_penilai', 0);
    }

    public function test_audit_mencatat_koreksi_final_penilai_dan_rubrik(): void
    {
        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 25],
                'final' => [$this->jawaban->id_soal_siswa => 1],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post('/cbt/koreksi/'.$this->jadwalId.'/penilai', ['guru' => [$this->guruKedua->id_guru]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->guruOwnerUser)->post('/cbt/bank-soal/'.$this->bankId.'/soal', [
            'jenis' => 5,
            'soal' => 'Soal dengan rubrik baru.',
            'jawaban' => '',
            'rubrik' => [['kriteria' => 'Isi', 'skor_maks' => 5]],
        ])->assertSessionHasNoErrors();

        $desc = fn (string $prefix): bool => DB::table('log')
            ->where('log_desc', 'like', $prefix.'%')
            ->exists();

        $this->assertTrue($desc('cbt.koreksi.simpan'), 'Simpan koreksi tidak tercatat.');
        $this->assertTrue($desc('cbt.koreksi.final'), 'Nilai final tidak tercatat.');
        $this->assertTrue($desc('cbt.koreksi.penilai'), 'Penugasan korektor tidak tercatat.');
        $this->assertTrue($desc('cbt.rubrik'), 'Perubahan rubrik tidak tercatat.');
    }

    public function test_rekap_rata_rata_per_kriteria_rubrik(): void
    {
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/cbt/koreksi?jadwal='.$this->jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Koreksi/Index')
                ->has('rubrikRekap', 2)
                ->where('rubrikRekap.0.kriteria', 'Ketepatan isi')
                ->where('rubrikRekap.0.jumlah', 2)
                ->where('rubrikRekap.0.rata_rata', 2.25)
                ->where('rubrikRekap.0.persen', 56.3)
                ->where('rubrikRekap.1.kriteria', 'Keruntutan bahasa')
                ->where('rubrikRekap.1.rata_rata', 2.75)
            );
    }

    public function test_email_hasil_dikirim_sekali_dan_penanda_baru_diisi_setelah_terkirim(): void
    {
        Queue::fake();
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        Queue::assertNotPushed(KirimNotifikasiHasilUjian::class);

        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        Queue::assertPushed(KirimNotifikasiHasilUjian::class, 1);
        Notification::assertSentOnDemandTimes(HasilUjianTersedia::class, 0);

        // Mengantrekan job belum berarti email terkirim.
        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertNull($nilai->hasil_notified_at, 'Penanda tidak boleh diisi saat notifikasi baru diantrekan.');

        Queue::pushed(KirimNotifikasiHasilUjian::class)->sole()->handle();

        Notification::assertSentOnDemandTimes(HasilUjianTersedia::class, 1);
        Notification::assertSentOnDemand(
            HasilUjianTersedia::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'siswaesai@example.test'
                && $notification->nilaiAkhir === 20.0
        );

        $nilai->refresh();
        $this->assertNotNull($nilai->hasil_notified_at);

        // Menyimpan ulang jawaban yang sama tidak mengirim email kedua.
        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        Queue::assertPushed(KirimNotifikasiHasilUjian::class, 1);
        Notification::assertSentOnDemandTimes(HasilUjianTersedia::class, 1);
    }

    public function test_pengiriman_gagal_tidak_menandai_hasil_dan_dicatat_di_log(): void
    {
        Queue::fake();
        config(['mail.default' => 'smtp']);
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();

        $job = Queue::pushed(KirimNotifikasiHasilUjian::class)->sole();
        $kegagalan = new RuntimeException('SMTP tidak dapat dihubungi');

        Notification::shouldReceive('sendNow')->andThrow($kegagalan);

        try {
            $job->handle();
            $this->fail('Job seharusnya meneruskan kegagalan pengiriman notifikasi.');
        } catch (RuntimeException $e) {
            $this->assertSame($kegagalan, $e);
        }

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertNull($nilai->hasil_notified_at, 'Kegagalan kirim tidak boleh menandai hasil sebagai terkirim.');

        Log::spy();
        $job->failed($kegagalan);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $pesan, array $konteks = []) => $pesan === 'Notifikasi hasil ujian gagal dikirim'
                && $konteks['id_nilai'] === $nilai->id_nilai);
    }

    public function test_mailer_log_tetap_menyimpan_notifikasi_in_app_dan_mencatat_kegagalan_email(): void
    {
        Queue::fake();
        Notification::fake();
        config(['mail.default' => 'log']);

        $this->selesaikanKoreksiDuaPenilai();

        $job = Queue::pushed(KirimNotifikasiHasilUjian::class)->sole();
        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));

        try {
            $job->handle();
            $this->fail('Job seharusnya gagal saat mailer tidak mengirim email.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak mengirim email', $e->getMessage());
            $gagal = $e;
        }

        Notification::assertSentOnDemandTimes(HasilUjianTersedia::class, 0);
        Notification::assertSentTo(
            $this->siswa,
            HasilUjianTersedia::class,
            fn ($notification, $channels) => $channels === ['database']
        );

        $nilai->refresh();
        $this->assertNotNull(
            $nilai->hasil_notified_at,
            'Notifikasi in-app tetap tersimpan walau email tidak terkirim.'
        );
        $this->assertNull($nilai->hasil_notify_failed_at, 'Kegagalan email dicatat saat job benar-benar gagal.');

        Log::spy();
        $job->failed($gagal);
        $nilai->refresh();

        $this->assertNotNull($nilai->hasil_notify_failed_at);
        $this->assertStringContainsString('log', (string) $nilai->hasil_notify_error);
        $this->assertNotNull($nilai->hasil_notified_at, 'Penanda notifikasi in-app tidak boleh dihapus oleh kegagalan email.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $pesan) => $pesan === 'Notifikasi hasil ujian gagal dikirim');
    }

    public function test_pengiriman_berhasil_menghapus_penanda_gagal_sebelumnya(): void
    {
        Queue::fake();
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->selesaikanKoreksiDuaPenilai();

        $job = Queue::pushed(KirimNotifikasiHasilUjian::class)->sole();
        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));

        DB::table('cbt_nilai')->where('id_nilai', $nilai->id_nilai)->update([
            'hasil_notify_failed_at' => now()->subDay(),
            'hasil_notify_error' => 'SMTP tidak dapat dihubungi',
        ]);

        $job->handle();
        $nilai->refresh();

        $this->assertNotNull($nilai->hasil_notified_at);
        $this->assertNull($nilai->hasil_notify_failed_at, 'Penanda gagal lama harus dibersihkan setelah berhasil.');
        $this->assertNull($nilai->hasil_notify_error);
    }

    public function test_siswa_tanpa_email_tetap_mendapat_notifikasi_database(): void
    {
        Queue::fake();
        $this->siswa->update(['email' => '']);

        $this->selesaikanKoreksiDuaPenilai();

        $job = Queue::pushed(KirimNotifikasiHasilUjian::class)->sole();
        $this->assertSame('', $job->email);

        $job->handle();

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));
        $this->assertNotNull(
            $nilai->hasil_notified_at,
            'Siswa tanpa email harus mendapat penanda status kirim dari notifikasi in-app.'
        );

        $notifikasi = $this->siswa->notifications()->sole();
        $this->assertNull($notifikasi->read_at);
        $this->assertSame('Penilaian Akhir Semester', $notifikasi->data['jenis_ujian']);
        $this->assertSame(20.0, (float) $notifikasi->data['nilai_akhir']);
    }

    public function test_notifikasi_gagal_dapat_diantrekan_ulang_dari_halaman_hasil(): void
    {
        Queue::fake();
        $this->selesaikanKoreksiDuaPenilai();

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $this->siswa->id_siswa, $this->jadwalId));

        DB::table('cbt_nilai')->where('id_nilai', $nilai->id_nilai)->update([
            'hasil_notify_failed_at' => now(),
            'hasil_notify_error' => 'SMTP tidak dapat dihubungi',
        ]);

        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/cbt/hasil/'.$this->jadwalId.'/notifikasi')
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(KirimNotifikasiHasilUjian::class, function (KirimNotifikasiHasilUjian $job) use ($nilai) {
            return $job->nilaiId === (int) $nilai->id_nilai;
        });

        $this->assertDatabaseHas('log', [
            'log_desc' => 'cbt.notifikasi.ulang: 1 notifikasi pada jadwal #'.$this->jadwalId.' diantrekan ulang',
        ]);
    }

    /** @return array<int, float> */
    private function rincian(float $isi, float $bahasa): array
    {
        return [
            $this->kriteriaIsi->id_rubrik => $isi,
            $this->kriteriaBahasa->id_rubrik => $bahasa,
        ];
    }

    private function selesaikanKoreksiDuaPenilai(): void
    {
        $this->tugaskanKorektor($this->guruOwner->id_guru, $this->guruKedua->id_guru);

        $this->actingAs($this->guruOwnerUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 30],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(3, 4.5)],
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->guruKeduaUser)->post(
            '/cbt/koreksi/'.$this->jadwalId.'/'.$this->siswa->id_siswa,
            [
                'nilai' => [$this->jawaban->id_soal_siswa => 10],
                'rincian' => [$this->jawaban->id_soal_siswa => $this->rincian(1.5, 1)],
            ]
        )->assertSessionHasNoErrors();
    }

    private function tugaskanKorektor(int ...$guruIds): void
    {
        foreach ($guruIds as $guruId) {
            KoreksiPenilai::create([
                'id_jadwal' => $this->jadwalId,
                'id_guru' => $guruId,
            ]);
        }
    }

    private function makeUser(string $username): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => $username,
            'last_name' => '',
        ]);
    }
}
