<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Cbt\Concerns\AuthorizesJadwal;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KelasSiswa;
use App\Models\Koreksi;
use App\Models\KoreksiFinal;
use App\Models\KoreksiPenilai;
use App\Models\Nilai;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\SoalRubrik;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\CbtScoring;
use App\Support\KoreksiEsai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KoreksiController extends Controller
{
    use AuthorizesJadwal;
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $user = $request->user();

        $jadwals = $this->jadwalQuery($user, $tp, $smt)
            ->with(['bank:id_bank,bank_nama,bank_kode,bank_kelas,bank_guru_id,tampil_esai,bobot_esai', 'jenis:id_jenis,nama_jenis,kode_jenis'])
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();

        $students = [];
        $pagination = null;
        $ringkasan = ['peserta' => 0, 'belum' => 0, 'selesai' => 0, 'perlu_tinjau' => 0];
        $wajib = 1;
        $penilaiIds = collect();

        $status = match ($request->query('status')) {
            'belum' => 'belum',
            'selesai' => 'selesai',
            'tinjau' => 'tinjau',
            default => 'semua',
        };

        if ($selected) {
            $wajib = KoreksiEsai::wajib($selected);
            $penilaiIds = KoreksiEsai::guruIds($selected);

            $paginator = $this->pesertaQuery($selected, $status)
                ->orderBy('id_siswa')
                ->paginate($this->perPage($request))
                ->withQueryString();

            $students = $this->rekapPeserta(
                $selected,
                collect($paginator->items())->pluck('id_siswa'),
                $tp,
                $smt,
                (int) $user->id,
                $wajib,
            )->all();

            $pagination = $paginator->toArray();
            unset($pagination['data']);

            $ringkasan = $this->ringkasan($selected, $wajib);
        }

        return Inertia::render('Cbt/Koreksi/Index', [
            'jadwalOptions' => $jadwals->map(fn (Jadwal $jadwal) => [
                'id_jadwal' => $jadwal->id_jadwal,
                'label' => trim(($jadwal->bank?->bank_nama ?? '-').' · '.($jadwal->jenis?->nama_jenis ?? '')),
                'tgl_mulai' => $this->dateLabel($jadwal->tgl_mulai),
                'tampil_esai' => (int) ($jadwal->bank?->tampil_esai ?? 0),
                'bobot_esai' => (int) ($jadwal->bank?->bobot_esai ?? 0),
            ]),
            'selected' => $selected?->id_jadwal,
            'jadwal' => $selected ? [
                'id_jadwal' => $selected->id_jadwal,
                'bank_nama' => $selected->bank?->bank_nama,
                'jenis' => $selected->jenis?->nama_jenis,
                'tgl_mulai' => $this->dateLabel($selected->tgl_mulai),
                'tampil_esai' => (int) ($selected->bank?->tampil_esai ?? 0),
                'bobot_esai' => (int) ($selected->bank?->bobot_esai ?? 0),
                'wajib' => $wajib,
                'penilai' => $penilaiIds->count(),
            ] : null,
            'students' => $students,
            'pagination' => $pagination,
            'ringkasan' => $ringkasan,
            'status' => $status,
            'periode' => $this->periodeLabel($tp, $smt),
            'isAdmin' => (bool) $user->isAdmin(),
            'selisihPersen' => (int) config('cbt.koreksi.selisih_persen', 15),
            'penilai' => $penilaiIds->values(),
            'rubrikRekap' => $selected ? $this->rekapRubrik($selected) : [],
            'guruOptions' => $user->isAdmin()
                ? Guru::whereNotNull('id_user')
                    ->whereHas('user', fn ($query) => $query->where('active', 1))
                    ->orderBy('nama_guru')
                    ->get(['id_guru', 'nama_guru'])
                    ->map(fn (Guru $guru) => [
                        'id_guru' => (int) $guru->id_guru,
                        'nama' => (string) $guru->nama_guru,
                    ])
                : [],
        ]);
    }

    public function show(Request $request, int $jadwalId, int $siswaId): Response|RedirectResponse
    {
        $jadwal = $this->authorizeKoreksi($request->user(), $jadwalId);
        $siswa = Siswa::findOrFail($siswaId);
        $user = $request->user();

        $rows = SoalSiswa::with(['soal.rubrik'])
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->where('jenis_soal', 5)
            ->orderBy('no_soal_alias')
            ->get();

        if ($rows->isEmpty()) {
            return redirect()->route('cbt.koreksi.index', ['jadwal' => $jadwal->id_jadwal])
                ->with('error', 'Tidak ada jawaban esai untuk siswa ini.');
        }

        $wajib = KoreksiEsai::wajib($jadwal);
        $ids = $rows->pluck('id_soal_siswa')->all();

        $koreksi = Koreksi::with('user:id,username,first_name,last_name')
            ->whereIn('id_soal_siswa', $ids)
            ->orderBy('updated_at')
            ->get()
            ->groupBy('id_soal_siswa');

        $finalMap = KoreksiFinal::with('user:id,username,first_name,last_name')
            ->whereIn('id_soal_siswa', $ids)
            ->get()
            ->keyBy('id_soal_siswa');

        $nilai = Nilai::find(CbtScoring::nilaiId((int) $siswa->id_siswa, (int) $jadwal->id_jadwal));
        $kelas = $this->classMap((int) $jadwal->id_tp, (int) $jadwal->id_smt);

        $jawaban = $rows->map(function (SoalSiswa $row) use ($koreksi, $finalMap, $user, $wajib) {
            $list = $koreksi->get($row->id_soal_siswa) ?? collect();
            $final = $finalMap->get($row->id_soal_siswa);
            $ringkas = KoreksiEsai::ringkas($list, (float) $row->point_soal, $wajib, $final);
            $saya = $list->firstWhere('id_user', $user->id);

            return [
                'id_soal_siswa' => $row->id_soal_siswa,
                'nomor' => (int) $row->no_soal_alias,
                'soal' => (string) ($row->soal?->soal ?? ''),
                'jawaban' => (string) ($row->jawaban_siswa ?? ''),
                'maksimal' => (float) $row->point_soal,
                'rubrik' => $row->soal
                    ? $row->soal->rubrik->map(fn (SoalRubrik $item) => [
                        'id_rubrik' => (int) $item->id_rubrik,
                        'kriteria' => (string) $item->kriteria,
                        'skor_maks' => (float) $item->skor_maks,
                    ])->values()->all()
                    : [],
                'koreksi_saya' => $saya ? [
                    'skor' => (float) $saya->skor,
                    'catatan' => (string) ($saya->catatan ?? ''),
                    'rincian' => $saya->detail ?: [],
                ] : null,
                'koreksi_lain' => $list
                    ->where('id_user', '!=', $user->id)
                    ->when($final, fn ($items) => $items->reject(
                        fn (Koreksi $item) => (int) $item->id_user === (int) $final->id_user
                    ))
                    ->map(fn (Koreksi $item) => [
                        'nama' => trim(($item->user?->first_name ?? '').' '.($item->user?->last_name ?? ''))
                            ?: (string) ($item->user?->username ?? 'Korektor'),
                        'skor' => (float) $item->skor,
                        'catatan' => (string) ($item->catatan ?? ''),
                    ])
                    ->values()
                    ->all(),
                'final' => $final ? [
                    'skor' => (float) $final->skor,
                    'catatan' => (string) ($final->catatan ?? ''),
                    'nama' => trim(($final->user?->first_name ?? '').' '.($final->user?->last_name ?? ''))
                        ?: (string) ($final->user?->username ?? 'Admin'),
                ] : null,
                'agregat' => [
                    ...$ringkas,
                    'lengkap' => $ringkas['lengkap'] || (int) $row->nilai_otomatis === 1,
                ],
            ];
        })->values();

        return Inertia::render('Cbt/Koreksi/Show', [
            'jadwal' => [
                'id_jadwal' => $jadwal->id_jadwal,
                'bank_nama' => $jadwal->bank?->bank_nama,
                'jenis' => $jadwal->jenis?->nama_jenis,
            ],
            'siswa' => [
                'id_siswa' => $siswa->id_siswa,
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
                'kelas' => $kelas->get((int) $siswa->id_siswa) ?? '',
            ],
            'jawaban' => $jawaban,
            'nilai' => $nilai ? [
                'pg' => (float) $nilai->pg_nilai,
                'kompleks' => (float) $nilai->kompleks_nilai,
                'jodohkan' => (float) $nilai->jodohkan_nilai,
                'isian' => (float) $nilai->isian_nilai,
                'esai' => (float) $nilai->essai_nilai,
                'dikoreksi' => (bool) $nilai->dikoreksi,
            ] : null,
            'wajib' => $wajib,
            'isAdmin' => (bool) $user->isAdmin(),
            'bolehMenilai' => KoreksiEsai::bolehMenilai($jadwal, $user),
            'selisihPersen' => (int) config('cbt.koreksi.selisih_persen', 15),
        ]);
    }

    public function save(Request $request, int $jadwalId, int $siswaId): RedirectResponse
    {
        $jadwal = $this->authorizeKoreksi($request->user(), $jadwalId);
        $siswa = Siswa::findOrFail($siswaId);
        $user = $request->user();

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:2000'],
            'rincian' => ['nullable', 'array'],
            'rincian.*' => ['nullable', 'array'],
            'final' => ['nullable', 'array'],
            'final.*' => ['nullable', 'boolean'],
        ]);

        $rows = SoalSiswa::with('soal.rubrik')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->where('id_siswa', $siswa->id_siswa)
            ->where('jenis_soal', 5)
            ->get()
            ->keyBy('id_soal_siswa');

        $wajib = KoreksiEsai::wajib($jadwal);
        $bolehSkor = KoreksiEsai::bolehMenilai($jadwal, $user);
        $isAdmin = (bool) $user->isAdmin();
        $dinilai = 0;
        $adaFinal = false;
        $finalLama = KoreksiFinal::whereIn('id_soal_siswa', $rows->keys()->all())
            ->get()
            ->keyBy('id_soal_siswa');
        $perubahanFinal = [];

        DB::transaction(function () use ($data, $rows, $user, $wajib, $bolehSkor, $isAdmin, $finalLama, &$dinilai, &$adaFinal, &$perubahanFinal) {
            foreach ($data['nilai'] as $id => $value) {
                if (! isset($rows[$id])) {
                    continue;
                }

                /** @var SoalSiswa $row */
                $row = $rows[$id];
                $maks = (float) $row->point_soal;
                $rubrik = $row->soal?->rubrik ?? collect();
                $rincian = $data['rincian'][$id] ?? null;
                $final = $isAdmin && (bool) ($data['final'][$id] ?? false);

                // Bila admin menetapkan final, skornya tidak ikut dihitung sebagai
                // suara korektor; hanya nilai final yang disimpan.
                if ($bolehSkor && ! $final) {
                    if ($rubrik->isNotEmpty() && ! is_array($rincian)) {
                        throw ValidationException::withMessages([
                            "rincian.{$id}" => 'Lengkapi skor tiap kriteria rubrik sebelum menyimpan.',
                        ]);
                    }

                    $skor = $rubrik->isNotEmpty()
                        ? KoreksiEsai::skorRubrik($rincian, $rubrik, $maks)
                        : (float) ($value ?? 0);

                    KoreksiEsai::simpan(
                        $row,
                        $user,
                        $skor,
                        $data['catatan'][$id] ?? null,
                        is_array($rincian) ? $rincian : [],
                        $this->snapshotRubrik($rubrik),
                        $wajib,
                    );

                    $dinilai++;
                }

                if ($final) {
                    $skorFinal = $rubrik->isNotEmpty() && is_array($rincian)
                        ? KoreksiEsai::skorRubrik($rincian, $rubrik, $maks)
                        : (float) ($value ?? 0);

                    KoreksiEsai::simpanFinal($row, $user, $skorFinal, $data['catatan'][$id] ?? null, $wajib);
                    $perubahanFinal[] = sprintf(
                        '#%s: %s → %s',
                        $row->id_soal_siswa,
                        isset($finalLama[$row->id_soal_siswa]) ? (float) $finalLama[$row->id_soal_siswa]->skor : '-',
                        $skorFinal
                    );
                    $dinilai++;
                    $adaFinal = true;
                } elseif ($isAdmin && KoreksiFinal::where('id_soal_siswa', $row->id_soal_siswa)->exists()) {
                    $perubahanFinal[] = sprintf('#%s: %s → dihapus', $row->id_soal_siswa, (float) $finalLama[$row->id_soal_siswa]->skor);
                    KoreksiEsai::hapusFinal($row, $wajib);
                    $dinilai++;
                }
            }
        });

        if ($dinilai === 0) {
            $pesan = $bolehSkor
                ? 'Tidak ada jawaban esai siswa ini yang cocok dengan nilai yang dikirim. Muat ulang halaman koreksi lalu coba lagi.'
                : 'Anda tidak ditugaskan sebagai korektor pada jadwal ini, sehingga hanya dapat menetapkan nilai final.';

            return back()->with('error', $pesan);
        }

        CbtScoring::simpan($jadwal, (int) $siswa->id_siswa);

        AuditLog::record('cbt.koreksi.simpan', sprintf(
            'Koreksi esai jadwal #%d siswa #%d: %d jawaban',
            $jadwal->id_jadwal,
            $siswa->id_siswa,
            $dinilai
        ));

        if ($perubahanFinal !== []) {
            AuditLog::record('cbt.koreksi.final', sprintf(
                'Nilai final jadwal #%d siswa #%d: %s',
                $jadwal->id_jadwal,
                $siswa->id_siswa,
                implode(', ', $perubahanFinal)
            ));
        }

        return back()->with(
            'success',
            $adaFinal
                ? 'Nilai final dan penilaian Anda berhasil disimpan.'
                : 'Nilai esai berhasil disimpan.'
        );
    }

    public function penilai(Request $request, int $jadwalId): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $jadwal = Jadwal::with('bank')->findOrFail($jadwalId);

        $data = $request->validate([
            'guru' => ['nullable', 'array'],
            'guru.*' => [
                'integer',
                Rule::exists('master_guru', 'id_guru')->whereNull('deleted_at'),
            ],
        ]);

        $sebelum = KoreksiPenilai::where('id_jadwal', $jadwal->id_jadwal)
            ->pluck('id_guru')
            ->sort()
            ->values();

        $ids = collect($data['guru'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        $gurus = Guru::with('user:id,active')->whereIn('id_guru', $ids)->get();

        if ($gurus->count() !== $ids->count() || $gurus->contains(fn (Guru $guru) => ! $guru->id_user || ! $guru->user?->active)) {
            throw ValidationException::withMessages([
                'guru' => 'Pilih hanya guru yang memiliki akun pengguna aktif.',
            ]);
        }

        DB::transaction(function () use ($jadwal, $ids) {
            $query = KoreksiPenilai::where('id_jadwal', $jadwal->id_jadwal);

            if ($ids->isEmpty()) {
                $query->delete();
            } else {
                $query->whereNotIn('id_guru', $ids->all())->delete();
            }

            foreach ($ids as $guruId) {
                KoreksiPenilai::firstOrCreate([
                    'id_jadwal' => $jadwal->id_jadwal,
                    'id_guru' => $guruId,
                ]);
            }
        });

        $this->sinkronJadwal($jadwal);

        AuditLog::record('cbt.koreksi.penilai', sprintf(
            'Penugasan korektor jadwal #%d: [%s] → [%s]',
            $jadwal->id_jadwal,
            $sebelum->implode(', '),
            $ids->implode(', ')
        ));

        $jumlah = $ids->count();

        return back()->with(
            'success',
            $jumlah === 0
                ? 'Penugasan korektor dikosongkan; pemilik bank soal menjadi korektor tunggal.'
                : $jumlah.' korektor ditugaskan pada jadwal ini.'
        );
    }

    public function markAll(Request $request, int $jadwalId): RedirectResponse
    {
        $jadwal = $this->authorizeKoreksi($request->user(), $jadwalId);

        abort_unless($request->user()->isAdmin(), 403);

        if (KoreksiEsai::wajib($jadwal) > 1) {
            return back()->with('error', 'Jadwal ini dinilai lebih dari satu korektor. Gunakan halaman koreksi per peserta agar setiap korektor mengisi skornya.');
        }

        $rows = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('jenis_soal', 5)
            ->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'Tidak ada jawaban esai pada jadwal ini.');
        }

        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $row) {
                $punyaKoreksi = Koreksi::where('id_soal_siswa', $row->id_soal_siswa)->exists();

                if ($punyaKoreksi) {
                    KoreksiEsai::sinkron($row, 1);
                } else {
                    KoreksiEsai::simpan($row, $request->user(), 0, null, [], [], 1);
                }
            }
        });

        foreach ($rows->pluck('id_siswa')->unique() as $siswaId) {
            CbtScoring::simpan($jadwal, (int) $siswaId);
        }

        AuditLog::record('cbt.koreksi.simpan', sprintf(
            'Tandai semua esai terkoreksi jadwal #%d (%d jawaban)',
            $jadwal->id_jadwal,
            $rows->count()
        ));

        return back()->with('success', $rows->pluck('id_siswa')->unique()->count().' siswa ditandai sudah dikoreksi.');
    }

    /**
     * Query peserta esai dengan filter status yang konsisten dengan nilai
     * tersimpan, sehingga pagination tetap benar untuk data besar.
     */
    private function pesertaQuery(Jadwal $jadwal, string $status)
    {
        $query = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('jenis_soal', 5)
            ->select('id_siswa')
            ->distinct();

        $belum = function ($sub) use ($jadwal) {
            $sub->select(DB::raw(1))
                ->from('cbt_soal_siswa as x')
                ->whereColumn('x.id_siswa', 'cbt_soal_siswa.id_siswa')
                ->where('x.id_jadwal', $jadwal->id_jadwal)
                ->where('x.jenis_soal', 5)
                ->where('x.nilai_otomatis', 0);
        };

        if ($status === 'belum') {
            $query->whereExists($belum);
        } elseif ($status === 'selesai') {
            $query->whereNotExists($belum);
        } elseif ($status === 'tinjau') {
            $query->whereIn('id_siswa', $this->tinjauQuery($jadwal));
        }

        return $query;
    }

    /** Query siswa yang memiliki minimal satu jawaban dengan selisih korektor besar. */
    private function tinjauQuery(Jadwal $jadwal)
    {
        return DB::table('cbt_soal_siswa as s')
            ->join('cbt_koreksi as k', 'k.id_soal_siswa', '=', 's.id_soal_siswa')
            ->where('s.id_jadwal', $jadwal->id_jadwal)
            ->where('s.jenis_soal', 5)
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))
                ->from('cbt_koreksi_final as f')
                ->whereColumn('f.id_soal_siswa', 's.id_soal_siswa'))
            ->select('s.id_siswa')
            ->groupBy('s.id_siswa', 's.id_soal_siswa', 's.point_soal')
            ->havingRaw(
                'COUNT(*) > 1 AND (MAX(k.skor) - MIN(k.skor)) >= (CAST(s.point_soal AS DECIMAL(10,2)) * ? / 100)',
                [(int) config('cbt.koreksi.selisih_persen', 15)]
            );
    }

    /** @return array{peserta: int, belum: int, selesai: int, perlu_tinjau: int} */
    private function ringkasan(Jadwal $jadwal, int $wajib): array
    {
        $base = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->where('jenis_soal', 5);

        $peserta = (clone $base)->distinct()->count('id_siswa');
        $belum = (clone $base)->where('nilai_otomatis', 0)->distinct()->count('id_siswa');

        $perluTinjau = DB::query()
            ->fromSub($this->tinjauQuery($jadwal)->select('s.id_soal_siswa'), 'tinjau')
            ->count();

        return [
            'peserta' => $peserta,
            'belum' => $belum,
            'selesai' => $peserta - $belum,
            'perlu_tinjau' => $perluTinjau,
            'wajib' => $wajib,
        ];
    }

    /**
     * Menghitung progres koreksi peserta pada halaman aktif.
     *
     * @param  Collection<int, int>  $siswaIds
     * @return Collection<int, array<string, mixed>>
     */
    private function rekapPeserta(Jadwal $jadwal, Collection $siswaIds, $tp, $smt, int $userId, int $wajib): Collection
    {
        if ($siswaIds->isEmpty()) {
            return collect();
        }

        $rows = SoalSiswa::with('siswa:id_siswa,nama,nis')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereIn('id_siswa', $siswaIds->all())
            ->where('jenis_soal', 5)
            ->orderBy('id_siswa')
            ->orderBy('no_soal_alias')
            ->get();

        $ids = $rows->pluck('id_soal_siswa')->all();

        $koreksi = Koreksi::whereIn('id_soal_siswa', $ids)
            ->get(['id_soal_siswa', 'id_user', 'skor'])
            ->groupBy('id_soal_siswa');

        $finals = KoreksiFinal::whereIn('id_soal_siswa', $ids)
            ->get()
            ->keyBy('id_soal_siswa');

        $nilaiMap = Nilai::whereIn(
            'id_nilai',
            $siswaIds->map(fn ($id) => CbtScoring::nilaiId((int) $id, (int) $jadwal->id_jadwal))->all()
        )->get()->keyBy('id_nilai');

        $kelas = $this->classMap($tp, $smt);
        $bySiswa = $rows->groupBy('id_siswa');

        return $siswaIds->map(function ($siswaId) use ($bySiswa, $koreksi, $finals, $nilaiMap, $jadwal, $kelas, $userId, $wajib) {
            $items = $bySiswa->get($siswaId, collect());
            $siswa = $items->first()?->siswa;
            $nilai = $nilaiMap->get(CbtScoring::nilaiId((int) $siswaId, (int) $jadwal->id_jadwal));
            $selesai = 0;
            $saya = 0;
            $tinjau = 0;

            foreach ($items as $row) {
                $list = $koreksi->get($row->id_soal_siswa) ?? collect();
                $ringkas = KoreksiEsai::ringkas(
                    $list,
                    (float) $row->point_soal,
                    $wajib,
                    $finals->get($row->id_soal_siswa)
                );

                if ($ringkas['lengkap'] || (int) $row->nilai_otomatis === 1) {
                    $selesai++;
                }
                if ($list->contains('id_user', $userId)) {
                    $saya++;
                }
                if ($ringkas['perlu_tinjau']) {
                    $tinjau++;
                }
            }

            $belum = $selesai < $items->count();

            return [
                'id_siswa' => (int) $siswaId,
                'nama' => $siswa?->nama ?? '-',
                'nis' => $siswa?->nis,
                'kelas' => $kelas->get((int) $siswaId) ?? '',
                'jumlah_esai' => $items->count(),
                'terkoreksi' => $selesai,
                'saya' => $saya,
                'wajib' => $wajib,
                'perlu_tinjau' => $tinjau,
                'nilai_esai' => (float) ($nilai?->essai_nilai ?? 0),
                'dikoreksi' => (bool) ($nilai?->dikoreksi ?? false),
                'selesai' => true,
                'belum' => $belum,
            ];
        })->values();
    }

    /**
     * Rata-rata skor tiap kriteria rubrik pada seluruh jawaban esai jadwal.
     * Snapshot rubrik yang tersimpan bersama koreksi dipakai lebih dulu agar
     * perubahan rubrik setelah penilaian tidak menggeser rekap.
     *
     * @return array<int, array{id_rubrik: int, kriteria: string, skor_maks: float, jumlah: int, rata_rata: float, persen: float}>
     */
    private function rekapRubrik(Jadwal $jadwal): array
    {
        $rows = SoalSiswa::with('soal.rubrik')
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->where('jenis_soal', 5)
            ->get(['id_soal_siswa', 'id_soal']);

        if ($rows->isEmpty()) {
            return [];
        }

        $koreksi = Koreksi::whereIn('id_soal_siswa', $rows->pluck('id_soal_siswa')->all())
            ->get(['id_soal_siswa', 'detail', 'detail_rubrik'])
            ->groupBy('id_soal_siswa');

        $rekap = [];

        foreach ($rows as $row) {
            $list = $koreksi->get($row->id_soal_siswa) ?? collect();

            if ($list->isEmpty()) {
                continue;
            }

            $snapshot = $list->first(fn (Koreksi $item) => ! empty($item->detail_rubrik))?->detail_rubrik;

            $rubrik = $snapshot;

            if (! is_array($rubrik) || $rubrik === []) {
                $rubrik = ($row->soal?->rubrik ?? collect())
                    ->map(fn (SoalRubrik $item) => [
                        'id_rubrik' => (int) $item->id_rubrik,
                        'kriteria' => (string) $item->kriteria,
                        'skor_maks' => (float) $item->skor_maks,
                    ])
                    ->values()
                    ->all();
            }

            foreach ($rubrik as $item) {
                $id = (int) ($item['id_rubrik'] ?? 0);
                $maks = (float) ($item['skor_maks'] ?? 0);

                if ($id <= 0 || $maks <= 0) {
                    continue;
                }

                $nilai = $list
                    ->map(fn (Koreksi $koreksi) => $koreksi->detail[$id] ?? $koreksi->detail[(string) $id] ?? null)
                    ->filter(fn ($value) => $value !== null)
                    ->map(fn ($value) => max(0.0, min((float) $value, $maks)));

                if ($nilai->isEmpty()) {
                    continue;
                }

                $rekap[$id] ??= [
                    'id_rubrik' => $id,
                    'kriteria' => (string) ($item['kriteria'] ?? ''),
                    'skor_maks' => $maks,
                    'jumlah' => 0,
                    'total' => 0.0,
                ];

                $rekap[$id]['jumlah'] += $nilai->count();
                $rekap[$id]['total'] += (float) $nilai->sum();
            }
        }

        return collect($rekap)
            ->map(fn (array $item) => [
                'id_rubrik' => $item['id_rubrik'],
                'kriteria' => $item['kriteria'],
                'skor_maks' => round($item['skor_maks'], 2),
                'jumlah' => $item['jumlah'],
                'rata_rata' => round($item['total'] / max(1, $item['jumlah']), 2),
                'persen' => round($item['total'] / max(0.01, $item['skor_maks'] * $item['jumlah']) * 100, 1),
            ])
            ->sortBy('id_rubrik')
            ->values()
            ->all();
    }

    /** @param  Collection<int, SoalRubrik>  $rubrik */
    private function snapshotRubrik(Collection $rubrik): array
    {
        return $rubrik->map(fn (SoalRubrik $item) => [
            'id_rubrik' => (int) $item->id_rubrik,
            'kriteria' => (string) $item->kriteria,
            'skor_maks' => (float) $item->skor_maks,
        ])->values()->all();
    }

    /**
     * Admin boleh melihat semua jadwal; guru hanya jadwal milik bank soalnya
     * atau jadwal tempat ia ditugaskan sebagai korektor.
     */
    private function authorizeKoreksi($user, int $id): Jadwal
    {
        $jadwal = Jadwal::with(['bank', 'jenis'])->findOrFail($id);

        if ($user->isAdmin()) {
            return $jadwal;
        }

        abort_unless(KoreksiEsai::bolehMenilai($jadwal, $user), 403);

        return $jadwal;
    }

    /** Menyelaraskan ulang status koreksi seluruh jawaban setelah penugasan berubah. */
    private function sinkronJadwal(Jadwal $jadwal): void
    {
        $wajib = KoreksiEsai::wajib($jadwal);

        $rows = SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)
            ->where('jenis_soal', 5)
            ->get();

        foreach ($rows as $row) {
            KoreksiEsai::sinkron($row, $wajib);
        }

        foreach ($rows->pluck('id_siswa')->unique() as $siswaId) {
            CbtScoring::simpan($jadwal, (int) $siswaId);
        }
    }

    private function classMap($tp, $smt)
    {
        return KelasSiswa::join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->pluck('master_kelas.nama_kelas', 'kelas_siswa.id_siswa');
    }

    private function jadwalQuery($user, $tp, $smt)
    {
        $query = Jadwal::where('id_tp', $tp)->where('id_smt', $smt);

        if (! $user->isAdmin()) {
            $guruId = (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0);
            $ditugaskan = KoreksiPenilai::where('id_guru', $guruId)->pluck('id_jadwal')->all();

            $query->where(function ($sub) use ($guruId, $ditugaskan) {
                $sub->whereHas('bank', fn ($bank) => $bank->where('bank_guru_id', $guruId));

                if ($ditugaskan !== []) {
                    $sub->orWhereIn('id_jadwal', $ditugaskan);
                }
            });
        }

        return $query;
    }

    private function dateLabel(?string $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->isoFormat('D MMM YYYY, HH:mm');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel($tp, $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
