<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji asap skala: data sekelas/sekolah kecil tidak boleh memicu ledakan query.
 */
class ScaleSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const STUDENTS = 300;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'admin-skala',
            'password' => 'rahasia123',
            'first_name' => 'Admin',
            'last_name' => 'Skala',
            'active' => 1,
            'created_on' => time(),
        ]);
        $this->admin->groups()->attach($adminGroup->id);

        $tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => '10']);

        $kelas = Kelas::create([
            'id_tp' => $tahun->id_tp,
            'id_smt' => $semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $students = [];
        $members = [];

        for ($i = 1; $i <= self::STUDENTS; $i++) {
            $students[] = [
                'nisn' => '2026'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'nis' => '2026'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama' => 'Siswa Skala '.$i,
                'username' => 'siswa-skala-'.$i,
                'password' => 'rahasia123',
                'kelas_awal' => 10,
                'nik' => '',
                'warga_negara' => 'WNI',
                'uid' => 'skala-'.$i,
            ];
        }

        foreach (array_chunk($students, 100) as $chunk) {
            DB::table('master_siswa')->insert($chunk);
        }

        $ids = DB::table('master_siswa')->orderBy('id_siswa')->pluck('id_siswa');

        foreach ($ids as $index => $id) {
            $members[] = [
                'id_kelas_siswa' => $index + 1,
                'id_tp' => $tahun->id_tp,
                'id_smt' => $semester->id_smt,
                'id_siswa' => $id,
                'id_kelas' => $kelas->id_kelas,
            ];
        }

        foreach (array_chunk($members, 100) as $chunk) {
            DB::table('kelas_siswa')->insert($chunk);
        }
    }

    public function test_student_list_stays_efficient_with_hundreds_of_rows(): void
    {
        DB::enableQueryLog();

        $started = microtime(true);
        $response = $this->actingAs($this->admin)->get('/master/siswa');
        $elapsed = (microtime(true) - $started) * 1000;
        $queries = count(DB::getQueryLog());

        $response->assertOk();

        $this->assertLessThan(80, $queries, "Halaman siswa menjalankan {$queries} query untuk 300 baris.");
        $this->assertLessThan(3000, $elapsed, sprintf('Halaman siswa butuh %.0f ms untuk 300 baris.', $elapsed));
    }

    public function test_student_search_and_pagination_stay_efficient(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get('/master/siswa?search=Skala+25');
        $queries = count(DB::getQueryLog());

        $response->assertOk();
        $this->assertLessThan(80, $queries, "Pencarian siswa menjalankan {$queries} query.");
    }
}
