<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const FIELDS = [
        'sekolah', 'nama_aplikasi', 'npsn', 'nss', 'jenjang', 'kepsek', 'nip',
        'alamat', 'desa', 'kecamatan', 'kota', 'provinsi', 'kode_pos',
        'telp', 'fax', 'web', 'email', 'kode_sekolah',
    ];

    public function edit(): Response
    {
        $setting = Setting::first() ?: new Setting;

        return Inertia::render('Setting/Sekolah', [
            'setting' => collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => $setting->{$field}])->all(),
            'logoKanan' => $setting->logo_kanan ? Storage::disk('public')->url($setting->logo_kanan) : null,
            'logoKiri' => $setting->logo_kiri ? Storage::disk('public')->url($setting->logo_kiri) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sekolah' => ['required', 'string', 'max:150'],
            'nama_aplikasi' => ['nullable', 'string', 'max:100'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'nss' => ['nullable', 'string', 'max:30'],
            'jenjang' => ['nullable', 'integer', 'in:1,2,3'],
            'kepsek' => ['nullable', 'string', 'max:100'],
            'nip' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'integer'],
            'telp' => ['nullable', 'string', 'max:30'],
            'fax' => ['nullable', 'string', 'max:30'],
            'web' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'kode_sekolah' => ['nullable', 'string', 'max:30'],
            'logo_kanan' => ['nullable', 'image', 'max:2048'],
            'logo_kiri' => ['nullable', 'image', 'max:2048'],
        ]);

        $setting = Setting::first() ?: new Setting;
        $previous = [
            'logo_kanan' => $setting->logo_kanan,
            'logo_kiri' => $setting->logo_kiri,
        ];

        foreach (['logo_kanan', 'logo_kiri'] as $logo) {
            if ($request->hasFile($logo)) {
                $data[$logo] = $request->file($logo)->store('logos', 'public');
            } else {
                unset($data[$logo]);
            }
        }

        $setting->fill($data)->save();

        foreach ($previous as $logo => $path) {
            if ($path && isset($data[$logo]) && $path !== $data[$logo]) {
                Storage::disk('public')->delete($path);
            }
        }

        AuditLog::record('setting.update', 'Profil sekolah diperbarui');

        return back()->with('success', 'Profil sekolah berhasil disimpan.');
    }
}
