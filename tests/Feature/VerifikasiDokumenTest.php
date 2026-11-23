<?php

namespace Tests\Feature;

use App\Support\QrCode;
use App\Support\VerifikasiDokumen;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerifikasiDokumenTest extends TestCase
{
    use RefreshDatabase;

    public function test_kode_dokumen_stabil_dan_halaman_verifikasi_menampilkan_ringkasan(): void
    {
        $kode = VerifikasiDokumen::terbitkan('rapor', '7-8', ['siswa' => 'Siswa A']);

        $this->assertSame($kode, VerifikasiDokumen::terbitkan('rapor', '7-8', ['siswa' => 'Siswa A']));
        $this->assertSame(1, DB::table('dokumen_verifikasi')->count());

        $this->get('/verifikasi/'.$kode)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Verifikasi')
                ->where('valid', true)
                ->where('jenis', 'rapor')
                ->where('ringkasan.siswa', 'Siswa A'));
    }

    public function test_kode_tidak_dikenal_dan_kode_dicabut_ditandai_tidak_berlaku(): void
    {
        $this->get('/verifikasi/TIDAKADA')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));

        $kode = VerifikasiDokumen::terbitkan('kartu-peserta', '1');
        DB::table('dokumen_verifikasi')->where('kode', $kode)->update(['revoked_at' => now()]);

        $this->get('/verifikasi/'.$kode)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));
    }

    public function test_indeks_unik_menolak_dua_kode_aktif_untuk_jenis_dan_ref_sama(): void
    {
        $kode = VerifikasiDokumen::terbitkan('rapor', '7-8', ['siswa' => 'Siswa A']);

        $this->assertSame('rapor|7-8', DB::table('dokumen_verifikasi')->where('kode', $kode)->value('kunci_aktif'));

        $this->expectException(QueryException::class);

        DB::table('dokumen_verifikasi')->insert([
            'kode' => 'KODEPALSU1',
            'jenis' => 'rapor',
            'ref' => '7-8',
            'kunci_aktif' => 'rapor|7-8',
            'ringkasan' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_kode_dicabut_tanpa_membebaskan_kunci_tetap_bisa_diganti(): void
    {
        $lama = VerifikasiDokumen::terbitkan('buku-induk', '42', ['siswa' => 'Siswa A']);

        // Meniru pencabutan langsung di database yang tidak membersihkan kunci.
        DB::table('dokumen_verifikasi')->where('kode', $lama)->update(['revoked_at' => now()]);

        $baru = VerifikasiDokumen::terbitkan('buku-induk', '42', ['siswa' => 'Siswa A']);

        $this->assertNotSame($lama, $baru);
        $this->assertNull(DB::table('dokumen_verifikasi')->where('kode', $lama)->value('kunci_aktif'));
        $this->assertSame('buku-induk|42', DB::table('dokumen_verifikasi')->where('kode', $baru)->value('kunci_aktif'));
        $this->assertSame(1, DB::table('dokumen_verifikasi')
            ->where('jenis', 'buku-induk')
            ->where('ref', '42')
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_qr_verifikasi_dihasilkan_sebagai_svg(): void
    {
        $svg = QrCode::svg(VerifikasiDokumen::url('ABCDEFGHJK'), 2, 2);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<path d="M', $svg);
        $this->assertStringContainsString('viewBox="0 0 ', $svg);
    }

    public function test_qr_verifikasi_dihasilkan_sebagai_png_untuk_pdf(): void
    {
        $url = VerifikasiDokumen::url('ABCDEFGHJK');
        $png = QrCode::png($url, 4, 4);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image);
        $this->assertSame((count(QrCode::matrix($url)) + 8) * 4, imagesx($image));
        $this->assertSame(0xFFFFFF, imagecolorat($image, 0, 0) & 0xFFFFFF, 'Zona sunyi QR harus putih.');
        $this->assertSame(0x000000, imagecolorat($image, 4 * 4, 4 * 4) & 0xFFFFFF, 'Modul gelap QR harus tercetak.');
    }
}
