<?php

namespace App\Http\Controllers\Rapor;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\RaporKunci;
use App\Models\RaporNilaiAkhir;
use App\Models\RaporNilaiHarian;
use App\Models\RaporNilaiPts;
use App\Support\Rapor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TarikCbtController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        $jadwalQuery = Jadwal::with(['bank:id_bank,bank_nama,bank_mapel_id,bank_kelas,bank_guru_id', 'bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->induk()
            ->orderBy('tgl_mulai');

        if (! $user->isAdmin()) {
            $jadwalQuery->whereHas('bank', fn ($query) => $query->where('bank_guru_id', $guruId));
        }

        $jadwal = $jadwalQuery->get()
            ->map(fn (Jadwal $item) => [
                'id_jadwal' => $item->id_jadwal,
                'label' => trim(($item->bank?->bank_nama ?? 'Ujian').' - '.($item->bank?->mapel?->nama_mapel ?? '-')),
                'jenis' => $item->jenis?->kode_jenis,
                'jenis_nama' => $item->jenis?->nama_jenis,
            ]);

        $selectedId = (int) $request->query('jadwal', 0);
        $target = $this->target($request->query('target'));

        $preview = null;

        if ($selectedId) {
            $selected = $jadwal->firstWhere('id_jadwal', $selectedId);
            abort_if(! $selected, 404);

            $preview = $this->preview($selectedId, $target, $tp->id_tp, $smt->id_smt);
        }

        return Inertia::render('Rapor/TarikCbt', [
            'jadwalOptions' => $jadwal,
            'selectedId' => $selectedId ?: null,
            'target' => $target,
            'preview' => $preview,
            'tahun' => $tp->tahun,
            'semester' => $smt->smt,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->period();
        $user = $request->user();
        $guruId = Rapor::guruId($user);

        $data = $request->validate([
            'id_jadwal' => ['required', 'integer', 'exists:cbt_jadwal,id_jadwal'],
            'target' => ['required', Rule::in(['pts', 'pas'])],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id_siswa' => ['required', 'integer'],
        ]);

        $jadwal = Jadwal::with('bank')->findOrFail($data['id_jadwal']);

        abort_unless($user->isAdmin() || (int) $jadwal->bank?->bank_guru_id === (int) $guruId, 403);

        $mapel = (int) ($jadwal->bank?->bank_mapel_id ?? 0);
        abort_if(! $mapel, 422, 'Bank soal belum memiliki mata pelajaran.');

        $setting = Rapor::setting($tp->id_tp, $smt->id_smt);
        $skor = $this->nilaiTerbaik($jadwal);

        $kelasSiswa = DB::table('kelas_siswa')
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereIn('id_siswa', array_map(fn ($row) => (int) $row['id_siswa'], $data['rows']))
            ->pluck('id_kelas', 'id_siswa');

        $locked = RaporKunci::where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->whereNotNull('locked_at')
            ->whereIn('id_kelas', $kelasSiswa->unique()->values())
            ->exists();

        abort_if($locked, 423, 'Sebagian kelas tujuan sudah dikunci. Buka kunci rapor terlebih dahulu.');

        $harian = RaporNilaiHarian::query()
            ->where('id_mapel', $mapel)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy(fn ($row) => $row->id_kelas.'-'.$row->id_siswa)
            ->map(fn ($row) => $row->p_rata_rata);

        $pts = RaporNilaiPts::query()
            ->where('id_mapel', $mapel)
            ->where('id_tp', $tp->id_tp)
            ->where('id_smt', $smt->id_smt)
            ->get()
            ->keyBy(fn ($row) => $row->id_kelas.'-'.$row->id_siswa)
            ->map(fn ($row) => $row->nilai);

        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $skor, $kelasSiswa, $harian, $pts, $mapel, $tp, $smt, $setting, &$saved, &$skipped) {
            foreach ($data['rows'] as $row) {
                $idSiswa = (int) $row['id_siswa'];
                $idKelas = (int) ($kelasSiswa[$idSiswa] ?? 0);
                $nilai = $this->score($skor[$idSiswa]['total'] ?? null);

                if (! $idKelas || $nilai === null) {
                    $skipped++;

                    continue;
                }

                $kkm = Rapor::kkm($mapel, $idKelas, $tp->id_tp, $smt->id_smt, $setting);

                if ($data['target'] === 'pts') {
                    RaporNilaiPts::updateOrCreate(
                        ['id_nilai_pts' => $mapel.$idKelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                        [
                            'id_siswa' => $idSiswa,
                            'id_mapel' => $mapel,
                            'id_kelas' => $idKelas,
                            'id_tp' => $tp->id_tp,
                            'id_smt' => $smt->id_smt,
                            'nilai' => $nilai,
                            'predikat' => Rapor::predikat($nilai, $kkm['kkm']),
                        ]
                    );
                } else {
                    $key = $idKelas.'-'.$idSiswa;
                    $akhir = Rapor::akhir($harian[$key] ?? null, $pts[$key] ?? null, $nilai, $kkm);

                    RaporNilaiAkhir::updateOrCreate(
                        ['id_nilai_akhir' => $mapel.$idKelas.$idSiswa.$tp->id_tp.$smt->id_smt],
                        [
                            'id_siswa' => $idSiswa,
                            'id_mapel' => $mapel,
                            'id_kelas' => $idKelas,
                            'id_tp' => $tp->id_tp,
                            'id_smt' => $smt->id_smt,
                            'nilai' => $nilai,
                            'akhir' => $akhir,
                            'predikat' => Rapor::predikat($akhir, $kkm['kkm']),
                        ]
                    );
                }

                $saved++;
            }
        });

        return back()->with(
            'success',
            "Nilai CBT ditarik ke rapor: {$saved} siswa tersimpan, {$skipped} dilewati."
        );
    }

    private function preview(int $jadwalId, string $target, int $tp, int $smt): array
    {
        $jadwal = Jadwal::with(['bank:id_bank,bank_nama,bank_mapel_id,bank_kelas', 'bank.mapel:id_mapel,nama_mapel'])
            ->findOrFail($jadwalId);
        $mapel = (int) ($jadwal->bank?->bank_mapel_id ?? 0);

        $kelasIds = array_values(array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas))));

        $siswa = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($kelasIds, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $kelasIds))
            ->orderBy('master_siswa.nama')
            ->get([
                'master_siswa.id_siswa',
                'master_siswa.nama',
                'master_siswa.nis',
                'master_kelas.id_kelas',
                'master_kelas.nama_kelas',
            ]);

        $skor = $this->nilaiTerbaik($jadwal);

        $existing = $target === 'pts'
            ? RaporNilaiPts::where('id_mapel', $mapel)->where('id_tp', $tp)->where('id_smt', $smt)->get()
            : RaporNilaiAkhir::where('id_mapel', $mapel)->where('id_tp', $tp)->where('id_smt', $smt)->get();

        $existingMap = $existing->keyBy(fn ($row) => $row->id_kelas.'-'.$row->id_siswa);

        $rows = $siswa->map(function ($item) use ($skor, $existingMap, $target) {
            $terbaik = $skor[$item->id_siswa] ?? null;
            $nilai = $this->score($terbaik['total'] ?? null);
            $current = $existingMap->get($item->id_kelas.'-'.$item->id_siswa);
            $currentValue = $target === 'pts' ? $current?->nilai : $current?->nilai;

            $status = $nilai === null
                ? 'kosong'
                : ($currentValue === null || $currentValue === ''
                    ? 'baru'
                    : ((int) $currentValue === $nilai ? 'sama' : 'berubah'));

            return [
                'id_siswa' => $item->id_siswa,
                'nama' => $item->nama,
                'nis' => $item->nis,
                'kelas' => $item->nama_kelas,
                'nilai' => $nilai,
                'lama' => $currentValue === null || $currentValue === '' ? null : (int) $currentValue,
                'remedial' => (bool) ($terbaik['dari_remedial'] ?? false),
                'status' => $status,
            ];
        })->values()->all();

        return [
            'mapel' => $jadwal->bank?->mapel?->nama_mapel,
            'bank' => $jadwal->bank?->bank_nama,
            'jenis' => $jadwal->jenis?->kode_jenis,
            'rows' => $rows,
            'summary' => [
                'baru' => collect($rows)->where('status', 'baru')->count(),
                'berubah' => collect($rows)->where('status', 'berubah')->count(),
                'sama' => collect($rows)->where('status', 'sama')->count(),
                'kosong' => collect($rows)->where('status', 'kosong')->count(),
                'remedial' => collect($rows)->where('remedial', true)->count(),
            ],
        ];
    }

    /**
     * Nilai terbaik per siswa untuk satu jadwal induk, termasuk hasil remedial.
     *
     * Peserta remedial mengulang soal yang sama, jadi nilai yang ditarik ke rapor
     * adalah yang tertinggi antara ujian utama dan sesi remedialnya.
     *
     * @return array<int, array{total: float, dari_remedial: bool}>
     */
    private function nilaiTerbaik(Jadwal $jadwal): array
    {
        $ids = Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)->pluck('id_jadwal')
            ->map(fn ($id) => (int) $id)
            ->push((int) $jadwal->id_jadwal)
            ->unique()
            ->values()
            ->all();

        $hasil = [];

        foreach (DB::table('cbt_nilai')->whereIn('id_jadwal', $ids)->get() as $row) {
            $total = (float) $row->pg_nilai + (float) $row->kompleks_nilai
                + (float) $row->jodohkan_nilai + (float) $row->isian_nilai + (float) $row->essai_nilai;

            if ($total <= 0) {
                continue;
            }

            $idSiswa = (int) $row->id_siswa;

            if (! isset($hasil[$idSiswa]) || $total > $hasil[$idSiswa]['total']) {
                $hasil[$idSiswa] = [
                    'total' => $total,
                    'dari_remedial' => (int) $row->id_jadwal !== (int) $jadwal->id_jadwal,
                ];
            }
        }

        return $hasil;
    }

    private function score(?float $total): ?int
    {
        if ($total === null || $total <= 0) {
            return null;
        }

        return max(0, min(100, (int) round($total)));
    }

    private function target(?string $value): string
    {
        return in_array($value, ['pts', 'pas'], true) ? $value : 'pts';
    }

    private function period(): array
    {
        [$tp, $smt] = Rapor::period();
        abort_if(! $tp || ! $smt, 409, 'Tahun pelajaran / semester aktif belum diatur.');

        return [$tp, $smt];
    }
}
