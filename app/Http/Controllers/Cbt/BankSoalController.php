<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\JenisUjian;
use App\Models\Kelas;
use App\Models\LevelKelas;
use App\Models\Mapel;
use App\Models\Semester;
use App\Models\Soal;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankSoalController extends Controller
{
    use ResolvesPerPage;

    private const TYPES = [
        'pg' => 1,
        'kompleks' => 2,
        'jodohkan' => 3,
        'isian' => 4,
        'esai' => 5,
    ];

    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();

        $query = BankSoal::query()
            ->with([
                'mapel:id_mapel,nama_mapel,kode',
                'jenis:id_jenis,nama_jenis,kode_jenis',
                'guru:id_guru,nama_guru',
            ])
            ->withCount([
                'soal as pg_count' => fn ($q) => $q->where('jenis', 1),
                'soal as kompleks_count' => fn ($q) => $q->where('jenis', 2),
                'soal as jodohkan_count' => fn ($q) => $q->where('jenis', 3),
                'soal as isian_count' => fn ($q) => $q->where('jenis', 4),
                'soal as esai_count' => fn ($q) => $q->where('jenis', 5),
                'soal as selected_count' => fn ($q) => $q->where('tampilkan', 1),
            ])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt);

        $guruId = $this->guruId($user);

        if (! $user->isAdmin()) {
            $query->where('bank_guru_id', $guruId ?? 0);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('bank_nama', 'like', "%{$search}%")
                    ->orWhere('bank_kode', 'like', "%{$search}%");
            });
        }

        if ($mapelId = $request->query('mapel')) {
            $query->where('bank_mapel_id', $mapelId);
        }

        if ($jenisId = $request->query('jenis')) {
            $query->where('bank_jenis_id', $jenisId);
        }

        $banks = $query->orderByDesc('id_bank')->paginate($this->perPage($request))->withQueryString()->through(fn (BankSoal $bank) => [
            'id_bank' => $bank->id_bank,
            'bank_kode' => $bank->bank_kode,
            'bank_nama' => $bank->bank_nama,
            'bank_level' => $bank->bank_level,
            'bank_kelas' => $bank->bank_kelas,
            'mapel' => $bank->mapel?->nama_mapel,
            'jenis' => $bank->jenis?->nama_jenis,
            'jenis_kode' => $bank->jenis?->kode_jenis,
            'guru' => $bank->guru?->nama_guru,
            'guru_id' => $bank->bank_guru_id,
            'mapel_id' => $bank->bank_mapel_id,
            'jenis_id' => $bank->bank_jenis_id,
            'kkm' => $bank->kkm,
            'opsi' => $bank->opsi,
            'status' => (int) $bank->status,
            'status_soal' => (int) $bank->status_soal,
            'tampil_pg' => $bank->tampil_pg,
            'tampil_kompleks' => $bank->tampil_kompleks,
            'tampil_jodohkan' => $bank->tampil_jodohkan,
            'tampil_isian' => $bank->tampil_isian,
            'tampil_esai' => $bank->tampil_esai,
            'bobot_pg' => $bank->bobot_pg,
            'bobot_kompleks' => $bank->bobot_kompleks,
            'bobot_jodohkan' => $bank->bobot_jodohkan,
            'bobot_isian' => $bank->bobot_isian,
            'bobot_esai' => $bank->bobot_esai,
            'deskripsi' => $bank->deskripsi,
            'soal_agama' => $bank->soal_agama,
            'counts' => [
                'pg' => $bank->pg_count,
                'kompleks' => $bank->kompleks_count,
                'jodohkan' => $bank->jodohkan_count,
                'isian' => $bank->isian_count,
                'esai' => $bank->esai_count,
                'selected' => $bank->selected_count,
            ],
            'has_jadwal' => Jadwal::where('id_bank', $bank->id_bank)->exists(),
        ]);

        return Inertia::render('Cbt/BankSoal/Index', [
            'banks' => $banks,
            'mapels' => Mapel::orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kode']),
            'jenisOptions' => JenisUjian::orderBy('id_jenis')->get(['id_jenis', 'nama_jenis', 'kode_jenis']),
            'levels' => LevelKelas::orderBy('id_level')->get(['id_level', 'level']),
            'kelasOptions' => $tp && $smt
                ? Kelas::where('id_tp', $tp)->where('id_smt', $smt)->orderBy('nama_kelas')->get(['id_kelas', 'nama_kelas'])
                : [],
            'gurus' => Guru::orderBy('nama_guru')->get(['id_guru', 'nama_guru']),
            'isAdmin' => $user->isAdmin(),
            'filters' => [
                'search' => $request->query('search', ''),
                'mapel' => $request->query('mapel', ''),
                'jenis' => $request->query('jenis', ''),
            ],
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $this->validated($request);
        $data['id_tp'] = $tp;
        $data['id_smt'] = $smt;
        $data['bank_guru_id'] = $request->user()->isAdmin()
            ? ($data['guru_id'] ?? null)
            : $this->guruId($request->user());
        unset($data['guru_id']);

        $bank = BankSoal::create($data);

        AuditLog::record('cbt.bank', sprintf('Bank #%d %s dibuat', $bank->id_bank, $bank->bank_kode));

        return back()->with('success', 'Bank soal berhasil dibuat.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);

        $data = $this->validated($request, $bank->id_bank);
        $data['bank_guru_id'] = $request->user()->isAdmin()
            ? ($data['guru_id'] ?? $bank->bank_guru_id)
            : $bank->bank_guru_id;
        unset($data['guru_id']);

        $berubah = collect($data)
            ->filter(fn ($value, $key) => (string) $bank->getAttribute($key) !== (string) $value)
            ->keys()
            ->all();

        $bank->update($data);

        AuditLog::record('cbt.bank', sprintf(
            'Bank #%d %s diubah (%s)',
            $bank->id_bank,
            $bank->bank_kode,
            $berubah === [] ? 'tanpa perubahan nilai' : implode(', ', $berubah)
        ));

        return back()->with('success', 'Bank soal berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);

        if (Jadwal::where('id_bank', $bank->id_bank)->exists()) {
            return back()->with('error', 'Bank soal masih dipakai pada jadwal ujian.');
        }

        DB::transaction(function () use ($bank) {
            Soal::where('bank_id', $bank->id_bank)->delete();
            $bank->delete();
        });

        AuditLog::record('cbt.bank.delete', sprintf('Bank #%d %s beserta soalnya dihapus', $bank->id_bank, $bank->bank_kode));

        return back()->with('success', 'Bank soal berhasil dihapus.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $query = BankSoal::whereIn('id_bank', $data['ids']);

        if (! $user->isAdmin()) {
            $query->where('bank_guru_id', $this->guruId($user) ?? 0);
        }

        $deleted = 0;
        $deletedIds = [];
        $blocked = [];

        foreach ($query->get() as $bank) {
            if (Jadwal::where('id_bank', $bank->id_bank)->exists()) {
                $blocked[] = $bank->bank_nama;

                continue;
            }

            DB::transaction(function () use ($bank) {
                Soal::where('bank_id', $bank->id_bank)->delete();
                $bank->delete();
            });

            $deletedIds[] = (int) $bank->id_bank;
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->with('error', 'Tidak ada bank soal yang bisa dihapus. '.$this->blockedLabel($blocked));
        }

        AuditLog::record('cbt.bank.delete', sprintf('%d bank soal dihapus: #%s', $deleted, implode(', #', $deletedIds)));

        return back()->with('success', "{$deleted} bank soal dihapus. ".$this->blockedLabel($blocked));
    }

    public function copy(Request $request, int $id): RedirectResponse
    {
        $bank = $this->ownedBank($request, $id);
        [$tp, $smt] = $this->activePeriod();
        $hasil = null;

        DB::transaction(function () use ($bank, $tp, $smt, &$hasil) {
            $newBank = $bank->replicate();
            $newBank->bank_kode = $this->uniqueKode($bank->bank_kode.'_COPY');
            $newBank->bank_nama = $bank->bank_nama.' (Copy)';
            $newBank->id_tp = $tp;
            $newBank->id_smt = $smt;
            $newBank->status_soal = 0;
            $newBank->save();

            foreach (Soal::where('bank_id', $bank->id_bank)->get() as $soal) {
                $copy = $soal->replicate();
                $copy->bank_id = $newBank->id_bank;
                $copy->created_on = time();
                $copy->updated_on = time();
                $copy->save();
            }

            $hasil = $newBank;
        });

        AuditLog::record('cbt.bank', sprintf(
            'Bank #%d %s diduplikasi dari bank #%d %s',
            $hasil?->id_bank,
            $hasil?->bank_kode,
            $bank->id_bank,
            $bank->bank_kode
        ));

        return back()->with('success', 'Bank soal berhasil diduplikasi.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'bank_kode' => ['required', 'string', 'max:255', Rule::unique('cbt_bank_soal', 'bank_kode')->ignore($ignoreId, 'id_bank')],
            'bank_nama' => ['required', 'string', 'max:250'],
            'bank_mapel_id' => ['required', 'integer', 'exists:master_mapel,id_mapel'],
            'bank_jenis_id' => ['required', 'integer', 'exists:cbt_jenis,id_jenis'],
            'bank_level' => ['required', 'integer', 'exists:level_kelas,id_level'],
            'bank_kelas' => ['nullable', 'array'],
            'bank_kelas.*' => ['integer', 'exists:master_kelas,id_kelas'],
            'guru_id' => ['nullable', 'integer', 'exists:master_guru,id_guru'],
            'opsi' => ['required', 'integer', 'in:4,5'],
            'kkm' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tampil_pg' => ['nullable', 'integer', 'min:0'],
            'tampil_kompleks' => ['nullable', 'integer', 'min:0'],
            'tampil_jodohkan' => ['nullable', 'integer', 'min:0'],
            'tampil_isian' => ['nullable', 'integer', 'min:0'],
            'tampil_esai' => ['nullable', 'integer', 'min:0'],
            'bobot_pg' => ['nullable', 'integer', 'min:0'],
            'bobot_kompleks' => ['nullable', 'integer', 'min:0'],
            'bobot_jodohkan' => ['nullable', 'integer', 'min:0'],
            'bobot_isian' => ['nullable', 'integer', 'min:0'],
            'bobot_esai' => ['nullable', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
            'soal_agama' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);

        $data['bank_kelas'] = implode(',', $data['bank_kelas'] ?? []);
        $data['kkm'] = $data['kkm'] ?? 0;
        $data['status'] = $data['status'] ?? 0;
        $data['soal_agama'] = $data['soal_agama'] ?? '-';

        foreach (self::TYPES as $key => $_) {
            $data['bobot_'.$key] = $data['bobot_'.$key] ?? 0;
            $data['tampil_'.$key] = $data['tampil_'.$key] ?? 0;
            $data['jml_'.($key === 'pg' ? 'soal' : $key)] = $data['tampil_'.$key];
        }

        return $data;
    }

    private function ownedBank(Request $request, int $id): BankSoal
    {
        $bank = BankSoal::findOrFail($id);

        if (! $request->user()->isAdmin() && (int) $bank->bank_guru_id !== (int) $this->guruId($request->user())) {
            abort(403);
        }

        return $bank;
    }

    private function guruId($user): ?int
    {
        return Guru::where('id_user', $user->id)->value('id_guru');
    }

    /**
     * @param  array<int, string>  $names
     */
    private function blockedLabel(array $names): string
    {
        return $names === [] ? '' : count($names).' bank dilewati karena masih dipakai jadwal: '.implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? '…' : '');
    }

    private function uniqueKode(string $base): string
    {
        $kode = $base;
        $i = 1;

        while (BankSoal::where('bank_kode', $kode)->exists()) {
            $kode = $base.$i;
            $i++;
        }

        return $kode;
    }

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel(?int $tp, ?int $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
