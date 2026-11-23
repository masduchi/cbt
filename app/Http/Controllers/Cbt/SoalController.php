<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Soal;
use App\Models\SoalRubrik;
use App\Support\AuditLog;
use App\Support\BankSoalCounter;
use App\Support\HtmlSanitizer;
use App\Support\SoalAssembler;
use App\Support\SoalText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SoalController extends Controller
{
    private const LETTERS = ['a', 'b', 'c', 'd', 'e'];

    public function index(Request $request, int $id): Response
    {
        $bank = $this->ownedBank($request, $id);

        $soals = Soal::where('bank_id', $bank->id_bank)
            ->with('rubrik')
            ->orderBy('jenis')
            ->orderBy('nomor_soal')
            ->get()
            ->map(fn (Soal $soal) => $this->presentSoal($soal));

        return Inertia::render('Cbt/BankSoal/Show', [
            'bank' => [
                ...$bank->only([
                    'id_bank', 'bank_kode', 'bank_nama', 'bank_level', 'bank_kelas', 'opsi', 'kkm',
                    'tampil_pg', 'tampil_kompleks', 'tampil_jodohkan', 'tampil_isian', 'tampil_esai',
                    'bobot_pg', 'bobot_kompleks', 'bobot_jodohkan', 'bobot_isian', 'bobot_esai',
                    'status_soal', 'deskripsi', 'bank_mapel_id', 'bank_jenis_id',
                ]),
                'mapel' => $bank->mapel?->nama_mapel,
                'jenis' => $bank->jenis?->nama_jenis,
            ],
            'soals' => $soals,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function assemble(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);

        $data = $request->validate([
            'komposisi' => ['required', 'array'],
            'komposisi.*.mudah' => ['nullable', 'integer', 'min:0', 'max:500'],
            'komposisi.*.sedang' => ['nullable', 'integer', 'min:0', 'max:500'],
            'komposisi.*.sulit' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $hasil = SoalAssembler::assemble($bank, $data['komposisi']);

        AuditLog::record('cbt.rakit', sprintf('Bank #%d: %d soal dirakit otomatis', $bank->id_bank, $hasil['total']));

        return back()->with('success', 'Perakitan selesai: '.$hasil['total'].' soal dipilih otomatis.');
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);
        $data = $this->validated($request);
        $jenis = (int) $data['jenis'];

        if (! $request->boolean('paksa')) {
            $this->guardDuplicate($bank, (string) $data['soal']);
        }

        $data['bank_id'] = $bank->id_bank;
        $data['mapel_id'] = $bank->bank_mapel_id ?? 0;
        $data['nomor_soal'] = (int) Soal::where('bank_id', $bank->id_bank)->where('jenis', $jenis)->max('nomor_soal') + 1;
        $data['created_on'] = time();
        $data['updated_on'] = time();
        $data['tampilkan'] = 0;
        $data['deskripsi'] = $data['deskripsi'] ?? '';

        $this->attachFiles($request, $data);

        $rubrik = $data['rubrik'] ?? [];
        unset($data['rubrik']);

        $soal = Soal::create($data);
        $this->simpanRubrik((int) $soal->id_soal, $rubrik);
        $this->catatRubrik($soal, [], $rubrik);
        BankSoalCounter::sync($bank);

        AuditLog::record('cbt.soal', sprintf('Bank #%d soal #%d (jenis %d) dibuat', $bank->id_bank, $soal->id_soal, $jenis));

        return back()->with('success', 'Soal berhasil disimpan.');
    }

    public function update(Request $request, int $id, int $soalId): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);
        $soal = Soal::where('bank_id', $bank->id_bank)->findOrFail($soalId);
        $data = $this->validated($request, (int) $soal->jenis);
        $data['jenis'] = (int) $soal->jenis;

        if (! $request->boolean('paksa')) {
            $this->guardDuplicate($bank, (string) $data['soal'], (int) $soal->id_soal);
        }

        $data['updated_on'] = time();
        $data['deskripsi'] = $data['deskripsi'] ?? '';

        $this->attachFiles($request, $data, $soal);

        $rubrik = $data['rubrik'] ?? [];
        unset($data['rubrik']);
        $rubrikLama = SoalRubrik::where('id_soal', $soal->id_soal)
            ->orderBy('urutan')
            ->get()
            ->map(fn (SoalRubrik $item) => [
                'id_rubrik' => (int) $item->id_rubrik,
                'kriteria' => (string) $item->kriteria,
                'skor_maks' => (float) $item->skor_maks,
            ])
            ->values()
            ->all();

        $soal->update($data);
        $this->simpanRubrik((int) $soal->id_soal, $rubrik);
        $this->catatRubrik($soal, $rubrikLama, $rubrik);
        BankSoalCounter::sync($bank);

        AuditLog::record('cbt.soal', sprintf('Bank #%d soal #%d diperbarui', $bank->id_bank, $soal->id_soal));

        return back()->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id, int $soalId): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);
        $soal = Soal::where('bank_id', $bank->id_bank)->findOrFail($soalId);

        $this->deleteFiles($soal);
        SoalRubrik::where('id_soal', $soal->id_soal)->delete();
        $soal->delete();

        $remaining = Soal::where('bank_id', $bank->id_bank)->where('jenis', $soal->jenis)
            ->orderBy('nomor_soal')->get();

        foreach ($remaining as $index => $item) {
            $item->update(['nomor_soal' => $index + 1]);
        }

        BankSoalCounter::sync($bank);

        AuditLog::record('cbt.soal.delete', sprintf('Bank #%d soal #%d dihapus', $bank->id_bank, $soal->id_soal));

        return back()->with('success', 'Soal berhasil dihapus.');
    }

    public function select(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);

        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = Soal::where('bank_id', $bank->id_bank)
            ->whereIn('id_soal', $data['ids'] ?? [])
            ->pluck('id_soal');

        DB::transaction(function () use ($bank, $ids) {
            Soal::where('bank_id', $bank->id_bank)->update(['tampilkan' => 0]);
            Soal::whereIn('id_soal', $ids)->update(['tampilkan' => 1]);
        });

        BankSoalCounter::sync($bank);

        AuditLog::record('cbt.soal.pilih', sprintf('Bank #%d: %d soal dipilih untuk ujian', $bank->id_bank, $ids->count()));

        return back()->with('success', 'Pilihan soal untuk ujian diperbarui.');
    }

    private function validated(Request $request, ?int $jenis = null): array
    {
        $jenis = (int) ($jenis ?? $request->input('jenis'));

        $rules = [
            'jenis' => ['required', 'integer', 'in:1,2,3,4,5'],
            'soal' => ['required', 'string'],
            'deskripsi' => ['nullable', 'string'],
            'kesulitan' => ['nullable', 'integer', 'min:1', 'max:10'],
            'timer' => ['nullable', 'boolean'],
            'timer_menit' => ['nullable', 'integer', 'min:0'],
            'paksa' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,mp3,wav,mp4', 'max:20480'],
        ];

        foreach (self::LETTERS as $letter) {
            $rules['file_'.$letter] = ['nullable', 'file', 'mimes:jpg,jpeg,png,gif', 'max:5120'];
        }

        if (in_array($jenis, [1, 2], true)) {
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $rules['opsi_'.$letter] = ['required', 'string'];
            }
            $rules['opsi_e'] = ['nullable', 'string'];
        }

        if ($jenis === 1) {
            $rules['jawaban'] = ['required', Rule::in(self::LETTERS)];
        } elseif ($jenis === 2) {
            $rules['jawaban'] = ['required', 'array', 'min:2'];
            $rules['jawaban.*'] = [Rule::in(self::LETTERS)];
        } elseif ($jenis === 3) {
            $rules['pairs'] = ['required', 'array', 'min:2'];
            $rules['pairs.*.kiri'] = ['required', 'string'];
            $rules['pairs.*.kanan'] = ['required', 'string'];
        } elseif ($jenis === 4) {
            $rules['jawaban'] = ['required', 'string'];
        } else {
            $rules['jawaban'] = ['nullable', 'string'];
            $rules['rubrik'] = ['nullable', 'array', 'max:20'];
            $rules['rubrik.*.id_rubrik'] = ['nullable', 'integer'];
            $rules['rubrik.*.kriteria'] = ['required', 'string', 'max:200'];
            $rules['rubrik.*.skor_maks'] = ['required', 'numeric', 'min:0.01', 'max:1000'];
        }

        $data = $request->validate($rules);

        $data['jenis'] = $jenis;
        $data['kesulitan'] = $data['kesulitan'] ?? 1;
        $data['timer'] = $data['timer'] ?? 0;
        $data['timer_menit'] = $data['timer_menit'] ?? 0;
        unset($data['paksa']);

        $data['soal'] = HtmlSanitizer::clean($data['soal']);
        $data['deskripsi'] = HtmlSanitizer::clean($data['deskripsi'] ?? '');

        if ($jenis === 2) {
            $data['jawaban'] = json_encode(array_values(array_unique($data['jawaban'])));
        } elseif ($jenis === 3) {
            $pairs = array_map(fn (array $pair) => [
                'kiri' => HtmlSanitizer::clean($pair['kiri']),
                'kanan' => HtmlSanitizer::clean($pair['kanan']),
            ], $data['pairs']);

            $data['jawaban'] = json_encode(['pairs' => $pairs]);
            unset($data['pairs']);
        } elseif ($jenis === 4) {
            $data['jawaban'] = trim(strip_tags($data['jawaban']));
        }

        $data['rubrik'] = $jenis === 5 ? $this->normalisasiRubrik($data['rubrik'] ?? []) : [];

        if (in_array($jenis, [1, 2], true)) {
            foreach (self::LETTERS as $letter) {
                $data['opsi_'.$letter] = HtmlSanitizer::clean((string) ($data['opsi_'.$letter] ?? ''));
            }
        } else {
            foreach (self::LETTERS as $letter) {
                unset($data['opsi_'.$letter]);
            }
        }

        return $data;
    }

    /**
     * Merapikan baris rubrik dari form: membuang kriteria kosong dan
     * menormalkan urutan serta besar skor.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array{id_rubrik: int|null, kriteria: string, skor_maks: float}>
     */
    private function normalisasiRubrik(array $rows): array
    {
        $hasil = [];

        foreach (array_values($rows) as $row) {
            $kriteria = HtmlSanitizer::clean(trim(strip_tags((string) ($row['kriteria'] ?? ''))));

            if ($kriteria === '') {
                continue;
            }

            $hasil[] = [
                'id_rubrik' => isset($row['id_rubrik']) && $row['id_rubrik'] !== '' ? (int) $row['id_rubrik'] : null,
                'kriteria' => mb_substr($kriteria, 0, 200),
                'skor_maks' => round(max(0.01, (float) ($row['skor_maks'] ?? 0)), 2),
            ];
        }

        return $hasil;
    }

    /**
     * Menyimpan rubrik soal esai: memperbarui baris yang sudah ada, menambah
     * baris baru, dan menghapus baris yang tidak lagi dikirim.
     *
     * @param  array<int, array{id_rubrik: int|null, kriteria: string, skor_maks: float}>  $rows
     */
    private function simpanRubrik(int $idSoal, array $rows): void
    {
        $tersimpan = SoalRubrik::where('id_soal', $idSoal)->pluck('id_rubrik')->all();
        $dipakai = [];

        DB::transaction(function () use ($idSoal, $rows, $tersimpan, &$dipakai) {
            foreach ($rows as $index => $row) {
                $id = in_array((int) ($row['id_rubrik'] ?? 0), $tersimpan, true) ? (int) $row['id_rubrik'] : null;

                $rubrik = $id
                    ? SoalRubrik::where('id_soal', $idSoal)->findOrFail($id)
                    : new SoalRubrik(['id_soal' => $idSoal]);

                $rubrik->fill([
                    'urutan' => $index + 1,
                    'kriteria' => $row['kriteria'],
                    'skor_maks' => $row['skor_maks'],
                ])->save();

                $dipakai[] = (int) $rubrik->id_rubrik;
            }

            SoalRubrik::where('id_soal', $idSoal)
                ->when($dipakai !== [], fn ($query) => $query->whereNotIn('id_rubrik', $dipakai))
                ->delete();
        });
    }

    /**
     * Mencatat perubahan rubrik agar riwayat penilaian bisa ditelusuri.
     *
     * @param  array<int, array{id_rubrik?: int|null, kriteria: string, skor_maks: float}>  $lama
     * @param  array<int, array{id_rubrik?: int|null, kriteria: string, skor_maks: float}>  $baru
     */
    private function catatRubrik(Soal $soal, array $lama, array $baru): void
    {
        $ringkas = fn (array $rows) => collect($rows)
            ->map(fn (array $row) => $row['kriteria'].' (maks '.rtrim(rtrim(number_format((float) $row['skor_maks'], 2, '.', ''), '0'), '.').')')
            ->implode('; ');

        $sebelum = $ringkas($lama);
        $sesudah = $ringkas($baru);

        if ($sebelum === $sesudah) {
            return;
        }

        AuditLog::record('cbt.rubrik', sprintf(
            'Rubrik soal #%d: [%s] → [%s]',
            $soal->id_soal,
            $sebelum,
            $sesudah
        ));
    }

    private function attachFiles(Request $request, array &$data, ?Soal $soal = null): void
    {
        $fields = ['file', ...array_map(fn ($letter) => 'file_'.$letter, self::LETTERS)];

        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                if ($soal?->{$field}) {
                    Storage::disk('public')->delete($soal->{$field});
                }
                $data[$field] = $request->file($field)->store('soal', 'public');
            } else {
                unset($data[$field]);
            }
        }
    }

    private function deleteFiles(Soal $soal): void
    {
        foreach (['file', ...array_map(fn ($letter) => 'file_'.$letter, self::LETTERS)] as $field) {
            if ($soal->{$field}) {
                Storage::disk('public')->delete($soal->{$field});
            }
        }
    }

    private function presentSoal(Soal $soal): array
    {
        $jawaban = $soal->jawaban;

        if ((int) $soal->jenis === 2) {
            $decoded = json_decode((string) $jawaban, true);
            $jawaban = is_array($decoded) ? $decoded : [];
        } elseif ((int) $soal->jenis === 3) {
            $decoded = json_decode((string) $jawaban, true);
            $jawaban = is_array($decoded) ? ($decoded['pairs'] ?? []) : [];
        }

        return [
            ...$soal->only([
                'id_soal', 'bank_id', 'jenis', 'nomor_soal', 'soal', 'deskripsi', 'kesulitan',
                'timer', 'timer_menit', 'tampilkan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
                'file', 'file_a', 'file_b', 'file_c', 'file_d', 'file_e',
            ]),
            'jawaban' => $jawaban,
            'rubrik' => $soal->rubrik
                ->map(fn (SoalRubrik $item) => [
                    'id_rubrik' => (int) $item->id_rubrik,
                    'kriteria' => (string) $item->kriteria,
                    'skor_maks' => (float) $item->skor_maks,
                ])
                ->values()
                ->all(),
        ];
    }

    private function guardDuplicate(BankSoal $bank, string $html, ?int $exceptId = null): void
    {
        $hash = SoalText::hash($html);
        if ($hash === '') {
            return;
        }

        $duplicate = Soal::where('bank_id', $bank->id_bank)
            ->when($exceptId, fn ($query) => $query->where('id_soal', '!=', $exceptId))
            ->get(['id_soal', 'nomor_soal', 'soal'])
            ->first(fn (Soal $item) => SoalText::hash($item->soal) === $hash);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'soal' => "Soal ini tampak sama dengan soal nomor {$duplicate->nomor_soal} pada bank ini. Periksa kembali, atau paksa simpan bila memang berbeda.",
            ]);
        }
    }

    private function ownedBank(Request $request, int $id): BankSoal
    {
        $bank = BankSoal::with(['mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis'])->findOrFail($id);

        if (! $request->user()->isAdmin()) {
            $guruId = Guru::where('id_user', $request->user()->id)->value('id_guru');
            if ((int) $bank->bank_guru_id !== (int) $guruId) {
                abort(403);
            }
        }

        return $bank;
    }
}
