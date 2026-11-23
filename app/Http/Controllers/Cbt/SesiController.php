<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Sesi;
use App\Models\SesiSiswa;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SesiController extends Controller
{
    public function index(): Response
    {
        $sesi = Sesi::withCount('siswaSesi')
            ->orderBy('waktu_mulai')
            ->get()
            ->map(fn (Sesi $item) => [
                'id_sesi' => $item->id_sesi,
                'nama_sesi' => $item->nama_sesi,
                'kode_sesi' => $item->kode_sesi,
                'waktu_mulai' => substr((string) $item->waktu_mulai, 0, 5),
                'waktu_akhir' => substr((string) $item->waktu_akhir, 0, 5),
                'aktif' => (int) $item->aktif,
                'siswa_count' => $item->siswa_sesi_count,
            ]);

        return Inertia::render('Cbt/Sesi', ['sesi' => $sesi]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sesi = Sesi::create($this->validated($request));

        AuditLog::record('cbt.sesi', sprintf(
            'Sesi #%d %s dibuat (%s-%s)',
            $sesi->id_sesi,
            $sesi->kode_sesi,
            substr((string) $sesi->waktu_mulai, 0, 5),
            substr((string) $sesi->waktu_akhir, 0, 5)
        ));

        return back()->with('success', 'Sesi berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $sesi = Sesi::findOrFail($id);
        $data = $this->validated($request, $id);

        $berubah = collect($data)
            ->filter(fn ($value, $key) => $this->banding($sesi->getAttribute($key)) !== $this->banding($value))
            ->keys()
            ->all();

        $sesi->update($data);

        AuditLog::record('cbt.sesi', sprintf(
            'Sesi #%d %s diubah (%s)',
            $sesi->id_sesi,
            $sesi->kode_sesi,
            $berubah === [] ? 'tanpa perubahan nilai' : implode(', ', $berubah)
        ));

        return back()->with('success', 'Sesi berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if (SesiSiswa::where('sesi_id', $id)->exists()) {
            return back()->with('error', 'Sesi masih dipakai pada penempatan siswa.');
        }

        $sesi = Sesi::findOrFail($id);
        $sesi->delete();

        AuditLog::record('cbt.sesi.delete', sprintf('Sesi #%d %s dihapus', $sesi->id_sesi, $sesi->kode_sesi));

        return back()->with('success', 'Sesi berhasil dihapus.');
    }

    /**
     * Kolom time tersimpan sebagai H:i:s sementara input H:i, jadi detiknya dipotong
     * agar perubahan yang sebenarnya tidak ikut tercatat.
     */
    private function banding(mixed $value): string
    {
        $value = (string) $value;

        return strlen($value) === 8 && str_contains($value, ':') ? substr($value, 0, 5) : $value;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nama_sesi' => ['required', 'string', 'max:50'],
            'kode_sesi' => ['required', 'string', 'max:10', Rule::unique('cbt_sesi', 'kode_sesi')->ignore($ignoreId, 'id_sesi')],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_akhir' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'aktif' => ['boolean'],
        ]);
    }
}
