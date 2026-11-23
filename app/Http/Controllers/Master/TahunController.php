<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Bulan;
use App\Models\Semester;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\ReferenceGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TahunController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Master/Tahun', [
            'tahun' => TahunPelajaran::orderByDesc('tahun')->orderByDesc('id_tp')->get(),
            'semester' => Semester::orderBy('id_smt')->get(),
            'bulan' => Bulan::orderBy('id_bln')->get(['id_bln', 'nama_bln']),
            'hariEfektif' => DB::table('master_hari_efektif')->pluck('jml_hari_efektif', 'id_hari_efektif'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'string', 'max:20', Rule::unique('master_tp', 'tahun')],
        ]);

        $data['active'] = TahunPelajaran::count() === 0 ? 1 : 0;
        $tahun = TahunPelajaran::create($data);

        AuditLog::record('master.tahun', 'Tahun pelajaran '.$tahun->tahun.' ditambahkan');

        return back()->with('success', 'Tahun pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tahun = TahunPelajaran::findOrFail($id);

        $data = $request->validate([
            'tahun' => ['required', 'string', 'max:20', Rule::unique('master_tp', 'tahun')->ignore($tahun->id_tp, 'id_tp')],
        ]);

        $tahun->update($data);

        AuditLog::record('master.tahun', 'Tahun pelajaran #'.$tahun->id_tp.' '.$tahun->tahun.' diperbarui');

        return back()->with('success', 'Tahun pelajaran berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tahun = TahunPelajaran::findOrFail($id);

        if ((int) $tahun->active === 1) {
            return back()->with('error', 'Tahun pelajaran aktif tidak dapat dihapus.');
        }

        $used = ReferenceGuard::tahun($tahun->id_tp);

        if ($used) {
            return back()->with('error', 'Tahun pelajaran masih dipakai oleh '.implode(', ', $used).'. Hapus data terkait terlebih dahulu.');
        }

        $tahun->delete();

        AuditLog::record('master.tahun.delete', 'Tahun pelajaran #'.$tahun->id_tp.' '.$tahun->tahun.' dihapus');

        return back()->with('success', 'Tahun pelajaran berhasil dihapus.');
    }

    public function activate(int $id): RedirectResponse
    {
        $tahun = TahunPelajaran::findOrFail($id);

        TahunPelajaran::where('active', 1)->update(['active' => 0]);
        $tahun->update(['active' => 1]);

        AuditLog::record('master.tahun.aktif', 'Tahun pelajaran '.$tahun->tahun.' diaktifkan');

        return back()->with('success', "Tahun pelajaran {$tahun->tahun} diaktifkan.");
    }

    public function saveHariEfektif(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hari' => ['required', 'array'],
            'hari.*' => ['nullable', 'integer', 'min:0', 'max:31'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['hari'] as $bulan => $jumlah) {
                $bulan = (int) $bulan;

                if ($bulan < 1 || $bulan > 12) {
                    continue;
                }

                DB::table('master_hari_efektif')->updateOrInsert(
                    ['id_hari_efektif' => $bulan],
                    ['jml_hari_efektif' => (int) ($jumlah ?? 0)]
                );
            }
        });

        AuditLog::record('master.tahun.hari-efektif', 'Hari efektif '.count($data['hari']).' bulan disimpan');

        return back()->with('success', 'Jumlah hari efektif berhasil disimpan.');
    }

    public function activateSemester(int $id): RedirectResponse
    {
        $semester = Semester::findOrFail($id);

        Semester::where('active', 1)->update(['active' => 0]);
        $semester->update(['active' => 1]);

        AuditLog::record('master.tahun.aktif', 'Semester '.$semester->nama_smt.' diaktifkan');

        return back()->with('success', "Semester {$semester->nama_smt} diaktifkan.");
    }
}
