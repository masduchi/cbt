<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Mapel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function roles(): array
    {
        return [
            'admin' => Group::create(['name' => 'admin', 'description' => 'Administrator']),
            'guru' => Group::create(['name' => 'guru', 'description' => 'Guru']),
            'siswa' => Group::create(['name' => 'siswa', 'description' => 'Siswa']),
        ];
    }

    private function user(string $username, Group $group, string $firstName = 'Pengguna'): User
    {
        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'first_name' => $firstName,
            'created_on' => time(),
            'active' => 1,
        ]);
        $user->groups()->attach($group->id);

        return $user;
    }

    public function test_global_search_returns_grouped_results(): void
    {
        $groups = $this->roles();
        $admin = $this->user('admincari', $groups['admin'], 'Admin');
        $this->user('budi.santoso', $groups['guru'], 'Budi Santoso');

        Mapel::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'status' => 1]);

        $mapel = $this->actingAs($admin)->getJson('/search?q=mat');
        $mapel->assertOk();
        $mapel->assertJsonPath('groups.0.label', 'Mata Pelajaran');
        $mapel->assertJsonFragment(['title' => 'Matematika']);

        $akun = $this->actingAs($admin)->getJson('/search?q=budi');
        $akun->assertOk();
        $akun->assertJsonPath('groups.0.label', 'Akun');
        $akun->assertJsonFragment(['title' => 'Budi Santoso', 'href' => '/users/guru']);
    }

    public function test_global_search_needs_two_characters_and_role_access(): void
    {
        $groups = $this->roles();
        $admin = $this->user('admincari', $groups['admin'], 'Admin');
        $siswa = $this->user('siswacari', $groups['siswa'], 'Siswa');

        $this->actingAs($admin)->getJson('/search?q=m')
            ->assertOk()
            ->assertJsonPath('groups', []);

        $this->actingAs($siswa)->getJson('/search?q=matematika')->assertForbidden();
    }

    public function test_user_listing_is_paginated_and_filterable(): void
    {
        $groups = $this->roles();
        $admin = $this->user('admincari', $groups['admin'], 'Admin');

        for ($i = 1; $i <= 17; $i++) {
            $this->user('guru'.$i, $groups['guru'], 'Guru '.$i);
        }

        $this->actingAs($admin)->get('/users/guru')->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 17)
            ->where('users.total', 17)
            ->where('filters.search', ''));

        $this->actingAs($admin)->get('/users/guru?search=Guru%2017')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.total', 1)
            ->where('filters.search', 'Guru 17'));
    }
}
