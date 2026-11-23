<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\KelompokMapel;
use App\Models\Mapel;
use App\Support\AuditLog;
use App\Support\ReferenceGuard;
use App\Support\SheetImport;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MapelController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        $query = Mapel::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_mapel', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Master/Mapel', [
            'mapel' => $query->orderBy('urutan')->orderBy('nama_mapel')->paginate($this->perPage($request))->withQueryString(),
            'filters' => ['search' => $request->query('search', '')],
            'kelompokOptions' => KelompokMapel::orderBy('id_parent')->orderBy('kode_kel_mapel')
                ->get(['id_kel_mapel', 'kode_kel_mapel', 'nama_kel_mapel']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['urutan'] ??= (int) Mapel::max('urutan') + 1;
        $data['status'] ??= 1;
        $data['kelompok'] ??= '-';

        $mapel = Mapel::create($data);

        AuditLog::record('master.mapel', 'Mapel '.$mapel->nama_mapel.' ditambahkan');

        return back()->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapel = Mapel::findOrFail($id);

        $mapel->update($this->validated($request, $mapel->id_mapel));

        AuditLog::record('master.mapel', 'Mapel #'.$mapel->id_mapel.' '.$mapel->nama_mapel.' diperbarui');

        return back()->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $mapel = Mapel::findOrFail($id);
        $used = ReferenceGuard::mapel($mapel->id_mapel);

        if ($used) {
            $mapel->update(['status' => 0]);

            AuditLog::record('master.mapel.delete', 'Mapel #'.$mapel->id_mapel.' '.$mapel->nama_mapel.' dinonaktifkan karena masih dipakai: '.implode(', ', $used));

            return back()->with('success', 'Mata pelajaran dinonaktifkan karena masih dipakai: '.implode(', ', $used).'.');
        }

        $mapel->delete();

        AuditLog::record('master.mapel.delete', 'Mapel #'.$mapel->id_mapel.' '.$mapel->nama_mapel.' dihapus');

        return back()->with('success', 'Mata pelajaran berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $mapel = Mapel::findOrFail($id);
        $mapel->update(['status' => 1]);

        AuditLog::record('master.mapel', 'Mapel #'.$mapel->id_mapel.' '.$mapel->nama_mapel.' diaktifkan kembali');

        return back()->with('success', 'Mata pelajaran diaktifkan kembali.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        [$header, $rows] = SheetImport::read($request->file('file'), ['nama_mapel']);

        if (! in_array('nama_mapel', $header, true)) {
            return back()->with('error', 'Kolom wajib tidak ditemukan: nama_mapel.');
        }

        $created = 0;
        $errors = [];
        $seen = [];
        $urutan = (int) Mapel::max('urutan');

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $nama = trim((string) ($data['nama_mapel'] ?? ''));

            if ($nama === '') {
                $errors[] = "Baris {$line}: nama mapel wajib diisi.";

                continue;
            }

            $key = mb_strtolower($nama);
            if (isset($seen[$key]) || Mapel::whereRaw('LOWER(nama_mapel) = ?', [$key])->exists()) {
                $errors[] = "Baris {$line}: mapel {$nama} sudah terdaftar.";

                continue;
            }

            $bobotP = (int) ($data['bobot_p'] ?? 1);
            $bobotK = (int) ($data['bobot_k'] ?? 1);
            $status = mb_strtolower(trim((string) ($data['status'] ?? '')));
            $urutanInput = trim((string) ($data['urutan'] ?? ''));

            Mapel::create([
                'nama_mapel' => $nama,
                'kode' => trim((string) ($data['kode'] ?? '')) ?: null,
                'kelompok' => trim((string) ($data['kelompok'] ?? '')) ?: '-',
                'bobot_p' => max(0, min(100, $bobotP)),
                'bobot_k' => max(0, min(100, $bobotK)),
                'urutan' => $urutanInput !== '' ? max(0, (int) $urutanInput) : ++$urutan,
                'status' => in_array($status, ['0', 'nonaktif', 'tidak', 'n', 'false'], true) ? 0 : 1,
            ]);

            $seen[$key] = true;
            $created++;
        }

        AuditLog::record('master.mapel.import', sprintf('Impor mata pelajaran: %d ditambahkan, %d baris dilewati', $created, count($errors)));

        $message = "Impor selesai: {$created} mata pelajaran ditambahkan.";
        if ($errors) {
            $message .= ' '.count($errors).' baris dilewati.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($errors, 0, 50));
    }

    public function importTemplate()
    {
        $headers = ['nama_mapel', 'kode', 'kelompok', 'bobot_p', 'bobot_k', 'urutan', 'status'];
        $sample = ['Matematika', 'MTK', 'A', '1', '1', '', '1'];

        return XlsxExport::download('template-impor-mapel.xlsx', $headers, [$sample], 'Mapel');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nama_mapel' => ['required', 'string', 'max:50', Rule::unique('master_mapel', 'nama_mapel')->ignore($ignoreId, 'id_mapel')],
            'kode' => ['nullable', 'string', 'max:20'],
            'kelompok' => ['nullable', 'string', 'max:5'],
            'bobot_p' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_k' => ['nullable', 'integer', 'min:0', 'max:100'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);
    }
}
