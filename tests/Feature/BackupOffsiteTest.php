<?php

namespace Tests\Feature;

use App\Support\BackupOffsite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupOffsiteTest extends TestCase
{
    public function test_salinan_offsite_menyalin_isi_dan_menulis_penanda(): void
    {
        $source = storage_path('app/uji-backup-src/20260101-010000');
        $target = storage_path('app/uji-offsite-target');

        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'arsip');
        File::put($source.DIRECTORY_SEPARATOR.'database.sql.gz', 'dump');
        File::put($source.DIRECTORY_SEPARATOR.'arsip'.DIRECTORY_SEPARATOR.'berkas.txt', 'isi');

        config(['backup.offsite_path' => $target]);

        try {
            $copied = BackupOffsite::copy($source);

            $this->assertSame('dump', File::get($copied.DIRECTORY_SEPARATOR.'database.sql.gz'));
            $this->assertSame('isi', File::get($copied.DIRECTORY_SEPARATOR.'arsip'.DIRECTORY_SEPARATOR.'berkas.txt'));
            $this->assertTrue(File::exists($copied.DIRECTORY_SEPARATOR.'offsite.json'));
            $this->assertTrue(BackupOffsite::exists('20260101-010000'));
            $this->assertFalse(BackupOffsite::exists('tidak-ada'));
        } finally {
            File::deleteDirectory(storage_path('app/uji-backup-src'));
            File::deleteDirectory($target);
        }
    }

    public function test_salinan_offsite_tidak_berjalan_tanpa_konfigurasi(): void
    {
        config(['backup.offsite_path' => '']);

        $this->assertFalse(BackupOffsite::configured());
        $this->assertFalse(BackupOffsite::exists('20260101-010000'));
    }
}
