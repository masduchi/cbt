<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /** Tabel nilai rapor yang ikut dicadangkan dan dipulihkan. */
    private const TABLES = [
        'rapor_admin_setting',
        'rapor_kkm',
        'rapor_kikd',
        'rapor_nilai_harian',
        'rapor_nilai_pts',
        'rapor_nilai_akhir',
        'rapor_nilai_sikap',
        'rapor_nilai_ekstra',
        'rapor_data_sikap',
        'rapor_data_fisik',
        'rapor_data_catatan',
        'rapor_prestasi',
        'rapor_catatan_wali',
        'rapor_naik',
        'rapor_kunci',
    ];

    /** Batas kewajaran satu tabel; berkas lebih besar ditolak sebelum menghapus data. */
    private const BATAS_BARIS_PER_TABEL = 50000;

    public function export(): StreamedResponse
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            $tables[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $payload = [
            'meta' => [
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'tables' => self::TABLES,
            ],
            'tables' => $tables,
        ];

        $filename = 'backup-rapor-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:json,txt', 'max:20480'],
            'confirm' => ['required', 'string'],
        ]);

        $file = $request->file('file');
        $name = basename((string) $file->getClientOriginalName());

        // Konfirmasi tidak pernah diisi otomatis oleh klien; server memeriksa
        // ulang agar satu klik tidak bisa menimpa seluruh nilai rapor.
        if (trim((string) $request->input('confirm')) !== $name) {
            return back()->withErrors([
                'confirm' => 'Ketik nama berkas backup dengan tepat untuk melanjutkan.',
            ]);
        }

        $raw = (string) file_get_contents($file->getRealPath());
        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['tables']) || ! is_array($payload['tables'])) {
            return back()->with('error', 'Berkas backup tidak dikenali.');
        }

        try {
            $tables = $this->validatedTables($payload);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $restored = 0;

        DB::transaction(function () use ($tables, &$restored) {
            foreach ($tables as $table => $rows) {
                DB::table($table)->delete();

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                    $restored += count($chunk);
                }
            }
        });

        AuditLog::record('rapor.restore', 'Restore nilai rapor: '.$restored.' baris');

        return back()->with('success', "Nilai rapor berhasil dipulihkan ({$restored} baris dari ".count($tables).' tabel).');
    }

    /**
     * Menyaring isi berkas sebelum ada data yang dihapus: hanya tabel yang
     * dikenal, hanya kolom yang benar-benar ada, dan hanya nilai skalar.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function validatedTables(array $payload): array
    {
        $result = [];

        foreach (self::TABLES as $table) {
            $rows = $payload['tables'][$table] ?? null;

            if ($rows === null) {
                continue;
            }

            if (! is_array($rows)) {
                throw new InvalidArgumentException("Bagian tabel {$table} pada berkas backup tidak valid.");
            }

            if (count($rows) > self::BATAS_BARIS_PER_TABEL) {
                throw new InvalidArgumentException("Tabel {$table} memuat lebih dari ".self::BATAS_BARIS_PER_TABEL.' baris; berkas ditolak.');
            }

            $columns = Schema::getColumnListing($table);
            $result[$table] = [];

            foreach (array_values($rows) as $index => $row) {
                $line = $index + 1;

                if (! is_array($row)) {
                    throw new InvalidArgumentException("Baris ke-{$line} pada tabel {$table} bukan objek.");
                }

                $unknown = array_diff(array_keys($row), $columns);

                if ($unknown !== []) {
                    throw new InvalidArgumentException("Baris ke-{$line} pada tabel {$table} memuat kolom tidak dikenal: ".implode(', ', $unknown).'.');
                }

                foreach ($row as $value) {
                    if (! is_scalar($value) && $value !== null) {
                        throw new InvalidArgumentException("Baris ke-{$line} pada tabel {$table} memuat nilai bertingkat yang tidak didukung.");
                    }
                }

                $result[$table][] = $row;
            }
        }

        return $result;
    }
}
