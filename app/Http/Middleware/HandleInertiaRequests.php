<?php

namespace App\Http\Middleware;

use App\Models\RunningText;
use App\Models\Setting;
use App\Support\Period;
use App\Support\SchoolLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $setting = Setting::first();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'username' => $request->user()->username,
                    'name' => trim($request->user()->first_name.' '.$request->user()->last_name),
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'photo' => $request->user()->photo_url,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'import_errors' => fn () => $request->session()->get('import_errors', []),
            ],
            'period' => function () {
                [$tahun, $semester] = Period::current();
                $isAdmin = Auth::user()?->isAdmin() ?? false;

                return [
                    'id_tp' => $tahun?->id_tp,
                    'id_smt' => $semester?->id_smt,
                    'tahun' => $tahun?->tahun,
                    'smt' => $semester?->smt,
                    'active' => (int) $tahun?->active === 1 && (int) $semester?->active === 1,
                    'overridden' => Period::overridden(),
                    'options' => $isAdmin ? Period::options() : [],
                ];
            },
            'appName' => config('app.name'),
            'runningText' => fn () => RunningText::orderBy('id_text')
                ->pluck('text')
                ->filter(fn ($text) => trim((string) $text) !== '')
                ->values(),
            'school' => [
                'sekolah' => $setting?->sekolah,
                'nama_aplikasi' => $setting?->nama_aplikasi,
                'logo' => SchoolLogo::url($setting?->logo_kiri),
                'logo_kanan' => SchoolLogo::url($setting?->logo_kanan),
            ],
        ];
    }
}
