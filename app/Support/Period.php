<?php

namespace App\Support;

use App\Models\Semester;
use App\Models\TahunPelajaran;
use Illuminate\Support\Facades\Auth;

class Period
{
    /**
     * Periode yang sedang dilihat. Admin dapat memilih periode arsip lewat sesi.
     *
     * @return array{0: ?TahunPelajaran, 1: ?Semester}
     */
    public static function current(): array
    {
        $user = Auth::user();

        if ($user && $user->isAdmin()) {
            $tp = (int) session('periode_tp');
            $smt = (int) session('periode_smt');

            if ($tp && $smt) {
                $tahun = TahunPelajaran::find($tp);
                $semester = Semester::find($smt);

                if ($tahun && $semester) {
                    return [$tahun, $semester];
                }
            }
        }

        return [
            TahunPelajaran::where('active', 1)->first(),
            Semester::where('active', 1)->first(),
        ];
    }

    public static function overridden(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->isAdmin() || ! session('periode_tp') || ! session('periode_smt')) {
            return false;
        }

        return (int) session('periode_tp') !== (int) TahunPelajaran::where('active', 1)->value('id_tp')
            || (int) session('periode_smt') !== (int) Semester::where('active', 1)->value('id_smt');
    }

    /**
     * @return array<int, array{value: string, label: string, active: bool}>
     */
    public static function options(): array
    {
        $years = TahunPelajaran::orderByDesc('tahun')->get(['id_tp', 'tahun', 'active']);
        $semesters = Semester::orderBy('id_smt')->get(['id_smt', 'smt', 'nama_smt', 'active']);

        $options = [];

        foreach ($years as $year) {
            foreach ($semesters as $semester) {
                $active = (int) $year->active === 1 && (int) $semester->active === 1;

                $options[] = [
                    'value' => $year->id_tp.':'.$semester->id_smt,
                    'label' => $year->tahun.' · '.$semester->smt,
                    'active' => $active,
                ];
            }
        }

        return $options;
    }
}
