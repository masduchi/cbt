<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AuditLog
{
    /**
     * Kode aksi yang dipakai pada halaman audit beserta labelnya.
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'auth.login' => 'Masuk aplikasi',
        'auth.login_failed' => 'Percobaan masuk gagal',
        'user.create' => 'Buat akun',
        'user.update' => 'Ubah akun',
        'user.status' => 'Aktif/nonaktif akun',
        'user.delete' => 'Hapus akun',
        'user.wali' => 'Tautan wali murid',
        'master.tahun' => 'Buat/ubah tahun pelajaran',
        'master.tahun.delete' => 'Hapus tahun pelajaran',
        'master.tahun.aktif' => 'Aktifkan tahun/semester',
        'master.tahun.hari-efektif' => 'Ubah hari efektif',
        'master.rollover' => 'Rollover tahun pelajaran',
        'master.jurusan' => 'Buat/ubah jurusan',
        'master.jurusan.delete' => 'Hapus jurusan',
        'master.mapel' => 'Buat/ubah mata pelajaran',
        'master.mapel.delete' => 'Hapus/nonaktifkan mata pelajaran',
        'master.mapel.import' => 'Impor mata pelajaran',
        'master.kelompok-mapel' => 'Buat/ubah kelompok mapel',
        'master.kelompok-mapel.delete' => 'Hapus kelompok mapel',
        'master.ekstra' => 'Buat/ubah ekstrakurikuler',
        'master.ekstra.delete' => 'Hapus ekstrakurikuler',
        'master.kelas' => 'Buat/ubah kelas',
        'master.kelas.delete' => 'Hapus/arsipkan kelas',
        'master.kelas.anggota' => 'Ubah anggota kelas',
        'master.siswa' => 'Buat/ubah data siswa',
        'master.siswa.delete' => 'Hapus/arsipkan siswa',
        'master.siswa.import' => 'Impor data siswa',
        'master.siswa.buku-induk' => 'Ubah buku induk siswa',
        'master.guru' => 'Buat/ubah data guru',
        'master.guru.delete' => 'Hapus/arsipkan guru',
        'master.guru.import' => 'Impor data guru',
        'setting.update' => 'Ubah pengaturan sekolah',
        'setting.audit.export' => 'Ekspor jejak audit',
        'setting.api' => 'Ubah pengaturan API',
        'setting.running' => 'Ubah running text',
        'setting.kop' => 'Ubah kop cetak',
        'rapor.setting' => 'Ubah pengaturan rapor',
        'rapor.kkm' => 'Ubah KKM & bobot',
        'rapor.restore' => 'Restore data rapor',
        'rapor.lock' => 'Kunci rapor',
        'rapor.unlock' => 'Buka kunci rapor',
        'rapor.nilai.harian' => 'Simpan nilai harian',
        'rapor.nilai.harian.import' => 'Impor nilai harian',
        'rapor.nilai.pts' => 'Simpan nilai PTS',
        'rapor.nilai.pts.import' => 'Impor nilai PTS',
        'rapor.nilai.pas' => 'Simpan nilai PAS',
        'rapor.nilai.pas.import' => 'Impor nilai PAS',
        'alumni.generate' => 'Buat data alumni',
        'alumni.update' => 'Ubah data alumni',
        'alumni.destroy' => 'Batalkan status alumni',
        'wali.struktur' => 'Simpan struktur organisasi kelas',
        'wali.catatan.mapel' => 'Simpan catatan mata pelajaran',
        'wali.catatan.mapel.delete' => 'Hapus catatan mata pelajaran',
        'wali.catatan.wali' => 'Simpan catatan wali kelas',
        'wali.catatan.wali.delete' => 'Hapus catatan wali kelas',
        'wali.siswa' => 'Ubah data siswa (wali kelas)',
        'wali.siswa.foto' => 'Ubah/hapus foto siswa (wali kelas)',
        'elearning.materi' => 'Buat/ubah materi',
        'elearning.tugas' => 'Buat/ubah tugas',
        'elearning.delete' => 'Hapus materi/tugas',
        'elearning.status' => 'Aktif/nonaktif materi/tugas',
        'elearning.tugas.nilai' => 'Nilai tugas siswa',
        'elearning.jadwal' => 'Atur jadwal materi/tugas',
        'elearning.jadwal-pelajaran' => 'Atur jadwal pelajaran',
        'pengumuman.store' => 'Kirim pengumuman',
        'pengumuman.destroy' => 'Hapus pengumuman',
        'pengumuman.comment' => 'Komentar pengumuman',
        'pengumuman.comment.delete' => 'Hapus komentar pengumuman',
        'pengumuman.reply' => 'Balas komentar pengumuman',
        'pengumuman.reply.delete' => 'Hapus balasan pengumuman',
        'elearning.nilai' => 'Nilai materi/tugas',
        'elearning.buka' => 'Buka materi/tugas',
        'elearning.selesai' => 'Selesaikan materi/tugas',
        'ujian.mulai' => 'Mulai ujian',
        'ujian.pelanggaran' => 'Pelanggaran ujian',
        'ujian.selesai' => 'Selesaikan ujian',
        'ujian.paksa' => 'Selesaikan ujian (pengawas)',
        'cbt.reset' => 'Reset waktu ujian',
        'cbt.force' => 'Selesaikan ujian paksa',
        'cbt.repeat' => 'Izinkan ulang ujian',
        'cbt.jadwal' => 'Buat/ubah jadwal ujian',
        'cbt.jadwal.delete' => 'Hapus jadwal ujian',
        'cbt.jadwal.status' => 'Aktif/nonaktif jadwal ujian',
        'cbt.token' => 'Perbarui token ujian',
        'cbt.bank' => 'Buat/ubah bank soal',
        'cbt.bank.delete' => 'Hapus bank soal',
        'cbt.sesi' => 'Buat/ubah sesi ujian',
        'cbt.sesi.delete' => 'Hapus sesi ujian',
        'cbt.ruang' => 'Buat/ubah ruang ujian',
        'cbt.ruang.delete' => 'Hapus ruang ujian',
        'cbt.kelasruang' => 'Atur kelas & ruang peserta',
        'cbt.soal' => 'Buat/ubah butir soal',
        'cbt.soal.delete' => 'Hapus butir soal',
        'cbt.soal.impor' => 'Impor butir soal',
        'cbt.soal.pilih' => 'Pilih soal ujian',
        'cbt.pengawas' => 'Atur pengawas ujian',
        'cbt.nomor' => 'Atur nomor peserta',
        'cbt.jenis' => 'Buat/ubah jenis ujian',
        'cbt.jenis.delete' => 'Hapus jenis ujian',
        'cbt.remedial' => 'Buat jadwal remedial',
        'cbt.remedial.delete' => 'Hapus jadwal remedial',
        'cbt.rakit' => 'Rakit soal otomatis',
        'cbt.rubrik' => 'Ubah rubrik esai',
        'cbt.koreksi.simpan' => 'Simpan koreksi esai',
        'cbt.koreksi.final' => 'Tetapkan nilai final esai',
        'cbt.koreksi.penilai' => 'Atur korektor esai',
        'cbt.notifikasi.ulang' => 'Kirim ulang notifikasi hasil',
        'cbt.kehadiran' => 'Tandai kehadiran peserta',
        'cbt.kehadiran.batal' => 'Batalkan kehadiran peserta',
        'verifikasi.cabut' => 'Cabut kode verifikasi dokumen',
        'database.backup' => 'Backup database',
        'database.delete' => 'Hapus berkas backup',
        'database.truncate' => 'Kosongkan tabel',
        'database.restore' => 'Pulihkan database dari backup',
    ];

    public static function record(string $action, string $description = '', int $type = 2): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        self::insert((int) $user->id, (int) ($user->groups->first()?->id ?? 0), (string) $user->role, $action, $description, $type);
    }

    /**
     * Percobaan masuk yang gagal terjadi sebelum ada pengguna terautentikasi,
     * jadi dicatat tanpa id_user agar tetap terlihat di halaman audit.
     */
    public static function recordAttempt(string $action, string $description = ''): void
    {
        self::insert(0, 0, 'tamu', $action, $description, 1);
    }

    private static function insert(int $userId, int $groupId, string $groupName, string $action, string $description, int $type): void
    {
        DB::table('log')->insert([
            'log_time' => now(),
            'id_user' => $userId,
            'id_group' => $groupId,
            'name_group' => $groupName,
            'log_type' => $type,
            'log_desc' => trim($action.($description !== '' ? ': '.$description : '')),
            'address' => substr((string) request()->ip(), 0, 45),
            'agent' => substr((string) request()->userAgent(), 0, 255),
            'device' => 'web',
        ]);
    }
}
