<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\TahunPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'id_tp' => ['required', 'integer', 'exists:master_tp,id_tp'],
            'id_smt' => ['required', 'integer', 'exists:master_smt,id_smt'],
        ]);

        $request->session()->put('periode_tp', (int) $data['id_tp']);
        $request->session()->put('periode_smt', (int) $data['id_smt']);

        return back()->with('success', 'Periode data diubah ke '.$this->label($data['id_tp'], $data['id_smt']).'.');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->session()->forget(['periode_tp', 'periode_smt']);

        return back()->with('success', 'Kembali ke periode aktif.');
    }

    private function label(int $tp, int $smt): string
    {
        $tahun = TahunPelajaran::where('id_tp', $tp)->value('tahun') ?? '-';
        $semester = Semester::where('id_smt', $smt)->value('nama_smt')
            ?? Semester::where('id_smt', $smt)->value('smt')
            ?? '-';

        return $tahun.' · '.$semester;
    }
}
