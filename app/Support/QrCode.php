<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Encoder QR Code ringkas mode byte, level koreksi L, versi 1-10.
 *
 * Dipakai untuk menempelkan QR verifikasi pada dokumen cetak tanpa menambah
 * dependensi baru. Hasilnya SVG agar tajam saat dicetak dan mudah disisipkan
 * pada halaman mana pun.
 */
class QrCode
{
    /**
     * versi => [total data codewords, jumlah blok, EC codewords per blok] level L.
     *
     * @var array<int, array{0: int, 1: int, 2: int}>
     */
    private const LEVEL_L = [
        1 => [19, 1, 7],
        2 => [34, 1, 10],
        3 => [55, 1, 15],
        4 => [80, 1, 20],
        5 => [108, 1, 26],
        6 => [136, 2, 18],
        7 => [156, 2, 20],
        8 => [194, 2, 24],
        9 => [232, 2, 30],
        10 => [274, 4, 18],
    ];

    /**
     * @var array<int, array<int, int>>
     */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * @var array{0: array<int, int>, 1: array<int, int>}|null
     */
    private static ?array $galois = null;

    public static function svg(string $text, int $scale = 4, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $total = count($matrix) + $quiet * 2;
        $path = '';

        foreach ($matrix as $row => $columns) {
            foreach ($columns as $col => $dark) {
                if ($dark) {
                    $path .= 'M'.($col + $quiet).' '.($row + $quiet).'h1v1h-1z';
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img" aria-label="Kode QR verifikasi"><rect width="%1$d" height="%1$d" fill="#ffffff"/><path d="%3$s" fill="#000000"/></svg>',
            $total,
            $total * $scale,
            $path
        );
    }

    /**
     * QR sebagai PNG agar dapat disematkan pada PDF (Dompdf tidak selalu
     * merender SVG inline) tanpa menambah dependensi.
     */
    public static function png(string $text, int $scale = 8, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $modules = count($matrix) + $quiet * 2;
        $width = $modules * $scale;
        $raw = '';

        for ($y = 0; $y < $width; $y++) {
            $raw .= "\x00";
            $row = intdiv($y, $scale) - $quiet;

            for ($x = 0; $x < $width; $x++) {
                $col = intdiv($x, $scale) - $quiet;
                $dark = $row >= 0 && $row < count($matrix)
                    && $col >= 0 && $col < count($matrix)
                    && $matrix[$row][$col];

                $raw .= $dark ? "\x00\x00\x00" : "\xff\xff\xff";
            }
        }

        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $width, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw, 9))
            .$chunk('IEND', '');
    }

    /**
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $text): array
    {
        $length = strlen($text);
        $version = null;

        foreach (self::LEVEL_L as $candidate => [$dataCodewords]) {
            $countBits = $candidate < 10 ? 8 : 16;

            if (4 + $countBits + $length * 8 <= $dataCodewords * 8) {
                $version = (int) $candidate;

                break;
            }
        }

        if ($version === null) {
            throw new InvalidArgumentException('Teks terlalu panjang untuk QR versi 1-10.');
        }

        $size = 17 + 4 * $version;
        $matrix = array_fill(0, $size, array_fill(0, $size, null));

        self::drawFinders($matrix, $size);
        self::drawTiming($matrix, $size);
        self::drawAlignment($matrix, $version, $size);
        self::drawFormat($matrix, $size, $version);
        self::drawData($matrix, $size, self::codewords(self::bitStream($text, $version, self::LEVEL_L[$version][0]), $version));

        return array_map(
            fn (array $row) => array_map(fn ($cell) => (bool) $cell, $row),
            $matrix
        );
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function drawFinders(array &$matrix, int $size): void
    {
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $y = $row + $r;
                    $x = $col + $c;

                    if ($y < 0 || $y >= $size || $x < 0 || $x >= $size) {
                        continue;
                    }

                    $matrix[$y][$x] = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function drawTiming(array &$matrix, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;

            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = $dark;
            }

            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = $dark;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function drawAlignment(array &$matrix, int $version, int $size): void
    {
        $positions = self::ALIGNMENT[$version];

        foreach ($positions as $row) {
            foreach ($positions as $col) {
                if (($row === 6 && $col === 6) || ($row === 6 && $col === $size - 7) || ($row === $size - 7 && $col === 6)) {
                    continue;
                }

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $matrix[$row + $r][$col + $c] = max(abs($r), abs($c)) !== 1;
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     */
    private static function drawFormat(array &$matrix, int $size, int $version): void
    {
        $bits = self::formatBits();

        for ($i = 0; $i < 15; $i++) {
            $dark = (($bits >> $i) & 1) === 1;

            if ($i < 6) {
                $matrix[$i][8] = $dark;
            } elseif ($i < 8) {
                $matrix[$i + 1][8] = $dark;
            } else {
                $matrix[$size - 15 + $i][8] = $dark;
            }

            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $dark;
            } elseif ($i === 8) {
                $matrix[8][7] = $dark;
            } else {
                $matrix[8][14 - $i] = $dark;
            }
        }

