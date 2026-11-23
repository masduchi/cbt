<?php

use App\Http\Controllers\AlumniController;
use App\Http\Controllers\ApiSettingController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BukuIndukController;
use App\Http\Controllers\Cbt\AnalisisController;
use App\Http\Controllers\Cbt\BankSoalController;
use App\Http\Controllers\Cbt\CetakController as CbtCetakController;
use App\Http\Controllers\Cbt\HasilController;
use App\Http\Controllers\Cbt\JadwalController;
use App\Http\Controllers\Cbt\JenisController;
use App\Http\Controllers\Cbt\KehadiranController;
use App\Http\Controllers\Cbt\KelasRuangController;
use App\Http\Controllers\Cbt\KoreksiController;
use App\Http\Controllers\Cbt\MediaController;
use App\Http\Controllers\Cbt\NomorPesertaController;
use App\Http\Controllers\Cbt\PengawasController;
use App\Http\Controllers\Cbt\RekapController;
use App\Http\Controllers\Cbt\RemedialController;
use App\Http\Controllers\Cbt\RuangController;
use App\Http\Controllers\Cbt\SesiController;
use App\Http\Controllers\Cbt\SoalController;
use App\Http\Controllers\Cbt\SoalImportExportController;
use App\Http\Controllers\Cbt\SoalMediaController;
use App\Http\Controllers\Cbt\StatusController;
use App\Http\Controllers\Cbt\TokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\Elearning\JadwalController as ElearningJadwalController;
use App\Http\Controllers\Elearning\JadwalPelajaranController;
use App\Http\Controllers\Elearning\LaporanController as ElearningLaporanController;
use App\Http\Controllers\Elearning\MateriController as ElearningMateriController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\KopCetakController;
use App\Http\Controllers\Master\EkstraController;
use App\Http\Controllers\Master\GuruController;
use App\Http\Controllers\Master\JurusanController;
use App\Http\Controllers\Master\KelasController;
use App\Http\Controllers\Master\KelompokMapelController;
use App\Http\Controllers\Master\MapelController;
use App\Http\Controllers\Master\RolloverController;
use App\Http\Controllers\Master\SiswaController;
use App\Http\Controllers\Master\TahunController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Rapor\BackupController as RaporBackupController;
use App\Http\Controllers\Rapor\CetakController as RaporCetakController;
use App\Http\Controllers\Rapor\DknController as RaporDknController;
use App\Http\Controllers\Rapor\KelasController as RaporKelasController;
use App\Http\Controllers\Rapor\NilaiController as RaporNilaiController;
use App\Http\Controllers\Rapor\SettingController as RaporSettingController;
use App\Http\Controllers\Rapor\TarikCbtController;
use App\Http\Controllers\RunningTextController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Siswa\ElearningController;
use App\Http\Controllers\Siswa\NotifikasiController as SiswaNotifikasiController;
use App\Http\Controllers\Siswa\PortalController as SiswaPortalController;
use App\Http\Controllers\Siswa\UjianController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifikasiAdminController;
use App\Http\Controllers\VerifikasiController;
use App\Http\Controllers\WaliKelasController;
use App\Http\Controllers\WaliPortalController;
use App\Support\Installer;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Installer::isInstalled() ? 'dashboard' : 'install.index');
});

Route::get('/health', HealthController::class)->name('health');

// Media soal disajikan lewat rute ini, bukan langsung dari disk publik.
Route::get('/media/{path}', SoalMediaController::class)
    ->where('path', '.*')
    ->middleware('auth')
    ->name('media.soal');

Route::get('verifikasi/{kode}', [VerifikasiController::class, 'show'])
    ->middleware('throttle:30,1')
    ->whereAlphaNumeric('kode')
    ->name('verifikasi.show');

Route::get('install', [InstallController::class, 'index'])->name('install.index');
Route::post('install/database', [InstallController::class, 'database'])->middleware('throttle:10,1')->name('install.database');
Route::post('install/administrator', [InstallController::class, 'admin'])->middleware('throttle:10,1')->name('install.admin');
Route::post('install/sekolah', [InstallController::class, 'school'])->middleware('throttle:10,1')->name('install.school');
Route::post('install/selesai', [InstallController::class, 'finish'])->name('install.finish');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->middleware('throttle:10,1');
    Route::get('/lupa-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/lupa-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', SearchController::class)->middleware('role:admin,guru')->name('search');
    Route::post('/periode', [PeriodController::class, 'update'])->name('periode.update');
    Route::post('/periode/reset', [PeriodController::class, 'reset'])->name('periode.reset');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::post('/profil', [ProfileController::class, 'updateProfile'])->name('profil.update');
    Route::post('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('master')->name('master.')->group(function () {
    Route::get('mapel', [MapelController::class, 'index'])->name('mapel.index');
    Route::get('siswa', [SiswaController::class, 'index'])->name('siswa.index');
    Route::get('kelas', [KelasController::class, 'index'])->name('kelas.index');
    Route::get('kelas/{id}/anggota', [KelasController::class, 'anggota'])->name('kelas.anggota');
});

