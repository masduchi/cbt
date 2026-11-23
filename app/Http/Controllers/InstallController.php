<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use App\Support\EnvFile;
use App\Support\Installer;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use PDOException;
use Throwable;

class InstallController extends Controller
{
    private const SCHOOL_FIELDS = [
        'nama_aplikasi', 'sekolah', 'npsn', 'kepsek', 'jenjang', 'satuan_pendidikan',
        'alamat', 'desa', 'kecamatan', 'kota', 'provinsi', 'telp', 'email',
    ];

    public function index(): Response|RedirectResponse
    {
        if (Installer::isInstalled()) {
            return redirect()->route('login')
                ->with('status', 'Aplikasi sudah terinstal. Silakan masuk dengan akun administrator.');
        }

        $admin = Installer::adminReady()
            ? User::whereHas('groups', fn ($query) => $query->where('name', 'admin'))->first()
            : null;

        $setting = Installer::settingReady() ? Setting::first() : null;

        return Inertia::render('Install/Index', [
            'step' => Installer::currentStep(),
            'databaseReady' => Installer::databaseReady(),
            'database' => [
                'host' => (string) config('database.connections.mysql.host', '127.0.0.1'),
                'port' => (string) config('database.connections.mysql.port', '3306'),
                'database' => (string) config('database.connections.mysql.database', ''),
                'username' => (string) config('database.connections.mysql.username', 'root'),
            ],
            'admin' => $admin ? [
                'nama_lengkap' => $admin->nama,
                'username' => $admin->username,
            ] : null,
            'sekolah' => $setting ? $setting->only(self::SCHOOL_FIELDS) : null,
            'jenjang' => config('installer.jenjang'),
            'satuan' => config('installer.satuan'),
        ]);
    }

    public function database(Request $request): RedirectResponse
    {
        if (Installer::isInstalled()) {
            return redirect()->route('install.index');
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:120'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ], [], [
            'host' => 'host name',
            'database' => 'nama database',
            'username' => 'username database',
        ]);

        if (! EnvFile::writable()) {
            return back()->withErrors([
                'database' => 'Berkas .env tidak dapat ditulis. Periksa izin akses berkas lalu coba lagi.',
            ]);
        }

        try {
            $this->createDatabaseIfMissing($data);
        } catch (PDOException $exception) {
            return back()->withErrors([
                'database' => 'Koneksi database gagal: '.$exception->getMessage(),
            ]);
        }

        EnvFile::put([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $data['host'],
            'DB_PORT' => (string) $data['port'],
            'DB_DATABASE' => (string) $data['database'],
            'DB_USERNAME' => (string) $data['username'],
            'DB_PASSWORD' => (string) ($data['password'] ?? ''),
            'DB_URL' => '',
        ]);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['host'],
            'database.connections.mysql.port' => $data['port'],
            'database.connections.mysql.database' => $data['database'],
            'database.connections.mysql.username' => $data['username'],
            'database.connections.mysql.password' => $data['password'] ?? '',
        ]);

        DB::purge('mysql');

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'database' => 'Skema database gagal dibuat: '.$exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Koneksi database berhasil. Skema dan data referensi sudah dibuat.');
    }

    public function admin(Request $request): RedirectResponse
    {
        if (Installer::isInstalled()) {
            return redirect()->route('install.index');
        }

        if (! Installer::databaseReady()) {
            return redirect()->route('install.index')
                ->with('error', 'Selesaikan langkah database terlebih dahulu.');
        }

        if (Installer::adminReady()) {
            return back()->with('success', 'Akun administrator sudah dibuat.');
        }

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [], [
            'nama_lengkap' => 'nama lengkap',
            'password' => 'password',
        ]);

        Installer::ensureGroups();

        $nama = trim((string) $data['nama_lengkap']);
        $bagian = preg_split('/\s+/', $nama) ?: [$nama];

        $user = User::create([
            'ip_address' => (string) $request->ip(),
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => trim((string) ($data['email'] ?? '')) ?: strtolower($data['username']).'@admin.local',
            'created_on' => time(),
            'active' => 1,
            'must_change_password' => false,
            'first_name' => (string) array_shift($bagian),
            'last_name' => implode(' ', $bagian),
        ]);

        $user->groups()->attach(Group::where('name', 'admin')->value('id'));
        $user->profile()->create([
            'nama_lengkap' => $nama,
            'jabatan' => 'Administrator',
            'level_access' => 0,
        ]);

        return back()->with('success', 'Akun administrator berhasil dibuat.');
    }

    public function school(Request $request): RedirectResponse
    {
        if (Installer::isInstalled()) {
            return redirect()->route('install.index');
        }

        if (! Installer::adminReady()) {
            return redirect()->route('install.index')
                ->with('error', 'Buat akun administrator terlebih dahulu.');
        }

        $data = $request->validate([
            'nama_aplikasi' => ['required', 'string', 'max:100'],
            'sekolah' => ['required', 'string', 'max:150'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'kepsek' => ['required', 'string', 'max:100'],
            'jenjang' => ['required', 'integer', 'in:1,2,3'],
            'satuan_pendidikan' => ['required', 'integer', 'between:1,3'],
            'alamat' => ['required', 'string', 'max:255'],
            'desa' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kota' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'telp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
        ], [], [
            'nama_aplikasi' => 'nama aplikasi',
            'sekolah' => 'nama sekolah',
            'satuan_pendidikan' => 'satuan pendidikan',
        ]);

        $setting = Setting::first() ?? new Setting;
        $setting->fill($data + ['db_versi' => '1.0'])->save();

        return back()->with('success', 'Identitas sekolah berhasil disimpan.');
    }

    public function finish(): RedirectResponse
    {
        if (Installer::marked()) {
            return redirect()->route('login');
        }

        if (! Installer::adminReady() || ! Installer::settingReady()) {
            return redirect()->route('install.index')
                ->with('error', 'Lengkapi langkah administrator dan identitas sekolah sebelum menyelesaikan instalasi.');
        }

        Installer::ensureActivePeriod();
        Installer::markInstalled();

        return redirect()->route('login')
            ->with('status', 'Instalasi selesai. Silakan masuk dengan akun administrator yang baru dibuat.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createDatabaseIfMissing(array $data): void
    {
        $dsn = sprintf('mysql:host=%s;port=%d', $data['host'], (int) $data['port']);
        $pdo = new PDO($dsn, (string) $data['username'], (string) ($data['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '', (string) $data['database'])
        ));

        $pdo->exec(sprintf('USE `%s`', str_replace('`', '', (string) $data['database'])));
    }
}
