<?php

namespace App\Http\Controllers;

use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $like = '%'.$query.'%';
        $encoded = urlencode($query);
        $groups = [];

        $siswas = Siswa::query()
            ->where(function ($q) use ($like) {
                $q->where('nama', 'like', $like)
                    ->orWhere('nis', 'like', $like)
                    ->orWhere('nisn', 'like', $like)
                    ->orWhere('username', 'like', $like);
            })
            ->orderBy('nama')
            ->limit(5)
            ->get(['id_siswa', 'nama', 'nis', 'nisn']);

        if ($siswas->isNotEmpty()) {
            $groups[] = [
                'label' => 'Siswa',
                'items' => $siswas->map(fn (Siswa $siswa) => [
                    'title' => $siswa->nama,
                    'subtitle' => 'NIS '.$siswa->nis.' · NISN '.$siswa->nisn,
                    'href' => '/master/siswa?search='.urlencode($siswa->nis ?: $siswa->nama),
                ])->values()->all(),
            ];
        }

        $gurus = Guru::query()
            ->where(function ($q) use ($like) {
                $q->where('nama_guru', 'like', $like)
                    ->orWhere('nip', 'like', $like)
                    ->orWhere('username', 'like', $like);
            })
            ->orderBy('nama_guru')
            ->limit(5)
            ->get(['id_guru', 'nama_guru', 'nip']);

        if ($gurus->isNotEmpty()) {
            $groups[] = [
                'label' => 'Guru',
                'items' => $gurus->map(fn (Guru $guru) => [
                    'title' => $guru->nama_guru,
                    'subtitle' => 'NIP '.$guru->nip,
                    'href' => '/master/guru?search='.urlencode($guru->nip ?: $guru->nama_guru),
                ])->values()->all(),
            ];
        }

        $mapels = Mapel::query()
            ->where(fn ($q) => $q->where('nama_mapel', 'like', $like)->orWhere('kode', 'like', $like))
            ->orderBy('urutan')
            ->limit(5)
            ->get(['id_mapel', 'nama_mapel', 'kode']);

        if ($mapels->isNotEmpty()) {
            $groups[] = [
                'label' => 'Mata Pelajaran',
                'items' => $mapels->map(fn (Mapel $mapel) => [
                    'title' => $mapel->nama_mapel,
                    'subtitle' => 'Kode '.($mapel->kode ?: '-'),
                    'href' => '/master/mapel?search='.urlencode($mapel->kode ?: $mapel->nama_mapel),
                ])->values()->all(),
            ];
        }

        $banks = BankSoal::query()
            ->where(function ($q) use ($like) {
                $q->where('bank_nama', 'like', $like)->orWhere('bank_kode', 'like', $like);
            })
            ->when(! $user->isAdmin(), fn ($q) => $q->where('bank_guru_id', (int) (Guru::where('id_user', $user->id)->value('id_guru') ?? 0)))
            ->orderByDesc('id_bank')
            ->limit(5)
            ->get(['id_bank', 'bank_nama', 'bank_kode']);

        if ($banks->isNotEmpty()) {
            $groups[] = [
                'label' => 'Bank Soal',
                'items' => $banks->map(fn (BankSoal $bank) => [
                    'title' => $bank->bank_nama,
                    'subtitle' => 'Kode '.($bank->bank_kode ?: '-'),
                    'href' => '/cbt/bank-soal?search='.urlencode($bank->bank_kode ?: $bank->bank_nama),
                ])->values()->all(),
            ];
        }

        $kelas = Kelas::query()
            ->where('nama_kelas', 'like', $like)
            ->orderBy('nama_kelas')
            ->limit(5)
            ->get(['id_kelas', 'nama_kelas']);

        if ($kelas->isNotEmpty()) {
            $groups[] = [
                'label' => 'Kelas',
                'items' => $kelas->map(fn (Kelas $row) => [
                    'title' => $row->nama_kelas,
                    'subtitle' => 'Rombel',
                    'href' => '/master/kelas?search='.urlencode($row->nama_kelas),
                ])->values()->all(),
            ];
        }

        if ($user->isAdmin()) {
            $users = User::query()
                ->with('groups')
                ->where(fn ($q) => $q->where('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like))
                ->orderBy('username')
                ->limit(5)
                ->get(['id', 'username', 'first_name', 'last_name']);

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Akun',
                    'items' => $users->map(fn (User $account) => [
                        'title' => trim($account->first_name.' '.$account->last_name) ?: $account->username,
                        'subtitle' => '@'.$account->username,
                        'href' => '/users/'.$account->role,
                    ])->values()->all(),
                ];
            }
        }

        return response()->json(['q' => $query, 'groups' => $groups, 'encoded' => $encoded]);
    }
}
