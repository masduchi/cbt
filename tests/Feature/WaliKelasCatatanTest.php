<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\JabatanGuru;
use App\Models\Kelas;
use App\Models\KelasCatatanMapel;
use App\Models\KelasCatatanWali;
use App\Models\KelasStruktur;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaliKelasCatatanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private Guru $guru;

    private User $otherGuruUser;

    private Guru $otherGuru;

    private User $siswaUser;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    private Kelas $otherKelas;

    private Mapel $mapel;

    private Siswa $siswaA;

    private Siswa $siswaB;

    private Siswa $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('adminwali', 'admin-wali@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('guruwali', 'guru-wali@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198800001', 'nama_guru' => 'Guru Wali']);

        $this->otherGuruUser = $this->user('gurulain', 'guru-lain@example.test');
        $this->otherGuruUser->groups()->attach($guruGroup->id);
        $this->otherGuru = Guru::create(['id_user' => $this->otherGuruUser->id, 'nip' => '198800002', 'nama_guru' => 'Guru Lain']);

        $this->siswaUser = $this->user('siswawali', 'siswa-wali@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
            'guru_id' => $this->guru->id_guru,
        ]);

        $this->otherKelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 2',
            'level_id' => 10,
            'guru_id' => $this->otherGuru->id_guru,
        ]);

        $this->mapel = Mapel::create([
            'nama_mapel' => 'Matematika',
            'kode' => 'MTK',
            'urutan' => 1,
        ]);

        $this->siswaA = $this->siswa('Ahmad Fauzi', '2027001', '2027000001', $this->kelas, 1);
        $this->siswaB = $this->siswa('Budi Santoso', '2027002', '2027000002', $this->kelas, 2);
        $this->outsider = $this->siswa('Citra Lestari', '2027003', '2027000003', $this->otherKelas, 3);

        $this->jabatan($this->guru, $this->kelas);
        $this->jabatan($this->otherGuru, $this->otherKelas);
    }

    public function test_wali_can_view_and_save_class_structure(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/wali/struktur')
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Struktur')
                ->has('classes', 1)
                ->where('classes.0.nama_kelas', 'X IPA 1')
                ->where('selected.nama_kelas', 'X IPA 1')
                ->has('students', 2)
                ->has('roles', 17));

        $this->actingAs($this->guruUser)
            ->post('/wali/struktur', [
                'id_kelas' => $this->kelas->id_kelas,
                'ketua' => $this->siswaA->id_siswa,
                'wakil_ketua' => $this->siswaB->id_siswa,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = KelasStruktur::where('id_kelas', $this->kelas->id_kelas)->firstOrFail();
        $this->assertSame($this->siswaA->id_siswa, (int) $record->ketua);
        $this->assertSame($this->siswaB->id_siswa, (int) $record->wakil_ketua);

        $this->actingAs($this->guruUser)
            ->post('/wali/struktur', [
                'id_kelas' => $this->kelas->id_kelas,
                'ketua' => $this->outsider->id_siswa,
            ])
            ->assertSessionHasErrors('ketua');

        $this->actingAs($this->guruUser)
            ->post('/wali/struktur', [
                'id_kelas' => $this->otherKelas->id_kelas,
                'ketua' => $this->outsider->id_siswa,
            ])
            ->assertForbidden();
    }

    public function test_admin_sees_all_classes_in_structure_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/wali/struktur')
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Struktur')
                ->has('classes', 2));
    }

    public function test_teacher_saves_subject_notes_and_wali_notes(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/wali/catatan')
            ->assertInertia(fn (Assert $page) => $page
                ->component('WaliKelas/Catatan')
                ->has('classes', 1)
                ->has('mapels', 1)
                ->where('selectedMapel', $this->mapel->id_mapel)
                ->where('isWali', true)
                ->has('students', 2));

        $this->actingAs($this->guruUser)
            ->post('/wali/catatan/mapel', [
                'id_kelas' => $this->kelas->id_kelas,
                'id_mapel' => $this->mapel->id_mapel,
                'type' => 1,
                'level' => 2,
                'text' => 'Kelas perlu pendampingan materi pecahan.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->guruUser)
            ->post('/wali/catatan/mapel', [
                'id_kelas' => $this->kelas->id_kelas,
                'id_mapel' => $this->mapel->id_mapel,
                'type' => 2,
                'id_siswa' => $this->siswaA->id_siswa,
                'level' => 1,
                'text' => 'Tingkatkan latihan soal mandiri.',
            ])
            ->assertRedirect();

        $this->actingAs($this->guruUser)
            ->post('/wali/catatan/wali', [
                'id_kelas' => $this->kelas->id_kelas,
                'type' => 1,
                'level' => 1,
                'text' => 'Jaga kebersihan kelas.',
            ])
            ->assertRedirect();

        $kelasNote = KelasCatatanMapel::where('type', 1)->firstOrFail();
        $siswaNote = KelasCatatanMapel::where('type', 2)->firstOrFail();
        $this->assertSame($this->guru->id_guru, (int) $kelasNote->id_guru);
        $this->assertSame('2', (string) $kelasNote->level);
        $this->assertSame($this->siswaA->id_siswa, (int) $siswaNote->id_siswa);

        $this->assertDatabaseHas('kelas_catatan_wali', [
            'id_kelas' => $this->kelas->id_kelas,
            'type' => 1,
            'text' => 'Jaga kebersihan kelas.',
        ]);

        $this->actingAs($this->guruUser)
            ->get('/wali/catatan?kelas='.$this->kelas->id_kelas.'&mapel='.$this->mapel->id_mapel)
            ->assertInertia(fn (Assert $page) => $page
                ->has('catatanKelasMapel', 1)
                ->where('catatanKelasMapel.0.level_label', 'Teguran')
                ->has('catatanSiswaMapel', 1)
                ->where('catatanSiswaMapel.0.siswa', 'Ahmad Fauzi')
                ->has('catatanKelasWali', 1)
                ->has('catatanSiswaWali', 0));

        $this->actingAs($this->guruUser)
            ->delete('/wali/catatan/mapel/'.$kelasNote->id_catatan)
            ->assertRedirect();

        $this->assertDatabaseMissing('kelas_catatan_mapel', ['id_catatan' => $kelasNote->id_catatan]);
    }

    public function test_teacher_cannot_write_notes_outside_own_class(): void
    {
        $this->actingAs($this->otherGuruUser)
            ->post('/wali/catatan/mapel', [
                'id_kelas' => $this->kelas->id_kelas,
                'id_mapel' => $this->mapel->id_mapel,
                'type' => 1,
                'level' => 1,
                'text' => 'Catatan lintas kelas.',
            ])
            ->assertForbidden();

        $this->actingAs($this->otherGuruUser)
            ->post('/wali/catatan/wali', [
                'id_kelas' => $this->kelas->id_kelas,
                'type' => 1,
                'level' => 1,
                'text' => 'Catatan wali kelas lain.',
            ])
            ->assertForbidden();

        $this->actingAs($this->otherGuruUser)
            ->get('/wali/struktur?kelas='.$this->kelas->id_kelas)
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.nama_kelas', 'X IPA 2'));
    }

    public function test_admin_can_add_wali_note_to_any_class(): void
    {
        $this->actingAs($this->admin)
            ->post('/wali/catatan/wali', [
                'id_kelas' => $this->kelas->id_kelas,
                'type' => 1,
                'level' => 3,
                'text' => 'Peringatan keterlambatan.',
            ])
            ->assertRedirect();

        $this->assertSame(1, KelasCatatanWali::where('id_kelas', $this->kelas->id_kelas)->count());
    }

    public function test_student_cannot_open_wali_pages(): void
    {
        $this->actingAs($this->siswaUser)->get('/wali/struktur')->assertForbidden();
        $this->actingAs($this->siswaUser)->get('/wali/catatan')->assertForbidden();
    }

    private function jabatan(Guru $guru, Kelas $kelas): void
    {
        JabatanGuru::create([
            'id_jabatan_guru' => 'jabatan-'.$guru->id_guru,
            'id_guru' => $guru->id_guru,
            'id_jabatan' => 1,
            'id_kelas' => $kelas->id_kelas,
            'mapel_kelas' => serialize([
                [
                    'id_mapel' => $this->mapel->id_mapel,
                    'nama_mapel' => $this->mapel->nama_mapel,
                    'kelas_mapel' => [['kelas' => $kelas->id_kelas]],
                ],
            ]),
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
        ]);
    }

    private function siswa(string $nama, string $nis, string $nisn, Kelas $kelas, int $sequence): Siswa
    {
        $siswa = Siswa::create([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => $nama,
            'username' => 'wali'.$sequence,
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-wali-'.$sequence,
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => $sequence,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $siswa->id_siswa,
            'id_kelas' => $kelas->id_kelas,
        ]);

        return $siswa;
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
