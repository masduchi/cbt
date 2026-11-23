<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\BankSoal;
use App\Models\DurasiSiswa;
use App\Models\Jadwal;
use App\Models\JenisUjian;
use App\Models\LogUjian;
use App\Models\Nilai;
use App\Models\RekapNilai;
use App\Models\RemedialPeserta;
use App\Models\Semester;
use App\Models\SoalSiswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\JadwalKonflik;
use App\Support\PercobaanArsip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JadwalController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();
        $guruId = $this->guruId($request);

        $jadwal = $tp && $smt
            ? Jadwal::with(['bank:id_bank,bank_kode,bank_nama,bank_mapel_id,tampil_pg,tampil_kompleks,tampil_jodohkan,tampil_isian,tampil_esai', 'bank.mapel:id_mapel,nama_mapel', 'jenis:id_jenis,nama_jenis,kode_jenis'])
                ->withCount(['durasi as peserta_count'])
                ->where('id_tp', $tp)->where('id_smt', $smt)
                ->when($guruId !== null, fn ($query) => $query->whereHas(
                    'bank',
                    fn ($bank) => $bank->where('bank_guru_id', $guruId),
                ))
                ->orderBy('tgl_mulai')
                ->get()
                ->map(fn (Jadwal $item) => [
                    'id_jadwal' => $item->id_jadwal,
                    'id_bank' => $item->id_bank,
                    'id_jenis' => $item->id_jenis,
                    'bank_kode' => $item->bank?->bank_kode,
                    'bank_nama' => $item->bank?->bank_nama,
                    'mapel' => $item->bank?->mapel?->nama_mapel,
                    'jenis' => $item->jenis?->nama_jenis,
                    'jenis_kode' => $item->jenis?->kode_jenis,
                    'tgl_mulai' => $this->dateInput($item->tgl_mulai),
                    'tgl_selesai' => $this->dateInput($item->tgl_selesai),
                    'durasi_ujian' => $item->durasi_ujian,
                    'acak_soal' => (int) $item->acak_soal,
                    'acak_opsi' => (int) $item->acak_opsi,
                    'pilih_acak' => (int) $item->pilih_acak,
                    'hasil_tampil' => (int) $item->hasil_tampil,
                    'token' => (int) $item->token,
                    'status' => (int) $item->status,
                    'ulang' => (int) $item->ulang,
                    'reset_login' => (int) $item->reset_login,
                    'rekap' => (int) $item->rekap,
                    'jam_ke' => (int) $item->jam_ke,
                    'peserta_count' => $item->peserta_count,
                ])
            : collect();

        return Inertia::render('Cbt/Jadwal/Index', [
            'jadwal' => $jadwal,
            'bankOptions' => $this->bankOptions($tp, $smt, $guruId),
            'jenisOptions' => JenisUjian::orderBy('id_jenis')->get(['id_jenis', 'nama_jenis', 'kode_jenis']),
            'isAdmin' => $request->user()->isAdmin(),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        if ($request->filled('id_bank')) {
            $this->guardBankOwnership($request, (int) $request->input('id_bank'));
        }

        $data = $this->validated($request);
        $data['id_tp'] = $tp;
        $data['id_smt'] = $smt;
        $data['time_create'] = now();

        $jadwal = Jadwal::create($data);
        $konflik = JadwalKonflik::cari($data, (int) $jadwal->id_jadwal);

        AuditLog::record('cbt.jadwal', sprintf(
            'Jadwal #%d dibuat · bank #%d · %s → %s',
            $jadwal->id_jadwal,
            $jadwal->id_bank,
            $jadwal->tgl_mulai,
            $jadwal->tgl_selesai
        ));

        return back()
            ->with('success', 'Jadwal ujian berhasil ditambahkan.')
            ->with('warning', $this->pesanKonflik($konflik));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->ownedJadwal($request, $id);
        $data = $this->validated($request, $jadwal->id_jadwal);
        $data['id_tp'] = $jadwal->id_tp;
        $data['id_smt'] = $jadwal->id_smt;

        $berubah = collect($data)
            ->filter(fn ($value, $key) => (string) $jadwal->getAttribute($key) !== (string) $value)
            ->keys()
            ->all();

        $jadwal->update($data);

        AuditLog::record('cbt.jadwal', sprintf(
            'Jadwal #%d diubah (%s) · %s → %s',
            $jadwal->id_jadwal,
            $berubah === [] ? 'tanpa perubahan nilai' : implode(', ', $berubah),
            $jadwal->tgl_mulai,
            $jadwal->tgl_selesai
        ));

        $konflik = JadwalKonflik::cari($data, (int) $jadwal->id_jadwal);

        return back()
            ->with('success', 'Jadwal ujian berhasil diperbarui.')
            ->with('warning', $this->pesanKonflik($konflik));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->ownedJadwal($request, $id);

        if ($this->punyaPengerjaan($jadwal)) {
            return back()->with('error', 'Ujian sudah dikerjakan siswa dan hasil belum direkap.');
        }

        DB::transaction(fn () => $this->hapusJadwal($jadwal));

        AuditLog::record('cbt.jadwal.delete', sprintf('Jadwal #%d (bank #%d) dihapus', $jadwal->id_jadwal, $jadwal->id_bank));

        return back()->with('success', 'Jadwal ujian berhasil dihapus.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = 0;
        $blocked = 0;
        $deletedIds = [];
        $guruId = $this->guruId($request);

        $jadwalQuery = Jadwal::whereIn('id_jadwal', $data['ids'])
            ->when($guruId !== null, fn ($query) => $query->whereHas(
                'bank',
                fn ($bank) => $bank->where('bank_guru_id', $guruId),
            ));

        foreach ($jadwalQuery->get() as $jadwal) {
            if ($this->punyaPengerjaan($jadwal)) {
                $blocked++;

                continue;
            }

            DB::transaction(fn () => $this->hapusJadwal($jadwal));

            $deletedIds[] = (int) $jadwal->id_jadwal;
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->with('error', 'Tidak ada jadwal yang bisa dihapus. '.$this->blockedJadwalLabel($blocked));
        }

        AuditLog::record('cbt.jadwal.delete', sprintf('%d jadwal dihapus: #%s', $deleted, implode(', #', $deletedIds)));

        return back()->with('success', "{$deleted} jadwal dihapus. ".$this->blockedJadwalLabel($blocked));
    }

    private function blockedJadwalLabel(int $blocked): string
    {
        return $blocked > 0 ? "{$blocked} jadwal dilewati karena sudah dikerjakan siswa dan belum direkap." : '';
    }

    /**
     * Jadwal remedial adalah turunan jadwal induk. Menghapus induk tanpa ikut
     * menghapus turunannya meninggalkan jadwal yatim yang tidak bisa diakses
     * dari menu remedial mana pun.
     */
    private function hapusJadwal(Jadwal $jadwal): void
    {
        foreach (Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)->get() as $anak) {
            $this->hapusSatuJadwal($anak);
        }

        $this->hapusSatuJadwal($jadwal);
    }

    private function hapusSatuJadwal(Jadwal $jadwal): void
    {
        $ids = Nilai::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa')
            ->merge(SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa'))
            ->merge(DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->pluck('id_siswa'))
            ->unique()
            ->all();

        PercobaanArsip::simpan($jadwal, $ids, 'jadwal_dihapus');

        SoalSiswa::where('id_jadwal', $jadwal->id_jadwal)->delete();
        DurasiSiswa::where('id_jadwal', $jadwal->id_jadwal)->delete();
        Nilai::where('id_jadwal', $jadwal->id_jadwal)->delete();
        RekapNilai::where('id_jadwal', $jadwal->id_jadwal)->delete();
        LogUjian::where('id_jadwal', $jadwal->id_jadwal)->delete();
        DB::table('cbt_pengawas')->where('id_jadwal', (string) $jadwal->id_jadwal)->delete();
        RemedialPeserta::where('id_jadwal', $jadwal->id_jadwal)->delete();

        $jadwal->delete();
    }

    private function punyaPengerjaan(Jadwal $jadwal): bool
    {
        $ids = Jadwal::where('id_jadwal_induk', $jadwal->id_jadwal)
            ->pluck('id_jadwal')
            ->push($jadwal->id_jadwal);

        return DurasiSiswa::whereIn('id_jadwal', $ids)->exists()
            && Jadwal::whereIn('id_jadwal', $ids)->where('rekap', 0)->exists();
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $jadwal = $this->ownedJadwal($request, $id);
        $akanAktif = (int) $jadwal->status !== 1;

        if ($akanAktif) {
            // ownedJadwal hanya memuat sebagian kolom bank, jadi muat ulang penuh
            // agar kebutuhan tampil_* ikut terbaca.
            $kurang = $this->kekuranganSoal($jadwal->bank()->first());

            if ($kurang !== []) {
                return back()->with('error', 'Jadwal belum bisa diaktifkan karena komposisi bank soal belum lengkap: '.implode('; ', $kurang).'.');
            }
        }

        $jadwal->update(['status' => (int) $jadwal->status === 1 ? 0 : 1]);

        AuditLog::record('cbt.jadwal.status', sprintf(
            'Jadwal #%d %s',
            $jadwal->id_jadwal,
            (int) $jadwal->status === 1 ? 'diaktifkan' : 'dinonaktifkan'
        ));

        return back()->with('success', 'Status jadwal diperbarui.');
    }

    /**
     * Guru hanya boleh mengelola jadwal dari bank soal miliknya sendiri.
     * Admin (null) tidak dibatasi; guru tanpa data guru memperoleh 0 sehingga tidak memiliki apa pun.
     */
    private function guruId(Request $request): ?int
    {
        $user = $request->user();

        return $user->isGuru() ? (int) ($user->guru?->id_guru ?? 0) : null;
    }

    private function ownedJadwal(Request $request, int $id): Jadwal
    {
        $jadwal = Jadwal::with('bank:id_bank,bank_guru_id')->findOrFail($id);
        $guruId = $this->guruId($request);

        if ($guruId !== null && (int) $jadwal->bank?->bank_guru_id !== $guruId) {
            abort(403, 'Jadwal ujian ini bukan milik Anda.');
        }

        return $jadwal;
    }

    private function guardBankOwnership(Request $request, int $bankId): void
    {
        $guruId = $this->guruId($request);

        if ($guruId === null) {
            return;
        }

        if (! BankSoal::where('id_bank', $bankId)->where('bank_guru_id', $guruId)->exists()) {
            throw ValidationException::withMessages([
                'id_bank' => 'Anda hanya dapat membuat jadwal untuk bank soal milik Anda sendiri.',
            ]);
        }
    }

    /**
     * @param  array<int, array{id_jadwal: int, nama: string, kelas: array<int, int>, guru: bool}>  $konflik
     */
    private function pesanKonflik(array $konflik): ?string
    {
        if ($konflik === []) {
            return null;
        }

        $items = array_map(
            fn (array $row) => '#'.$row['id_jadwal'].' '.$row['nama'].($row['guru'] ? ' (guru sama)' : ' (kelas sama)'),
            array_slice($konflik, 0, 3)
        );

        $lebih = count($konflik) > 3 ? ' dan '.(count($konflik) - 3).' lainnya' : '';

        return 'Jadwal tersimpan, tetapi waktunya bertumpang tindih dengan '.implode(', ', $items).$lebih.'.';
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'id_bank' => ['required', 'integer', 'exists:cbt_bank_soal,id_bank', Rule::unique('cbt_jadwal', 'id_bank')->ignore($ignoreId, 'id_jadwal')],
            'id_jenis' => ['required', 'integer', 'exists:cbt_jenis,id_jenis'],
            'tgl_mulai' => ['required', 'string', 'max:20'],
            'tgl_selesai' => ['required', 'string', 'max:20', 'after:tgl_mulai'],
            'durasi_ujian' => ['required', 'integer', 'min:1'],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_opsi' => ['nullable', 'boolean'],
            'pilih_acak' => ['nullable', 'boolean'],
            'hasil_tampil' => ['nullable', 'boolean'],
            'token' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'ulang' => ['nullable', 'boolean'],
            'reset_login' => ['nullable', 'boolean'],
            'jam_ke' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach (['acak_soal', 'acak_opsi', 'pilih_acak', 'hasil_tampil', 'token', 'status', 'ulang', 'reset_login'] as $field) {
            $data[$field] = (int) ($data[$field] ?? 0);
        }

        $data['jam_ke'] = $data['jam_ke'] ?? 0;

        return $data;
    }

    /**
     * Daftar komposisi soal yang belum memenuhi kebutuhan jadwal. Jadwal yang
     * diaktifkan tanpa komposisi lengkap baru gagal saat siswa menekan mulai.
     *
     * @return array<int, string>
     */
    private function kekuranganSoal(?BankSoal $bank): array
    {
        if (! $bank) {
            return ['bank soal tidak ditemukan'];
        }

        $tersedia = DB::table('cbt_soal')
            ->where('bank_id', $bank->id_bank)
            ->where('tampilkan', 1)
            ->selectRaw('jenis, COUNT(*) as jumlah')
            ->groupBy('jenis')
            ->pluck('jumlah', 'jenis');

        $kebutuhan = [
            1 => ['label' => 'PG', 'butuh' => (int) $bank->tampil_pg],
            2 => ['label' => 'kompleks', 'butuh' => (int) $bank->tampil_kompleks],
            3 => ['label' => 'jodohkan', 'butuh' => (int) $bank->tampil_jodohkan],
            4 => ['label' => 'isian', 'butuh' => (int) $bank->tampil_isian],
            5 => ['label' => 'esai', 'butuh' => (int) $bank->tampil_esai],
        ];

        $kurang = [];

        foreach ($kebutuhan as $jenis => $info) {
            $jumlah = (int) ($tersedia[$jenis] ?? 0);

            if ($jumlah < $info['butuh']) {
                $kurang[] = $info['label'].' butuh '.$info['butuh'].', tersedia '.$jumlah;
            }
        }

        return $kurang;
    }

    private function bankOptions(?int $tp, ?int $smt, ?int $guruId = null): array
    {
        if (! $tp || ! $smt) {
            return [];
        }

        return BankSoal::with('mapel:id_mapel,nama_mapel')
            ->withCount([
                'soal as pg_selected' => fn ($q) => $q->where('jenis', 1)->where('tampilkan', 1),
                'soal as kompleks_selected' => fn ($q) => $q->where('jenis', 2)->where('tampilkan', 1),
                'soal as jodohkan_selected' => fn ($q) => $q->where('jenis', 3)->where('tampilkan', 1),
                'soal as isian_selected' => fn ($q) => $q->where('jenis', 4)->where('tampilkan', 1),
                'soal as esai_selected' => fn ($q) => $q->where('jenis', 5)->where('tampilkan', 1),
            ])
            ->where('id_tp', $tp)->where('id_smt', $smt)
            ->when($guruId !== null, fn ($query) => $query->where('bank_guru_id', $guruId))
            ->orderBy('bank_nama')
            ->get()
            ->map(function (BankSoal $bank) {
                $ready = $bank->pg_selected >= (int) $bank->tampil_pg
                    && $bank->kompleks_selected >= (int) $bank->tampil_kompleks
                    && $bank->jodohkan_selected >= (int) $bank->tampil_jodohkan
                    && $bank->isian_selected >= (int) $bank->tampil_isian
                    && $bank->esai_selected >= (int) $bank->tampil_esai
                    && ((int) $bank->tampil_pg + (int) $bank->tampil_kompleks + (int) $bank->tampil_jodohkan + (int) $bank->tampil_isian + (int) $bank->tampil_esai) > 0;

                return [
                    'id_bank' => $bank->id_bank,
                    'bank_kode' => $bank->bank_kode,
                    'bank_nama' => $bank->bank_nama,
                    'mapel' => $bank->mapel?->nama_mapel,
                    'ready' => $ready,
                ];
            })
            ->values()
            ->all();
    }

    private function dateInput(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return str_replace(' ', 'T', substr($value, 0, 16));
    }

    private function activePeriod(): array
    {
        return [
            TahunPelajaran::where('active', 1)->value('id_tp'),
            Semester::where('active', 1)->value('id_smt'),
        ];
    }

    private function periodeLabel(?int $tp, ?int $smt): string
    {
        $tahun = $tp ? TahunPelajaran::where('id_tp', $tp)->value('tahun') : null;
        $semester = $smt ? Semester::where('id_smt', $smt)->value('smt') : null;

        return trim(($tahun ?? '-').' · '.($semester ?? '-'));
    }
}
