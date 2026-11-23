<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KelasSiswa;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReply;
use App\Models\Siswa;
use App\Support\AuditLog;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PengumumanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Post::query();

        if (! $user->isAdmin()) {
            $guruId = $this->guruId($user);

            abort_unless($guruId, 403, 'Akun ini tidak terhubung dengan data guru.');

            $query->where(function ($inner) use ($guruId) {
                $inner->where(fn ($own) => $own->where('dari_group', 2)->where('dari', $guruId))
                    ->orWhere('kepada', 'like', '%semua_guru%');
            });
        }

        $posts = $query->orderByDesc('updated')->orderByDesc('id_post')->get();

        return Inertia::render('Pengumuman/Index', [
            'posts' => $this->payload($posts, $user),
            'targets' => $this->targets(),
            'periode' => $this->periode(),
        ]);
    }

    public function siswaIndex(Request $request): Response
    {
        $siswa = $this->student($request);
        $classIds = $this->classIds($siswa->id_siswa);

        $posts = Post::orderByDesc('updated')->orderByDesc('id_post')->get()
            ->filter(fn (Post $post) => $this->siswaCanView($post, $classIds))
            ->values();

        return Inertia::render('Pengumuman/Siswa', [
            'posts' => $this->payload($posts, $request->user(), $siswa),
            'siswa' => ['nama' => $siswa->nama, 'kelas' => $this->classLabel($classIds)],
            'periode' => $this->periode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kepada' => ['required', 'array', 'min:1'],
            'kepada.*' => ['string', 'max:50', 'regex:/^(semua_guru|semua_siswa|kelas:\d+)$/'],
            'text' => ['required', 'string', 'max:10000'],
        ]);

        $targets = collect($data['kepada'])->unique()->values();
        $classIds = $targets->filter(fn ($target) => str_starts_with($target, 'kelas:'))
            ->map(fn ($target) => (int) substr($target, 6))
            ->unique();

        if ($classIds->isNotEmpty() && Kelas::whereIn('id_kelas', $classIds)->count() !== $classIds->count()) {
            throw ValidationException::withMessages(['kepada' => 'Kelas tujuan tidak ditemukan.']);
        }

        [$dari, $group] = $this->identity($request);

        $post = Post::create([
            'dari' => $dari,
            'dari_group' => $group,
            'kepada' => serialize($targets->all()),
            'text' => trim($data['text']),
            'tanggal' => now(),
            'updated' => now(),
        ]);

        AuditLog::record('pengumuman.store', 'Pengumuman #'.$post->id_post.' → '.implode(', ', $targets->all()));

        return back()->with('success', 'Pengumuman berhasil dikirim.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->ensureAuthor($request, $post->dari, (int) $post->dari_group);

        DB::transaction(function () use ($post) {
            $commentIds = PostComment::where('id_post', $post->id_post)->pluck('id_comment');

            if ($commentIds->isNotEmpty()) {
                PostReply::whereIn('id_comment', $commentIds)->delete();
            }

            PostComment::where('id_post', $post->id_post)->delete();
            $post->delete();
        });

        AuditLog::record('pengumuman.destroy', 'Pengumuman #'.$post->id_post.' dihapus');

        return back()->with('success', 'Pengumuman dihapus.');
    }

    public function comment(Request $request, Post $post): RedirectResponse
    {
        abort_unless($this->canView($post, $request->user()), 403);

        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        [$dari, $group] = $this->identity($request);

        PostComment::create([
            'id_post' => $post->id_post,
            'dari' => $dari,
            'dari_group' => $group,
            'text' => trim($data['text']),
            'type' => '1',
        ]);

        $post->update(['updated' => now()]);

        AuditLog::record('pengumuman.comment', 'Pengumuman #'.$post->id_post.' dikomentari');

        return back()->with('success', 'Komentar dikirim.');
    }

    public function destroyComment(Request $request, PostComment $comment): RedirectResponse
    {
        $this->ensureAuthor($request, $comment->dari, (int) $comment->dari_group);

        DB::transaction(function () use ($comment) {
            PostReply::where('id_comment', $comment->id_comment)->delete();
            $comment->delete();
        });

        AuditLog::record('pengumuman.comment.delete', 'Komentar #'.$comment->id_comment.' dihapus');

        return back()->with('success', 'Komentar dihapus.');
    }

    public function reply(Request $request, PostComment $comment): RedirectResponse
    {
        $post = Post::find($comment->id_post);
        abort_unless($post && $this->canView($post, $request->user()), 403);

        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        [$dari, $group] = $this->identity($request);

        PostReply::create([
            'id_comment' => $comment->id_comment,
            'dari' => $dari,
            'dari_group' => $group,
            'text' => trim($data['text']),
            'type' => '1',
        ]);

        AuditLog::record('pengumuman.reply', 'Komentar #'.$comment->id_comment.' dibalas');

        return back()->with('success', 'Balasan dikirim.');
    }

    public function destroyReply(Request $request, PostReply $reply): RedirectResponse
    {
        $this->ensureAuthor($request, $reply->dari, (int) $reply->dari_group);
        AuditLog::record('pengumuman.reply.delete', 'Balasan #'.$reply->id_reply.' dihapus');

        $reply->delete();

        return back()->with('success', 'Balasan dihapus.');
    }

    private function payload(Collection $posts, $user, ?Siswa $siswa = null): Collection
    {
        $comments = PostComment::whereIn('id_post', $posts->pluck('id_post'))
            ->orderBy('tanggal')
            ->orderBy('id_comment')
            ->get();

        $replies = PostReply::whereIn('id_comment', $comments->pluck('id_comment'))
            ->orderBy('tanggal')
            ->orderBy('id_reply')
            ->get();

        [$gurus, $siswas] = $this->names($posts->concat($comments)->concat($replies));
        [$identity, $group] = $this->identityOf($user, $siswa);
        $classNames = $this->classNames($posts);

        $repliesByComment = $replies->groupBy('id_comment');
        $commentsByPost = $comments->groupBy('id_post');

        return $posts->map(function (Post $post) use ($commentsByPost, $repliesByComment, $gurus, $siswas, $identity, $group, $classNames) {
            return [
                'id_post' => (int) $post->id_post,
                'text' => (string) $post->text,
                'tanggal' => $this->dateLabel($post->tanggal),
                'author' => $this->name($post->dari, (int) $post->dari_group, $gurus, $siswas),
                'mine' => (int) $post->dari === $identity && (int) $post->dari_group === $group,
                'targets' => $this->targetLabels($post, $classNames),
                'comments' => $commentsByPost->get($post->id_post, collect())->map(function (PostComment $comment) use ($repliesByComment, $gurus, $siswas, $identity, $group) {
                    return [
                        'id_comment' => (int) $comment->id_comment,
                        'text' => (string) $comment->text,
                        'tanggal' => $this->dateLabel($comment->tanggal),
                        'author' => $this->name($comment->dari, (int) $comment->dari_group, $gurus, $siswas),
                        'mine' => (int) $comment->dari === $identity && (int) $comment->dari_group === $group,
                        'replies' => $repliesByComment->get($comment->id_comment, collect())->map(function (PostReply $reply) use ($gurus, $siswas, $identity, $group) {
                            return [
                                'id_reply' => (int) $reply->id_reply,
                                'text' => (string) $reply->text,
                                'tanggal' => $this->dateLabel($reply->tanggal),
                                'author' => $this->name($reply->dari, (int) $reply->dari_group, $gurus, $siswas),
                                'mine' => (int) $reply->dari === $identity && (int) $reply->dari_group === $group,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();
    }

    private function targets(): array
    {
        $classes = Kelas::where('id_tp', Period::current()[0]?->id_tp)
            ->where('id_smt', Period::current()[1]?->id_smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas'])
            ->map(fn (Kelas $kelas) => ['value' => 'kelas:'.$kelas->id_kelas, 'label' => $kelas->nama_kelas])
            ->all();

        return [
            ['value' => 'semua_guru', 'label' => 'Semua Guru'],
            ['value' => 'semua_siswa', 'label' => 'Semua Siswa'],
            ...$classes,
        ];
    }

    private function targetLabels(Post $post, array $classNames): array
    {
        return collect($this->targetValues($post))
            ->map(function (string $target) use ($classNames) {
                if ($target === 'semua_guru') {
                    return 'Semua Guru';
                }

                if ($target === 'semua_siswa') {
                    return 'Semua Siswa';
                }

                if (str_starts_with($target, 'kelas:')) {
                    $id = (int) substr($target, 6);

                    return $classNames[$id] ?? 'Kelas '.$id;
                }

                return $target;
            })
            ->values()
            ->all();
    }

    private function targetValues(Post $post): array
    {
        $value = @unserialize((string) $post->kepada, ['allowed_classes' => false]);

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn ($item) => $item !== ''));
        }

        return $post->kepada ? [(string) $post->kepada] : [];
    }

    private function classNames(Collection $posts): array
    {
        $ids = $posts->flatMap(fn (Post $post) => $this->targetValues($post))
            ->filter(fn ($target) => str_starts_with($target, 'kelas:'))
            ->map(fn ($target) => (int) substr($target, 6))
            ->unique();

        return $ids->isEmpty() ? [] : Kelas::whereIn('id_kelas', $ids)->pluck('nama_kelas', 'id_kelas')->all();
    }

    private function names(Collection $items): array
    {
        $guruIds = $items->filter(fn ($item) => (int) $item->dari_group === 2)->pluck('dari')->filter()->unique()->all();
        $siswaIds = $items->filter(fn ($item) => (int) $item->dari_group === 3)->pluck('dari')->filter()->unique()->all();

        return [
            $guruIds ? Guru::whereIn('id_guru', $guruIds)->pluck('nama_guru', 'id_guru')->all() : [],
            $siswaIds ? Siswa::whereIn('id_siswa', $siswaIds)->pluck('nama', 'id_siswa')->all() : [],
        ];
    }

    private function name($dari, int $group, array $gurus, array $siswas): string
    {
        if ($group === 1 || ! $dari) {
            return 'Administrator';
        }

        return $group === 2
            ? ($gurus[(int) $dari] ?? 'Guru')
            : ($siswas[(int) $dari] ?? 'Siswa');
    }

    private function identity(Request $request): array
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return [0, 1];
        }

        if ($user->isGuru()) {
            $guruId = $this->guruId($user);
            abort_unless($guruId, 403, 'Akun ini tidak terhubung dengan data guru.');

            return [(int) $guruId, 2];
        }

        $siswa = $this->student($request);

        return [(int) $siswa->id_siswa, 3];
    }

    private function identityOf($user, ?Siswa $siswa): array
    {
        if ($user->isAdmin()) {
            return [0, 1];
        }

        if ($user->isGuru()) {
            return [(int) ($this->guruId($user) ?? 0), 2];
        }

        return [(int) ($siswa?->id_siswa ?? 0), 3];
    }

    private function ensureAuthor(Request $request, $dari, int $group): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        [$identity, $identityGroup] = $this->identity($request);

        abort_unless((int) $dari === $identity && $group === $identityGroup, 403);
    }

    private function canView(Post $post, $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $targets = $this->targetValues($post);

        if ($user->isGuru()) {
            $guruId = $this->guruId($user);

            return in_array('semua_guru', $targets, true)
                || ((int) $post->dari_group === 2 && (int) $post->dari === (int) $guruId);
        }

        return $this->siswaCanView($post, $this->classIds($this->studentFor($user)->id_siswa));
    }

    private function siswaCanView(Post $post, array $classIds): bool
    {
        $targets = $this->targetValues($post);

        if (in_array('semua_siswa', $targets, true)) {
            return true;
        }

        foreach ($classIds as $classId) {
            if (in_array('kelas:'.$classId, $targets, true)) {
                return true;
            }
        }

        return false;
    }

    private function student(Request $request): Siswa
    {
        return $this->studentFor($request->user());
    }

    private function studentFor($user): Siswa
    {
        $siswa = Siswa::where('username', $user->username)->first();

        abort_unless($siswa, 403, 'Akun ini tidak terhubung dengan data siswa.');

        return $siswa;
    }

    private function classIds(int $siswaId): array
    {
        [$tahun, $semester] = Period::current();

        return KelasSiswa::where('id_siswa', $siswaId)
            ->where('id_tp', $tahun?->id_tp)
            ->where('id_smt', $semester?->id_smt)
            ->pluck('id_kelas')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function classLabel(array $classIds): ?string
    {
        return $classIds ? Kelas::whereIn('id_kelas', $classIds)->orderBy('nama_kelas')->pluck('nama_kelas')->implode(', ') : null;
    }

    private function guruId($user): ?int
    {
        return Guru::where('id_user', $user->id)->value('id_guru');
    }

    private function dateLabel(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function periode(): string
    {
        [$tahun, $semester] = Period::current();

        return trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-'));
    }
}
