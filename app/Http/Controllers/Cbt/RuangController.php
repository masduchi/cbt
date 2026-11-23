<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Ruang;
use App\Models\SesiSiswa;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RuangController extends Controller
{
    public function index(): Response
    {
        $ruang = Ruang::withCount('siswaSesi')
            ->orderBy('nama_ruang')
            ->get()
            ->map(fn (Ruang $item) => [
                'id_ruang' => $item->id_ruang,
                'nama_ruang' => $item->nama_ruang,
                'kode_ruang' => $item->kode_ruang,
                'siswa_count' => $item->siswa_sesi_count,
            ]);

        return Inertia::render('Cbt/Ruang', ['ruang' => $ruang]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ruang = Ruang::create($this->validated($request));

        AuditLog::record('cbt.ruang', sprintf('Ruang #%d %s dibuat', $ruang->id_ruang, $ruang->kode_ruang));

        return back()->with('success', 'Ruang berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ruang = Ruang::findOrFail($id);
        $data = $this->validated($request, $id);

        $berubah = collect($data)
            ->filter(fn ($value, $key) => (string) $ruang->getAttribute($key) !== (string) $value)
            ->keys()
            ->all();

        $ruang->update($data);

        AuditLog::record('cbt.ruang', sprintf(
            'Ruang #%d %s diubah (%s)',
            $ruang->id_ruang,
            $ruang->kode_ruang,
            $berubah === [] ? 'tanpa perubahan nilai' : implode(', ', $berubah)
        ));

        return back()->with('success', 'Ruang berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if (SesiSiswa::where('ruang_id', $id)->exists()) {
            return back()->with('error', 'Ruang masih dipakai pada penempatan siswa.');
        }

        $ruang = Ruang::findOrFail($id);
        $ruang->delete();

        AuditLog::record('cbt.ruang.delete', sprintf('Ruang #%d %s dihapus', $ruang->id_ruang, $ruang->kode_ruang));

        return back()->with('success', 'Ruang berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nama_ruang' => ['required', 'string', 'max:50'],
            'kode_ruang' => ['required', 'string', 'max:10', Rule::unique('cbt_ruang', 'kode_ruang')->ignore($ignoreId, 'id_ruang')],
        ]);
    }
}
