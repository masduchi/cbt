<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\LevelKelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\AuditLog;
use App\Support\ReferenceGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KelasController extends Controller
{
    public function index(Request $request): Response
    {
        [$tp, $smt] = $this->activePeriod();

        $kelas = Kelas::with(['jurusan:id_jurusan,nama_jurusan', 'level:id_level,level', 'waliKelas:id_guru,nama_guru'])
            ->withCount(['siswa as siswa_count' => fn ($q) => $q->where('kelas_siswa.id_tp', $tp)->where('kelas_siswa.id_smt', $smt)])
            ->where('id_tp', $tp)
            ->where('id_smt', $smt)
            ->when($request->boolean('arsip'), fn ($query) => $query->onlyTrashed())
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_kelas', 'like', "%{$search}%")
                        ->orWhere('kode_kelas', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_kelas')
            ->get();

        return Inertia::render('Master/Kelas', [
            'kelas' => $kelas,
            'levels' => LevelKelas::orderBy('id_level')->get(),
            'jurusan' => Jurusan::orderBy('nama_jurusan')->get(['id_jurusan', 'nama_jurusan']),
            'guru' => Guru::orderBy('nama_guru')->get(['id_guru', 'nama_guru']),
            'periode' => $this->periodeLabel($tp, $smt),
            'arsip' => $request->boolean('arsip'),
            'filters' => ['search' => $request->query('search', '')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $data = $this->validated($request);
        $data['id_tp'] = $tp;
        $data['id_smt'] = $smt;
        $data['set_siswa'] = '0';

        $kelas = Kelas::create($data);

        AuditLog::record('master.kelas', 'Kelas '.$kelas->nama_kelas.' ditambahkan');

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->update($this->validated($request));

        AuditLog::record('master.kelas', 'Kelas #'.$kelas->id_kelas.' '.$kelas->nama_kelas.' diperbarui');

        return back()->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $kelas = Kelas::findOrFail($id);
        $used = ReferenceGuard::kelas($kelas->id_kelas);

        if ($used) {
            $kelas->delete();

            AuditLog::record('master.kelas.delete', 'Kelas #'.$kelas->id_kelas.' '.$kelas->nama_kelas.' diarsipkan, riwayat tetap tersimpan: '.implode(', ', $used));

            return back()->with('success', 'Kelas diarsipkan. Riwayat berikut tetap tersimpan: '.implode(', ', $used).'.');
        }

        DB::table('kelas_siswa')->where('id_kelas', $kelas->id_kelas)->delete();
        $kelas->forceDelete();

        AuditLog::record('master.kelas.delete', 'Kelas #'.$kelas->id_kelas.' '.$kelas->nama_kelas.' dihapus');

        return back()->with('success', 'Kelas berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        $kelas = Kelas::withTrashed()->findOrFail($id);
        $kelas->restore();

        AuditLog::record('master.kelas', 'Kelas #'.$kelas->id_kelas.' '.$kelas->nama_kelas.' dipulihkan dari arsip');

        return back()->with('success', 'Kelas dipulihkan dari arsip.');
    }

    public function anggota(int $id, Request $request): Response
    {
        $kelas = Kelas::with(['jurusan:id_jurusan,nama_jurusan', 'level:id_level,level'])->findOrFail($id);
        [$tp, $smt] = $this->activePeriod();

        $members = DB::table('kelas_siswa')
            ->join('master_siswa', 'master_siswa.id_siswa', '=', 'kelas_siswa.id_siswa')
            ->where('kelas_siswa.id_kelas', $kelas->id_kelas)
            ->where('kelas_siswa.id_tp', $tp)
            ->where('kelas_siswa.id_smt', $smt)
            ->orderBy('master_siswa.nama')
            ->get(['master_siswa.id_siswa', 'master_siswa.nis', 'master_siswa.nisn', 'master_siswa.nama', 'master_siswa.jenis_kelamin']);

        $available = Siswa::query()
            ->whereNotIn('id_siswa', function ($query) use ($tp, $smt) {
                $query->select('id_siswa')->from('kelas_siswa')
                    ->where('id_tp', $tp)->where('id_smt', $smt)
                    ->whereNotNull('id_siswa');
            });

        if ($search = trim((string) $request->query('search'))) {
            $available->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Master/KelasAnggota', [
            'kelas' => $kelas,
            'members' => $members,
            'available' => $available->orderBy('nama')->limit(50)->get(['id_siswa', 'nis', 'nisn', 'nama', 'jenis_kelamin']),
            'search' => $request->query('search', ''),
            'periode' => $this->periodeLabel($tp, $smt),
        ]);
    }

    public function addAnggota(Request $request, int $id): RedirectResponse
    {
        $kelas = Kelas::findOrFail($id);
        $data = $request->validate([
            'siswa' => ['required', 'array', 'min:1'],
            'siswa.*' => ['integer', 'exists:master_siswa,id_siswa'],
        ]);

        [$tp, $smt] = $this->activePeriod();

        if (! $tp || ! $smt) {
            return back()->with('error', 'Aktifkan tahun pelajaran dan semester terlebih dahulu.');
        }

        $nextId = (int) DB::table('kelas_siswa')->max('id_kelas_siswa');

        $added = 0;
        foreach (array_unique($data['siswa']) as $siswaId) {
            $exists = DB::table('kelas_siswa')
                ->where('id_tp', $tp)->where('id_smt', $smt)
                ->where('id_siswa', $siswaId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('kelas_siswa')->insert([
                'id_kelas_siswa' => ++$nextId,
                'id_tp' => $tp,
                'id_smt' => $smt,
                'id_siswa' => $siswaId,
                'id_kelas' => $kelas->id_kelas,
            ]);
            $added++;
        }

        AuditLog::record('master.kelas.anggota', $added.' siswa ditambahkan ke kelas '.$kelas->nama_kelas);

        return back()->with('success', "{$added} siswa ditambahkan ke kelas {$kelas->nama_kelas}.");
    }

    public function removeAnggota(int $id, int $siswa): RedirectResponse
    {
        Kelas::findOrFail($id);
        [$tp, $smt] = $this->activePeriod();

        DB::table('kelas_siswa')
            ->where('id_kelas', $id)
            ->where('id_siswa', $siswa)
            ->when($tp && $smt, fn ($query) => $query->where('id_tp', $tp)->where('id_smt', $smt))
            ->delete();

        AuditLog::record('master.kelas.anggota', 'Siswa #'.$siswa.' dikeluarkan dari kelas #'.$id);

        return back()->with('success', 'Siswa dikeluarkan dari kelas.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_kelas' => ['required', 'string', 'max:30'],
            'kode_kelas' => ['nullable', 'string', 'max:20'],
            'level_id' => ['required', 'integer', 'exists:level_kelas,id_level'],
            'jurusan_id' => ['nullable', 'integer', 'exists:master_jurusan,id_jurusan'],
            'guru_id' => ['nullable', 'integer', 'exists:master_guru,id_guru'],
        ]);
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
