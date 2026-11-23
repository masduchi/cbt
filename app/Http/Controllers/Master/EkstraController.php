<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Ekstra;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EkstraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Master/Ekstra', [
            'ekstra' => Ekstra::orderBy('nama_ekstra')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ekstra = Ekstra::create($this->validated($request));

        AuditLog::record('master.ekstra', 'Ekstrakurikuler '.$ekstra->nama_ekstra.' ditambahkan');

        return back()->with('success', 'Ekstrakurikuler berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ekstra = Ekstra::findOrFail($id);
        $ekstra->update($this->validated($request));

        AuditLog::record('master.ekstra', 'Ekstrakurikuler #'.$ekstra->id_ekstra.' '.$ekstra->nama_ekstra.' diperbarui');

        return back()->with('success', 'Ekstrakurikuler berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $ekstra = Ekstra::findOrFail($id);
        $ekstra->delete();

        AuditLog::record('master.ekstra.delete', 'Ekstrakurikuler #'.$ekstra->id_ekstra.' '.$ekstra->nama_ekstra.' dihapus');

        return back()->with('success', 'Ekstrakurikuler berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_ekstra' => ['required', 'string', 'max:100'],
            'kode_ekstra' => ['required', 'string', 'max:20'],
        ]);
    }
}
