<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Token;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function index(): Response
    {
        $token = Token::orderBy('id_token')->first();
        $updated = $token?->updatedAt();
        $expires = $token?->expiresAt();

        return Inertia::render('Cbt/Token', [
            'token' => [
                'token' => $token?->token ?? '',
                'auto' => (int) ($token?->auto ?? 0),
                'jarak' => (int) ($token?->jarak ?? 1),
                'updated' => $updated?->format('Y-m-d H:i:s'),
                'updated_iso' => $updated?->toIso8601String(),
                'expires' => $expires?->format('Y-m-d H:i:s'),
                'kedaluwarsa' => (bool) $token?->kedaluwarsa(),
            ],
            'isAdmin' => request()->user()->isAdmin(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'force' => ['nullable', 'boolean'],
            'auto' => ['nullable', 'boolean'],
            'jarak' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $token = Token::orderBy('id_token')->first();

        if (! $token) {
            $token = new Token;
            $token->token = '';
            $token->auto = 0;
            $token->jarak = 1;
            $token->updated = '';
        }

        $token->auto = (int) ($data['auto'] ?? $token->auto);
        $token->jarak = (int) ($data['jarak'] ?? $token->jarak);

        $updated = $token->updatedAt();
        $elapsed = $updated ? abs(now()->diffInMinutes($updated)) : PHP_INT_MAX;

        $dibuat = $request->boolean('force') || $elapsed >= $token->jarak;

        if ($dibuat) {
            $token->token = $this->newToken();
            $token->updated = now()->format('Y-m-d H:i:s');
        }

        $token->save();

        AuditLog::record('cbt.token', $dibuat
            ? 'Token baru dibuat (jarak '.$token->jarak.' menit)'
            : 'Pengaturan token disimpan tanpa membuat token baru');

        return back()->with('success', 'Token diperbarui.');
    }

    private function newToken(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';

        for ($i = 0; $i < 6; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $token;
    }
}