        $matrix[$size - 8][8] = true;

        if ($version >= 7) {
            $versionBits = self::versionBits($version);

            for ($i = 0; $i < 18; $i++) {
                $dark = (($versionBits >> $i) & 1) === 1;
                $matrix[intdiv($i, 3)][$i % 3 + $size - 11] = $dark;
                $matrix[$i % 3 + $size - 11][intdiv($i, 3)] = $dark;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool|null>>  $matrix
     * @param  array<int, int>  $codewords
     */
    private static function drawData(array &$matrix, int $size, array $codewords): void
    {
        $bits = [];

        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        $index = 0;
        $total = count($bits);
        $row = $size - 1;
        $step = -1;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }

            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;

                    if ($matrix[$row][$x] !== null) {
                        continue;
                    }

                    $dark = ($bits[$index] ?? 0) === 1;
                    $index++;

                    // Mask 0: (row + col) % 2 === 0.
                    if (($row + $x) % 2 === 0) {
                        $dark = ! $dark;
                    }

                    $matrix[$row][$x] = $dark;
                }

                $row += $step;

                if ($row < 0 || $row >= $size) {
                    $row -= $step;
                    $step = -$step;

                    break;
                }
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private static function bitStream(string $text, int $version, int $dataCodewords): array
    {
        $bits = [];
        self::push($bits, 0b0100, 4);
        self::push($bits, strlen($text), $version < 10 ? 8 : 16);

        foreach (str_split($text) as $char) {
            self::push($bits, ord($char), 8);
        }

        $capacity = $dataCodewords * 8;

        for ($i = 0; $i < 4 && count($bits) < $capacity; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $pad = [0xEC, 0x11];
        $index = 0;

        while (count($bits) < $capacity) {
            self::push($bits, $pad[$index % 2], 8);
            $index++;
        }

        return $bits;
    }

    /**
     * @param  array<int, int>  $bits
     */
    private static function push(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * @param  array<int, int>  $bits
     * @return array<int, int>
     */
    private static function codewords(array $bits, int $version): array
    {
        [$totalData, $blocks, $ecPerBlock] = self::LEVEL_L[$version];

        $data = [];

        for ($i = 0; $i < $totalData; $i++) {
            $byte = 0;

            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | ($bits[$i * 8 + $b] ?? 0);
            }

            $data[] = $byte;
        }

        $base = intdiv($totalData, $blocks);
        $extra = $totalData % $blocks;
        $groups = [];
        $offset = 0;

        for ($i = 0; $i < $blocks; $i++) {
            $length = $base + ($i >= $blocks - $extra ? 1 : 0);
            $groups[] = array_slice($data, $offset, $length);
            $offset += $length;
        }

        $eccs = array_map(fn (array $group) => self::reedSolomon($group, $ecPerBlock), $groups);

        $result = [];
        $longest = $base + ($extra > 0 ? 1 : 0);

        for ($i = 0; $i < $longest; $i++) {
            foreach ($groups as $group) {
                if (isset($group[$i])) {
                    $result[] = $group[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($eccs as $ecc) {
                $result[] = $ecc[$i];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private static function reedSolomon(array $data, int $degree): array
    {
        $generator = self::generator($degree);
        $ecc = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $ecc[0];
            array_shift($ecc);
            $ecc[] = 0;

            for ($i = 0; $i < $degree; $i++) {
                $ecc[$i] ^= self::multiply($generator[$i + 1], $factor);
            }
        }

        return $ecc;
    }

    /**
     * @return array<int, int>
     */
    private static function generator(int $degree): array
    {
        $poly = [1];

        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            $root = self::power($i);

            foreach ($poly as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::multiply($coefficient, $root);
            }

            $poly = $next;
        }

        return $poly;
    }

    private static function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        [$exp, $log] = self::galois();

        return $exp[($log[$a] + $log[$b]) % 255];
    }

    private static function power(int $exponent): int
    {
        [$exp] = self::galois();

        return $exp[$exponent];
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private static function galois(): array
    {
        if (self::$galois !== null) {
            return self::$galois;
        }

        $exp = [];
        $log = [];
        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;

            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return self::$galois = [$exp, $log];
    }

    private static function formatBits(): int
    {
        // Level L (01) dengan mask 0.
        $data = 0b01000;
        $value = $data << 10;
        $generator = 0x537;

        while (self::bitLength($value) >= self::bitLength($generator)) {
            $value ^= $generator << (self::bitLength($value) - self::bitLength($generator));
        }

        return (($data << 10) | $value) ^ 0x5412;
    }

    private static function versionBits(int $version): int
    {
        $value = $version << 12;
        $generator = 0x1F25;

        while (self::bitLength($value) >= self::bitLength($generator)) {
            $value ^= $generator << (self::bitLength($value) - self::bitLength($generator));
        }

        return ($version << 12) | $value;
    }

    private static function bitLength(int $value): int
    {
        $length = 0;

        while ($value !== 0) {
            $length++;
            $value >>= 1;
        }

        return $length;
    }
}
