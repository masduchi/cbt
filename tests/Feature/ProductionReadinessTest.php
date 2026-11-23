<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use App\Support\UjianSessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private int $jenisId;

    private Siswa $siswaA;

    private Siswa $siswaB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('admin');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guru = $this->user('guru');
        $this->guru->groups()->attach($guruGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTR', 'urutan' => 1]);

        $this->userA = $this->user('siswaa');
        $this->userB = $this->user('siswab');
        $this->userA->groups()->attach($siswaGroup->id);
        $this->userB->groups()->attach($siswaGroup->id);

        $this->siswaA = $this->siswa('siswaa', 'Siswa A', 1);
        $this->siswaB = $this->siswa('siswab', 'Siswa B', 2);

        $this->jenisId = DB::table('cbt_jenis')->insertGetId([
            'nama_jenis' => 'Penilaian Akhir',
            'kode_jenis' => 'PA',
        ]);
    }

    public function test_analisis_butir_menghitung_daya_beda_korelasi_dan_kr20(): void
    {
        $bankId = $this->bank(['tampil_pg' => 2, 'bobot_pg' => 100]);
        $jadwalId = $this->jadwal($bankId);

        foreach ([1, 2] as $nomor) {
            $soalId = DB::table('cbt_soal')->insertGetId([
                'bank_id' => $bankId,
                'jenis' => 1,
                'nomor_soal' => $nomor,
                'soal' => '<p>Soal '.$nomor.'</p>',
                'deskripsi' => '',
                'jawaban' => 'B',
                'tampilkan' => 1,
                'kesulitan' => 1,
            ]);

            foreach ([$this->siswaA->id_siswa, $this->siswaB->id_siswa] as $index => $siswaId) {
                DB::table('cbt_soal_siswa')->insert([
                    'id_soal_siswa' => $siswaId.$jadwalId.$soalId,
                    'id_bank' => $bankId,
                    'id_jadwal' => $jadwalId,
                    'id_soal' => $soalId,
                    'id_siswa' => $siswaId,
                    'jenis_soal' => 1,
                    'no_soal_alias' => $nomor,
                    'jawaban_siswa' => $index === 0 ? 'B' : 'A',
                    'jawaban_benar' => 'B',
                    'point_soal' => '50',
                    'nilai_koreksi' => '0',
                    'nilai_otomatis' => 0,
                    'soal_end' => $nomor === 2 ? 1 : 0,
                    'time_create' => now(),
                ]);
            }
        }

        $this->actingAs($this->admin)->get('/cbt/analisis?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Analisis/Index')
                ->where('ringkasan.peserta', 2)
                ->has('butir', 2)
                ->where('butir.0.daya_beda', 1)
                ->where('butir.0.kelompok_atas', 100)
                ->where('butir.0.kelompok_bawah', 0)
            );

        $this->actingAs($this->admin)->get('/cbt/analisis/export?jadwal='.$jadwalId)
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_analisis_butir_lintas_ujian_menggabungkan_soal_yang_sama(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100]);
        $soalId = $this->soal($bankId, 1, 'B', 1);
        $jadwalIds = [$this->jadwal($bankId), $this->jadwal($bankId)];

        foreach ($jadwalIds as $indexJadwal => $jadwalId) {
            foreach ([$this->siswaA, $this->siswaB] as $indexSiswa => $siswa) {
                DB::table('cbt_soal_siswa')->insert([
                    'id_soal_siswa' => $siswa->id_siswa.$jadwalId.$soalId,
                    'id_bank' => $bankId,
                    'id_jadwal' => $jadwalId,
                    'id_soal' => $soalId,
                    'id_siswa' => $siswa->id_siswa,
                    'jenis_soal' => 1,
                    'no_soal_alias' => 1,
                    'jawaban_siswa' => $indexSiswa === 0 ? 'B' : 'A',
                    'jawaban_benar' => 'B',
                    'point_soal' => '100',
                    'nilai_koreksi' => '0',
                    'nilai_otomatis' => 0,
                    'soal_end' => 1,
                    'time_create' => now(),
                ]);
            }
        }

        $this->actingAs($this->admin)->get('/cbt/analisis?lintas=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Analisis/Index')
                ->where('lintas', true)
                ->has('lintasButir', 1)
                ->where('lintasButir.0.id_soal', $soalId)
                ->where('lintasButir.0.jumlah_ujian', 2)
                ->where('lintasButir.0.peserta', 4)
                ->where('lintasButir.0.benar', 2)
                ->where('lintasButir.0.persentase', 50)
            );
    }

    public function test_analisis_butir_lintas_ujian_mengabaikan_jadwal_remedial(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100]);
        $soalId = $this->soal($bankId, 1, 'B', 1);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        foreach ([$indukId, $remedialId] as $jadwalId) {
            DB::table('cbt_soal_siswa')->insert([
                'id_soal_siswa' => $jadwalId.$soalId,
                'id_bank' => $bankId,
                'id_jadwal' => $jadwalId,
                'id_soal' => $soalId,
                'id_siswa' => $this->siswaA->id_siswa,
                'jenis_soal' => 1,
                'no_soal_alias' => 1,
                'jawaban_siswa' => 'B',
                'jawaban_benar' => 'B',
                'point_soal' => '100',
                'nilai_koreksi' => '0',
                'nilai_otomatis' => 0,
                'soal_end' => 1,
                'time_create' => now(),
            ]);
        }

        $this->actingAs($this->admin)->get('/cbt/analisis?lintas=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Analisis/Index')
                ->has('lintasButir', 1)
                ->where('lintasButir.0.id_soal', $soalId)
                ->where('lintasButir.0.jumlah_ujian', 1)
                ->where('lintasButir.0.peserta', 1)
                ->where('lintasButir.0.benar', 1)
                ->where('lintasButir.0.persentase', 100)
            );
    }

    public function test_remedial_hanya_untuk_peserta_terdaftar_dan_dibatasi_percobaan(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100, 'kkm' => 75]);
        $this->soal($bankId, 1, 'B', 1);
        $jadwalId = $this->jadwal($bankId);

        $this->actingAs($this->userA)->post('/ujian/'.$jadwalId.'/mulai');

        $this->assertDatabaseHas('log', [
            'id_user' => $this->userA->id,
            'log_desc' => 'ujian.mulai: Siswa #'.$this->siswaA->id_siswa.' jadwal #'.$jadwalId.': Memulai Ujian',
        ]);

        $this->actingAs($this->userA)->postJson('/ujian/'.$jadwalId.'/selesai')->assertOk();

        $this->assertDatabaseHas('log', [
            'id_user' => $this->userA->id,
            'log_desc' => 'ujian.selesai: Siswa #'.$this->siswaA->id_siswa.' jadwal #'.$jadwalId.': Menyelesaikan Ujian',
        ]);

        // Peserta di luar kelas bank soal tidak boleh dibuatkan jadwal remedial.
        $this->actingAs($this->admin)->post('/cbt/remedial/'.$jadwalId, [
            'tgl_mulai' => now()->addMinutes(5)->format('Y-m-d\TH:i'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'durasi_ujian' => 60,
            'max_percobaan' => 1,
            'siswa' => [$this->siswaA->id_siswa, 999999],
        ])->assertSessionHas('error');

        $this->assertSame(0, DB::table('cbt_jadwal')->where('id_jadwal_induk', $jadwalId)->count());

        $this->actingAs($this->admin)->post('/cbt/remedial/'.$jadwalId, [
            'tgl_mulai' => now()->addMinutes(5)->format('Y-m-d\TH:i'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'durasi_ujian' => 60,
            'max_percobaan' => 1,
            'siswa' => [$this->siswaA->id_siswa],
        ])->assertSessionHas('success');

        $remedialId = (int) DB::table('cbt_jadwal')->where('id_jadwal_induk', $jadwalId)->value('id_jadwal');

        $this->assertTrue($remedialId > 0);
        $this->assertDatabaseHas('cbt_remedial_peserta', [
            'id_jadwal' => $remedialId,
            'id_siswa' => $this->siswaA->id_siswa,
        ]);

        $this->actingAs($this->userB)->get('/ujian/'.$remedialId)->assertNotFound();

        $this->actingAs($this->admin)->get('/cbt/remedial?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cbt/Remedial/Index'));

        $this->actingAs($this->userA)->get('/ujian/'.$remedialId)->assertOk();
        $this->actingAs($this->userA)->post('/ujian/'.$remedialId.'/mulai')
            ->assertRedirect('/ujian/'.$remedialId.'/kerjakan');

        $this->assertSame(1, (int) DB::table('cbt_remedial_peserta')
            ->where('id_jadwal', $remedialId)
            ->where('id_siswa', $this->siswaA->id_siswa)
            ->value('percobaan'));

        $this->actingAs($this->userA)->postJson('/ujian/'.$remedialId.'/selesai')->assertOk();

        $this->actingAs($this->userA)->post('/ujian/'.$remedialId.'/mulai')
            ->assertRedirect('/ujian')
            ->assertSessionHas('error');
    }

    public function test_izin_mengulang_menyimpan_riwayat_percobaan_sebelum_dihapus(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100, 'kkm' => 75]);
        $soalId = $this->soal($bankId, 1, 'B', 1);
        $jadwalId = $this->jadwal($bankId);
        $siswaId = $this->siswaA->id_siswa;

        DB::table('cbt_nilai')->insert([
            'id_nilai' => 990001,
            'pg_benar' => 1,
            'pg_nilai' => 80,
            'essai_nilai' => 0,
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwalId,
            'kompleks_nilai' => 0,
            'jodohkan_nilai' => 0,
            'isian_nilai' => 0,
            'dikoreksi' => 1,
            'time_create' => now(),
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => '990001001',
            'id_bank' => $bankId,
            'id_jadwal' => $jadwalId,
            'id_soal' => $soalId,
            'id_siswa' => $siswaId,
            'jenis_soal' => 1,
            'no_soal_alias' => 1,
            'jawaban_siswa' => 'B',
            'jawaban_benar' => 'B',
            'point_soal' => '100',
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 1,
            'soal_end' => 1,
            'time_create' => now(),
        ]);

        DB::table('cbt_durasi_siswa')->insert([
            'id_durasi' => 990001,
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwalId,
            'status' => 2,
            'lama_ujian' => '00:10:00',
            'mulai' => '2026-11-17 09:00:00',
            'selesai' => '2026-11-17 09:10:00',
            'reset' => 0,
            'time_create' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post('/cbt/status/'.$jadwalId.'/repeat', ['siswa' => [$siswaId]])
            ->assertRedirect();

        $riwayat = DB::table('cbt_percobaan_riwayat')->first();

        $this->assertNotNull($riwayat);
        $this->assertSame($jadwalId, (int) $riwayat->id_jadwal);
        $this->assertSame($siswaId, (int) $riwayat->id_siswa);
        $this->assertSame('pengawas_mengulang', $riwayat->alasan);
        $this->assertEqualsWithDelta(80.0, (float) $riwayat->total, 0.01);
        $this->assertSame('B', json_decode($riwayat->jawaban, true)[0]['jawaban']);
        $this->assertSame('00:10:00', $riwayat->lama_ujian);

        $this->assertDatabaseMissing('cbt_nilai', ['id_jadwal' => $jadwalId, 'id_siswa' => $siswaId]);
        $this->assertDatabaseMissing('cbt_soal_siswa', ['id_jadwal' => $jadwalId, 'id_siswa' => $siswaId]);

        $this->actingAs($this->admin)->get('/cbt/hasil?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Hasil/Index')
                ->has('riwayat', 1)
                ->where('riwayat.0.nama', 'Siswa A')
                ->where('riwayat.0.alasan', 'pengawas_mengulang')
            );
    }

    public function test_dashboard_tidak_menghitung_jadwal_remedial_sebagai_ujian_terpisah(): void
    {
        $bankId = $this->bank();
        $jadwalId = $this->jadwal($bankId);

        $remedialId = DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 90,
            'status' => 1,
            'remedial' => 1,
            'id_jadwal_induk' => $jadwalId,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);

        foreach ([$jadwalId, $remedialId] as $index => $idJadwal) {
            DB::table('cbt_nilai')->insert([
                'id_nilai' => 991001 + $index,
                'pg_benar' => 1,
                'pg_nilai' => 70 + ($index * 10),
                'essai_nilai' => 0,
                'id_siswa' => $this->siswaA->id_siswa,
                'id_jadwal' => $idJadwal,
                'kompleks_nilai' => 0,
                'jodohkan_nilai' => 0,
                'isian_nilai' => 0,
                'dikoreksi' => 1,
                'time_create' => now(),
            ]);

            DB::table('cbt_durasi_siswa')->insert([
                'id_durasi' => 992001 + $index,
                'id_siswa' => $this->siswaA->id_siswa,
                'id_jadwal' => $idJadwal,
                'status' => 2,
                'lama_ujian' => '00:10:00',
                'mulai' => '2026-11-17 09:00:00',
                'selesai' => '2026-11-17 09:10:00',
                'reset' => 0,
                'time_create' => now(),
            ]);
        }

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.dinilai', 1)
                ->where('analytics.selesai', 1)
                ->has('analytics.chart', 1)
                ->where('stats.jadwal', 1)
            );
    }

    public function test_antrean_koreksi_esai_bisa_disaring_belum_dan_selesai(): void
    {
        $bankId = $this->bank(['tampil_esai' => 1, 'bobot_esai' => 100]);
        $jadwalId = $this->jadwal($bankId);

        $soalId = DB::table('cbt_soal')->insertGetId([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 5,
            'nomor_soal' => 1,
            'soal' => '<p>Jelaskan.</p>',
            'deskripsi' => '',
            'jawaban' => '',
            'tampilkan' => 1,
            'kesulitan' => 1,
        ]);

        foreach ([$this->siswaA, $this->siswaB] as $index => $siswa) {
            DB::table('cbt_soal_siswa')->insert([
                'id_soal_siswa' => '9'.$siswa->id_siswa.$jadwalId,
                'id_bank' => $bankId,
                'id_jadwal' => $jadwalId,
                'id_soal' => $soalId,
                'id_siswa' => $siswa->id_siswa,
                'jenis_soal' => 5,
                'no_soal_alias' => 1,
                'jawaban_siswa' => 'Jawaban '.$siswa->nama,
                'jawaban_benar' => '',
                'point_soal' => '100',
                'nilai_koreksi' => $index === 0 ? '80' : '0',
                'nilai_otomatis' => $index === 0 ? 1 : 0,
                'soal_end' => 1,
                'time_create' => now(),
            ]);

            DB::table('cbt_nilai')->insert([
                'id_nilai' => $siswa->id_siswa * 100000 + $jadwalId,
                'pg_benar' => 0,
                'pg_nilai' => 0,
                'essai_nilai' => $index === 0 ? 80 : 0,
                'id_siswa' => $siswa->id_siswa,
                'id_jadwal' => $jadwalId,
                'kompleks_nilai' => 0,
                'jodohkan_nilai' => 0,
                'isian_nilai' => 0,
                'dikoreksi' => $index === 0 ? 1 : 0,
                'time_create' => now(),
            ]);
        }

        $this->actingAs($this->admin)->get('/cbt/koreksi?jadwal='.$jadwalId.'&status=belum')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Koreksi/Index')
                ->where('status', 'belum')
                ->where('ringkasan.peserta', 2)
                ->where('ringkasan.belum', 1)
                ->where('ringkasan.selesai', 1)
                ->has('students', 1)
                ->where('students.0.id_siswa', $this->siswaB->id_siswa)
            );

        $this->actingAs($this->admin)->get('/cbt/koreksi?jadwal='.$jadwalId.'&status=selesai')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'selesai')
                ->has('students', 1)
                ->where('students.0.id_siswa', $this->siswaA->id_siswa)
            );
    }

    public function test_token_ujian_kedaluwarsa_menolak_memulai_ujian(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100]);
        $this->soal($bankId, 1, 'B', 1);
        $jadwalId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $jadwalId)->update(['token' => 1]);
        DB::table('cbt_token')->insert([
            'token' => 'ABC123',
            'auto' => 1,
            'jarak' => 1,
            'updated' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->userA)
            ->from('/ujian/'.$jadwalId)
            ->post('/ujian/'.$jadwalId.'/mulai', ['token' => 'ABC123'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Token sudah kedaluwarsa. Minta pengawas membuat token baru.');

        $this->assertDatabaseMissing('cbt_durasi_siswa', [
            'id_jadwal' => $jadwalId,
            'id_siswa' => $this->siswaA->id_siswa,
        ]);

        DB::table('cbt_token')->update(['updated' => now()->format('Y-m-d H:i:s')]);

        $this->actingAs($this->userA)
            ->post('/ujian/'.$jadwalId.'/mulai', ['token' => 'ABC123'])
            ->assertRedirect('/ujian/'.$jadwalId.'/kerjakan');
    }

    public function test_reset_otomatis_remedial_mengarsipkan_percobaan_lalu_memulai_ulang(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100, 'kkm' => 75]);
        $soalId = $this->soal($bankId, 1, 'B', 1);
        $indukId = $this->jadwal($bankId);

        $remedialId = DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'durasi_ujian' => 90,
            'status' => 1,
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
            'token' => 0,
            'acak_soal' => 0,
            'acak_opsi' => 0,
            'ulang' => 0,
            'reset_login' => 0,
            'hasil_tampil' => 1,
            'jam_ke' => 0,
        ]);

        $siswaId = $this->siswaA->id_siswa;

        DB::table('cbt_remedial_peserta')->insert([
            'id_jadwal' => $remedialId,
            'id_siswa' => $siswaId,
            'id_jadwal_induk' => $indukId,
            'percobaan' => 0,
            'max_percobaan' => 2,
            'time_create' => now(),
        ]);

        DB::table('cbt_soal_siswa')->insert([
            'id_soal_siswa' => '8'.$siswaId.$remedialId,
            'id_bank' => $bankId,
            'id_jadwal' => $remedialId,
            'id_soal' => $soalId,
            'id_siswa' => $siswaId,
            'jenis_soal' => 1,
            'no_soal_alias' => 1,
            'jawaban_siswa' => 'B',
            'jawaban_benar' => 'B',
            'point_soal' => '100',
            'nilai_koreksi' => '0',
            'nilai_otomatis' => 1,
            'soal_end' => 1,
            'time_create' => now(),
        ]);

        DB::table('cbt_nilai')->insert([
            'id_nilai' => $siswaId * 100000 + $remedialId,
            'pg_benar' => 1,
            'pg_nilai' => 80,
            'essai_nilai' => 0,
            'id_siswa' => $siswaId,
            'id_jadwal' => $remedialId,
            'kompleks_nilai' => 0,
            'jodohkan_nilai' => 0,
            'isian_nilai' => 0,
            'dikoreksi' => 1,
            'time_create' => now(),
        ]);

        DB::table('cbt_durasi_siswa')->insert([
            'id_durasi' => $siswaId * 100000 + $remedialId,
            'id_siswa' => $siswaId,
            'id_jadwal' => $remedialId,
            'status' => 2,
            'lama_ujian' => '00:12:00',
            'mulai' => '2026-11-17 09:00:00',
            'selesai' => '2026-11-17 09:12:00',
            'reset' => 0,
            'time_create' => now(),
        ]);

        $this->actingAs($this->userA)
            ->post('/ujian/'.$remedialId.'/mulai', [])
            ->assertRedirect('/ujian/'.$remedialId.'/kerjakan');

        $riwayat = DB::table('cbt_percobaan_riwayat')->first();

        $this->assertNotNull($riwayat);
        $this->assertSame('reset_otomatis', $riwayat->alasan);
        $this->assertSame($remedialId, (int) $riwayat->id_jadwal);
        $this->assertEqualsWithDelta(80.0, (float) $riwayat->total, 0.01);

        $this->assertSame(1, (int) DB::table('cbt_durasi_siswa')
            ->where('id_jadwal', $remedialId)
            ->where('id_siswa', $siswaId)
            ->value('status'));

        $this->assertDatabaseMissing('cbt_nilai', ['id_jadwal' => $remedialId, 'id_siswa' => $siswaId]);
    }

    public function test_perakitan_soal_otomatis_memilih_soal_sesuai_komposisi(): void
    {
        $bankId = $this->bank(['tampil_pg' => 0]);

        foreach ([1, 2, 4, 5, 8, 9] as $index => $kesulitan) {
            $this->soal($bankId, $index + 1, 'B', $kesulitan);
        }

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/rakit', [
            'komposisi' => [
                1 => ['mudah' => 2, 'sedang' => 1, 'sulit' => 1],
            ],
        ])->assertSessionHas('success');

        $this->assertSame(4, (int) DB::table('cbt_soal')->where('bank_id', $bankId)->where('tampilkan', 1)->count());
        $this->assertSame(4, (int) DB::table('cbt_bank_soal')->where('id_bank', $bankId)->value('tampil_pg'));

        $this->actingAs($this->admin)->post('/cbt/bank-soal/'.$bankId.'/rakit', [
            'komposisi' => [
                1 => ['mudah' => 5, 'sedang' => 0, 'sulit' => 0],
            ],
        ])->assertSessionHasErrors('komposisi');
    }

    public function test_jejak_audit_dipaginasi_dan_filter_terbawa_antar_halaman(): void
    {
        $rows = [];
        for ($i = 1; $i <= 45; $i++) {
            $rows[] = [
                'log_time' => now()->subMinutes($i),
                'id_user' => $this->admin->id,
                'id_group' => 1,
                'name_group' => 'admin',
                'log_type' => 2,
                'log_desc' => 'user.update: baris '.$i,
                'address' => '127.0.0.1',
                'agent' => 'test',
                'device' => 'web',
            ];
        }
        DB::table('log')->insert($rows);

        $this->actingAs($this->admin)->get('/setting/audit?per_page=25')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 45)
                ->where('logs.per_page', 25)
                ->where('logs.last_page', 2)
                ->has('logs.data', 25)
                ->where('logs.next_page_url', fn ($url) => is_string($url) && str_contains($url, 'page=2') && str_contains($url, 'per_page=25'))
            );

        $this->actingAs($this->admin)->get('/setting/audit?page=2&per_page=25&aksi=user.update')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.current_page', 2)
                ->where('logs.per_page', 25)
                ->where('filters.per_page', 25)
                ->has('logs.data', 20)
            );

        $this->actingAs($this->admin)->get('/setting/audit?per_page=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('logs.per_page', 40));
    }

    public function test_jejak_audit_mencatat_aksi_dan_menampilkan_halaman(): void
    {
        $this->actingAs($this->admin)->post('/users/guru', [
            'username' => 'gurubaru',
            'nama' => 'Guru Baru',
            'password' => 'rahasia123',
            'active' => true,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'name_group' => 'admin',
        ]);

        $this->assertStringStartsWith('user.create', (string) DB::table('log')->orderByDesc('id_log')->value('log_desc'));

        $this->actingAs($this->admin)->get('/setting/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/Audit')
                ->has('logs.data', 1)
                ->where('logs.data.0.kode', 'user.create')
            );

        $this->actingAs($this->guru)->get('/setting/audit')->assertForbidden();
    }

    public function test_portal_wali_murid_hanya_untuk_anak_tertaut(): void
    {
        $group = Group::create(['name' => 'orangtua', 'description' => 'Wali Murid']);
        $wali = $this->user('wali');
        $wali->groups()->attach($group->id);

        DB::table('wali_siswa')->insert([
            'id_user' => $wali->id,
            'id_siswa' => $this->siswaA->id_siswa,
            'time_create' => now(),
        ]);

        $this->actingAs($wali)->get('/wali-murid')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Wali/Dashboard')
                ->has('anak', 1)
                ->where('anak.0.id_siswa', $this->siswaA->id_siswa)
            );

        $this->actingAs($wali)->get('/wali-murid/'.$this->siswaA->id_siswa)->assertOk();
        $this->actingAs($wali)->get('/wali-murid/'.$this->siswaB->id_siswa)->assertForbidden();
        $this->actingAs($wali)->get('/dashboard')->assertRedirect('/wali-murid');
    }

    public function test_perpindahan_sesi_tanpa_dua_sesi_aktif_tidak_dicatat_sebagai_pelanggaran(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100]);
        $this->soal($bankId, 1, 'B', 1);
        $jadwalId = $this->jadwal($bankId);

        $this->actingAs($this->userA)->post('/ujian/'.$jadwalId.'/mulai');

        // Sesi lama hanya dipakai sekali lalu berhenti (browser restart, sesi
        // kedaluwarsa, atau perpindahan jaringan lab): bukan pelanggaran.
        UjianSessionGuard::record(
            $jadwalId,
            (int) $this->siswaA->id_siswa,
            str_repeat('l', 40),
            '10.9.9.9',
            now()->subMinutes(5)->getTimestamp(),
        );

        $this->actingAs($this->userA)->get('/ujian/'.$jadwalId.'/kerjakan')->assertOk();

        $this->assertSame(0, DB::table('log_ujian')
            ->where('id_jadwal', $jadwalId)
            ->where('log_type', 3)
            ->count());

        $durasi = DB::table('cbt_durasi_siswa')
            ->where('id_jadwal', $jadwalId)
            ->where('id_siswa', $this->siswaA->id_siswa)
            ->first();

        $this->assertNotSame(str_repeat('l', 40), $durasi->session_id);
        $this->assertSame('127.0.0.1', $durasi->ip_address);
    }

    public function test_dua_sesi_aktif_bersamaan_terdeteksi_dan_berhenti_setelah_sesi_lain_diam(): void
    {
        $jadwalId = $this->jadwal($this->bank());
        $siswaId = (int) $this->siswaA->id_siswa;
        $now = now()->getTimestamp();
        $sesiA = str_repeat('a', 40);
        $sesiB = str_repeat('b', 40);

        $this->assertNull(UjianSessionGuard::record($jadwalId, $siswaId, $sesiA, '10.1.1.1', $now));
        $this->assertNull(UjianSessionGuard::record($jadwalId, $siswaId, $sesiB, '10.2.2.2', $now + 5));

        $lain = UjianSessionGuard::record($jadwalId, $siswaId, $sesiA, '10.1.1.1', $now + 10);

        $this->assertNotNull($lain);
        $this->assertSame('10.2.2.2', $lain['ip']);

        // Sesi B berhenti: setelah jendela overlap lewat, tidak ada lagi tuduhan.
        $this->assertNull(UjianSessionGuard::record($jadwalId, $siswaId, $sesiA, '10.1.1.1', $now + 400));
    }

    public function test_status_pengawas_menampilkan_jenis_pelanggaran(): void
    {
        $jadwalId = $this->jadwal($this->bank());

        $logs = [
            ['sesi_ganda', 'Dua sesi aktif bersamaan: perangkat lain aktif 4 detik lalu dari 10.9.9.9'],
            ['sesi_ganda', 'Dua sesi aktif bersamaan: perangkat lain aktif 40 detik lalu dari 10.9.9.9'],
            ['pindah_tab', 'Berpindah tab atau jendela'],
        ];

        foreach ($logs as $index => [$kode, $desc]) {
            DB::table('log_ujian')->insert([
                'log_time' => now()->subMinutes($index),
                'id_siswa' => $this->siswaA->id_siswa,
                'id_jadwal' => $jadwalId,
                'log_type' => 3,
                'log_desc' => $desc,
                'kode' => $kode,
                'address' => '127.0.0.1',
                'agent' => 'test',
                'device' => 'web',
                'reset' => 0,
            ]);
        }

        $this->actingAs($this->admin)->get('/cbt/status?jadwal='.$jadwalId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('peserta.0.violations', 3)
                ->where('peserta.0.violations_rincian.0.kode', 'sesi_ganda')
                ->where('peserta.0.violations_rincian.0.jumlah', 2)
                ->where('peserta.0.violations_rincian.0.label', 'Dua sesi aktif bersamaan')
                ->where('peserta.0.violations_rincian.1.kode', 'pindah_tab')
            );
    }

    public function test_rekap_memakai_nilai_remedial_terbaik_tanpa_kolom_ganda(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100, 'kkm' => 75]);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $this->nilaiCbt($indukId, (int) $this->siswaA->id_siswa, 60);
        $this->nilaiCbt($remedialId, (int) $this->siswaA->id_siswa, 90);

        $this->actingAs($this->admin)->get('/cbt/rekap')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Rekap/Index')
                ->has('columns', 1)
                ->where('columns.0.id_jadwal', $indukId)
                ->where('rows.0.nilai.'.$indukId, 90)
                ->where('rows.0.rata_rata', 90)
            );
    }

    public function test_tarik_cbt_memakai_nilai_terbaik_dari_remedial(): void
    {
        $bankId = $this->bank(['tampil_pg' => 1, 'bobot_pg' => 100, 'kkm' => 75]);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $this->nilaiCbt($indukId, (int) $this->siswaA->id_siswa, 60);
        $this->nilaiCbt($remedialId, (int) $this->siswaA->id_siswa, 90);

        $this->actingAs($this->admin)->get('/rapor/tarik-cbt?jadwal='.$indukId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rapor/TarikCbt')
                ->has('jadwalOptions', 1)
                ->where('preview.rows.0.nilai', 90)
                ->where('preview.rows.0.remedial', true)
                ->where('preview.summary.remedial', 1)
            );
    }

    public function test_menghapus_jadwal_induk_ikut_menghapus_sesi_remedial(): void
    {
        $bankId = $this->bank();
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $this->actingAs($this->admin)->delete('/cbt/jadwal/'.$indukId)->assertRedirect();

        $this->assertDatabaseMissing('cbt_jadwal', ['id_jadwal' => $indukId]);
        $this->assertDatabaseMissing('cbt_jadwal', ['id_jadwal' => $remedialId]);
    }

    public function test_dropdown_analisis_dan_hasil_hanya_memuat_jadwal_induk(): void
    {
        $bankId = $this->bank();
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        foreach (['/cbt/analisis', '/cbt/hasil'] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('jadwalOptions', 1)
                    ->where('jadwalOptions.0.id_jadwal', $indukId)
                );
        }
    }

    public function test_efektivitas_remedial_membandingkan_peserta_yang_sama(): void
    {
        $bankId = $this->bank(['kkm' => 75, 'tampil_pg' => 1, 'bobot_pg' => 100]);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $siswaC = $this->siswa('siswac', 'Siswa C', 3);

        // Nilai ujian induk: A belum tuntas, B sudah tuntas tetapi tidak ikut remedial.
        $this->nilaiCbt($indukId, $this->siswaA->id_siswa, 40);
        $this->nilaiCbt($indukId, $this->siswaB->id_siswa, 90);

        // A ikut remedial dan naik; C hanya punya nilai remedial tanpa nilai induk.
        $this->nilaiCbt($remedialId, $this->siswaA->id_siswa, 80);
        $this->nilaiCbt($remedialId, $siswaC->id_siswa, 60);

        foreach ([$this->siswaA->id_siswa, $siswaC->id_siswa] as $siswaId) {
            DB::table('cbt_remedial_peserta')->insert([
                'id_jadwal' => $remedialId,
                'id_siswa' => $siswaId,
                'id_jadwal_induk' => $indukId,
                'percobaan' => 1,
                'max_percobaan' => 2,
                'time_create' => now(),
            ]);
        }

        $this->actingAs($this->admin)->get('/cbt/remedial?jadwal='.$indukId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Remedial/Index')
                ->where('efektivitas.peserta', 2)
                ->where('efektivitas.dinilai', 2)
                ->where('efektivitas.dibandingkan', 1)
                ->where('efektivitas.naik', 1)
                ->where('efektivitas.rata_sebelum', 40)
                ->where('efektivitas.rata_sesudah', 80)
                ->where('efektivitas.tuntas_sebelum', 0)
                ->where('efektivitas.tuntas_sesudah', 1)
                ->has('sesiRemedial', 1)
                ->where('sesiRemedial.0.dinilai', 2)
                ->where('sesiRemedial.0.dibandingkan', 1)
                ->where('sesiRemedial.0.rata_sebelum', 40)
                ->where('sesiRemedial.0.rata_sesudah', 80)
                ->where('sesiRemedial.0.tuntas_sebelum', 0)
                ->where('sesiRemedial.0.tuntas_sesudah', 1)
            );
    }

    public function test_hasil_menampilkan_nilai_terbaik_induk_dan_remedial(): void
    {
        $bankId = $this->bank(['kkm' => 75, 'tampil_pg' => 1, 'bobot_pg' => 100]);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $siswaC = $this->siswa('siswac', 'Siswa C', 3);

        $this->nilaiCbt($indukId, $this->siswaA->id_siswa, 40);
        $this->nilaiCbt($indukId, $this->siswaB->id_siswa, 90);
        $this->nilaiCbt($indukId, $siswaC->id_siswa, 50);

        // Nilai remedial A lebih tinggi, jadi A memakai 80 dan naik ke peringkat 2.
        $this->nilaiCbt($remedialId, $this->siswaA->id_siswa, 80);

        $this->actingAs($this->admin)->get('/cbt/hasil?jadwal='.$indukId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Hasil/Index')
                ->has('peserta', 3)
                ->where('peserta.0.id_siswa', $this->siswaB->id_siswa)
                ->where('peserta.0.total', 90)
                ->where('peserta.0.remedial', false)
                ->where('peserta.1.id_siswa', $this->siswaA->id_siswa)
                ->where('peserta.1.rank', 2)
                ->where('peserta.1.total', 80)
                ->where('peserta.1.komponen.pg', 80)
                ->where('peserta.1.remedial', true)
                ->where('peserta.2.id_siswa', $siswaC->id_siswa)
                ->where('peserta.2.total', 50)
            );
    }

    public function test_peserta_remedial_memakai_nilai_terbaik_induk_dan_remedial(): void
    {
        $bankId = $this->bank(['kkm' => 75, 'tampil_pg' => 1, 'bobot_pg' => 100]);
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        $this->nilaiCbt($indukId, $this->siswaA->id_siswa, 40);
        $this->nilaiCbt($indukId, $this->siswaB->id_siswa, 50);
        $this->nilaiCbt($remedialId, $this->siswaA->id_siswa, 80);

        DB::table('cbt_remedial_peserta')->insert([
            'id_jadwal' => $remedialId,
            'id_siswa' => $this->siswaA->id_siswa,
            'id_jadwal_induk' => $indukId,
            'percobaan' => 1,
            'max_percobaan' => 2,
            'time_create' => now(),
        ]);

        $this->actingAs($this->admin)->get('/cbt/remedial?jadwal='.$indukId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Remedial/Index')
                ->has('peserta', 2)
                ->where('peserta.0.id_siswa', $this->siswaA->id_siswa)
                ->where('peserta.0.nilai', 80)
                ->where('peserta.0.nilai_induk', 40)
                ->where('peserta.0.dari_remedial', true)
                ->where('peserta.0.tuntas', true)
                ->where('peserta.0.perlu_remedial', false)
                ->where('peserta.0.remedial.nilai_terbaik', 80)
                ->where('peserta.1.id_siswa', $this->siswaB->id_siswa)
                ->where('peserta.1.nilai', 50)
                ->where('peserta.1.dari_remedial', false)
                ->where('peserta.1.perlu_remedial', true)
            );
    }

    public function test_jejak_audit_mencatat_perubahan_jadwal_token_dan_bank_soal(): void
    {
        $this->actingAs($this->admin)->post('/cbt/bank-soal', [
            'bank_kode' => 'AUDIT-1',
            'bank_nama' => 'Bank Audit',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => 10,
            'opsi' => 4,
        ])->assertSessionHas('success');

        $bankId = (int) DB::table('cbt_bank_soal')->where('bank_kode', 'AUDIT-1')->value('id_bank');
        $this->assertSame('cbt.bank: Bank #'.$bankId.' AUDIT-1 dibuat', DB::table('log')->orderByDesc('id_log')->value('log_desc'));

        $this->actingAs($this->admin)->put('/cbt/bank-soal/'.$bankId, [
            'bank_kode' => 'AUDIT-1',
            'bank_nama' => 'Bank Audit Diubah',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => 10,
            'opsi' => 4,
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.bank: Bank #'.$bankId.' AUDIT-1 diubah (bank_nama)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/jadwal', [
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20T08:00',
            'tgl_selesai' => '2026-11-20T10:00',
            'durasi_ujian' => 90,
        ])->assertSessionHas('success');

        $jadwalId = (int) DB::table('cbt_jadwal')->where('id_bank', $bankId)->value('id_jadwal');
        $this->assertStringStartsWith(
            'cbt.jadwal: Jadwal #'.$jadwalId.' dibuat',
            (string) DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->put('/cbt/jadwal/'.$jadwalId, [
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'tgl_mulai' => '2026-11-20T08:30',
            'tgl_selesai' => '2026-11-20T10:30',
            'durasi_ujian' => 90,
        ])->assertSessionHas('success');

        $this->assertStringStartsWith(
            'cbt.jadwal: Jadwal #'.$jadwalId.' diubah (tgl_mulai, tgl_selesai)',
            (string) DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/jadwal/'.$jadwalId.'/status')->assertSessionHas('success');
        $this->assertSame(
            'cbt.jadwal.status: Jadwal #'.$jadwalId.' diaktifkan',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/token', ['force' => 1])->assertSessionHas('success');
        $this->assertStringStartsWith(
            'cbt.token: Token baru dibuat',
            (string) DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->delete('/cbt/jadwal/'.$jadwalId)->assertSessionHas('success');
        $this->assertSame(
            'cbt.jadwal.delete: Jadwal #'.$jadwalId.' (bank #'.$bankId.') dihapus',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->delete('/cbt/bank-soal/'.$bankId)->assertSessionHas('success');
        $this->assertSame(
            'cbt.bank.delete: Bank #'.$bankId.' AUDIT-1 beserta soalnya dihapus',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );
    }

    public function test_jejak_audit_mencatat_pengaturan_master_cbt(): void
    {
        $this->actingAs($this->admin)->post('/cbt/sesi', [
            'nama_sesi' => 'Sesi 1',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '08:00',
            'waktu_akhir' => '10:00',
            'aktif' => 1,
        ])->assertSessionHas('success');

        $sesiId = (int) DB::table('cbt_sesi')->where('kode_sesi', 'S1')->value('id_sesi');
        $this->assertSame(
            'cbt.sesi: Sesi #'.$sesiId.' S1 dibuat (08:00-10:00)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->put('/cbt/sesi/'.$sesiId, [
            'nama_sesi' => 'Sesi 1 Revisi',
            'kode_sesi' => 'S1',
            'waktu_mulai' => '08:00',
            'waktu_akhir' => '10:00',
            'aktif' => 1,
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.sesi: Sesi #'.$sesiId.' S1 diubah (nama_sesi)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/ruang', [
            'nama_ruang' => 'Ruang 01',
            'kode_ruang' => 'R01',
        ])->assertSessionHas('success');

        $ruangId = (int) DB::table('cbt_ruang')->where('kode_ruang', 'R01')->value('id_ruang');
        $this->assertSame(
            'cbt.ruang: Ruang #'.$ruangId.' R01 dibuat',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->put('/cbt/ruang/'.$ruangId, [
            'nama_ruang' => 'Ruang A',
            'kode_ruang' => 'R01',
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.ruang: Ruang #'.$ruangId.' R01 diubah (nama_ruang)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/jenis', [
            'nama_jenis' => 'Ujian Praktik',
            'kode_jenis' => 'PRK',
        ])->assertSessionHas('success');

        $jenisId = (int) DB::table('cbt_jenis')->where('kode_jenis', 'PRK')->value('id_jenis');
        $this->assertSame(
            'cbt.jenis: Jenis #'.$jenisId.' PRK Ujian Praktik dibuat',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->put('/cbt/jenis/'.$jenisId, [
            'nama_jenis' => 'Ujian Praktik Revisi',
            'kode_jenis' => 'PRK',
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.jenis: Jenis #'.$jenisId.' PRK Ujian Praktik Revisi diubah (nama_jenis)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $guruId = DB::table('master_guru')->insertGetId([
            'nip' => '198501012010011001',
            'nama_guru' => 'Guru Pengawas',
            'username' => 'gurupengawas',
        ]);
        $jadwalId = $this->jadwal($this->bank());

        $this->actingAs($this->admin)->post('/cbt/pengawas', [
            'rows' => [[
                'id_jadwal' => $jadwalId,
                'id_ruang' => $ruangId,
                'id_sesi' => $sesiId,
                'id_guru' => [$guruId],
            ]],
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.pengawas: Pengawas jadwal #'.$jadwalId.': 1 penugasan disimpan (1 berubah)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/nomor-peserta', [
            'items' => [
                ['id_siswa' => $this->siswaA->id_siswa, 'nomor_peserta' => '0001'],
                ['id_siswa' => $this->siswaB->id_siswa, 'nomor_peserta' => '0002'],
            ],
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.nomor: Nomor peserta disimpan: 2 siswa (2 berubah)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/nomor-peserta/generate', [
            'id_kelas' => $this->kelas->id_kelas,
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.nomor: Nomor peserta digenerate untuk 2 siswa kelas #'.$this->kelas->id_kelas,
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->post('/cbt/nomor-peserta/reset', [
            'id_kelas' => $this->kelas->id_kelas,
        ])->assertSessionHas('success');

        $this->assertSame(
            'cbt.nomor: Nomor peserta kelas #'.$this->kelas->id_kelas.' dikosongkan (2 siswa)',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->delete('/cbt/jenis/'.$jenisId)->assertSessionHas('success');
        $this->assertSame(
            'cbt.jenis.delete: Jenis #'.$jenisId.' PRK Ujian Praktik Revisi dihapus',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->delete('/cbt/sesi/'.$sesiId)->assertSessionHas('success');
        $this->assertSame(
            'cbt.sesi.delete: Sesi #'.$sesiId.' S1 dihapus',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );

        $this->actingAs($this->admin)->delete('/cbt/ruang/'.$ruangId)->assertSessionHas('success');
        $this->assertSame(
            'cbt.ruang.delete: Ruang #'.$ruangId.' R01 dihapus',
            DB::table('log')->orderByDesc('id_log')->value('log_desc')
        );
    }

    public function test_hasil_menampilkan_riwayat_percobaan_remedial_induk(): void
    {
        $bankId = $this->bank();
        $indukId = $this->jadwal($bankId);
        $remedialId = $this->jadwal($bankId);

        DB::table('cbt_jadwal')->where('id_jadwal', $remedialId)->update([
            'remedial' => 1,
            'id_jadwal_induk' => $indukId,
        ]);

        DB::table('cbt_percobaan_riwayat')->insert([
            'id_jadwal' => $remedialId,
            'id_siswa' => $this->siswaA->id_siswa,
            'id_jadwal_induk' => $indukId,
            'percobaan' => 1,
            'total' => 80,
            'alasan' => 'pengawas_mengulang',
            'selesai' => now(),
            'time_create' => now(),
        ]);

        $this->actingAs($this->admin)->get('/cbt/hasil?jadwal='.$indukId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cbt/Hasil/Index')
                ->has('riwayat', 1)
                ->where('riwayat.0.nama', 'Siswa A')
                ->where('riwayat.0.sumber', 'Remedial')
                ->where('riwayat.0.alasan', 'pengawas_mengulang')
                ->where('riwayat.0.total', 80)
            );
    }

    private function nilaiCbt(int $jadwalId, int $siswaId, int $total): void
    {
        DB::table('cbt_nilai')->insert([
            'id_nilai' => ($jadwalId * 100000) + $siswaId,
            'pg_nilai' => (string) $total,
            'id_siswa' => $siswaId,
            'id_jadwal' => $jadwalId,
            'time_create' => now(),
        ]);
    }

    private function bank(array $overrides = []): int
    {
        return DB::table('cbt_bank_soal')->insertGetId(array_merge([
            'bank_kode' => 'BANK-TEST',
            'bank_nama' => 'Bank Tes',
            'bank_mapel_id' => $this->mapel->id_mapel,
            'bank_jenis_id' => $this->jenisId,
            'bank_level' => 10,
            'bank_kelas' => (string) $this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'opsi' => 4,
            'kkm' => 0,
            'tampil_pg' => 0,
            'bobot_pg' => 0,
        ], $overrides));
    }

    private function soal(int $bankId, int $nomor, string $jawaban, int $kesulitan): int
    {
        return DB::table('cbt_soal')->insertGetId([
            'bank_id' => $bankId,
            'mapel_id' => $this->mapel->id_mapel,
            'jenis' => 1,
            'nomor_soal' => $nomor,
            'soal' => '<p>Soal '.$nomor.'</p>',
            'deskripsi' => '',
            'opsi_a' => 'A',
            'opsi_b' => 'B',
            'opsi_c' => 'C',
            'opsi_d' => 'D',
            'jawaban' => $jawaban,
            'tampilkan' => 1,
            'kesulitan' => $kesulitan,
        ]);
    }

    private function jadwal(int $bankId): int
    {
        return DB::table('cbt_jadwal')->insertGetId([
            'id_bank' => $bankId,
            'id_jenis' => $this->jenisId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'tgl_mulai' => now()->subHour()->format('Y-m-d H:i:s'),
            'tgl_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
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
    }

    private function siswa(string $username, string $nama, int $index): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => '2029000'.(100 + $index),
            'nis' => '20290'.(100 + $index),
            'nama' => $nama,
            'username' => $username,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-'.$username,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $index,
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
            'first_name' => $username,
            'last_name' => '',
        ]);
    }
}
