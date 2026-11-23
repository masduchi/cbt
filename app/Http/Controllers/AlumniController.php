<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Models\BukuInduk;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AlumniController extends Controller
{
    use ResolvesPerPage;

    private const STATUS = [
        2 => 'Lulus',
        3 => 'Pindah',
        4 => 'Keluar',
    ];

    public function index(Request $request): Response
    {
        $status = (int) $request->query('status');
        $search = trim((string) $request->query('search'));

        $query = DB::table('buku_induk')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'buku_induk.id_siswa')
            ->whereIn('buku_induk.status', array_keys(self::STATUS))
            ->select([
                'buku_induk.id_siswa',
                'buku_induk.status',
                'buku_induk.tahun_lulus',
                'buku_induk.kelas_akhir',
                'buku_induk.no_ijazah',
                'buku_induk.lanjut_ke',
                'buku_induk.pindah_ke',
                'buku_induk.alasan_pindah',
                'buku_induk.tgl_pindah',
                'buku_induk.bekerja_di',
                'buku_induk.catatan_penting',
                'master_siswa.nama',
                'master_siswa.nis',
                'master_siswa.nisn',
            ])
            ->when(in_array($status, array_keys(self::STATUS), true), fn ($q) => $q->where('buku_induk.status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('master_siswa.nama', 'like', "%{$search}%")
                        ->orWhere('master_siswa.nis', 'like', "%{$search}%")
                        ->orWhere('master_siswa.nisn', 'like', "%{$search}%");
                });
            })
            ->orderBy('master_siswa.nama');

        $classes = $this->classes();

        return Inertia::render('Alumni/Index', [
            'alumni' => $query->paginate($this->perPage($request))->withQueryString()->through(fn ($row) => [
                'id_siswa' => (int) $row->id_siswa,
                'nama' => (string) $row->nama,
                'nis' => (string) $row->nis,
                'nisn' => (string) $row->nisn,
                'status' => (int) $row->status,
                'status_label' => self::STATUS[(int) $row->status] ?? '-',
                'tahun_lulus' => $row->tahun_lulus ? (int) $row->tahun_lulus : null,
                'kelas_akhir' => (string) ($row->kelas_akhir ?? ''),
                'no_ijazah' => (string) ($row->no_ijazah ?? ''),
                'lanjut_ke' => (string) ($row->lanjut_ke ?? ''),
                'pindah_ke' => (string) ($row->pindah_ke ?? ''),
                'alasan_pindah' => (string) ($row->alasan_pindah ?? ''),
                'tgl_pindah' => (string) ($row->tgl_pindah ?? ''),
                'bekerja_di' => (string) ($row->bekerja_di ?? ''),
                'catatan_penting' => (string) ($row->catatan_penting ?? ''),
            ]),
            'filters' => ['status' => $status ?: null, 'search' => $search],
            'classes' => $classes,
            'stats' => $this->stats(),
            'tahunOptions' => TahunPelajaran::orderByDesc('tahun')->pluck('tahun')->values(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kelas' => ['required', 'integer', 'exists:master_kelas,id_kelas'],
            'status' => ['required', 'integer', 'in:2,3,4'],
            'tahun_lulus' => ['required', 'integer', 'min:1900', 'max:2200'],
        ]);

        $kelas = Kelas::findOrFail((int) $data['kelas']);
        $studentIds = DB::table('kelas_siswa')
            ->where('id_tp', $kelas->id_tp)
            ->where('id_smt', $kelas->id_smt)
            ->where('id_kelas', $kelas->id_kelas)
            ->pluck('id_siswa')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return back()->with('error', 'Kelas ini belum memiliki siswa.');
        }

        $siswas = Siswa::whereIn('id_siswa', $studentIds)->get(['id_siswa', 'uid']);

        DB::transaction(function () use ($siswas, $kelas, $data) {
            foreach ($siswas as $siswa) {
                BukuInduk::updateOrCreate(
                    ['id_siswa' => $siswa->id_siswa],
                    [
                        'uid' => (string) $siswa->uid,
                        'status' => (int) $data['status'],
                        'tahun_lulus' => (int) $data['tahun_lulus'],
                        'kelas_akhir' => $kelas->nama_kelas,
                    ]
                );
            }
        });

        AuditLog::record('alumni.generate', $siswas->count().' siswa kelas '.$kelas->nama_kelas.' → '.self::STATUS[(int) $data['status']]);

        return back()->with('success', $siswas->count().' siswa dipindahkan ke data alumni.');
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'integer', 'in:2,3,4'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'kelas_akhir' => ['nullable', 'string', 'max:50'],
            'no_ijazah' => ['nullable', 'string', 'max:50'],
            'lanjut_ke' => ['nullable', 'string', 'max:50'],
            'pindah_ke' => ['nullable', 'string', 'max:100'],
            'alasan_pindah' => ['nullable', 'string', 'max:100'],
            'tgl_pindah' => ['nullable', 'date'],
            'bekerja_di' => ['nullable', 'string', 'max:100'],
            'catatan_penting' => ['nullable', 'string', 'max:2000'],
        ]);

        BukuInduk::updateOrCreate(
            ['id_siswa' => $siswa->id_siswa],
            [
                ...$data,
                'uid' => (string) $siswa->uid,
                'tahun_lulus' => $data['tahun_lulus'] ?? null,
            ]
        );

        AuditLog::record('alumni.update', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' → '.self::STATUS[(int) $data['status']]);

        return back()->with('success', 'Data alumni diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        $record = BukuInduk::find($siswa->id_siswa);

        if ($record) {
            $record->update(['status' => 1]);
        }

        AuditLog::record('alumni.destroy', 'Siswa #'.$siswa->id_siswa.' '.$siswa->nama.' dikembalikan ke data siswa');

        return back()->with('success', 'Status alumni dibatalkan.');
    }

    private function classes(): array
    {
        [$tahun, $semester] = Period::current();

        if (! $tahun || ! $semester) {
            return [];
        }

        return Kelas::where('id_tp', $tahun->id_tp)
            ->where('id_smt', $semester->id_smt)
            ->orderBy('nama_kelas')
            ->get(['id_kelas', 'nama_kelas'])
            ->map(fn (Kelas $kelas) => [
                'id_kelas' => (int) $kelas->id_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'jumlah' => DB::table('kelas_siswa')
                    ->where('id_tp', $tahun->id_tp)
                    ->where('id_smt', $semester->id_smt)
                    ->where('id_kelas', $kelas->id_kelas)
                    ->count(),
            ])
            ->all();
    }

    private function stats(): array
    {
        $counts = DB::table('buku_induk')
            ->whereIn('status', array_keys(self::STATUS))
            ->select('status', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'lulus' => (int) ($counts[2] ?? 0),
            'pindah' => (int) ($counts[3] ?? 0),
            'keluar' => (int) ($counts[4] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }
}
