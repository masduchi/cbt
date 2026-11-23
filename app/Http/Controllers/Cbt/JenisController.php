<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\BankSoal;
use App\Models\Jadwal;
use App\Models\JenisUjian;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JenisController extends Controller
{
    public function index(): Response
    {
        $jenis = JenisUjian::withCount([
            'bankSoal as bank_count',
            'jadwal as jadwal_count',
        ])->orderBy('id_jenis')->get();

        return Inertia::render('Cbt/Jenis', ['jenis' => $jenis]);
    }

    public function store(Request $request): RedirectResponse
    {
        $jenis = JenisUjian::create($this->validated($request));

        AuditLog::record('cbt.jenis', sprintf(
            'Jenis #%d %s %s dibuat',
            $jenis->id_jenis,
            $jenis->kode_jenis,
            $jenis->nama_jenis
        ));

        return back()->with('success', 'Jenis ujian berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $jenis = JenisUjian::findOrFail($id);
        $data = $this->validated($request, $id);

        $berubah = collect($data)
            ->filter(fn ($value, $key) => (string) $jenis->getAttribute($key) !== (string) $value)
            ->keys()
            ->all();

        $jenis->update($data);

        AuditLog::record('cbt.jenis', sprintf(
            'Jenis #%d %s %s diubah (%s)',
            $jenis->id_jenis,
            $jenis->kode_jenis,
            $jenis->nama_jenis,
            $berubah === [] ? 'tanpa perubahan nilai' : implode(', ', $berubah)
        ));

        return back()->with('success', 'Jenis ujian berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if (Jadwal::where('id_jenis', $id)->exists()) {
            return back()->with('error', 'Jenis ujian masih dipakai pada jadwal ujian.');
        }

        if (BankSoal::where('bank_jenis_id', $id)->exists()) {
            return back()->with('error', 'Jenis ujian masih dipakai pada bank soal.');
        }

        $jenis = JenisUjian::findOrFail($id);
        $jenis->delete();

        AuditLog::record('cbt.jenis.delete', sprintf('Jenis #%d %s %s dihapus', $jenis->id_jenis, $jenis->kode_jenis, $jenis->nama_jenis));

        return back()->with('success', 'Jenis ujian berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nama_jenis' => ['required', 'string', 'max:50'],
            'kode_jenis' => ['required', 'string', 'max:10', Rule::unique('cbt_jenis', 'kode_jenis')->ignore($ignoreId, 'id_jenis')],
        ]);
    }
}
