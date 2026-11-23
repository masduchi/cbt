<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Cbt\Concerns\AuthorizesJadwal;
use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\NomorPeserta;
use App\Models\Siswa;
use App\Support\AuditLog;
use App\Support\Period;
use App\Support\VerifikasiDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KehadiranController extends Controller
{
    use AuthorizesJadwal;

    public function index(Request $request): Response
    {
        [$tahun, $semester] = Period::current();
        $tp = $tahun?->id_tp;
        $smt = $semester?->id_smt;
        $user = $request->user();

        $jadwals = Jadwal::with(['bank:id_bank,bank_nama,bank_kelas,bank_guru_id', 'jenis:id_jenis,nama_jenis'])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'bank',
                fn ($bank) => $bank->where('bank_guru_id', (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0))
            ))
            ->orderBy('tgl_mulai')
            ->get();

        $selected = $jadwals->firstWhere('id_jadwal', (int) $request->query('jadwal')) ?? $jadwals->first();
        $peserta = $selected ? $this->peserta($selected, $tp, $smt) : [];
        $hadir = collect($peserta)->where('hadir', true)->count();

        return Inertia::render('Cbt/Kehadiran', [
            'jadwalOptions' => $jadwals->map(fn (Jadwal $item) => [
                'id_jadwal' => $item->id_jadwal,
                'label' => trim(($item->bank?->bank_nama ?? '-').' · '.($item->jenis?->nama_jenis ?? '')),
                'tgl_mulai' => (string) $item->tgl_mulai,
            ]),
            'selected' => $selected?->id_jadwal,
            'jadwal' => $selected ? [
                'id_jadwal' => $selected->id_jadwal,
                'bank_nama' => $selected->bank?->bank_nama,
                'jenis' => $selected->jenis?->nama_jenis,
                'tgl_mulai' => (string) $selected->tgl_mulai,
                'tgl_selesai' => (string) $selected->tgl_selesai,
            ] : null,
            'peserta' => $peserta,
            'ringkasan' => [
                'peserta' => count($peserta),
                'hadir' => $hadir,
                'belum' => count($peserta) - $hadir,
            ],
            'periode' => trim(($tahun?->tahun ?? '-').' · '.($semester?->smt ?? '-')),
        ]);
    }

    /**
     * Tandai hadir lewat QR kartu peserta, nomor peserta, atau NIS. Masukan
     * dibaca apa adanya sehingga pemindai QR/barcode yang mengetik lalu menekan
     * Enter langsung berfungsi.
     */
    public function store(Request $request, int $jadwal): RedirectResponse
    {
        $model = $this->authorizeJadwal($request->user(), $jadwal);
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:255'],
        ]);

        $kode = trim($data['kode']);
        $peserta = collect($this->peserta($model, (int) $model->id_tp, (int) $model->id_smt));

        $hasil = $this->cocokkan($kode, $peserta, (int) $model->id_jadwal);

        if ($hasil['peserta'] === null) {
            return back()->with('error', (string) $hasil['error']);
        }

        $cocok = $hasil['peserta'];

        Kehadiran::updateOrCreate(
            [
                'id_jadwal' => (int) $model->id_jadwal,
                'id_siswa' => (int) $cocok['id_siswa'],
            ],
            [
                'id_ruang' => $cocok['id_ruang'],
                'id_sesi' => $cocok['id_sesi'],
                'kode' => $hasil['kode'],
                'metode' => $hasil['metode'],
                'id_user' => (int) $request->user()->id,
                'waktu' => now(),
            ]
        );

        AuditLog::record('cbt.kehadiran', $cocok['nama'].' hadir pada jadwal #'.$model->id_jadwal);

        return back()->with('success', $cocok['nama'].' ditandai hadir.');
    }

    public function destroy(Request $request, int $jadwal, int $siswa): RedirectResponse
    {
        $model = $this->authorizeJadwal($request->user(), $jadwal);

        $dihapus = Kehadiran::where('id_jadwal', (int) $model->id_jadwal)
            ->where('id_siswa', $siswa)
            ->delete();

        if ($dihapus === 0) {
            return back()->with('error', 'Peserta ini belum ditandai hadir.');
        }

        AuditLog::record('cbt.kehadiran.batal', 'Kehadiran siswa #'.$siswa.' pada jadwal #'.$model->id_jadwal.' dibatalkan');

        return back()->with('success', 'Tanda hadir dibatalkan.');
    }

    /**
     * Cocokkan masukan pemindai dengan peserta: nomor peserta/NIS lebih dulu,
     * lalu kode verifikasi pada QR kartu peserta.
     *
     * @param  Collection<int, array<string, mixed>>  $peserta
     * @return array{peserta: array<string, mixed>|null, kode: string, metode: string, error: string|null}
     */
    private function cocokkan(string $kode, Collection $peserta, int $jadwal): array
    {
        $langsung = $peserta->first(fn (array $row) => $this->kodeCocok($kode, $row));

        if ($langsung !== null) {
            return ['peserta' => $langsung, 'kode' => $kode, 'metode' => 'kode', 'error' => null];
        }

        $dokumen = $this->temukanVerifikasi($kode);

        if ($dokumen === null) {
            return $this->gagal('Kode "'.$kode.'" tidak cocok dengan peserta pada jadwal ini.');
        }

        if ($dokumen->revoked_at !== null) {
            return $this->gagal('Kode verifikasi '.$dokumen->kode.' sudah dicabut. Ketik nomor peserta/NIS.');
        }

        $ref = (string) $dokumen->ref;

        if ($dokumen->jenis === 'kartu-peserta-siswa') {
            [$idJadwal, $idSiswa] = array_pad(explode('-', $ref, 2), 2, '0');

            if ((int) $idJadwal !== $jadwal) {
                return $this->gagal('QR kartu ini milik jadwal ujian lain. Pastikan jadwal yang dipilih sudah benar.');
            }
        } elseif ($dokumen->jenis === 'kartu-global') {
            $idSiswa = explode('-', $ref)[0];
        } elseif ($dokumen->jenis === 'kartu-peserta') {
            return $this->gagal('QR pada kartu cetakan lama hanya memverifikasi keaslian dokumen, bukan identitas peserta. Ketik nomor peserta/NIS yang tertera di kartu.');
        } else {
            return $this->gagal('Kode verifikasi ini bukan kartu peserta ujian. Ketik nomor peserta/NIS.');
        }

        $row = $peserta->firstWhere('id_siswa', (int) $idSiswa);

        if ($row === null) {
            return $this->gagal('Siswa pada QR '.$dokumen->kode.' bukan peserta pada jadwal ini.');
        }

        return ['peserta' => $row, 'kode' => (string) $dokumen->kode, 'metode' => 'qr', 'error' => null];
    }

    /**
     * @return array{peserta: null, kode: string, metode: string, error: string}
     */
    private function gagal(string $pesan): array
    {
        return ['peserta' => null, 'kode' => '', 'metode' => 'kode', 'error' => $pesan];
    }

    /**
     * QR kartu berisi URL halaman verifikasi; kode diambil dari segmen terakhir
     * agar hasil pindai URL maupun kode yang diketik langsung sama-sama dikenali.
     */
    private function temukanVerifikasi(string $kode): ?object
    {
        if (str_contains($kode, '/')) {
            $path = (string) (parse_url($kode, PHP_URL_PATH) ?: '');
            $kode = basename(rtrim($path, '/'));
        }

        $kode = strtoupper(trim(rawurldecode($kode)));

        return $kode === '' ? null : VerifikasiDokumen::temukan($kode);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function kodeCocok(string $kode, array $row): bool
    {
        $nomor = trim((string) ($row['no_peserta'] ?? ''));

        return ($nomor !== '' && strcasecmp($nomor, $kode) === 0)
            || strcasecmp(trim((string) ($row['nis'] ?? '')), $kode) === 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peserta(Jadwal $jadwal, ?int $tp, ?int $smt): array
    {
        $bankKelas = array_filter(array_map('intval', explode(',', (string) $jadwal->bank?->bank_kelas)));

        $kelasSiswa = DB::table('kelas_siswa')
            ->join('master_kelas', 'master_kelas.id_kelas', '=', 'kelas_siswa.id_kelas')
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->when($bankKelas, fn ($query) => $query->whereIn('kelas_siswa.id_kelas', $bankKelas))
            ->get(['kelas_siswa.id_siswa', 'kelas_siswa.id_kelas', 'master_kelas.nama_kelas'])
            ->keyBy('id_siswa');

        $nomor = NomorPeserta::where('id_tp', $tp)->where('id_smt', $smt)->pluck('nomor_peserta', 'id_siswa');

        $lokasi = DB::table('cbt_kelas_ruang')
            ->leftJoin('cbt_ruang', 'cbt_ruang.id_ruang', '=', 'cbt_kelas_ruang.id_ruang')
            ->leftJoin('cbt_sesi', 'cbt_sesi.id_sesi', '=', 'cbt_kelas_ruang.id_sesi')
            ->where('cbt_kelas_ruang.id_tp', $tp)
            ->where('cbt_kelas_ruang.id_smt', $smt)
            ->get(['cbt_kelas_ruang.id_kelas', 'cbt_kelas_ruang.id_ruang', 'cbt_kelas_ruang.id_sesi', 'cbt_ruang.nama_ruang', 'cbt_sesi.nama_sesi'])
            ->keyBy('id_kelas');

        $hadir = Kehadiran::where('id_jadwal', (int) $jadwal->id_jadwal)->get()->keyBy('id_siswa');

        return Siswa::whereIn('id_siswa', $kelasSiswa->keys())
            ->orderBy('nama')
            ->get()
            ->map(function (Siswa $siswa) use ($kelasSiswa, $nomor, $lokasi, $hadir) {
                $kelas = $kelasSiswa->get($siswa->id_siswa);
                $tempat = $kelas ? $lokasi->get($kelas->id_kelas) : null;
                $catatan = $hadir->get($siswa->id_siswa);

                return [
                    'id_siswa' => (int) $siswa->id_siswa,
                    'nama' => (string) $siswa->nama,
                    'nis' => (string) $siswa->nis,
                    'no_peserta' => $nomor[$siswa->id_siswa] ?? null,
                    'kelas' => (string) ($kelas->nama_kelas ?? ''),
                    'ruang' => $tempat->nama_ruang ?? null,
                    'sesi' => $tempat->nama_sesi ?? null,
                    'id_ruang' => $tempat->id_ruang ?? null,
                    'id_sesi' => $tempat->id_sesi ?? null,
                    'hadir' => $catatan !== null,
                    'waktu' => $catatan?->waktu?->format('H:i:s'),
                ];
            })
            ->values()
            ->all();
    }
}
