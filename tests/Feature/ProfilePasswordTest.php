<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $mustChange = false): User
    {
        $group = Group::create(['name' => 'admin', 'description' => 'Administrator']);

        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => 'ujicoba',
            'password' => 'rahasia123',
            'email' => 'uji@example.test',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => $mustChange,
        ]);

        $user->groups()->attach($group->id);

        return $user;
    }

    public function test_profile_page_is_available(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/profil');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profil/Index')
            ->where('profile.username', 'ujicoba')
            ->where('mustChange', false));
    }

    public function test_user_with_default_password_is_forced_to_profile(): void
    {
        $user = $this->makeUser(true);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/profil');
    }

    public function test_password_can_be_changed_and_flag_is_cleared(): void
    {
        $user = $this->makeUser(true);

        $response = $this->actingAs($user)->post('/profil/password', [
            'current_password' => 'rahasia123',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('passwordbaru123', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/profil/password', [
            'current_password' => 'salah',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('rahasia123', $user->refresh()->password));
    }
}
