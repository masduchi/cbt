<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class NotifikasiController extends Controller
{
    /**
     * Pusat notifikasi in-app siswa dari kanal database.
     */
    public function index(Request $request): Response
    {
        $siswa = $this->student($request);

        $rows = $siswa->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification) => [
                'id' => (string) $notification->id,
                'ujian' => (string) ($notification->data['nama_ujian'] ?? 'Ujian'),
                'jenis_ujian' => (string) ($notification->data['jenis_ujian'] ?? ''),
                'nilai_akhir' => $notification->data['nilai_akhir'] ?? null,
                'waktu' => $notification->created_at
                    ? Carbon::parse($notification->created_at)->isoFormat('D MMMM YYYY, HH:mm')
                    : '-',
                'dibaca' => $notification->read_at !== null,
            ]);

        return Inertia::render('Siswa/Notifikasi', [
            'student' => [
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
            ],
            'notifikasi' => $rows,
            'ringkasan' => [
                'total' => $siswa->notifications()->count(),
                'belum' => $siswa->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Notifikasi dicari lewat relasi milik siswa yang login, sehingga ID milik
     * siswa lain berakhir 404 dan tidak bisa ditandai dibaca.
     */
    public function baca(Request $request, string $notifikasi): RedirectResponse
    {
        $siswa = $this->student($request);

        $siswa->notifications()->whereKey($notifikasi)->firstOrFail()->markAsRead();

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $siswa = $this->student($request);

        $siswa->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function student(Request $request): Siswa
    {
        $siswa = Siswa::where('username', $request->user()->username)->first();

        abort_unless($siswa, 403, 'Akun ini tidak terhubung dengan data siswa.');

        return $siswa;
    }
}
