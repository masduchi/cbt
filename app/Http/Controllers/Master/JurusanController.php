<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JurusanController extends Controller
{
    public function index(): Response
    {
        $jurusan = Jurusan::with('mapels:id_mapel,nama_mapel')
            ->orderBy('nama_jurusan')
            ->get()
            ->map(function (Jurusan $item) {
                return [
                    'id_jurusan' => $item->id_jurusan,
                    'nama_jurusan' => $item->nama_jurusan,
                    'kode_jurusan' => $item->kode_jurusan,
                    'status' => (int) $item->status,
                    'deletable' => (int) $item->deletable,
                    'mapel_ids' => $item->mapels->map(fn (Mapel $mapel) => (int) $mapel->id_mapel)->values()->all(),
                    'mapel_names' => $item->mapels->pluck('nama_mapel')->values()->all(),
                ];
            });

        return Inertia::render('Master/Jurusan', [
            'jurusan' => $jurusan,
            'mapel' => Mapel::orderBy('kelompok')->orderBy('nama_mapel')->get(['id_mapel', 'nama_mapel', 'kelompok']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $mapels = $this->mapelIds($data);

        DB::transaction(function () use ($data, $mapels) {
            $jurusan = Jurusan::create($this->payload($data, $mapels));
            $jurusan->mapels()->sync($mapels);
        });

        AuditLog::record('master.jurusan', 'Jurusan '.$data['nama_jurusan'].' ditambahkan');

        return back()->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $jurusan = Jurusan::findOrFail($id);
        $data = $this->validated($request, $jurusan->id_jurusan);
        $mapels = $this->mapelIds($data);

        DB::transaction(function () use ($jurusan, $data, $mapels) {
            $jurusan->update($this->payload($data, $mapels));
            $jurusan->mapels()->sync($mapels);
        });

        AuditLog::record('master.jurusan', 'Jurusan #'.$jurusan->id_jurusan.' '.$jurusan->nama_jurusan.' diperbarui');

        return back()->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $jurusan = Jurusan::findOrFail($id);

        if (Kelas::where('jurusan_id', $jurusan->id_jurusan)->exists()) {
            return back()->with('error', 'Jurusan masih digunakan oleh kelas. Hapus atau ubah kelas terkait terlebih dahulu.');
        }

        DB::transaction(function () use ($jurusan) {
            $jurusan->mapels()->detach();
            $jurusan->delete();
        });

        AuditLog::record('master.jurusan.delete', 'Jurusan #'.$jurusan->id_jurusan.' '.$jurusan->nama_jurusan.' dihapus');

        return back()->with('success', 'Jurusan berhasil dihapus.');
    }

    private function mapelIds(array $data): array
    {
        return array_values(array_unique(array_map('intval', $data['mapel'] ?? [])));
    }

    private function payload(array $data, array $mapels): array
    {
        unset($data['mapel']);
        $data['mapel_peminatan'] = implode(',', $mapels);

        return $data;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nama_jurusan' => ['required', 'string', 'max:30', Rule::unique('master_jurusan', 'nama_jurusan')->ignore($ignoreId, 'id_jurusan')],
            'kode_jurusan' => ['nullable', 'string', 'max:10'],
            'mapel' => ['array'],
            'mapel.*' => ['integer', 'exists:master_mapel,id_mapel'],
        ]);
    }
}
