# CBT Sekolah

Aplikasi ujian berbasis komputer (CBT) untuk sekolah, lengkap dengan manajemen data master, e-learning, dan rapor. Dibangun dengan Laravel, Inertia, dan Vue 3.

## Fitur

### Manajemen data
- Tahun pelajaran dan semester aktif, termasuk wizard kenaikan tahun ajaran.
- Jurusan, mata pelajaran, kelas beserta anggota kelas, guru, siswa, dan ekstrakurikuler.
- Impor massal CSV/XLSX dengan templat unduhan untuk mapel, guru, dan siswa.
- Arsip (soft delete) dengan pemulihan dan penjagaan referensi data yang masih dipakai.

### CBT
- Bank soal dengan editor teks kaya, rumus KaTeX, tempel gambar, impor/ekspor soal, dan deteksi soal serupa.
- Jadwal ujian, sesi, ruang, nomor peserta, dan token ujian.
- Ujian siswa dengan penyimpanan jawaban bertahap, batas waktu, dan pencatatan pelanggaran (pindah tab, keluar layar penuh).
- Koreksi esai, analisis butir soal, rekap nilai, serta ekspor CSV dan XLSX.
- Cetak kartu peserta, daftar hadir, dan berita acara per jadwal.
- Integrasi nilai CBT ke rapor PTS/PAS.

### E-learning
- Materi dan tugas per kelas, dengan tenggat waktu.
- Pengumpulan tugas siswa, penilaian, dan umpan balik guru.
- Rekap keterbacaan materi dan status tugas siswa.

### Rapor
- Pengaturan KKM, bobot nilai, wali kelas, dan guru pengampu.
- Input nilai harian, PTS, dan PAS, termasuk impor nilai PTS/PAS.
- Penarikan nilai dari jadwal CBT ke komponen rapor.
- Data sikap, catatan wali kelas, prestasi, kondisi fisik, ekstrakurikuler, dan kenaikan kelas.
- Kunci nilai per kelas dengan jejak audit, cetak rapor dan leger dalam PDF.

### Lainnya
- Dashboard analitik untuk admin dan guru: rata-rata nilai, tingkat ketuntasan, ujian berjalan, dan pengingat tindakan.
- Pencarian global untuk admin dan guru.
- Pemilih periode untuk meninjau data tahun ajaran lain.
- Paginasi dan pencarian pada tabel data besar.
- Halaman kesehatan sistem (`/health`) dan perintah backup harian.

## Peran pengguna

| Peran | Akses utama |
| --- | --- |
| Admin | Semua fitur, pengaturan sekolah, akun pengguna, konfigurasi CBT, rapor, dan cetak dokumen |
| Guru | Bank soal, jadwal, koreksi, analisis, e-learning, dan rapor untuk kelas yang diampu |
| Siswa | Ujian, hasil ujian, materi, dan tugas e-learning |

Akun dibuat oleh admin. Login memakai username atau email. Untuk siswa, password awal bawaan adalah NISN jika admin tidak mengisi password saat membuat akun.

## Teknologi

- PHP 8.3, Laravel 13
- MySQL 8 (produksi dan pengembangan), SQLite tidak dipakai untuk data aplikasi
- Inertia.js 3, Vue 3, Vite 8, Tailwind CSS 4
- dompdf, PhpSpreadsheet
- PHPUnit 12, Laravel Pint

## Kebutuhan sistem

- PHP 8.3 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, `gd`, `zip`, dan `dom`
- Composer 2
- Node.js 22 atau lebih baru dan npm
- MySQL 8
- `mysqldump` pada server jika ingin menjalankan backup

## Instalasi lokal

1. Siapkan database MySQL, misalnya `cbt_laravel`.
2. Salin konfigurasi dan sesuaikan koneksi database:

   ```bash
   cp .env.example .env
   ```

   Isi `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.

3. Jalankan pemasangan:

   ```bash
   composer setup
   ```

   Perintah ini memasang dependensi PHP dan Node, membuat `APP_KEY`, menjalankan migrasi, lalu membangun aset.

4. Isi data awal (opsional pada database baru):

   ```bash
   php artisan db:seed
   ```

   Seeder membuat akun `admin` dengan password `admin123`, pengaturan sekolah contoh, dan tahun pelajaran aktif. Ganti password tersebut segera setelah login pertama.

5. Jalankan server pengembangan:

   ```bash
   php artisan serve
   npm run dev
   ```

   Atau jalankan seluruh proses pengembangan dengan `composer dev`.

## Perintah penting

```bash
php artisan test              # menjalankan seluruh tes
vendor/bin/pint --test        # memeriksa gaya kode
vendor/bin/pint               # merapikan gaya kode
npm run build                 # membangun aset produksi
npm run lint                  # memeriksa gaya kode Vue/JS
npm run test:e2e              # smoke test browser (Playwright)
XDEBUG_MODE=coverage php artisan test --coverage --min=80   # coverage minimal 80%
php artisan migrate           # menjalankan migrasi baru
php artisan backup:run        # backup database dan berkas publik
php artisan schedule:work     # menjalankan penjadwal di lingkungan pengembangan
```

Tes memakai database terpisah `cbt_laravel_test` sesuai `phpunit.xml`. Pastikan database tersebut ada sebelum menjalankan tes.

## Backup dan pemantauan

`php artisan backup:run` membuat dump MySQL terkompresi dan arsip `storage/app/public` di `storage/app/backups/<stamp>`. Backup lama dihapus berdasarkan `BACKUP_RETENTION_DAYS` (bawaan 14 hari). Penjadwal menjalankannya setiap hari pada `BACKUP_SCHEDULE_AT` (bawaan `01:00`).

Pada server produksi, jalankan penjadwal melalui cron:

```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Jika `mysqldump` tidak berada di PATH, set `MYSQLDUMP_PATH` ke lokasi binernya.

