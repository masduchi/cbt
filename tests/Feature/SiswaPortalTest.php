<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\KelasCatatanMapel;
use App\Models\KelasCatatanWali;
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

class SiswaPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    private User $studentUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Mapel $mapel;

    private Siswa $ahmad;

    private Siswa $budi;

    private KelasCatatanMapel $mapelClassNote;

    private KelasCatatanMapel $mapelMyNote;

    private KelasCatatanMapel $mapelOtherNote;

    private KelasCatatanWali $waliMyNote;

    private KelasCatatanWali $waliOtherClassNote;

    private KelasJadwalMateri $itemMateri;

    private KelasJadwalMateri $itemTugas;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 09:00:00');

        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->guruUser = $this->user('guruportal', 'guru-portal@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);

        $this->studentUser = $this->user('siswaportal', 'siswa-portal@example.test');
        $this->studentUser->groups()->attach($siswaGroup->id);

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

        DB::table('kelas_jadwal_kbm')->insert([
            'id_kbm' => $this->tahun->id_tp.$this->semester->id_smt.$this->kelas->id_kelas,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_kelas' => $this->kelas->id_kelas,
            'kbm_jam_pel' => 40,
            'kbm_jam_mulai' => '07:00',
            'kbm_jml_mapel_hari' => 3,
            'istirahat' => serialize([['ist' => 2, 'dur' => 20]]),
        ]);

        DB::table('kelas_jadwal_mapel')->insert([
            ['id_jadwal' => 1, 'id_tp' => $this->tahun->id_tp, 'id_smt' => $this->semester->id_smt, 'id_kelas' => $this->kelas->id_kelas, 'id_hari' => 1, 'jam_ke' => 1, 'id_mapel' => $this->mapel->id_mapel],
            ['id_jadwal' => 2, 'id_tp' => $this->tahun->id_tp, 'id_smt' => $this->semester->id_smt, 'id_kelas' => $this->kelas->id_kelas, 'id_hari' => 4, 'jam_ke' => 2, 'id_mapel' => $this->mapel->id_mapel],
        ]);

        $this->ahmad = $this->siswa('2026001', 'Ahmad Fauzi', 'siswaportal', 1, 10);
        $this->budi = $this->siswa('2026002', 'Budi Santoso', 'budiportal', 2, 20);

        $materi = KelasMateri::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'kode_materi' => 'MT-1',
            'id_guru' => 0,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'id_mapel' => $this->mapel->id_mapel,
            'kode_mapel' => 'MTK',
            'judul_materi' => 'Materi Portal',
            'isi_materi' => 'Isi',
            'youtube' => '',
            'jenis' => 1,
            'status' => 1,
        ]);

        $tugas = KelasMateri::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'kode_materi' => 'TG-1',
            'id_guru' => 0,
            'materi_kelas' => serialize([$this->kelas->id_kelas]),
            'id_mapel' => $this->mapel->id_mapel,
            'kode_mapel' => 'MTK',
            'judul_materi' => 'Tugas Portal',
            'isi_materi' => 'Isi',
            'youtube' => '',
            'jenis' => 2,
            'status' => 1,
        ]);

        $this->itemMateri = $this->jadwal('20261112-J1-T1', (int) $materi->id_materi, 1, '2026-11-12 07:00:00');
        $this->itemTugas = $this->jadwal('20261112-J2-T2', (int) $tugas->id_materi, 2, '2026-11-12 08:00:00');

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
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'Web',
        ]);

        LogMateri::create([
            'id_log' => $this->ahmad->id_siswa.$this->itemTugas->id_kjm,
            'log_time' => '2026-11-12 08:05:00',
            'id_siswa' => $this->ahmad->id_siswa,
            'jam_ke' => 2,
            'id_materi' => $this->itemTugas->id_kjm,
            'id_mapel' => $this->mapel->id_mapel,
            'log_type' => 1,
            'log_desc' => 'Membuka tugas',
            'address' => '127.0.0.1',
            'agent' => 'phpunit',
            'device' => 'Web',
        ]);

        $this->mapelClassNote = $this->catatanMapel(1, null, 'Catatan untuk seluruh kelas');
        $this->mapelMyNote = $this->catatanMapel(2, $this->ahmad->id_siswa, 'Catatan khusus Ahmad');
        $this->mapelOtherNote = $this->catatanMapel(2, $this->budi->id_siswa, 'Catatan untuk Budi');

        $this->waliMyNote = KelasCatatanWali::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'type' => 2,
            'level' => '2',
            'tgl' => '2026-11-11 10:00:00',
            'id_siswa' => $this->ahmad->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
            'text' => 'Tingkatkan ketepatan waktu.',
            'readed' => '0',
            'reading' => serialize([]),
            'jml' => 0,
        ]);

        $otherKelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
        ]);

        $this->waliOtherClassNote = KelasCatatanWali::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'type' => 1,
            'level' => '1',
            'tgl' => '2026-11-10 10:00:00',
            'id_siswa' => null,
            'id_kelas' => $otherKelas->id_kelas,
            'text' => 'Catatan kelas lain.',
            'readed' => '0',
            'reading' => serialize([]),
            'jml' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_can_view_schedule_with_time_slots(): void
    {
        $this->actingAs($this->studentUser)
            ->get('/siswa/jadwal')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Siswa/Jadwal')
                ->where('kelasId', $this->kelas->id_kelas)
                ->has('days', 6)
                ->has('slots', 3)
                ->where('slots.0.jam', 1)
                ->where('slots.0.mulai', '07:00')
                ->where('slots.0.selesai', '07:40')
                ->where('slots.0.mapel.1.nama_mapel', 'Matematika')
                ->where('slots.0.mapel.4', null)
                ->where('slots.1.mapel.4.nama_mapel', 'Matematika')
                ->where('slots.1.istirahat.durasi', 20)
                ->where('student.nama', 'Ahmad Fauzi'));
    }

    public function test_attendance_month_is_derived_from_student_logs(): void
    {
        $this->actingAs($this->studentUser)
            ->get('/siswa/kehadiran?bulan=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Siswa/Kehadiran')
                ->has('days', 30)
                ->where('days.11.status', 'sebagian')
                ->where('days.11.selesai', 1)
                ->where('days.11.total', 2)
                ->where('days.10.status', 'libur')
                ->where('totals.sebagian', 1)
                ->where('hariEfektif', 1)
                ->has('today.items', 2)
                ->where('today.items.0.status', 'selesai')
                ->where('today.items.1.status', 'dibuka'));
    }

    public function test_student_sees_only_own_and_class_notes(): void
    {
        $this->actingAs($this->studentUser)
            ->get('/siswa/catatan')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Siswa/Catatan')
                ->has('notes', 3)
                ->where('summary.total', 3)
                ->where('summary.belum', 3)
                ->where('summary.mapel', 2)
                ->where('summary.wali', 1)
                ->where('notes.2.text', 'Tingkatkan ketepatan waktu.'));
    }

    public function test_student_can_mark_class_and_personal_notes_as_read(): void
    {
        $this->actingAs($this->studentUser)
            ->post('/siswa/catatan/mapel/'.$this->mapelClassNote->id_catatan)
            ->assertRedirect()
            ->assertSessionHas('success');

        $classNote = $this->mapelClassNote->fresh();
        $reading = unserialize($classNote->reading);
        $this->assertContains((int) $this->ahmad->id_siswa, array_map('intval', $reading));

        $this->actingAs($this->studentUser)
            ->post('/siswa/catatan/wali/'.$this->waliMyNote->id_catatan)
            ->assertRedirect();

        $this->assertNotSame('0', $this->waliMyNote->fresh()->readed);

        $response = $this->actingAs($this->studentUser)->get('/siswa/catatan');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('notes.0.read', false)
            ->where('notes.1.read', true)
            ->where('notes.2.read', true)
            ->where('summary.belum', 1));
    }

    public function test_student_cannot_mark_other_students_or_other_classes_notes(): void
    {
        $this->actingAs($this->studentUser)
            ->post('/siswa/catatan/mapel/'.$this->mapelOtherNote->id_catatan)
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->post('/siswa/catatan/wali/'.$this->waliOtherClassNote->id_catatan)
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->post('/siswa/catatan/lain/1')
            ->assertNotFound();
    }

    public function test_non_students_cannot_open_student_portal(): void
    {
        $this->actingAs($this->guruUser)->get('/siswa/jadwal')->assertStatus(403);
        $this->actingAs($this->guruUser)->get('/siswa/kehadiran')->assertStatus(403);
        $this->actingAs($this->guruUser)->get('/siswa/catatan')->assertStatus(403);
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

    private function catatanMapel(int $type, ?int $idSiswa, string $text): KelasCatatanMapel
    {
        return KelasCatatanMapel::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'type' => $type,
            'id_siswa' => $idSiswa,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'id_guru' => null,
            'level' => '1',
            'tgl' => '2026-11-12 06:00:00',
            'text' => $text,
            'readed' => '0',
            'reading' => serialize([]),
            'jml' => 0,
        ]);
    }

    private function jadwal(string $suffix, int $idMateri, int $jenis, string $date): KelasJadwalMateri
    {
        return KelasJadwalMateri::create([
            'id_kjm' => 'K'.$this->kelas->id_kelas.'-'.$suffix,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_materi' => $idMateri,
            'id_mapel' => $this->mapel->id_mapel,
            'id_kelas' => $this->kelas->id_kelas,
            'jadwal_materi' => $date,
            'jenis' => $jenis,
        ]);
    }

    private function siswa(string $nis, string $nama, string $username, int $pivotId, int $level): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => '006'.substr($nis, -7),
            'nis' => $nis,
            'nama' => $nama,
            'username' => $username,
            'password' => 'rahasia123',
            'kelas_awal' => $level,
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
}
