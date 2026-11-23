<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SecureDefaultAccounts extends Command
{
    /**
     * Password bawaan yang dipakai pada data contoh dan instalasi awal.
     *
     * @var list<string>
     */
    private const DEFAULT_PASSWORDS = ['admin123', 'password', '123456', '12345678'];

    protected $signature = 'app:secure-default-accounts
        {--apply : Tandai akun agar wajib mengganti password saat login berikutnya}';

    protected $description = 'Mendeteksi akun yang masih memakai password bawaan sebelum aplikasi dipakai di produksi';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $flagged = 0;

        foreach (User::orderBy('id')->get() as $user) {
            $usesDefault = collect(self::DEFAULT_PASSWORDS)
                ->contains(fn (string $password) => Hash::check($password, (string) $user->password));

            if (! $usesDefault) {
                continue;
            }

            $flagged++;

            if (! $apply) {
                $this->line("  - {$user->username} ({$user->role}) masih memakai password bawaan");

                continue;
            }

            $user->forceFill(['must_change_password' => 1])->save();

            $this->line("  - {$user->username} ({$user->role}) ditandai wajib ganti password");
        }

        if ($flagged === 0) {
            $this->info('Tidak ada akun yang memakai password bawaan.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn("{$flagged} akun perlu diamankan. Jalankan ulang dengan --apply untuk menandainya.");

            return self::SUCCESS;
        }

        $this->info("{$flagged} akun wajib mengganti password pada login berikutnya.");

        return self::SUCCESS;
    }
}
