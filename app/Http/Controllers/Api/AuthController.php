<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || (int) $user->active !== 1 || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        $plain = Str::random(64);

        ApiToken::create([
            'id_user' => $user->id,
            'address' => (string) $request->ip(),
            'agent' => substr((string) $request->userAgent(), 0, 500),
            'device' => $data['device'] ?? 'web',
            'token' => hash('sha256', $plain),
        ]);

        return response()->json([
            'token' => $plain,
            'token_type' => 'Bearer',
            'user' => $this->payload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Token berhasil dicabut.']);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
            'email' => $user->email,
            'role' => $user->role,
            'foto_url' => $user->photo_url,
            'last_login' => $user->last_login,
        ];
    }
}
