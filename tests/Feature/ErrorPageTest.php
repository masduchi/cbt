<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);

        $this->guru = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'guru-error',
            'password' => 'rahasia123',
            'email' => 'guru-error@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => 'Guru',
            'last_name' => 'Error',
        ]);

        $this->guru->groups()->attach($guruGroup->id);
    }

    public function test_missing_page_uses_the_application_error_page(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404));
    }

    public function test_forbidden_page_uses_the_application_error_page(): void
    {
        $this->actingAs($this->guru)
            ->get('/master/buku-induk')
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403));
    }

    public function test_expired_session_uses_the_application_error_page(): void
    {
        Route::middleware('web')->get('/uji-419', function (): never {
            throw new TokenMismatchException;
        });

        $this->get('/uji-419')
            ->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 419));
    }

    public function test_server_error_uses_the_application_error_page_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/uji-500', function (): never {
            throw new RuntimeException('Uji halaman error.');
        });

        $this->get('/uji-500')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 500));
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/halaman-yang-tidak-ada')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_requests_that_expect_inertia_json_keep_their_own_error_handling(): void
    {
        $response = $this->get('/halaman-yang-tidak-ada', ['X-Inertia' => 'true']);

        $response->assertStatus(404);
        $this->assertNull($response->headers->get('X-Inertia'));
        $this->assertStringNotContainsString('Kode 404', (string) $response->getContent());
    }
}
