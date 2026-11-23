<?php

namespace Tests\Feature;

use App\Support\AuditLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class AuditLogGuardrailTest extends TestCase
{
    /**
     * Modul yang sudah memakai jejak audit. Setiap route mutasinya wajib
     * menulis AuditLog supaya permukaan yang sudah diaudit tidak berlubang.
     *
     * @var array<int, string>
     */
    private const AUDITED_PREFIXES = [
        'cbt.',
        'master.',
        'rapor.pengaturan.',
        'rapor.kkm.',
        'rapor.backup.',
        'rapor.nilai.',
        'alumni.',
        'wali.',
        'elearning.',
        'pengumuman.',
        'setting.',
        'users.',
    ];

    /**
     * Pengecualian yang disengaja: unggah gambar inline adalah bagian dari
     * penyuntingan butir soal yang sudah tercatat lewat cbt.soal.
     *
     * @var array<string, string>
     */
    private const PENGECUALIAN = [
        'cbt.media.store' => 'Unggah gambar inline bagian dari penyuntingan butir soal',
    ];

    /**
     * Route mutasi yang tidak memakai prefiks modul di atas.
     *
     * @var array<int, string>
     */
    private const AUDITED_ROUTES = [
        'rapor.kelas.kunci',
        'rapor.kelas.buka-kunci',
    ];

    public function test_setiap_kode_aksi_audit_dipakai_di_kode_aplikasi(): void
    {
        $source = $this->sumberAplikasi();

        foreach (array_keys(AuditLog::ACTIONS) as $code) {
            $this->assertStringContainsString(
                "'".$code."'",
                $source,
                "Kode aksi audit '{$code}' terdaftar tetapi tidak pernah dipakai di app/."
            );
        }
    }

    public function test_setiap_penulisan_audit_memakai_kode_aksi_terdaftar(): void
    {
        preg_match_all("/AuditLog::record\\('([^']+)'/", $this->sumberAplikasi(), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $code) {
            $this->assertArrayHasKey(
                $code,
                AuditLog::ACTIONS,
                "Kode aksi '{$code}' ditulis ke log tetapi belum terdaftar di AuditLog::ACTIONS."
            );
        }
    }

    public function test_route_mutasi_pada_modul_teraudit_memanggil_auditlog(): void
    {
        $diperiksa = 0;
        $terlewat = [];
        foreach (array_keys(self::PENGECUALIAN) as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Pengecualian '{$name}' bukan route yang terdaftar.");
        }

        foreach (Route::getRoutes() as $route) {
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
                continue;
            }

            $name = (string) $route->getName();

            if (! $this->termasukKebijakanAudit($name) || isset(self::PENGECUALIAN[$name])) {
                continue;
            }

            $action = $route->getActionName();

            if (! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);
            $diperiksa++;

            if (! $this->metodeMenulisAudit($class, $method)) {
                $terlewat[] = $name;
            }
        }

        $this->assertGreaterThan(80, $diperiksa, 'Kebijakan audit tidak memeriksa route mutasi apa pun.');
        $this->assertSame([], $terlewat, 'Route mutasi tanpa AuditLog::record: '.implode(', ', $terlewat));
    }

    private function sumberAplikasi(): string
    {
        return collect(File::allFiles(app_path()))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => File::get($file->getPathname()))
            ->implode("\n");
    }

    private function termasukKebijakanAudit(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (in_array($name, self::AUDITED_ROUTES, true)) {
            return true;
        }

        foreach (self::AUDITED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function metodeMenulisAudit(string $class, string $method): bool
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file((string) $reflection->getFileName());

        if ($lines === false) {
            return false;
        }

        $body = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));

        return str_contains($body, 'AuditLog::record');
    }
}
