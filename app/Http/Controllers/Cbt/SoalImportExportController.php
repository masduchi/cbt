<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Soal;
use App\Support\AuditLog;
use App\Support\BankSoalCounter;
use App\Support\DocxDocument;
use App\Support\HtmlSanitizer;
use App\Support\SoalText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class SoalImportExportController extends Controller
{
    private const JENIS_LABELS = [1 => 'PG', 2 => 'Kompleks', 3 => 'Jodohkan', 4 => 'Isian', 5 => 'Uraian'];

    private const HEADERS = [
        'jenis', 'soal', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
        'jawaban', 'deskripsi', 'kesulitan', 'timer_menit',
    ];

    /**
     * Nama kolom yang lazim dipakai guru pada berkas buatan sendiri.
     * Semua nilai sudah dinormalisasi (huruf kecil, tanpa spasi/tanda baca).
     */
    private const HEADER_ALIASES = [
        'jenis' => ['jenis', 'jenissoal', 'tipe', 'tipesoal', 'bentuk', 'bentuksoal', 'tipepertanyaan'],
        'soal' => ['soal', 'pertanyaan', 'butirsoal', 'pertanyaansoal', 'isi', 'stem'],
        'opsi_a' => ['opsi_a', 'opsia', 'pilihana', 'jawabana', 'a'],
        'opsi_b' => ['opsi_b', 'opsib', 'pilihanb', 'jawabanb', 'b'],
        'opsi_c' => ['opsi_c', 'opsic', 'pilihanc', 'jawabanc', 'c'],
        'opsi_d' => ['opsi_d', 'opsid', 'pilihand', 'jawaband', 'd'],
        'opsi_e' => ['opsi_e', 'opsie', 'pilihane', 'jawabane', 'e'],
        'jawaban' => ['jawaban', 'kunci', 'kuncijawaban', 'jawab', 'kuncijawab'],
        'deskripsi' => ['deskripsi', 'pembahasan', 'penjelasan', 'bahas'],
        'kesulitan' => ['kesulitan', 'tingkatkesulitan', 'level', 'bobot'],
        'timer_menit' => ['timer_menit', 'timermenit', 'timer', 'waktumenit', 'waktu'],
    ];

    public function export(Request $request, int $id): HttpResponse
    {
        $bank = $this->ownedBank($request, $id);

        if ($request->query('format') === 'docx') {
            return $this->exportWord($bank);
        }

        return $this->exportExcel($bank, $request->boolean('template'));
    }

    public function import(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,docx'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $errors = [];
        $rows = $extension === 'docx'
            ? $this->parseWord($file->getRealPath(), $errors)
            : $this->parseExcel($file->getRealPath(), $errors);

        $created = $this->insertRows($bank, $rows, $errors);

        BankSoalCounter::sync($bank);

        AuditLog::record('cbt.soal.impor', sprintf('Impor %d soal ke bank #%d %s', $created, $bank->id_bank, $bank->bank_kode));

        $message = "Impor selesai: {$created} soal ditambahkan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    private function exportExcel(BankSoal $bank, bool $template): HttpResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Soal');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        if ($template) {
            $guide = $spreadsheet->createSheet();
            $guide->setTitle('Petunjuk');
            $rows = [
                ['Kolom', 'Isi', 'Contoh'],
                ['jenis', 'PG, Kompleks, Jodohkan, Isian, atau Uraian (boleh angka 1-5)', 'PG'],
                ['soal', 'Isi pertanyaan. Rumus LaTeX ditulis di antara \\( dan \\).', 'Hitung \\(2^3\\) ...'],
                ['opsi_a sampai opsi_e', 'Wajib A-D untuk PG dan Kompleks. E opsional.', 'Empat'],
                ['jawaban', 'PG: satu huruf. Kompleks: huruf dipisah koma. Jodohkan: kiri|kanan dipisah titik koma.', 'a,c atau 1|satu;2|dua'],
                ['deskripsi', 'Pembahasan, opsional.', 'Karena ...'],
                ['kesulitan', 'Angka 1 sampai 10, kosong dianggap 1.', '3'],
                ['timer_menit', 'Menit timer per soal, kosong berarti tanpa timer.', '2'],
                [],
                ['Catatan', 'Hapus sheet Petunjuk sebelum mengunggah bila perlu. Sheet yang dibaca bernama Soal.'],
                ['', 'Baris contoh di bawah hanya untuk gambaran, jangan diunggah.'],
            ];
            $guide->fromArray($rows, null, 'A1');
            $guide->getStyle('A1:C1')->getFont()->setBold(true);
            foreach (['A', 'B', 'C'] as $column) {
                $guide->getColumnDimension($column)->setWidth(28);
            }
        } else {
            $soals = Soal::where('bank_id', $bank->id_bank)
                ->orderByRaw('FIELD(jenis, 1, 2, 3, 4, 5)')
                ->orderBy('nomor_soal')
                ->get();

            $line = 2;
            foreach ($soals as $soal) {
                $sheet->fromArray([
                    self::JENIS_LABELS[(int) $soal->jenis] ?? 'PG',
                    SoalText::plain($soal->soal),
                    SoalText::plain($soal->opsi_a),
                    SoalText::plain($soal->opsi_b),
                    SoalText::plain($soal->opsi_c),
                    SoalText::plain($soal->opsi_d),
                    SoalText::plain($soal->opsi_e),
                    $this->answerExport((int) $soal->jenis, (string) $soal->jawaban),
                    SoalText::plain($soal->deskripsi),
                    (int) ($soal->kesulitan ?: 1),
                    (int) ($soal->timer_menit ?: 0),
                ], null, 'A'.$line);
                $line++;
            }
            unset($jenisOrder);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        $name = $this->slug($bank->bank_kode).($template ? '-template' : '').'.xlsx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    private function exportWord(BankSoal $bank): HttpResponse
    {
        $paragraphs = ['BANK SOAL '.$bank->bank_kode.' - '.$bank->bank_nama, ''];

        $soals = Soal::where('bank_id', $bank->id_bank)
            ->orderByRaw('FIELD(jenis, 1, 2, 3, 4, 5)')
            ->orderBy('nomor_soal')
            ->get();

        $lastJenis = null;
        foreach ($soals as $soal) {
            $jenis = (int) $soal->jenis;
            if ($jenis !== $lastJenis) {
                $paragraphs[] = '';
                $paragraphs[] = strtoupper(self::JENIS_LABELS[$jenis] ?? 'SOAL');
                $lastJenis = $jenis;
            }

            $paragraphs[] = $soal->nomor_soal.'. '.SoalText::plain($soal->soal);

            if (in_array($jenis, [1, 2], true)) {
                foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                    $option = SoalText::plain($soal->{'opsi_'.$letter});
                    if ($option !== '') {
                        $paragraphs[] = strtoupper($letter).'. '.$option;
                    }
                }
            } elseif ($jenis === 3) {
                foreach ($this->decodePairs((string) $soal->jawaban) as $pair) {
                    $paragraphs[] = '- '.SoalText::plain($pair['kiri']).' -> '.SoalText::plain($pair['kanan']);
                }
            }

            if (in_array($jenis, [1, 2, 3], true)) {
                $paragraphs[] = 'Jawaban: '.$this->answerExport($jenis, (string) $soal->jawaban);
            } elseif ($jenis === 4) {
                $paragraphs[] = 'Jawaban: '.SoalText::plain($soal->jawaban);
            }

            $deskripsi = SoalText::plain($soal->deskripsi);
            if ($deskripsi !== '') {
                $paragraphs[] = 'Pembahasan: '.str_replace("\n", ' ', $deskripsi);
            }
            $paragraphs[] = '';
        }

        $binary = DocxDocument::fromParagraphs($paragraphs);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$this->slug($bank->bank_kode).'-soal.docx"',
        ]);
    }

    private function parseExcel(string $path, array &$errors): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Soal') ?? $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        if (! $data) {
            $errors[] = 'Berkas Excel kosong.';

            return [];
        }

        $headerRow = null;
        $header = [];

        foreach (array_slice($data, 0, 10, true) as $index => $line) {
            $candidate = array_map(fn ($value) => $this->normalizeHeader((string) $value), $line);

            $hits = 0;
            foreach (self::HEADERS as $key) {
                if ($this->headerIndex($candidate, $key) !== null) {
                    $hits++;
                }
            }

            if ($hits >= 2) {
                $headerRow = (int) $index;
                $header = $candidate;

                break;
            }
        }

        if ($headerRow === null) {
            $errors[] = 'Baris judul kolom tidak ditemukan. Letakkan nama kolom pada 10 baris pertama (mis. jenis, soal, opsi_a, jawaban).';

            return [];
        }

        $map = [];
        foreach (self::HEADERS as $key) {
            $index = $this->headerIndex($header, $key);
            if ($index === null && in_array($key, ['jenis', 'soal'], true)) {
                $errors[] = "Kolom wajib '{$key}' tidak ditemukan pada baris judul.";

                return [];
            }
            $map[$key] = $index;
        }

        $rows = [];
        foreach (array_slice($data, $headerRow + 1) as $offset => $line) {
            $rowNumber = $headerRow + $offset + 2;
            $row = [];
            foreach ($map as $key => $index) {
                $row[$key] = $index === null ? '' : trim((string) ($line[$index] ?? ''));
            }

            if (implode('', $row) === '') {
                continue;
            }

            $row['_row'] = $rowNumber;
            $rows[] = $row;
        }

        return $rows;
    }

    private function parseWord(string $path, array &$errors): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $errors[] = 'Berkas Word tidak dapat dibuka. Simpan ulang sebagai .docx.';

            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            $errors[] = 'Struktur berkas Word tidak dikenali.';

            return [];
        }

        preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $matches);

        $lines = [];
        foreach ($matches[0] as $paragraph) {
            $paragraph = (string) preg_replace('#<w:br\s*/?>#', '<w:t>__BR__</w:t>', $paragraph);
            preg_match_all('#<w:t[^>]*>(.*?)</w:t>#s', $paragraph, $texts);
            $text = str_replace('__BR__', "\n", implode('', $texts[1]));
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

            foreach (explode("\n", $text) as $piece) {
                $lines[] = trim($piece);
            }
        }

        $questions = [];
        $current = null;
        $field = null;
        $jenisLabel = 'PG';

        $flush = function () use (&$questions, &$current) {
            if ($current && trim($current['soal']) !== '') {
                $questions[] = $current;
            }
            $current = null;
        };

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(PG|KOMPLEKS|JODOHKAN|ISIAN|URAIAN)$/i', $line, $heading)) {
                $flush();
                $jenisLabel = strtoupper($heading[1]);
                $field = null;

                continue;
            }

            if (preg_match('/^(\d+)\s*[.)]\s*(.+)$/u', $line, $match)) {
                $flush();
                $current = ['jenis' => $jenisLabel, 'soal' => $match[2], 'opsi' => [], 'jawaban' => '', 'deskripsi' => '', 'kesulitan' => 1, 'timer_menit' => 0];
                $field = 'soal';

                continue;
            }

            if ($jenisLabel === 'JODOHKAN' && str_starts_with($line, '-')) {
                continue;
            }

            if (! $current) {
                continue;
            }

            if (preg_match('/^([A-Ea-e])\s*[.)]\s*(.+)$/u', $line, $match)) {
                $current['opsi'][strtolower($match[1])] = $match[2];
                $field = null;

                continue;
            }

            if (preg_match('/^(?:jawaban|kunci)\s*:\s*(.*)$/iu', $line, $match)) {
                $current['jawaban'] = trim($match[1]);
                $field = null;

                continue;
            }

            if (preg_match('/^(?:pembahasan|catatan)\s*:\s*(.*)$/iu', $line, $match)) {
                $current['deskripsi'] = trim($match[1]);
                $field = 'deskripsi';

                continue;
            }

            if ($field === 'soal') {
                $current['soal'] .= ' '.$line;
            } elseif ($field === 'deskripsi') {
                $current['deskripsi'] .= ' '.$line;
            }
        }
        $flush();

        $rows = [];
        foreach ($questions as $index => $question) {
            $row = [
                'jenis' => $question['jenis'] ?? 'PG',
                'soal' => $question['soal'],
                'jawaban' => strtolower($question['jawaban']),
                'deskripsi' => $question['deskripsi'],
                'kesulitan' => (string) $question['kesulitan'],
                'timer_menit' => '0',
                '_row' => $index + 1,
            ];

            foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                $row['opsi_'.$letter] = $question['opsi'][$letter] ?? '';
            }

            $rows[] = $row;
        }

        if (! $rows) {
            $errors[] = 'Tidak ada soal yang terbaca. Gunakan format: "1. Pertanyaan", "A. Opsi", lalu "Jawaban: A".';
        }

        return $rows;
    }

    private function insertRows(BankSoal $bank, array $rows, array &$errors): int
    {
        $existing = Soal::where('bank_id', $bank->id_bank)->get(['id_soal', 'nomor_soal', 'soal'])
            ->mapWithKeys(fn (Soal $item) => [SoalText::hash($item->soal) => (int) $item->nomor_soal])
            ->all();

        $before = Soal::where('bank_id', $bank->id_bank)->count();
        $seen = $existing;
        $nextNumber = [];

        foreach ($rows as $row) {
            $label = 'Baris/soal '.($row['_row'] ?? '?');

            try {
                $data = $this->normalizeRow($row);
            } catch (ValidationException $exception) {
                $errors[] = $label.': '.$exception->validator->errors()->first();

                continue;
            }

            $hash = SoalText::hash($data['soal']);
            if (isset($seen[$hash])) {
                $errors[] = $label.': duplikat dengan soal nomor '.$seen[$hash].'.';

                continue;
            }

            $jenis = (int) $data['jenis'];
            $nextNumber[$jenis] = ($nextNumber[$jenis] ?? (int) Soal::where('bank_id', $bank->id_bank)->where('jenis', $jenis)->max('nomor_soal')) + 1;

            Soal::create([
                'bank_id' => $bank->id_bank,
                'mapel_id' => $bank->bank_mapel_id ?? 0,
                'jenis' => $jenis,
                'nomor_soal' => $nextNumber[$jenis],
                'soal' => $data['soal'],
                'opsi_a' => $data['opsi_a'],
                'opsi_b' => $data['opsi_b'],
                'opsi_c' => $data['opsi_c'],
                'opsi_d' => $data['opsi_d'],
                'opsi_e' => $data['opsi_e'],
                'jawaban' => $data['jawaban'],
                'deskripsi' => $data['deskripsi'],
                'kesulitan' => $data['kesulitan'],
                'timer' => $data['timer_menit'] > 0 ? 1 : 0,
                'timer_menit' => $data['timer_menit'],
                'tampilkan' => 0,
                'created_on' => time(),
                'updated_on' => time(),
            ]);

            $seen[$hash] = $nextNumber[$jenis];
        }

        return Soal::where('bank_id', $bank->id_bank)->count() - $before;
    }

    private function normalizeRow(array $row): array
    {
        $jenis = $this->jenisFrom((string) ($row['jenis'] ?? ''));
        if (! $jenis) {
            throw ValidationException::withMessages(['jenis' => 'Jenis soal tidak dikenali (PG, Kompleks, Jodohkan, Isian, Uraian).']);
        }

        $soal = HtmlSanitizer::clean((string) ($row['soal'] ?? ''));
        if (SoalText::plain($soal) === '') {
            throw ValidationException::withMessages(['soal' => 'Pertanyaan kosong.']);
        }

        $options = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
            $options[$letter] = HtmlSanitizer::clean((string) ($row['opsi_'.$letter] ?? ''));
        }

        $jawabanRaw = trim((string) ($row['jawaban'] ?? ''));
        $jawaban = '';
        $opsi = array_fill_keys(['a', 'b', 'c', 'd', 'e'], '');

        if (in_array($jenis, [1, 2], true)) {
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                if (SoalText::plain($options[$letter]) === '') {
                    throw ValidationException::withMessages(['opsi' => 'Opsi A sampai D wajib diisi untuk soal PG/Kompleks.']);
                }
            }
            $opsi = $options;

            if ($jenis === 1) {
                $answer = strtolower(substr($jawabanRaw, 0, 1));
                if (! in_array($answer, ['a', 'b', 'c', 'd', 'e'], true) || SoalText::plain($options[$answer]) === '') {
                    throw ValidationException::withMessages(['jawaban' => 'Kunci PG harus satu huruf A-E yang opsinya terisi.']);
                }
                $jawaban = $answer;
            } else {
                preg_match_all('/[a-e]/i', $jawabanRaw, $letters);
                $picked = array_values(array_unique(array_map('strtolower', $letters[0])));
                sort($picked);
                if (count($picked) < 2) {
                    throw ValidationException::withMessages(['jawaban' => 'Kunci Kompleks minimal dua huruf, mis. a,c.']);
                }
                foreach ($picked as $letter) {
                    if (SoalText::plain($options[$letter]) === '') {
                        throw ValidationException::withMessages(['jawaban' => "Opsi {$letter} kosong tetapi dipilih sebagai kunci."]);
                    }
                }
                $jawaban = json_encode($picked);
            }
        } elseif ($jenis === 3) {
            $pairs = $this->parsePairs($jawabanRaw);
            if (count($pairs) < 2) {
                throw ValidationException::withMessages(['jawaban' => 'Pasangan minimal dua, format kiri|kanan dipisah titik koma.']);
            }
            $jawaban = json_encode(['pairs' => $pairs]);
        } elseif ($jenis === 4) {
            $jawaban = trim(strip_tags($jawabanRaw));
            if ($jawaban === '') {
                throw ValidationException::withMessages(['jawaban' => 'Kunci isian wajib diisi.']);
            }
        } else {
            $jawaban = HtmlSanitizer::clean($jawabanRaw);
        }

        return [
            'jenis' => $jenis,
            'soal' => $soal,
            'opsi_a' => $opsi['a'],
            'opsi_b' => $opsi['b'],
            'opsi_c' => $opsi['c'],
            'opsi_d' => $opsi['d'],
            'opsi_e' => $opsi['e'],
            'jawaban' => $jawaban,
            'deskripsi' => HtmlSanitizer::clean((string) ($row['deskripsi'] ?? '')),
            'kesulitan' => max(1, min(10, (int) ($row['kesulitan'] ?? 1))),
            'timer_menit' => max(0, (int) ($row['timer_menit'] ?? 0)),
        ];
    }

    private function jenisFrom(string $value): int
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '-', '_'], '', $value);

        return match (true) {
            in_array($value, ['1', 'pg', 'pilihanganda'], true) => 1,
            in_array($value, ['2', 'kompleks', 'pgkompleks'], true) => 2,
            in_array($value, ['3', 'jodohkan', 'menjodohkan'], true) => 3,
            in_array($value, ['4', 'isian', 'isiansingkat'], true) => 4,
            in_array($value, ['5', 'uraian', 'esai', 'essay'], true) => 5,
            default => 0,
        };
    }

    private function headerIndex(array $header, string $key): ?int
    {
        foreach (self::HEADER_ALIASES[$key] ?? [$this->normalizeHeader($key)] as $alias) {
            $index = array_search($alias, $header, true);

            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function parsePairs(string $raw): array
    {
        $chunks = preg_split('/[;\n]+/', $raw) ?: [];
        $pairs = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $parts = preg_split('/\s*(?:\||=|->)\s*/', $chunk, 2);
            if (! $parts || count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                continue;
            }

            $pairs[] = [
                'kiri' => HtmlSanitizer::clean(trim($parts[0])),
                'kanan' => HtmlSanitizer::clean(trim($parts[1])),
            ];
        }

        return $pairs;
    }

    private function answerExport(int $jenis, string $jawaban): string
    {
        if ($jenis === 1) {
            return strtoupper($jawaban);
        }

        if ($jenis === 2) {
            $decoded = json_decode($jawaban, true);

            return is_array($decoded) ? strtoupper(implode(',', $decoded)) : (string) $jawaban;
        }

        if ($jenis === 3) {
            return implode('; ', array_map(
                fn (array $pair) => SoalText::plain($pair['kiri']).'|'.SoalText::plain($pair['kanan']),
                $this->decodePairs($jawaban),
            ));
        }

        return SoalText::plain($jawaban);
    }

    private function decodePairs(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $pairs = $decoded['pairs'] ?? [];
        if (! is_array($pairs)) {
            return [];
        }

        return array_values(array_filter($pairs, fn ($pair) => is_array($pair) && isset($pair['kiri'], $pair['kanan'])));
    }

    private function normalizeHeader(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9_]/', '', strtolower(trim($value)));
    }

    private function slug(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: 'bank-soal';
    }

    private function ownedBank(Request $request, int $id): BankSoal
    {
        $bank = BankSoal::findOrFail($id);

        if (! $request->user()->isAdmin()) {
            $guruId = Guru::where('id_user', $request->user()->id)->value('id_guru');
            if ((int) $bank->bank_guru_id !== (int) $guruId) {
                abort(403);
            }
        }

        return $bank;
    }
}