Route::middleware(['auth', 'role:admin'])->prefix('master')->name('master.')->group(function () {
    Route::get('tahun/rollover', [RolloverController::class, 'index'])->name('tahun.rollover');
    Route::post('tahun/rollover', [RolloverController::class, 'store'])->name('tahun.rollover.store');
    Route::get('tahun', [TahunController::class, 'index'])->name('tahun.index');
    Route::post('tahun', [TahunController::class, 'store'])->name('tahun.store');
    Route::put('tahun/{id}', [TahunController::class, 'update'])->name('tahun.update');
    Route::delete('tahun/{id}', [TahunController::class, 'destroy'])->name('tahun.destroy');
    Route::post('tahun/{id}/aktif', [TahunController::class, 'activate'])->name('tahun.activate');
    Route::post('semester/{id}/aktif', [TahunController::class, 'activateSemester'])->name('semester.activate');
    Route::post('tahun/hari-efektif', [TahunController::class, 'saveHariEfektif'])->name('tahun.hari-efektif');

    Route::get('jurusan', [JurusanController::class, 'index'])->name('jurusan.index');
    Route::post('jurusan', [JurusanController::class, 'store'])->name('jurusan.store');
    Route::put('jurusan/{id}', [JurusanController::class, 'update'])->name('jurusan.update');
    Route::delete('jurusan/{id}', [JurusanController::class, 'destroy'])->name('jurusan.destroy');

    Route::post('mapel', [MapelController::class, 'store'])->name('mapel.store');
    Route::post('mapel/import', [MapelController::class, 'import'])->name('mapel.import');
    Route::get('mapel/import-template', [MapelController::class, 'importTemplate'])->name('mapel.import.template');
    Route::put('mapel/{id}', [MapelController::class, 'update'])->name('mapel.update');
    Route::delete('mapel/{id}', [MapelController::class, 'destroy'])->name('mapel.destroy');
    Route::post('mapel/{id}/restore', [MapelController::class, 'restore'])->name('mapel.restore');

    Route::get('kelompok-mapel', [KelompokMapelController::class, 'index'])->name('kelompok-mapel.index');
    Route::post('kelompok-mapel', [KelompokMapelController::class, 'store'])->name('kelompok-mapel.store');
    Route::put('kelompok-mapel/{id}', [KelompokMapelController::class, 'update'])->whereNumber('id')->name('kelompok-mapel.update');
    Route::delete('kelompok-mapel/{id}', [KelompokMapelController::class, 'destroy'])->whereNumber('id')->name('kelompok-mapel.destroy');

    Route::get('ekstra', [EkstraController::class, 'index'])->name('ekstra.index');
    Route::post('ekstra', [EkstraController::class, 'store'])->name('ekstra.store');
    Route::put('ekstra/{id}', [EkstraController::class, 'update'])->name('ekstra.update');
    Route::delete('ekstra/{id}', [EkstraController::class, 'destroy'])->name('ekstra.destroy');

    Route::get('siswa/ekspor', [SiswaController::class, 'export'])->name('siswa.export');
    Route::get('siswa/ekspor/dapodik', [SiswaController::class, 'exportDapodik'])->name('siswa.export.dapodik');
    Route::get('siswa/ekspor/emis', [SiswaController::class, 'exportEmis'])->name('siswa.export.emis');
    Route::get('siswa/create', [SiswaController::class, 'create'])->name('siswa.create');
    Route::post('siswa', [SiswaController::class, 'store'])->name('siswa.store');
    Route::post('siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
    Route::get('siswa/import-template', [SiswaController::class, 'importTemplate'])->name('siswa.import.template');
    Route::get('siswa/{id}/edit', [SiswaController::class, 'edit'])->name('siswa.edit');
    Route::post('siswa/{id}', [SiswaController::class, 'update'])->name('siswa.update');
    Route::delete('siswa/{id}', [SiswaController::class, 'destroy'])->name('siswa.destroy');
    Route::post('siswa/{id}/restore', [SiswaController::class, 'restore'])->name('siswa.restore');

    Route::post('kelas', [KelasController::class, 'store'])->name('kelas.store');
    Route::put('kelas/{id}', [KelasController::class, 'update'])->name('kelas.update');
    Route::delete('kelas/{id}', [KelasController::class, 'destroy'])->name('kelas.destroy');
    Route::post('kelas/{id}/restore', [KelasController::class, 'restore'])->name('kelas.restore');
    Route::post('kelas/{id}/anggota', [KelasController::class, 'addAnggota'])->name('kelas.anggota.add');
    Route::delete('kelas/{id}/anggota/{siswa}', [KelasController::class, 'removeAnggota'])->name('kelas.anggota.remove');

    Route::get('guru', [GuruController::class, 'index'])->name('guru.index');
    Route::post('guru', [GuruController::class, 'store'])->name('guru.store');
    Route::post('guru/import', [GuruController::class, 'import'])->name('guru.import');
    Route::get('guru/import-template', [GuruController::class, 'importTemplate'])->name('guru.import.template');
    Route::put('guru/{id}', [GuruController::class, 'update'])->name('guru.update');
    Route::delete('guru/{id}', [GuruController::class, 'destroy'])->name('guru.destroy');
    Route::post('guru/{id}/restore', [GuruController::class, 'restore'])->name('guru.restore');

    Route::get('buku-induk', [BukuIndukController::class, 'index'])->name('buku-induk.index');
    Route::get('buku-induk/{siswa}/pdf', [BukuIndukController::class, 'pdf'])->whereNumber('siswa')->name('buku-induk.pdf');
    Route::get('buku-induk/{siswa}', [BukuIndukController::class, 'edit'])->whereNumber('siswa')->name('buku-induk.edit');
    Route::post('buku-induk/{siswa}', [BukuIndukController::class, 'update'])->whereNumber('siswa')->name('buku-induk.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('alumni')->name('alumni.')->group(function () {
    Route::get('/', [AlumniController::class, 'index'])->name('index');
    Route::post('generate', [AlumniController::class, 'generate'])->name('generate');
    Route::put('{siswa}', [AlumniController::class, 'update'])->whereNumber('siswa')->name('update');
    Route::delete('{siswa}', [AlumniController::class, 'destroy'])->whereNumber('siswa')->name('destroy');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('wali')->name('wali.')->group(function () {
    Route::get('struktur', [WaliKelasController::class, 'struktur'])->name('struktur.index');
    Route::post('struktur', [WaliKelasController::class, 'saveStruktur'])->name('struktur.save');
    Route::get('catatan', [WaliKelasController::class, 'catatan'])->name('catatan.index');
    Route::post('catatan/mapel', [WaliKelasController::class, 'storeCatatanMapel'])->name('catatan.mapel.store');
    Route::delete('catatan/mapel/{catatan}', [WaliKelasController::class, 'destroyCatatanMapel'])->whereNumber('catatan')->name('catatan.mapel.destroy');
    Route::post('catatan/wali', [WaliKelasController::class, 'storeCatatanWali'])->name('catatan.wali.store');
    Route::delete('catatan/wali/{catatan}', [WaliKelasController::class, 'destroyCatatanWali'])->whereNumber('catatan')->name('catatan.wali.destroy');
    Route::get('siswa', [WaliKelasController::class, 'siswa'])->name('siswa.index');
    Route::post('siswa/{siswa}', [WaliKelasController::class, 'updateSiswa'])->whereNumber('siswa')->name('siswa.update');
    Route::post('siswa/{siswa}/foto', [WaliKelasController::class, 'uploadFoto'])->whereNumber('siswa')->name('siswa.foto.upload');
    Route::delete('siswa/{siswa}/foto', [WaliKelasController::class, 'deleteFoto'])->whereNumber('siswa')->name('siswa.foto.delete');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('cbt')->name('cbt.')->group(function () {
    Route::post('media', [MediaController::class, 'store'])->name('media.store');

    Route::get('bank-soal', [BankSoalController::class, 'index'])->name('bank-soal.index');
    Route::get('bank-soal/{id}', [SoalController::class, 'index'])->name('bank-soal.show');
    Route::get('bank-soal/{id}/ekspor', [SoalImportExportController::class, 'export'])->whereNumber('id')->name('bank-soal.export');
    Route::post('bank-soal/{id}/rakit', [SoalController::class, 'assemble'])->whereNumber('id')->name('bank-soal.rakit');
    Route::post('bank-soal/{id}/impor', [SoalImportExportController::class, 'import'])->whereNumber('id')->name('bank-soal.import');
    Route::post('bank-soal', [BankSoalController::class, 'store'])->name('bank-soal.store');
    Route::put('bank-soal/{id}', [BankSoalController::class, 'update'])->name('bank-soal.update');
    Route::delete('bank-soal/{id}', [BankSoalController::class, 'destroy'])->name('bank-soal.destroy');
    Route::post('bank-soal/hapus-terpilih', [BankSoalController::class, 'destroySelected'])->name('bank-soal.destroy-selected');
    Route::post('bank-soal/{id}/copy', [BankSoalController::class, 'copy'])->name('bank-soal.copy');
    Route::post('bank-soal/{id}/soal', [SoalController::class, 'store'])->name('soal.store');
    Route::post('bank-soal/{id}/soal/{soal}', [SoalController::class, 'update'])->name('soal.update');
    Route::delete('bank-soal/{id}/soal/{soal}', [SoalController::class, 'destroy'])->name('soal.destroy');
    Route::post('bank-soal/{id}/pilih', [SoalController::class, 'select'])->name('soal.select');

    Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
    Route::post('jadwal', [JadwalController::class, 'store'])->name('jadwal.store');
    Route::put('jadwal/{id}', [JadwalController::class, 'update'])->name('jadwal.update');
    Route::delete('jadwal/{id}', [JadwalController::class, 'destroy'])->name('jadwal.destroy');
    Route::post('jadwal/hapus-terpilih', [JadwalController::class, 'destroySelected'])->name('jadwal.destroy-selected');
    Route::post('jadwal/{id}/status', [JadwalController::class, 'toggleStatus'])->name('jadwal.status');
    Route::get('jadwal/{jadwal}/kartu', [CbtCetakController::class, 'kartu'])->whereNumber('jadwal')->name('jadwal.kartu');
    Route::get('jadwal/{jadwal}/kartu/pdf', [CbtCetakController::class, 'kartuPdf'])->whereNumber('jadwal')->name('jadwal.kartu.pdf');
    Route::get('kartu-global', [CbtCetakController::class, 'kartuGlobal'])->name('kartu-global');
    Route::get('kartu-global/pdf', [CbtCetakController::class, 'kartuGlobalPdf'])->name('kartu-global.pdf');
    Route::get('jadwal/{jadwal}/hadir', [CbtCetakController::class, 'hadir'])->whereNumber('jadwal')->name('jadwal.hadir');
    Route::get('jadwal/{jadwal}/berita-acara', [CbtCetakController::class, 'beritaAcara'])->whereNumber('jadwal')->name('jadwal.berita-acara');

    Route::get('token', [TokenController::class, 'index'])->name('token.index');
    Route::post('token', [TokenController::class, 'generate'])->name('token.generate');

    Route::get('remedial', [RemedialController::class, 'index'])->name('remedial.index');
    Route::get('remedial/export', [RemedialController::class, 'export'])->name('remedial.export');
    Route::post('remedial/{id}', [RemedialController::class, 'store'])->whereNumber('id')->name('remedial.store');
    Route::delete('remedial/{id}', [RemedialController::class, 'destroy'])->whereNumber('id')->name('remedial.destroy');

    Route::get('kehadiran', [KehadiranController::class, 'index'])->name('kehadiran.index');
    Route::post('kehadiran/{jadwal}', [KehadiranController::class, 'store'])->whereNumber('jadwal')->name('kehadiran.store');
    Route::delete('kehadiran/{jadwal}/{siswa}', [KehadiranController::class, 'destroy'])->whereNumber('jadwal')->whereNumber('siswa')->name('kehadiran.destroy');

    Route::get('status', [StatusController::class, 'index'])->name('status.index');
    Route::get('status/{jadwal}/stream', [StatusController::class, 'stream'])->whereNumber('jadwal')->name('status.stream');
    Route::post('status/{jadwal}/reset', [StatusController::class, 'reset'])->name('status.reset');
    Route::post('status/{jadwal}/force', [StatusController::class, 'force'])->name('status.force');
    Route::post('status/{jadwal}/repeat', [StatusController::class, 'repeat'])->name('status.repeat');

    Route::get('koreksi', [KoreksiController::class, 'index'])->name('koreksi.index');
    Route::post('koreksi/{jadwal}/tandai', [KoreksiController::class, 'markAll'])->whereNumber('jadwal')->name('koreksi.mark-all');
    Route::post('koreksi/{jadwal}/penilai', [KoreksiController::class, 'penilai'])->whereNumber('jadwal')->name('koreksi.penilai');
    Route::get('koreksi/{jadwal}/{siswa}', [KoreksiController::class, 'show'])->name('koreksi.show');
    Route::post('koreksi/{jadwal}/{siswa}', [KoreksiController::class, 'save'])->name('koreksi.save');

    Route::get('hasil', [HasilController::class, 'index'])->name('hasil.index');
    Route::get('hasil/export', [HasilController::class, 'export'])->name('hasil.export');
    Route::post('hasil/{jadwal}/notifikasi', [HasilController::class, 'ulangNotifikasi'])->whereNumber('jadwal')->name('hasil.notifikasi');

    Route::get('analisis', [AnalisisController::class, 'index'])->name('analisis.index');
    Route::get('analisis/export', [AnalisisController::class, 'export'])->name('analisis.export');

    Route::get('rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('rekap/export', [RekapController::class, 'export'])->name('rekap.export');
    Route::get('rekap/export-xlsx', [RekapController::class, 'exportExcel'])->name('rekap.export.xlsx');
});

Route::middleware(['auth', 'role:siswa'])->prefix('ujian')->name('ujian.')->group(function () {
    Route::get('/', [UjianController::class, 'index'])->name('index');
    Route::get('hasil', [UjianController::class, 'hasil'])->name('hasil');
    Route::get('{jadwal}', [UjianController::class, 'show'])->whereNumber('jadwal')->name('show');
    Route::post('{jadwal}/mulai', [UjianController::class, 'start'])->whereNumber('jadwal')->name('start');
    Route::get('{jadwal}/kerjakan', [UjianController::class, 'exam'])->whereNumber('jadwal')->name('exam');
    Route::post('{jadwal}/jawaban', [UjianController::class, 'saveAnswer'])->whereNumber('jadwal')->name('answer');
    Route::post('{jadwal}/checkpoint', [UjianController::class, 'checkpoint'])->whereNumber('jadwal')->name('checkpoint');
    Route::post('{jadwal}/pelanggaran', [UjianController::class, 'violation'])->whereNumber('jadwal')->name('violation');
    Route::post('{jadwal}/selesai', [UjianController::class, 'finish'])->whereNumber('jadwal')->name('finish');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('elearning')->name('elearning.')->group(function () {
    Route::get('materi', [ElearningMateriController::class, 'materi'])->name('materi.index');
    Route::get('tugas', [ElearningMateriController::class, 'tugas'])->name('tugas.index');
    Route::get('tugas/{id}', [ElearningMateriController::class, 'tugasDetail'])->whereNumber('id')->name('tugas.detail');
    Route::post('tugas/{id}/nilai', [ElearningMateriController::class, 'gradeTugas'])->whereNumber('id')->name('tugas.grade');
    Route::post('materi', [ElearningMateriController::class, 'storeMateri'])->name('materi.store');
    Route::post('tugas', [ElearningMateriController::class, 'storeTugas'])->name('tugas.store');
    Route::put('{id}', [ElearningMateriController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('{id}', [ElearningMateriController::class, 'destroy'])->whereNumber('id')->name('destroy');
    Route::post('{id}/status', [ElearningMateriController::class, 'toggleStatus'])->whereNumber('id')->name('status');

    Route::get('jadwal', [ElearningJadwalController::class, 'index'])->name('jadwal.index');
    Route::post('jadwal', [ElearningJadwalController::class, 'set'])->name('jadwal.set');

    Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
    Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'save'])->name('jadwal-pelajaran.save');

    Route::get('nilai-harian', [ElearningLaporanController::class, 'nilaiHarian'])->name('nilai-harian.index');
    Route::post('nilai-harian', [ElearningLaporanController::class, 'simpanNilaiHarian'])->name('nilai-harian.save');
    Route::get('kehadiran-harian', [ElearningLaporanController::class, 'kehadiranHarian'])->name('kehadiran-harian.index');
    Route::get('kehadiran-bulanan', [ElearningLaporanController::class, 'kehadiranBulanan'])->name('kehadiran-bulanan.index');
    Route::get('rekap-nilai', [ElearningLaporanController::class, 'rekapNilai'])->name('rekap-nilai.index');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('pengumuman')->name('pengumuman.')->group(function () {
    Route::get('/', [PengumumanController::class, 'index'])->name('index');
    Route::post('/', [PengumumanController::class, 'store'])->name('store');
    Route::delete('{post}', [PengumumanController::class, 'destroy'])->whereNumber('post')->name('destroy');
});

Route::middleware('auth')->group(function () {
    Route::post('pengumuman/{post}/komentar', [PengumumanController::class, 'comment'])->whereNumber('post')->name('pengumuman.comment');
    Route::delete('pengumuman/komentar/{comment}', [PengumumanController::class, 'destroyComment'])->whereNumber('comment')->name('pengumuman.comment.destroy');
    Route::post('pengumuman/komentar/{comment}/balasan', [PengumumanController::class, 'reply'])->whereNumber('comment')->name('pengumuman.reply');
    Route::delete('pengumuman/balasan/{reply}', [PengumumanController::class, 'destroyReply'])->whereNumber('reply')->name('pengumuman.reply.destroy');
});

Route::middleware(['auth', 'role:admin,guru'])->prefix('rapor')->name('rapor.')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::get('pengaturan', [RaporSettingController::class, 'index'])->name('setting.index');
        Route::post('pengaturan', [RaporSettingController::class, 'saveSetting'])->name('setting.save');
        Route::post('pengaturan/wali', [RaporSettingController::class, 'saveWali'])->name('setting.wali');
        Route::post('pengaturan/pengampu', [RaporSettingController::class, 'savePengampu'])->name('setting.pengampu');
    });
    Route::get('kkm', [RaporSettingController::class, 'kkm'])->name('kkm.index');
    Route::post('kkm', [RaporSettingController::class, 'saveKkm'])->name('kkm.save');

    Route::get('nilai', [RaporNilaiController::class, 'index'])->name('nilai.index');
    Route::get('tarik-cbt', [TarikCbtController::class, 'index'])->name('tarik.index');
    Route::post('tarik-cbt', [TarikCbtController::class, 'apply'])->name('tarik.apply');
    Route::get('nilai/harian/{mapel}/{kelas}', [RaporNilaiController::class, 'harian'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.harian');
    Route::post('nilai/harian/{mapel}/{kelas}', [RaporNilaiController::class, 'saveHarian'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.harian.save');
    Route::post('nilai/harian/{mapel}/{kelas}/import', [RaporNilaiController::class, 'importHarian'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.harian.import');
    Route::get('nilai/harian/{mapel}/{kelas}/template', [RaporNilaiController::class, 'templateHarian'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.harian.template');
    Route::get('nilai/pts/{mapel}/{kelas}', [RaporNilaiController::class, 'pts'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pts');
    Route::post('nilai/pts/{mapel}/{kelas}', [RaporNilaiController::class, 'savePts'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pts.save');
    Route::post('nilai/pts/{mapel}/{kelas}/import', [RaporNilaiController::class, 'importPts'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pts.import');
    Route::get('nilai/pts/{mapel}/{kelas}/template', [RaporNilaiController::class, 'templatePts'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pts.template');
    Route::get('nilai/pas/{mapel}/{kelas}', [RaporNilaiController::class, 'pas'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pas');
    Route::post('nilai/pas/{mapel}/{kelas}', [RaporNilaiController::class, 'savePas'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pas.save');
    Route::post('nilai/pas/{mapel}/{kelas}/import', [RaporNilaiController::class, 'importPas'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pas.import');
    Route::get('nilai/pas/{mapel}/{kelas}/template', [RaporNilaiController::class, 'templatePas'])->whereNumber('mapel')->whereNumber('kelas')->name('nilai.pas.template');

    Route::get('kelas', [RaporKelasController::class, 'index'])->name('kelas.index');
    Route::get('kelas/{kelas}', [RaporKelasController::class, 'data'])->whereNumber('kelas')->name('kelas.data');
    Route::post('kelas/{kelas}/sikap', [RaporKelasController::class, 'saveSikap'])->whereNumber('kelas')->name('kelas.sikap');
    Route::post('kelas/{kelas}/catatan', [RaporKelasController::class, 'saveCatatan'])->whereNumber('kelas')->name('kelas.catatan');
    Route::post('kelas/{kelas}/prestasi', [RaporKelasController::class, 'savePrestasi'])->whereNumber('kelas')->name('kelas.prestasi');
    Route::post('kelas/{kelas}/peringkat', [RaporKelasController::class, 'hitungPeringkat'])->whereNumber('kelas')->name('kelas.peringkat');
    Route::post('kelas/{kelas}/fisik', [RaporKelasController::class, 'saveFisik'])->whereNumber('kelas')->name('kelas.fisik');
    Route::post('kelas/{kelas}/ekstra', [RaporKelasController::class, 'saveEkstra'])->whereNumber('kelas')->name('kelas.ekstra');
    Route::post('kelas/{kelas}/import/{jenis}', [RaporKelasController::class, 'importComponent'])->whereNumber('kelas')->whereIn('jenis', ['sikap', 'catatan', 'prestasi', 'fisik', 'ekstra'])->name('kelas.import');
    Route::get('kelas/{kelas}/template/{jenis}', [RaporKelasController::class, 'templateComponent'])->whereNumber('kelas')->whereIn('jenis', ['sikap', 'catatan', 'prestasi', 'fisik', 'ekstra'])->name('kelas.template');
    Route::post('kelas/{kelas}/naik', [RaporKelasController::class, 'saveNaik'])->whereNumber('kelas')->name('kelas.naik');
    Route::post('kelas/{kelas}/kunci', [RaporKelasController::class, 'kunci'])->whereNumber('kelas')->name('kelas.kunci');
    Route::delete('kelas/{kelas}/kunci', [RaporKelasController::class, 'bukaKunci'])->whereNumber('kelas')->name('kelas.buka-kunci');

    Route::get('cetak', [RaporCetakController::class, 'index'])->name('cetak.index');
    Route::get('cetak/{kelas}', [RaporCetakController::class, 'siswa'])->whereNumber('kelas')->name('cetak.siswa');
    Route::get('cetak/{kelas}/{siswa}/pdf', [RaporCetakController::class, 'raporPdf'])->whereNumber('kelas')->whereNumber('siswa')->name('cetak.rapor.pdf');
    Route::get('cetak/{kelas}/{siswa}', [RaporCetakController::class, 'rapor'])->whereNumber('kelas')->whereNumber('siswa')->name('cetak.rapor');
    Route::get('leger/{kelas}/pdf', [RaporCetakController::class, 'legerPdf'])->whereNumber('kelas')->name('leger.pdf');
    Route::get('leger/{kelas}', [RaporCetakController::class, 'leger'])->whereNumber('kelas')->name('leger');

    Route::get('dkn', [RaporDknController::class, 'index'])->name('dkn.index');
    Route::get('dkn/{kelas}/pdf', [RaporDknController::class, 'pdf'])->whereNumber('kelas')->name('dkn.pdf');
    Route::get('dkn/{kelas}', [RaporDknController::class, 'show'])->whereNumber('kelas')->name('dkn.show');
});

Route::middleware(['auth', 'role:siswa'])->prefix('siswa/elearning')->name('siswa.elearning.')->group(function () {
    Route::get('materi', [ElearningController::class, 'materi'])->name('materi.index');
    Route::get('tugas', [ElearningController::class, 'tugas'])->name('tugas.index');
    Route::get('materi/{kjm}', [ElearningController::class, 'show'])->name('materi.show');
    Route::get('tugas/{kjm}', [ElearningController::class, 'show'])->name('tugas.show');
    Route::post('materi/{kjm}/selesai', [ElearningController::class, 'submit'])->name('materi.submit');
    Route::post('tugas/{kjm}/selesai', [ElearningController::class, 'submit'])->name('tugas.submit');
});

Route::middleware(['auth', 'role:orangtua'])->prefix('wali-murid')->name('wali-murid.')->group(function () {
    Route::get('/', [WaliPortalController::class, 'index'])->name('index');
    Route::get('{siswa}', [WaliPortalController::class, 'show'])->whereNumber('siswa')->name('show');
});

Route::middleware(['auth', 'role:siswa'])->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('pengumuman', [PengumumanController::class, 'siswaIndex'])->name('pengumuman.index');
    Route::get('notifikasi', [SiswaNotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('notifikasi/baca-semua', [SiswaNotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::post('notifikasi/{notifikasi}/baca', [SiswaNotifikasiController::class, 'baca'])
        ->where('notifikasi', '[0-9a-fA-F-]{36}')
        ->name('notifikasi.baca');
    Route::get('jadwal', [SiswaPortalController::class, 'jadwal'])->name('jadwal');
    Route::get('kehadiran', [SiswaPortalController::class, 'kehadiran'])->name('kehadiran');
    Route::get('catatan', [SiswaPortalController::class, 'catatan'])->name('catatan');
    Route::post('catatan/{table}/{catatan}', [SiswaPortalController::class, 'baca'])
        ->whereIn('table', ['mapel', 'wali'])
        ->whereNumber('catatan')
        ->name('catatan.baca');
});

Route::middleware(['auth', 'role:admin'])->prefix('cbt')->name('cbt.')->group(function () {
    Route::get('jenis', [JenisController::class, 'index'])->name('jenis.index');
    Route::post('jenis', [JenisController::class, 'store'])->name('jenis.store');
    Route::put('jenis/{id}', [JenisController::class, 'update'])->name('jenis.update');
    Route::delete('jenis/{id}', [JenisController::class, 'destroy'])->name('jenis.destroy');

    Route::get('sesi', [SesiController::class, 'index'])->name('sesi.index');
    Route::post('sesi', [SesiController::class, 'store'])->name('sesi.store');
    Route::put('sesi/{id}', [SesiController::class, 'update'])->name('sesi.update');
    Route::delete('sesi/{id}', [SesiController::class, 'destroy'])->name('sesi.destroy');

    Route::get('ruang', [RuangController::class, 'index'])->name('ruang.index');
    Route::post('ruang', [RuangController::class, 'store'])->name('ruang.store');
    Route::put('ruang/{id}', [RuangController::class, 'update'])->name('ruang.update');
    Route::delete('ruang/{id}', [RuangController::class, 'destroy'])->name('ruang.destroy');

    Route::get('kelas-ruang/siswa', [KelasRuangController::class, 'siswaIndex'])->name('kelas-ruang.siswa');
    Route::post('kelas-ruang/siswa', [KelasRuangController::class, 'saveSiswa'])->name('kelas-ruang.siswa.save');

    Route::get('kelas-ruang', [KelasRuangController::class, 'index'])->name('kelas-ruang.index');
    Route::post('kelas-ruang', [KelasRuangController::class, 'save'])->name('kelas-ruang.save');
    Route::delete('kelas-ruang/{id}', [KelasRuangController::class, 'release'])->name('kelas-ruang.release');

    Route::get('pengawas', [PengawasController::class, 'index'])->name('pengawas.index');
    Route::post('pengawas', [PengawasController::class, 'save'])->name('pengawas.save');

    Route::get('nomor-peserta', [NomorPesertaController::class, 'index'])->name('nomor-peserta.index');
    Route::post('nomor-peserta', [NomorPesertaController::class, 'save'])->name('nomor-peserta.save');
    Route::post('nomor-peserta/generate', [NomorPesertaController::class, 'generate'])->name('nomor-peserta.generate');
    Route::post('nomor-peserta/reset', [NomorPesertaController::class, 'reset'])->name('nomor-peserta.reset');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('setting/sekolah', [SettingController::class, 'edit'])->name('setting.sekolah.edit');
    Route::post('setting/sekolah', [SettingController::class, 'update'])->name('setting.sekolah.update');

    Route::get('setting/running-text', [RunningTextController::class, 'index'])->name('setting.running-text.index');
    Route::post('setting/running-text', [RunningTextController::class, 'store'])->name('setting.running-text.store');
    Route::put('setting/running-text/{text}', [RunningTextController::class, 'update'])->whereNumber('text')->name('setting.running-text.update');
    Route::delete('setting/running-text/{text}', [RunningTextController::class, 'destroy'])->whereNumber('text')->name('setting.running-text.destroy');

    Route::get('setting/api', [ApiSettingController::class, 'index'])->name('setting.api.index');
    Route::post('setting/api', [ApiSettingController::class, 'save'])->name('setting.api.save');
    Route::delete('setting/api/token/{token}', [ApiSettingController::class, 'destroyToken'])->whereNumber('token')->name('setting.api.token.destroy');

    Route::get('setting/audit', [AuditController::class, 'index'])->name('setting.audit.index');
    Route::get('setting/audit/export', [AuditController::class, 'export'])->name('setting.audit.export');

    Route::get('setting/verifikasi', [VerifikasiAdminController::class, 'index'])->name('setting.verifikasi.index');
    Route::post('setting/verifikasi/{kode}/cabut', [VerifikasiAdminController::class, 'cabut'])
        ->where('kode', '[A-Za-z0-9]+')
        ->name('setting.verifikasi.cabut');

    Route::get('setting/database', [DatabaseController::class, 'index'])->name('setting.database.index');
    Route::post('setting/database/backup', [DatabaseController::class, 'backup'])->name('setting.database.backup');
    Route::delete('setting/database/backup/{name}', [DatabaseController::class, 'destroyBackup'])->name('setting.database.backup.destroy');
    Route::post('setting/database/backup/{name}/restore', [DatabaseController::class, 'restoreBackup'])
        ->where('name', '[A-Za-z0-9._-]+')
        ->name('setting.database.backup.restore');
    Route::post('setting/database/truncate', [DatabaseController::class, 'truncate'])->name('setting.database.truncate');

    Route::get('setting/kop', [KopCetakController::class, 'index'])->name('setting.kop.index');
    Route::post('setting/kop', [KopCetakController::class, 'save'])->name('setting.kop.save');

    Route::get('rapor/backup', [RaporBackupController::class, 'export'])->name('rapor.backup.export');
    Route::post('rapor/backup', [RaporBackupController::class, 'restore'])->name('rapor.backup.restore');

    Route::get('users/{role}', [UserController::class, 'index'])->whereIn('role', ['admin', 'guru', 'siswa', 'orangtua'])->name('users.index');
    Route::post('users/{role}', [UserController::class, 'store'])->whereIn('role', ['admin', 'guru', 'siswa', 'orangtua'])->name('users.store');
    Route::post('users/{role}/status', [UserController::class, 'bulkStatus'])->whereIn('role', ['admin', 'guru', 'siswa', 'orangtua'])->name('users.status');
    Route::put('users/{role}/{user}', [UserController::class, 'update'])->whereIn('role', ['admin', 'guru', 'siswa', 'orangtua'])->name('users.update');
    Route::delete('users/{role}/{user}', [UserController::class, 'destroy'])->whereIn('role', ['admin', 'guru', 'siswa', 'orangtua'])->name('users.destroy');
});
