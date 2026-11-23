<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EksporSiswaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $group = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $this->admin = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'admin',
            'password' => 'rahasia123',
            'email' => 'admin@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => 'admin',
            'last_name' => '',
        ]);
        $this->admin->groups()->attach($group->id);

        TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        Siswa::create([
            'nisn' => '2029000101',
            'nis' => '202900101',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'username' => 'budi',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '3201010101010001',
            'agama' => 'Islam',
            'alamat' => 'Jl. Merdeka 1',
            'warga_negara' => 'WNI',
            'uid' => 'uid-budi',
        ]);
    }

    public function test_ekspor_dapodik_memakai_kolom_standar(): void
    {
        $sheet = $this->unduh('/master/siswa/ekspor/dapodik');

        $this->assertSame('peserta_didik_id', $sheet->getCell('A1')->getValue());
        $this->assertSame('nama', $sheet->getCell('B1')->getValue());
        $this->assertSame('uid-budi', $sheet->getCell('A2')->getValue());
        $this->assertSame('Budi Santoso', $sheet->getCell('B2')->getValue());
        $this->assertSame('2026/2027', $sheet->getCell('X2')->getValue());
    }

    public function test_ekspor_emis_memakai_kolom_standar(): void
    {
        $sheet = $this->unduh('/master/siswa/ekspor/emis');

        $this->assertSame('NISN', $sheet->getCell('A1')->getValue());
        $this->assertSame('Nama Siswa', $sheet->getCell('C1')->getValue());
        $this->assertSame('2029000101', (string) $sheet->getCell('A2')->getValue());
        $this->assertSame('Budi Santoso', $sheet->getCell('C2')->getValue());
        $this->assertSame('Ganjil', $sheet->getCell('X2')->getValue());
    }

    private function unduh(string $url)
    {
        $response = $this->actingAs($this->admin)->get($url)->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'siswa').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }
}
