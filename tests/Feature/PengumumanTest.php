<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReply;
use App\Models\RunningText;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PengumumanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guruUser;

    private Guru $guru;

    private User $siswaUser;

    private Siswa $siswa;

    private TahunPelajaran $tahun;

    private Semester $semester;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = Group::create(['name' => 'admin', 'description' => 'Administrator']);
        $guruGroup = Group::create(['name' => 'guru', 'description' => 'Guru']);
        $siswaGroup = Group::create(['name' => 'siswa', 'description' => 'Siswa']);

        $this->admin = $this->user('adminpengumuman', 'admin-pengumuman@example.test');
        $this->admin->groups()->attach($adminGroup->id);

        $this->guruUser = $this->user('gurupengumuman', 'guru-pengumuman@example.test');
        $this->guruUser->groups()->attach($guruGroup->id);
        $this->guru = Guru::create(['id_user' => $this->guruUser->id, 'nip' => '198700003', 'nama_guru' => 'Guru Pengumuman']);

        $this->siswaUser = $this->user('siswapengumuman', 'siswa-pengumuman@example.test');
        $this->siswaUser->groups()->attach($siswaGroup->id);

        $this->tahun = TahunPelajaran::create(['tahun' => '2026/2027', 'active' => 1]);
        $this->semester = Semester::create(['smt' => '1', 'nama_smt' => 'Ganjil', 'active' => 1]);
        DB::table('level_kelas')->insert(['id_level' => 10, 'level' => 'X']);

        $this->kelas = Kelas::create([
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'nama_kelas' => 'X IPA 1',
            'level_id' => 10,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '0067654321',
            'nis' => '2026009',
            'nama' => 'Siti Aminah',
            'username' => 'siswapengumuman',
            'password' => 'rahasia123',
            'kelas_awal' => 10,
            'nik' => '',
            'warga_negara' => 'WNI',
            'uid' => 'uji-pengumuman',
        ]);

        DB::table('kelas_siswa')->insert([
            'id_kelas_siswa' => 1,
            'id_tp' => $this->tahun->id_tp,
            'id_smt' => $this->semester->id_smt,
            'id_siswa' => $this->siswa->id_siswa,
            'id_kelas' => $this->kelas->id_kelas,
        ]);
    }

    public function test_admin_can_send_announcement_to_class(): void
    {
        $this->actingAs($this->admin)
            ->post('/pengumuman', [
                'kepada' => ['semua_siswa', 'kelas:'.$this->kelas->id_kelas],
                'text' => 'Ujian praktik dimulai pekan depan.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $post = Post::firstOrFail();
        $this->assertSame(0, (int) $post->dari);
        $this->assertSame(1, (int) $post->dari_group);
        $this->assertSame(['semua_siswa', 'kelas:'.$this->kelas->id_kelas], unserialize($post->kepada));

        $this->actingAs($this->admin)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengumuman/Index')
                ->has('posts', 1)
                ->where('posts.0.author', 'Administrator')
                ->where('posts.0.mine', true)
                ->where('posts.0.targets.0', 'Semua Siswa')
                ->where('posts.0.targets.1', 'X IPA 1')
                ->has('posts.0.comments', 0)
                ->has('targets', 3));
    }

    public function test_teacher_sees_group_announcements_and_can_comment(): void
    {
        $this->actingAs($this->admin)->post('/pengumuman', [
            'kepada' => ['semua_guru'],
            'text' => 'Rapat guru pukul 13.00.',
        ])->assertRedirect();

        $post = Post::firstOrFail();

        $this->actingAs($this->guruUser)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.mine', false)
                ->has('posts.0.comments', 0));

        $this->actingAs($this->guruUser)
            ->post('/pengumuman/'.$post->id_post.'/komentar', ['text' => 'Siap, hadir.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $comment = PostComment::firstOrFail();
        $this->assertSame($this->guru->id_guru, (int) $comment->dari);
        $this->assertSame(2, (int) $comment->dari_group);

        $this->actingAs($this->admin)
            ->post('/pengumuman/komentar/'.$comment->id_comment.'/balasan', ['text' => 'Terima kasih.'])
            ->assertRedirect();

        $this->assertDatabaseHas('post_reply', [
            'id_comment' => $comment->id_comment,
            'dari' => 0,
            'dari_group' => 1,
            'text' => 'Terima kasih.',
        ]);

        $this->actingAs($this->guruUser)
            ->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.author', 'Administrator')
                ->where('posts.0.comments.0.author', 'Guru Pengumuman')
                ->where('posts.0.comments.0.mine', true)
                ->has('posts.0.comments.0.replies', 1)
                ->where('posts.0.comments.0.replies.0.author', 'Administrator'));

        $this->actingAs($this->guruUser)
            ->delete('/pengumuman/'.$post->id_post)
            ->assertStatus(403);
    }

    public function test_student_sees_only_targeted_announcements_and_can_reply(): void
    {
        Post::create([
            'dari' => 0,
            'dari_group' => 1,
            'kepada' => serialize(['semua_siswa']),
            'text' => 'Libur nasional hari Senin.',
            'tanggal' => now(),
            'updated' => now(),
        ]);

        $hidden = Post::create([
            'dari' => 0,
            'dari_group' => 1,
            'kepada' => serialize(['semua_guru']),
            'text' => 'Informasi internal guru.',
            'tanggal' => now(),
            'updated' => now(),
        ]);

        $this->actingAs($this->siswaUser)
            ->get('/siswa/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengumuman/Siswa')
                ->has('posts', 1)
                ->where('posts.0.text', 'Libur nasional hari Senin.')
                ->where('siswa.nama', 'Siti Aminah'));

        $post = Post::where('text', 'Libur nasional hari Senin.')->firstOrFail();

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/'.$post->id_post.'/komentar', ['text' => 'Baik, terima kasih.'])
            ->assertRedirect();

        $comment = PostComment::firstOrFail();
        $this->assertSame(3, (int) $comment->dari_group);
        $this->assertSame($this->siswa->id_siswa, (int) $comment->dari);

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/komentar/'.$comment->id_comment.'/balasan', ['text' => 'Balasan saya.'])
            ->assertRedirect();

        $this->assertDatabaseHas('post_reply', [
            'id_comment' => $comment->id_comment,
            'dari_group' => 3,
        ]);

        $this->actingAs($this->siswaUser)
            ->post('/pengumuman/'.$hidden->id_post.'/komentar', ['text' => 'Tidak boleh.'])
            ->assertStatus(403);

        $this->actingAs($this->siswaUser)->get('/pengumuman')->assertStatus(403);
    }

    public function test_deleting_announcement_removes_thread(): void
    {
        $this->actingAs($this->admin)->post('/pengumuman', [
            'kepada' => ['semua_siswa'],
            'text' => 'Informasi singkat.',
        ]);

        $post = Post::firstOrFail();
        PostComment::create(['id_post' => $post->id_post, 'dari' => 0, 'dari_group' => 1, 'text' => 'Komentar', 'type' => '1']);
        $comment = PostComment::firstOrFail();
        PostReply::create(['id_comment' => $comment->id_comment, 'dari' => 0, 'dari_group' => 1, 'text' => 'Balasan', 'type' => '1']);

        $this->actingAs($this->admin)
            ->delete('/pengumuman/'.$post->id_post)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('post', 0);
        $this->assertDatabaseCount('post_comments', 0);
        $this->assertDatabaseCount('post_reply', 0);
    }

    public function test_running_text_crud_and_shared_prop(): void
    {
        $this->actingAs($this->guruUser)->get('/setting/running-text')->assertStatus(403);

        $this->actingAs($this->admin)
            ->post('/setting/running-text', ['text' => 'Selamat datang di CBT sekolah.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $text = RunningText::firstOrFail();

        $this->actingAs($this->admin)
            ->get('/setting/running-text')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Setting/RunningText')
                ->has('texts', 1)
                ->where('texts.0.text', 'Selamat datang di CBT sekolah.'));

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('runningText.0', 'Selamat datang di CBT sekolah.'));

        $this->actingAs($this->admin)
            ->put('/setting/running-text/'.$text->id_text, ['text' => 'Teks diperbarui.'])
            ->assertRedirect();

        $this->assertSame('Teks diperbarui.', RunningText::firstOrFail()->text);

        $this->actingAs($this->admin)
            ->delete('/setting/running-text/'.$text->id_text)
            ->assertRedirect();

        $this->assertDatabaseCount('running_text', 0);
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
