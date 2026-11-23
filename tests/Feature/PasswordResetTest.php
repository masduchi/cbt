<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $this->user = $this->user('adminreset', 'admin-reset@example.test');
        $this->user->groups()->attach($adminGroup->id);
    }

    public function test_guest_can_open_forgot_and_reset_pages(): void
    {
        $this->get('/lupa-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));

        $this->get('/reset-password/token-abc?email=admin-reset@example.test')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'token-abc')
                ->where('email', 'admin-reset@example.test'));
    }

    public function test_reset_link_is_sent_for_registered_email(): void
    {
        Notification::fake();

        $this->post('/lupa-password', ['email' => 'admin-reset@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($this->user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'admin-reset@example.test']);
    }

    public function test_unknown_email_gets_generic_message_without_sending_mail(): void
    {
        Notification::fake();

        $this->post('/lupa-password', ['email' => 'tidak-ada@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Jika email terdaftar, tautan reset password telah dikirim. Periksa kotak masuk Anda.');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_valid_token_resets_password_and_allows_login(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();
        $token = Password::broker()->createToken($this->user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin-reset@example.test',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('PasswordBaru123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->post('/login', [
            'identity' => 'adminreset',
            'password' => 'PasswordBaru123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->post('/reset-password', [
            'token' => 'token-salah',
            'email' => 'admin-reset@example.test',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('rahasia123', $this->user->fresh()->password));
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
