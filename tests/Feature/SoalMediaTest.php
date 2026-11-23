<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoalMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_soal_menolak_pengguna_yang_belum_masuk(): void
    {
        $this->get('/media/soal/uji.png')->assertRedirect('/login');
    }

    public function test_media_soal_disajikan_setelah_login(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('soal/uji.png', 'gambar');

        $this->actingAs($this->userUji())
            ->get('/media/soal/uji.png')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_media_di_luar_folder_soal_ditolak(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profil/uji.png', 'gambar');

        $this->actingAs($this->userUji())
            ->get('/media/profil/uji.png')
            ->assertNotFound();
    }

    public function test_sanitizer_menulis_ulang_url_storage_soal(): void
    {
        $html = HtmlSanitizer::clean('<p><img src="/storage/soal/inline/a.png" alt="soal"></p>');

        $this->assertStringContainsString('/media/soal/inline/a.png', $html);
        $this->assertStringNotContainsString('/storage/soal/inline/a.png', $html);
    }

    private function userUji(): User
    {
        return User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'mediasoal',
            'password' => 'rahasia123',
            'email' => 'mediasoal@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => 'Media',
            'last_name' => 'Soal',
        ]);
    }
}
