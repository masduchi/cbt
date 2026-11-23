<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SheetImport
{
    /**
     * @return array{0: string[], 1: array<int, array{line: int, data: array<string, string>}>}
     */
    public static function read(UploadedFile $file, array $required = []): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $matrix = in_array($extension, ['xlsx', 'xls'], true)
            ? self::fromSpreadsheet($file)
            : self::fromCsv($file);

        if (! $matrix) {
            return [[], []];
        }

        $headerRow = self::headerRow($matrix, $required);
        $header = array_map(fn ($value) => self::normalizeHeader($value), $matrix[$headerRow] ?? []);
        $rows = [];

        foreach ($matrix as $index => $line) {
            if ($index <= $headerRow) {
                continue;
            }

            $values = array_map(fn ($value) => trim((string) $value), $line);

            if (count(array_filter($values, fn ($value) => $value !== '')) === 0) {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count($header)), count($header), '');
            $rows[] = ['line' => $index + 1, 'data' => array_combine($header, $values) ?: []];
        }

        return [$header, $rows];
    }

    /**
     * Cari baris judul kolom pada 10 baris pertama, sehingga berkas yang diawali
     * baris judul/logo/keterangan tetap bisa diimpor.
     */
    private static function headerRow(array $matrix, array $required): int
    {
        if ($required === []) {
            return 0;
        }

        foreach (array_slice($matrix, 0, 10, true) as $index => $line) {
            $header = array_map(fn ($value) => self::normalizeHeader($value), $line);

            if (array_intersect($required, $header) !== []) {
                return (int) $index;
            }
        }

        return 0;
    }

    private static function normalizeHeader($value): string
    {
        $slug = Str::slug(trim((string) $value), '_');

        return preg_replace('/^([a-z])_([0-9]+)$/', '$1$2', $slug) ?? $slug;
    }

    private static function fromSpreadsheet(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        return $matrix;
    }

    private static function fromCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        if ($firstLine !== false && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $matrix = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $matrix[] = array_map(fn ($value) => (string) $value, $line);
        }

        fclose($handle);

        if (isset($matrix[0][0])) {
            $matrix[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $matrix[0][0]);
        }

        return $matrix;
    }
}