Endpoint `/health` mengembalikan status database, izin tulis storage dan cache, serta nama backup terakhir. Gunakan endpoint ini untuk pemeriksaan uptime.

## Konfigurasi

Variabel lingkungan penting:

| Variabel | Keterangan |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Set `production` dan `false` saat deploy (dipaksa oleh image Docker) |
| `APP_FORCE_HTTPS`, `SESSION_SECURE_COOKIE`, `TRUSTED_PROXIES` | Aktifkan saat berjalan di balik reverse proxy TLS |
| `DB_*` | Koneksi MySQL |
| `DB_ROOT_PASSWORD` | Hanya untuk layanan MySQL pada Docker Compose |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Bawaan memakai database |
| `APP_LOCALE` | Bawaan `id` |
| `BACKUP_RETENTION_DAYS`, `BACKUP_SCHEDULE_AT`, `MYSQLDUMP_PATH` | Pengaturan backup |
| `BACKUP_OFFSITE_PATH` | Folder salinan backup kedua (mount jaringan/disk eksternal) |
| `HEALTH_TOKEN` | Token untuk membuka detail `/health`; kosong berarti terbuka |
| `LOG_CHANNEL`, `LOG_LEVEL` | Pengaturan log |

Zona waktu aplikasi tetap `Asia/Jakarta` pada `config/app.php`.

## Deploy dengan Docker

`Dockerfile` membangun aset dengan Node, memasang dependensi PHP tanpa paket pengembangan, lalu menjalankan Apache dengan document root `public/`.

1. Siapkan `.env` produksi dan set `APP_KEY`:

   ```bash
   php artisan key:generate --show
   ```

   Salin hasilnya ke `APP_KEY` di `.env`. Jangan memakai berkas `.env` pengembangan di server.

2. Sesuaikan `APP_URL`, kredensial database, dan set `APP_ENV=production`, `APP_DEBUG=false`.

3. Jalankan:

   ```bash
   docker compose up -d --build
   ```

   Layanan `app` menjalankan migrasi saat start, lalu Apache pada port `8080`. Layanan `scheduler` menjalankan `php artisan schedule:work` untuk backup harian. Layanan `db` memakai MySQL 8 dengan healthcheck.

4. Periksa status:

   ```bash
   curl http://localhost:8080/health
   ```

Catatan produksi:

- Gunakan pengguna MySQL khusus, bukan `root`, karena image MySQL menolak `MYSQL_USER=root`.
- Isi `DB_ROOT_PASSWORD` di `.env` untuk layanan `db`.
- Letakkan reverse proxy TLS di depan port `8080`.
- Middleware `SecurityHeaders` menambahkan `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, dan `Cross-Origin-Opener-Policy`; HSTS dipasang saat permintaan HTTPS, dan CSP hanya aktif pada lingkungan produksi.
- Log produksi memakai kanal `daily` dengan retensi 14 hari (`LOG_CHANNEL`, `LOG_DAILY_DAYS`).
- Simpan volume `storage/app` secara persisten agar unggahan soal dan backup tidak hilang saat kontainer dibuat ulang.
- Batasi akses langsung ke MySQL dari luar jaringan Docker.
- Jalankan `php artisan migrate --force` sebagai bagian dari rilis. Perintah ini sudah dijalankan otomatis oleh layanan `app` pada compose.
- Ganti password akun awal dan hapus data contoh sebelum sekolah memakai aplikasi. Compose menjalankan `php artisan app:secure-default-accounts --apply` setiap start, dan penjadwal mengulanginya setiap hari, sehingga akun dengan password bawaan wajib mengganti password pada login berikutnya.
- Layanan `queue` menjalankan `php artisan queue:work`; tanpa layanan ini notifikasi hasil ujian menumpuk di tabel `jobs`. Health menandai antrean `sync` atau backlog sebagai `degraded`.
- Isi `BACKUP_OFFSITE_PATH` agar setiap backup harian disalin ke storage kedua. Bila diisi tetapi salinannya tidak ada, `/health` ikut `degraded` dan `backup:run` gagal dengan pesan yang jelas.
- Isi `HEALTH_TOKEN` agar detail `/health` (nama backup, jumlah notifikasi gagal, status antrean) tidak terbuka untuk publik; pemantau uptime mengirim token lewat header `X-Health-Token`.
- Media soal di bawah `storage/app/public/soal` hanya disajikan lewat rute `/media/soal/...` yang membutuhkan login; Apache memblokir akses langsung ke `/storage/soal/`.
- Image menjalankan `php artisan optimize` (config, route, view cache) setiap start. Saat mengembangkan tanpa Docker, gunakan `php artisan optimize:clear` bila mengubah `.env`.
- `SESSION_SECURE_COOKIE` bawaan `true` di compose; set `false` hanya bila menguji lewat HTTP tanpa TLS, karena cookie sesi tidak akan terkirim.

## Integrasi berkelanjutan

`.github/workflows/ci.yml` menjalankan:

- `composer install`
- `npm ci` dan `npm run build`
- `vendor/bin/pint --test`
- `composer audit` dan `npm audit`
- `php artisan test` terhadap layanan MySQL 8 dengan gerbang coverage 80%

## Struktur direktori

```
app/                  Controller, model, dan utilitas
config/               Konfigurasi aplikasi
database/migrations/  Skema basis data
lang/id/              Pesan validasi dan autentikasi bahasa Indonesia
resources/js/         Halaman dan komponen Vue (Inertia)
resources/css/        Gaya Tailwind
routes/web.php        Rute aplikasi
tests/                Tes fitur dan unit
```
