<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    private const BATAS_PERCOBAAN_IP = 5;

    private const BATAS_PERCOBAAN_AKUN = 20;

    private const JENDELA_MENIT = 10;

    public function show(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Spasi tak sengaja (salin-tempel) tidak boleh membuat login gagal.
        $identity = trim($credentials['identity']);
        $kunciLogin = Str::lower($identity);

        // Dua ambang: batas rendah per akun+IP supaya satu perangkat tidak bisa
        // menebak terus, dan batas tinggi per akun agar tebakan terdistribusi
        // tetap berhenti. Ambang per-IP saja membuat siswa lain di jaringan yang
        // sama bisa terkunci hanya karena satu perangkat salah kata sandi.
        $sejak = now()->subMinutes(self::JENDELA_MENIT)->getTimestamp();
        $ip = substr((string) $request->ip(), 0, 45);

        $percobaanAkun = LoginAttempt::where('login', $kunciLogin)
            ->where('time', '>=', $sejak)
            ->count();

        $percobaanIp = LoginAttempt::where('login', $kunciLogin)
            ->where('ip_address', $ip)
            ->where('time', '>=', $sejak)
            ->count();

        if ($percobaanAkun >= self::BATAS_PERCOBAAN_AKUN || $percobaanIp >= self::BATAS_PERCOBAAN_IP) {
            throw ValidationException::withMessages([
                'identity' => 'Terlalu banyak percobaan masuk. Tunggu beberapa menit sebelum mencoba lagi.',
            ]);
        }

        $user = User::where('username', $identity)
            ->orWhere('email', $identity)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            LoginAttempt::create([
                'ip_address' => $ip,
                'login' => mb_substr($kunciLogin, 0, 100),
                'time' => now()->getTimestamp(),
            ]);

            AuditLog::recordAttempt('auth.login_failed', $identity.' dari '.$request->ip());

            throw ValidationException::withMessages([
                'identity' => 'Username atau password salah.',
            ]);
        }

        if ((int) $user->active !== 1) {
            throw ValidationException::withMessages([
                'identity' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        LoginAttempt::where('login', $kunciLogin)->delete();

        $user->forceFill(['last_login' => time()])->save();

        AuditLog::record('auth.login', $user->username.' ('.$user->role.')', 1);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
