<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['message' => 'Token API tidak disediakan.'], 401);
        }

        $token = ApiToken::where('token', hash('sha256', $plain))->first();

        if (! $token) {
            return response()->json(['message' => 'Token API tidak valid.'], 401);
        }

        $user = User::find($token->id_user);

        if (! $user || (int) $user->active !== 1) {
            $token->delete();

            return response()->json(['message' => 'Akun untuk token ini tidak aktif.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
