<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\KelompokMapel;
use App\Models\Mapel;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KelompokMapelController extends Controller
{
    private const KATEGORI = ['WAJIB', 'PEMINATAN', 'MULOK', 'PAI (Kemenag)'];

    public function index(): Response
    {
        $usage = Mapel::query()
            ->selectRaw('kelompok, COUNT(*) as total')
            ->whereNotNull('kelompok')
            ->groupBy('kelompok')
            ->pluck('total', 'kelompok');

        return Inertia::render('Master/KelompokMapel', [
            'kelompok' => KelompokMapel::orderBy('id_parent')
                ->orderBy('kode_kel_mapel')
                ->get()
                ->map(fn (KelompokMapel $row) => [
                    'id_kel_mapel' => $row->id_kel_mapel,
                    'kode_kel_mapel' => $row->kode_kel_mapel,
                    'nama_kel_mapel' => $row->nama_kel_mapel,
                    'kategori' => $row->kategori,
                    'id_parent' => (int) $row->id_parent,
                    'parent' => $row->id_parent ? KelompokMapel::where('id_kel_mapel', $row->id_parent)->value('nama_kel_mapel') : null,
                    'mapel_count' => (int) ($usage[$row->kode_kel_mapel] ?? 0),
                ]),
            'parents' => KelompokMapel::where('id_parent', 0)
                ->orderBy('kode_kel_mapel')
                ->get(['id_kel_mapel', 'nama_kel_mapel']),
            'kategoriOptions' => self::KATEGORI,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kelompok = KelompokMapel::create($this->validated($request));

        AuditLog::record('master.kelompok-mapel', 'Kelompok '.$kelompok->kode_kel_mapel.' ditambahkan');

        return back()->with('success', 'Kelompok mata pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $kelompok = KelompokMapel::findOrFail($id);
        $previousKode = $kelompok->kode_kel_mapel;
        $data = $this->validated($request, $kelompok->id_kel_mapel);

        if (! $kelompok->update($data)) {
            return back()->with('error', 'Kelompok mata pelajaran gagal diperbarui.');
        }

        if ($previousKode !== $data['kode_kel_mapel']) {
            Mapel::where('kelompok', $previousKode)->update(['kelompok' => $data['kode_kel_mapel']]);
        }

        AuditLog::record('master.kelompok-mapel', 'Kelompok #'.$kelompok->id_kel_mapel.' '.$data['kode_kel_mapel'].' diperbarui');

        return back()->with('success', 'Kelompok mata pelajaran berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $kelompok = KelompokMapel::findOrFail($id);

        if (KelompokMapel::where('id_parent', $kelompok->id_kel_mapel)->exists()) {
            return back()->with('error', 'Kelompok masih memiliki sub-kelompok. Hapus sub-kelompok terlebih dahulu.');
        }

        $used = Mapel::where('kelompok', $kelompok->kode_kel_mapel)->count();

        if ($used > 0) {
            return back()->with('error', "Kelompok masih dipakai {$used} mata pelajaran.");
        }

        $kelompok->delete();

        AuditLog::record('master.kelompok-mapel.delete', 'Kelompok #'.$kelompok->id_kel_mapel.' '.$kelompok->kode_kel_mapel.' dihapus');

        return back()->with('success', 'Kelompok mata pelajaran berhasil dihapus.');
    }

    /**
     * @return array{kode_kel_mapel: string, nama_kel_mapel: string, kategori: string, id_parent: int}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'kode_kel_mapel' => [
                'required', 'string', 'max:10',
                Rule::unique('master_kelompok_mapel', 'kode_kel_mapel')->ignore($ignoreId, 'id_kel_mapel'),
            ],
            'nama_kel_mapel' => ['required', 'string', 'max:100'],
            'kategori' => ['required', 'string', Rule::in(self::KATEGORI)],
            'id_parent' => ['nullable', 'integer', Rule::exists('master_kelompok_mapel', 'id_kel_mapel')->where('id_parent', 0)],
        ]);

        $data['kode_kel_mapel'] = strtoupper(trim($data['kode_kel_mapel']));
        $data['id_parent'] = (int) ($data['id_parent'] ?? 0);

        return $data;
    }
}
