<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DatabaseController extends Controller
{
    /**
     * 0 = terlindungi, 1 = master, 2 = transaksi, klasifikasi informasi untuk halaman read-only.
     *
     * @var array<string, int>
     */
    private const TABLE_CATEGORIES = [
        'api_setting' => 0,
        'api_token' => 0,
        'buku_induk' => 0,
        'bulan' => 0,
        'cache' => 0,
        'cache_locks' => 0,
        'cbt_jenis' => 0,
        'cbt_ruang' => 0,
        'cbt_sesi' => 0,
        'cbt_token' => 0,
        'failed_jobs' => 0,
        'groups' => 0,
        'hari' => 0,
        'job_batches' => 0,
        'jobs' => 0,
        'level_guru' => 0,
        'level_kelas' => 0,
        'login_attempts' => 0,
        'master_hari_efektif' => 0,
        'master_smt' => 0,
        'master_tp' => 0,
        'migrations' => 0,
        'password_reset_tokens' => 0,
        'rapor_admin_setting' => 0,
        'running_text' => 0,
        'sessions' => 0,
        'setting' => 0,
        'users' => 0,
        'users_groups' => 0,
        'users_profile' => 0,

        'cbt_kop_absensi' => 1,
        'cbt_kop_berita' => 1,
        'cbt_kop_kartu' => 1,
        'jabatan_guru' => 1,
        'jurusan_mapel' => 1,
        'kelas_ekstra' => 1,
        'master_ekstra' => 1,
        'master_guru' => 1,
        'master_jurusan' => 1,
        'master_kelas' => 1,
        'master_kelompok_mapel' => 1,
        'master_mapel' => 1,
        'master_siswa' => 1,
        'rapor_catatan_wali' => 1,
        'rapor_data_catatan' => 1,
        'rapor_data_fisik' => 1,
        'rapor_data_sikap' => 1,
        'rapor_fisik' => 1,
        'rapor_kikd' => 1,
        'rapor_kkm' => 1,
        'rapor_naik' => 1,
        'rapor_nilai_akhir' => 1,
        'rapor_nilai_ekstra' => 1,
        'rapor_nilai_harian' => 1,
        'rapor_nilai_pts' => 1,
        'rapor_nilai_sikap' => 1,
        'rapor_prestasi' => 1,

        'cbt_bank_soal' => 2,
        'cbt_durasi_siswa' => 2,
        'cbt_jadwal' => 2,
        'cbt_kelas_ruang' => 2,
        'cbt_nilai' => 2,
        'cbt_nomor_peserta' => 2,
        'cbt_pengawas' => 2,
        'cbt_rekap' => 2,
        'cbt_rekap_nilai' => 2,
        'cbt_sesi_siswa' => 2,
        'cbt_soal' => 2,
        'cbt_soal_siswa' => 2,
        'kelas_catatan_mapel' => 2,
        'kelas_catatan_wali' => 2,
        'kelas_jadwal_kbm' => 2,
        'kelas_jadwal_mapel' => 2,
        'kelas_jadwal_materi' => 2,
        'kelas_materi' => 2,
        'kelas_siswa' => 2,
        'kelas_struktur' => 2,
        'log' => 2,
        'log_materi' => 2,
        'log_ujian' => 2,
        'post' => 2,
        'post_comments' => 2,
        'post_reply' => 2,
        'rapor_kunci' => 2,
    ];

    private const CATEGORY_LABELS = [
        0 => 'Terlindungi',
        1 => 'Master',
        2 => 'Transaksi',
    ];

    public function index(): Response
    {
        $tables = $this->tables();

        return Inertia::render('Setting/Database', [
            'driver' => DB::getDriverName(),
            'migrations' => $this->migrations(),
            'tables' => $tables,
            'totals' => [
                'rows' => array_sum(array_column($tables, 'rows')),
                'bytes' => array_sum(array_column($tables, 'bytes')),
                'size' => Number::fileSize((int) array_sum(array_column($tables, 'bytes'))),
            ],
            'backups' => $this->backups(),
            'productionWarnings' => $this->productionWarnings(),
        ]);
    }

    /**
     * Konfigurasi yang lolos di lokal tetapi berbahaya di produksi: antrean
     * sync menjalankan pekerjaan di dalam request dan mailer log membuang
     * notifikasi tanpa jejak.
     *
     * @return array<int, string>
     */
    private function productionWarnings(): array
    {
        if (! app()->environment('production')) {
            return [];
        }

        $warnings = [];

        if ((string) config('queue.default') === 'sync') {
            $warnings[] = 'Antrean masih memakai driver "sync": pekerjaan berat seperti notifikasi berjalan di dalam request dan bisa timeout. Atur QUEUE_CONNECTION=database dan jalankan worker.';
        }

        if ((string) config('mail.default') === 'log') {
            $warnings[] = 'Mailer masih memakai driver "log": email notifikasi hasil ujian tidak benar-benar terkirim. Atur MAIL_MAILER=smtp bila notifikasi dipakai.';
        }

        return $warnings;
    }

    public function backup(): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('backup:run');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Backup gagal dijalankan: '.$e->getMessage());
        }

        if ($exitCode !== 0) {
            return back()->with('error', 'Backup gagal. Pastikan mysqldump tersedia dan konfigurasi database benar.');
        }

        AuditLog::record('database.backup', 'Backup manual dijalankan');

        return back()->with('success', 'Backup database dan berkas publik berhasil dibuat.');
    }

    public function destroyBackup(string $name): RedirectResponse
    {
        $name = basename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return back()->with('error', 'Nama backup tidak valid.');
        }

        $path = storage_path('app/backups/'.$name);

        if (! File::isDirectory($path)) {
            return back()->with('error', 'Backup tidak ditemukan.');
        }

        File::deleteDirectory($path);

        AuditLog::record('database.delete', 'Hapus backup '.$name);

        return back()->with('success', "Backup {$name} berhasil dihapus.");
    }

    public function restoreBackup(Request $request, string $name): RedirectResponse
    {
        $name = basename($name);
        $data = $request->validate([
            'confirm' => ['required', 'string', 'max:64'],
        ]);

        if ($name === '' || $name === '.' || $name === '..' || ! File::isDirectory(storage_path('app/backups/'.$name))) {
            return back()->with('error', 'Backup tidak ditemukan.');
        }

        if ($data['confirm'] !== $name) {
            return back()->with('error', 'Konfirmasi tidak cocok. Ketik nama backup persis sama.');
        }

        try {
            $exitCode = Artisan::call('backup:restore', [
                'name' => $name,
                '--force' => true,
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Pemulihan gagal dijalankan: '.$e->getMessage());
        }

        if ($exitCode !== 0) {
            return back()->with('error', 'Pemulihan gagal. Periksa dump backup dan ketersediaan klien mysql.');
        }

        AuditLog::record('database.restore', 'Database dipulihkan dari backup '.$name);

        return back()->with('success', "Database berhasil dipulihkan dari backup {$name}.");
    }

    public function truncate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'table' => ['required', 'string', 'max:64'],
            'confirm' => ['required', 'string', 'max:64'],
        ]);

        $table = $data['table'];

        // Nama tabel dipakai langsung ke query, jadi tolak selain huruf/angka/underscore
        // sebelum diperiksa (mis. "../etc" membuat Schema::hasTable melempar exception).
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || ! Schema::hasTable($table)) {
            return back()->with('error', 'Tabel tidak ditemukan.');
        }

        if ((self::TABLE_CATEGORIES[$table] ?? 0) !== 2) {
            return back()->with('error', 'Tabel ini dilindungi dan tidak boleh dikosongkan.');
        }

        if ($data['confirm'] !== $table) {
            return back()->with('error', 'Konfirmasi tidak cocok. Ketik nama tabel persis sama.');
        }

        $driver = DB::getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            DB::table($table)->truncate();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengosongkan tabel: '.$e->getMessage());
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        AuditLog::record('database.truncate', 'Tabel '.$table.' dikosongkan');

        return back()->with('success', "Tabel {$table} berhasil dikosongkan.");
    }

    /**
     * @return array{total: int, applied: int, pending: array<int, string>, batches: int}
     */
    private function migrations(): array
    {
        $applied = DB::table('migrations')->pluck('batch', 'migration');

        $files = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values();

        $pending = $files->reject(fn (string $name) => $applied->has($name))->values();

        return [
            'total' => $files->count(),
            'applied' => $files->count() - $pending->count(),
            'pending' => $pending->all(),
            'batches' => $applied->unique()->count(),
        ];
    }

    /**
     * @return array<int, array{name: string, rows: ?int, bytes: int, size: string, category: int, category_label: string}>
     */
    private function tables(): array
    {
        return collect(Schema::getTables(DB::getDatabaseName()))
            ->map(function (array $table) {
                $name = $table['name'];
                $rows = null;

                try {
                    $rows = DB::table($name)->count();
                } catch (Throwable) {
                    //
                }

                $bytes = (int) ($table['size'] ?? 0);
                $category = self::TABLE_CATEGORIES[$name] ?? 0;

                return [
                    'name' => $name,
                    'rows' => $rows,
                    'bytes' => $bytes,
                    'size' => $bytes > 0 ? Number::fileSize($bytes) : '-',
                    'category' => $category,
                    'category_label' => self::CATEGORY_LABELS[$category],
                ];
            })
            ->sortBy(fn (array $table) => strtolower($table['name']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, files: array<int, string>, bytes: int, size: string, verified: bool, created_at: string}>
     */
    private function backups(): array
    {
        $root = storage_path('app/backups');

        if (! File::isDirectory($root)) {
            return [];
        }

        return collect(File::directories($root))
            ->map(function (string $directory) {
                $files = collect(File::files($directory))->reject(fn ($file) => $file->getFilename() === 'verify.json');
                $bytes = (int) $files->sum(fn ($file) => $file->getSize());

                return [
                    'name' => basename($directory),
                    'files' => $files->map(fn ($file) => $file->getFilename())->values()->all(),
                    'bytes' => $bytes,
                    'size' => Number::fileSize($bytes),
                    'verified' => File::exists($directory.DIRECTORY_SEPARATOR.'verify.json'),
                    'created_at' => Carbon::createFromTimestamp(File::lastModified($directory), config('app.timezone'))
                        ->translatedFormat('d M Y H:i'),
                ];
            })
            ->sortByDesc('name')
            ->values()
            ->all();
    }
}
