<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username, Group $group): User
    {
        $user = User::create([
            'ip_address' => '127.0.0.1',
            'username' => $username,
            'password' => 'rahasia123',
            'email' => $username.'@example.test',
            'first_name' => ucfirst($username),
            'created_on' => time(),
            'active' => 1,
        ]);
        $user->groups()->attach($group->id);

        return $user;
    }

    public function test_dashboard_renders_analytics_for_staff_and_skips_for_students(): void
    {
        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);

        $admin = $this->user('adminberanda', $adminGroup);
        $guru = $this->user('guruberanda', $guruGroup);
        Guru::create(['id_user' => $guru->id, 'nip' => '198700001', 'nama_guru' => 'Guru Beranda']);
        $siswa = $this->user('siswaberanda', $siswaGroup);

        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('analytics')
            ->has('analytics.chart', 0)
            ->where('analytics.selesai', 0)
            ->has('reminders'));

        $this->actingAs($guru)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('analytics')
            ->where('stats.siswa', 0)
            ->has('reminders'));

        $this->actingAs($siswa)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('analytics', null)
            ->has('reminders', 0));
    }
}
