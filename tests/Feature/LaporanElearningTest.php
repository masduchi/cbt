<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasJadwalMateri;
use App\Models\KelasMateri;
use App\Models\LogMateri;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanElearningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private Guru $guru;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private KelasMateri $materi;

    private KelasMateri $tugas;

    private KelasJadwalMateri $itemMateri;

    private KelasJadwalMateri $itemTugas;

    private Siswa $ahmad;

    private Siswa $budi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 09:00:00');

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('adminlaporan', 'admin-laporan@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('gurulaporan', 'guru-laporan@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198700002', 'nama_guru' => 'Guru Laporan']);

        $siswaUser = $this->user('siswalaporan', 'siswa-laporan@example.test');
        $siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->mapel = Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'status' => 1]);

        $this->materi = $this->kelasMateri('Materi Pecahan', 1);
        $this->tugas = $this->kelasMateri('Tugas Pecahan', 2);

        $this->itemMateri = $this->jadwal('K'.$this->kelas->id_kelas.'-20261112-J1-T1', $this->materi, 1, '2026-11-12 07:00:00');
        $this->itemTugas = $this->jadwal('K'.$this->kelas->id_kelas.'-20261112-J2-T2', $this->tugas, 2, '2026-11-12 08:00:00');

        $this->ahmad = $this->siswa('2026001', 'Ahmad Fauzi', 'ahmad.laporan', 1);
        $this->budi = $this->siswa('2026002', 'Budi Santoso', 'budi.laporan', 2);

        LogMateri::create([
            'id_log' => $this->ahmad->id_siswa.$this->itemMateri->id_kjm,
            'log_time' => '2026-11-12 07:05:00',
            'id_siswa' => $this->ahmad->id_siswa,
            'jam_ke' => 1,
            'id_materi' => $this->itemMateri->id_kjm,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 2,
            'log_desc' => 'Menyelesaikan materi',
            'finish_time' => '2026-11-12 07:40:00',
            'nilai' => '80',
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'Web',
        ]);

        LogMateri::create([
            'id_log' => $this->budi->id_siswa.$this->itemMateri->id_kjm,
            'log_time' => '2026-11-12 07:06:00',
            'id_siswa' => $this->budi->id_siswa,
            'jam_ke' => 1,
            'id_materi' => $this->itemMateri->id_kjm,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 1,
            'log_desc' => 'Membuka materi',
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'Web',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_nilai_harian_lists_scheduled_items_and_student_cells(): void
    {
        $this->actingAs($this->admin)
            ->get('/elearning/nilai-harian?kelas='.$this->kelas->id_kelas.'&tanggal=2026-11-12')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/NilaiHarian')
                ->where('kelasId', $this->kelas->id_kelas)
                ->where('tanggal', '2026-11-12')
                ->has('items', 2)
                ->has('rows', 2)
                ->where('rows.0.nama', 'Ahmad Fauzi')
                ->where('rows.0.cells.'.$this->itemMateri->id_kjm.'.status', 'selesai')
                ->where('rows.0.cells.'.$this->itemMateri->id_kjm.'.nilai', 80)
                ->where('rows.0.cells.'.$this->itemTugas->id_kjm.'.status', 'kosong')
                ->where('summary.siswa', 2)
                ->where('summary.item', 2)
                ->where('summary.terisi', 1)
                ->where('summary.rata', 80));
    }

    public function test_admin_can_save_daily_scores_and_reports_reflect_them(): void
    {
        $this->actingAs($this->admin)
            ->post('/elearning/nilai-harian', [
                'kelas' => $this->kelas->id_kelas,
                'tanggal' => '2026-11-12',
                'rows' => [
                    ['id_siswa' => $this->ahmad->id_siswa, 'id_kjm' => $this->itemMateri->id_kjm, 'nilai' => 85, 'catatan' => 'Perbaikan'],
                    ['id_siswa' => $this->budi->id_siswa, 'id_kjm' => $this->itemTugas->id_kjm, 'nilai' => 90, 'catatan' => 'Bagus'],
                    ['id_siswa' => 999, 'id_kjm' => $this->itemMateri->id_kjm, 'nilai' => 70, 'catatan' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('log_materi', [
            'id_log' => $this->ahmad->id_siswa.$this->itemMateri->id_kjm,
            'nilai' => '85',
            'catatan' => 'Perbaikan',
            'dinilai_oleh' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('log_materi', [
            'id_log' => $this->budi->id_siswa.$this->itemTugas->id_kjm,
            'id_siswa' => $this->budi->id_siswa,
            'log_type' => 2,
            'nilai' => '90',
        ]);

        $this->assertDatabaseCount('log_materi', 3);

        $this->assertDatabaseHas('log', [
            'id_user' => $this->admin->id,
            'log_desc' => 'elearning.nilai: 2 nilai materi/tugas disimpan',
        ]);

        $this->actingAs($this->admin)
            ->get('/elearning/kehadiran-harian?kelas='.$this->kelas->id_kelas.'&tanggal=2026-11-12')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/KehadiranHarian')
                ->where('rows.0.status', 'sebagian')
                ->where('rows.0.cells.'.$this->itemMateri->id_kjm, 'selesai')
                ->where('rows.0.cells.'.$this->itemTugas->id_kjm, 'kosong')
                ->where('rows.1.status', 'sebagian')
                ->where('rows.1.cells.'.$this->itemMateri->id_kjm, 'dibuka')
                ->where('rows.1.cells.'.$this->itemTugas->id_kjm, 'dibuka')
                ->where('summary.sebagian', 2)
                ->where('summary.hadir', 0));

        $this->actingAs($this->admin)
            ->get('/elearning/kehadiran-bulanan?kelas='.$this->kelas->id_kelas.'&bulan=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/KehadiranBulanan')
                ->has('days', 30)
                ->where('summary.hariEfektif', 1)
                ->where('rows.0.cells.2026-11-12.status', 'sebagian')
                ->where('rows.0.cells.2026-11-13.status', 'libur')
                ->where('rows.0.totals.sebagian', 1));

        $this->actingAs($this->admin)
            ->get('/elearning/rekap-nilai?kelas='.$this->kelas->id_kelas.'&mapel='.$this->mapel->id_mapel)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Elearning/RekapNilai')
                ->where('mapelId', $this->mapel->id_mapel)
                ->has('items', 2)
                ->where('rows.0.rata_materi', 85)
                ->where('rows.0.rata_tugas', null)
                ->where('rows.0.rata', 85)
                ->where('rows.1.rata_materi', null)
                ->where('rows.1.rata_tugas', 90)
                ->where('rows.1.rata', 90)
                ->where('summary.rata', 87.5));
    }

    public function test_guru_scope_limits_subjects_and_filters_students(): void
    {
        $otherMapel = Mapel::create(['nama_mapel' => 'Biologi', 'kode' => 'BIO', 'urutan' => 2, 'status' => 1]);
        $other = KelasMateri::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'kode_materi' => 'MT-2',
            'id_guru' => 999,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'id_mapel' => $otherMapel->id_mapel,
            'kode_mapel' => 'BIO',
            'judul_materi' => 'Materi Biologi',
            'isi_materi' => 'Isi',
            'youtube' => '',
            'jenis' => 1,
        ]);

        $this->jadwal('K'.$this->kelas->id_kelas.'-20261112-J3-T1', $other, 1, '2026-11-12 09:00:00');

        $this->actingAs($this->admin)
            ->get('/elearning/rekap-nilai?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page->has('mapels', 2));

        $this->actingAs($this->guruUser)
            ->get('/elearning/rekap-nilai?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->has('mapels', 1)
                ->where('mapels.0.id_mapel', $this->mapel->id_mapel)
                ->where('kelasId', $this->kelas->id_kelas));
    }

    public function test_students_cannot_access_elearning_reports(): void
    {
        $siswaUser = User::where('username', 'siswalaporan')->firstOrFail();

        $this->actingAs($siswaUser)->get('/elearning/nilai-harian')->assertStatus(403);
        $this->actingAs($siswaUser)->get('/elearning/kehadiran-bulanan')->assertStatus(403);
        $this->actingAs($siswaUser)->post('/elearning/nilai-harian', [])->assertStatus(403);
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

    private function kelasMateri(string $judul, int $jenis): KelasMateri
    {
        return KelasMateri::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'kode_materi' => $jenis === 2 ? 'TG-1' : 'MT-1',
            'id_guru' => $this->guru->id_guru,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'id_mapel' => $this->mapel->id_mapel,
            'kode_mapel' => 'MTK',
            'judul_materi' => $judul,
            'isi_materi' => 'Isi materi',
            'youtube' => '',
            'jenis' => $jenis,
        ]);
    }

    private function jadwal(string $idKjm, KelasMateri $materi, int $jenis, string $jadwal): KelasJadwalMateri
    {
        return KelasJadwalMateri::create([
            'id_kjm' => $idKjm,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $materi->id_materi,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => $jadwal,
            'jenis' => $jenis,
        ]);
    }

    private function siswa(string $nis, string $nama, string $username, int $kelasSiswaId): Siswa
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
            'id_kelas_siswa' => $kelasSiswaId,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);

        return $siswa;
    }
}
