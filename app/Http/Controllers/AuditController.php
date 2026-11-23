<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn ($row) => $this->row($row));

        $users = DB::table('users')
            ->whereIn('id', $this->idUserLog())
            ->orderBy('first_name')
            ->get(['users.id', 'users.username', 'users.first_name', 'users.last_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: $row->username,
            ]);

        return Inertia::render('Setting/Audit', [
            'logs' => $logs,
            'users' => $users,
            'actions' => collect(AuditLog::ACTIONS)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'filters' => $filters,
        ]);
    }

    /**
     * Ekspor jejak audit memakai filter yang sama dengan halaman agar hasil
     * pemeriksaan bisa diserahkan ke pihak luar tanpa membuka aplikasi.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $headers = ['Waktu', 'Pengguna', 'Username', 'Peran', 'Aksi', 'Detail', 'IP', 'Agent'];

        $rows = $this->query($filters)
            ->limit(20000)
            ->get()
            ->map(function ($row) {
                $data = $this->row($row);

                return [
                    $data['waktu'],
                    $data['user'],
                    $data['username'],
                    $data['role'],
                    $data['aksi'],
                    $data['detail'],
                    $data['ip'],
                    $data['agent'],
                ];
            });

        AuditLog::record('setting.audit.export', 'Ekspor jejak audit');

        return XlsxExport::download(
            'audit-'.now()->format('Ymd-Hi').'.xlsx',
            $headers,
            $rows,
            'Jejak Audit'
        );
    }

    /**
     * @return array{q: string, aksi: string, user: int, dari: string, sampai: string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 40);
        $perPage = in_array($perPage, [25, 40, 50, 100], true) ? $perPage : 40;

        return [
            'q' => trim((string) $request->query('q')),
            'aksi' => trim((string) $request->query('aksi')),
            'user' => (int) $request->query('user'),
            'dari' => (string) $request->query('dari'),
            'sampai' => (string) $request->query('sampai'),
            'per_page' => $perPage,
        ];
    }

    private function query(array $filters)
    {
        return DB::query()
            ->fromSub($this->gabunganLog(), 'audit')
            ->leftJoin('users', 'users.id', '=', 'audit.id_user')
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($inner) use ($filters) {
                $inner->where('audit.log_desc', 'like', '%'.$filters['q'].'%')
                    ->orWhere('users.username', 'like', '%'.$filters['q'].'%')
                    ->orWhere('users.first_name', 'like', '%'.$filters['q'].'%')
                    ->orWhere('audit.address', 'like', '%'.$filters['q'].'%');
            }))
            ->when($filters['aksi'] !== '' && isset(AuditLog::ACTIONS[$filters['aksi']]),
                fn ($query) => $query->where('audit.log_desc', 'like', $filters['aksi'].'%'))
            ->when($filters['user'] > 0, fn ($query) => $query->where('audit.id_user', $filters['user']))
            ->when($this->validDate($filters['dari']),
                fn ($query) => $query->where('audit.log_time', '>=', Carbon::parse($filters['dari'])->startOfDay()))
            ->when($this->validDate($filters['sampai']),
                fn ($query) => $query->where('audit.log_time', '<=', Carbon::parse($filters['sampai'])->endOfDay()))
            ->orderByDesc('audit.log_time')
            ->orderByDesc('audit.id_log');
    }

    /**
     * @return array<string, mixed>
     */
    private function row($row): array
    {
        $description = (string) $row->log_desc;
        $code = str_contains($description, ':') ? strstr($description, ':', true) : $description;
        $detail = str_contains($description, ':') ? ltrim(substr($description, strlen($code) + 1)) : '';

        return [
            'id_log' => (int) $row->id_log,
            'waktu' => $row->log_time ? Carbon::parse($row->log_time)->isoFormat('D MMM YYYY, HH:mm') : '-',
            'user' => $row->first_name ? trim($row->first_name.' '.($row->last_name ?? '')) : ($row->username ?? 'Sistem'),
            'username' => $row->username,
            'role' => (string) $row->name_group,
            'aksi' => AuditLog::ACTIONS[$code] ?? $code,
            'kode' => $code,
            'detail' => $detail,
            'ip' => (string) $row->address,
            'agent' => (string) $row->agent,
        ];
    }

    /**
     * Log lama dipindahkan ke log_arsip oleh cbt:arsip-log, sehingga halaman
     * audit harus membaca keduanya agar jejak audit tidak tampak hilang.
     */
    private function gabunganLog()
    {
        $columns = ['id_log', 'log_time', 'id_user', 'name_group', 'log_desc', 'address', 'agent'];

        return DB::table('log')
            ->select($columns)
            ->unionAll(DB::table('log_arsip')->select($columns));
    }

    private function idUserLog()
    {
        $gabungan = DB::table('log')
            ->select('id_user')
            ->where('id_user', '>', 0)
            ->union(DB::table('log_arsip')->select('id_user')->where('id_user', '>', 0));

        return DB::query()->fromSub($gabungan, 'sumber')->select('sumber.id_user');
    }

    private function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
