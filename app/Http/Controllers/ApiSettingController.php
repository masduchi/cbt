<?php

namespace App\Http\Controllers;

use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ApiSettingController extends Controller
{
    public function index(): Response
    {
        $setting = ApiSetting::current();
        $tokens = ApiToken::orderByDesc('timestamp')->orderByDesc('id_api')->get();
        $users = User::with(['groups', 'profile'])
            ->whereIn('id', $tokens->pluck('id_user')->unique()->all())
            ->get()
            ->keyBy('id');

        $rows = $tokens->map(function (ApiToken $token) use ($users) {
            $user = $users[(int) $token->id_user] ?? null;

            return [
                'id_api' => (int) $token->id_api,
                'nama' => $user?->nama ?? 'Pengguna tidak ditemukan',
                'username' => $user?->username,
                'role' => $user?->role,
                'device' => $token->device,
                'address' => $token->address,
                'agent' => $token->agent,
                'created_at' => $token->timestamp
                    ? Carbon::parse($token->timestamp)->translatedFormat('d M Y H:i')
                    : '',
            ];
        });

        return Inertia::render('Setting/Api', [
            'setting' => [
                'auto_sync' => (bool) $setting->auto_sync,
                'edit_profile_siswa' => (bool) $setting->edit_profile_siswa,
                'edit_profile_guru' => (bool) $setting->edit_profile_guru,
            ],
            'tokens' => $rows,
            'endpoints' => [
                'token' => url('/api/auth/token'),
                'me' => url('/api/me'),
                'sync' => url('/api/sync'),
                'profil' => url('/api/profil'),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_sync' => ['required', 'boolean'],
            'edit_profile_siswa' => ['required', 'boolean'],
            'edit_profile_guru' => ['required', 'boolean'],
        ]);

        ApiSetting::current()->update($data);

        AuditLog::record('setting.api', 'Pengaturan API diperbarui');

        return back()->with('success', 'Pengaturan API disimpan.');
    }

    public function destroyToken(int $token): RedirectResponse
    {
        $row = ApiToken::findOrFail($token);
        $row->delete();

        AuditLog::record('setting.api', 'Token API #'.$token.' dicabut');

        return back()->with('success', 'Token API dicabut.');
    }
}
