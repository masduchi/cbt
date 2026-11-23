export function buildMenu(role) {
    if (role === 'guru') {
        return [
            { label: 'Beranda', icon: 'th-large', href: '/dashboard' },
            { label: 'Pengumuman', icon: 'bullhorn', href: '/pengumuman' },
            {
                label: 'E-learning',
                icon: 'book-open',
                items: [
                    { label: 'Jadwal Pelajaran', icon: 'calendar-alt', href: '/elearning/jadwal-pelajaran' },
                    { label: 'Materi Belajar', icon: 'edit', href: '/elearning/materi' },
                    { label: 'Tugas Kelas', icon: 'clipboard-list', href: '/elearning/tugas' },
                    { label: 'Jadwal Materi', icon: 'calendar-alt', href: '/elearning/jadwal' },
                ],
            },
            {
                label: 'Hasil E-learning',
                icon: 'clipboard-check',
                items: [
                    { label: 'Nilai Harian', icon: 'clipboard-check', href: '/elearning/nilai-harian' },
                    { label: 'Kehadiran Harian', icon: 'user-check', href: '/elearning/kehadiran-harian' },
                    { label: 'Kehadiran Bulanan', icon: 'calendar-alt', href: '/elearning/kehadiran-bulanan' },
                    { label: 'Rekap Nilai', icon: 'file-excel', href: '/elearning/rekap-nilai' },
                ],
            },
            {
                label: 'Ujian',
                icon: 'clipboard-check',
                items: [
                    { label: 'Bank Soal', icon: 'book', href: '/cbt/bank-soal' },
                    { label: 'Jadwal Ujian', icon: 'clock', href: '/cbt/jadwal' },
                    { label: 'Koreksi Esai', icon: 'pen', href: '/cbt/koreksi' },
                    { label: 'Status Siswa', icon: 'users', href: '/cbt/status' },
                    { label: 'Hasil Ujian', icon: 'chart-bar', href: '/cbt/hasil' },
                    { label: 'Remedial', icon: 'redo', href: '/cbt/remedial' },
                    { label: 'Analisis Soal', icon: 'file-excel', href: '/cbt/analisis' },
                ],
            },
            {
                label: 'Rapor',
                icon: 'file-excel',
                items: [
                    { label: 'KKM & Bobot', icon: 'clipboard-check', href: '/rapor/kkm' },
                    { label: 'Input Nilai', icon: 'pen', href: '/rapor/nilai' },
                    { label: 'Tarik Nilai CBT', icon: 'download', href: '/rapor/tarik-cbt' },
                    { label: 'Data Kelas', icon: 'users', href: '/rapor/kelas' },
                    { label: 'Cetak Rapor', icon: 'file-excel', href: '/rapor/cetak' },
                    { label: 'DKN', icon: 'chart-bar', href: '/rapor/dkn' },
                ],
            },
            {
                label: 'Wali Kelas',
                icon: 'sitemap',
                items: [
                    { label: 'Struktur Kelas', icon: 'sitemap', href: '/wali/struktur' },
                    { label: 'Data Siswa', icon: 'users', href: '/wali/siswa' },
                    { label: 'Catatan Kelas', icon: 'comment-dots', href: '/wali/catatan' },
                ],
            },
            {
                label: 'Data',
                icon: 'users',
                items: [
                    { label: 'Siswa', icon: 'users', href: '/master/siswa' },
                    { label: 'Kelas / Rombel', icon: 'school', href: '/master/kelas' },
                    { label: 'Mata Pelajaran', icon: 'book-open', href: '/master/mapel' },
                ],
            },
        ];
    }

    if (role === 'orangtua') {
        return [
            { label: 'Anak Saya', icon: 'users', href: '/wali-murid' },
        ];
    }

    if (role === 'siswa') {
        return [
            { label: 'Beranda', icon: 'th-large', href: '/dashboard' },
            { label: 'Notifikasi', icon: 'bell', href: '/siswa/notifikasi' },
            { label: 'Pengumuman', icon: 'bullhorn', href: '/siswa/pengumuman' },
            { label: 'Jadwal Pelajaran', icon: 'calendar-alt', href: '/siswa/jadwal' },
            { label: 'Materi Belajar', icon: 'edit', href: '/siswa/elearning/materi' },
            { label: 'Tugas', icon: 'clipboard-list', href: '/siswa/elearning/tugas' },
            { label: 'Kehadiran', icon: 'user-check', href: '/siswa/kehadiran' },
            { label: 'Catatan Guru', icon: 'comment-dots', href: '/siswa/catatan' },
            { label: 'Ujian Saya', icon: 'tv', href: '/ujian' },
            { label: 'Hasil Ujian', icon: 'chart-bar', href: '/ujian/hasil' },
        ];
    }

    return [
        { label: 'Beranda', icon: 'th-large', href: '/dashboard' },
        { label: 'Pengumuman', icon: 'bullhorn', href: '/pengumuman' },
        {
            label: 'Data Master',
            icon: 'users',
            items: [
                { label: 'Tahun Pelajaran', icon: 'calendar-alt', href: '/master/tahun' },
                { label: 'Jurusan', icon: 'project-diagram', href: '/master/jurusan' },
                { label: 'Mata Pelajaran', icon: 'book-open', href: '/master/mapel' },
                { label: 'Kelompok Mapel', icon: 'folder-tree', href: '/master/kelompok-mapel' },
                { label: 'Ekstrakurikuler', icon: 'graduation-cap', href: '/master/ekstra' },
                { label: 'Siswa', icon: 'users', href: '/master/siswa' },
                { label: 'Kelas / Rombel', icon: 'school', href: '/master/kelas' },
                { label: 'Guru', icon: 'user-cog', href: '/master/guru' },
                { label: 'Buku Induk', icon: 'address-book', href: '/master/buku-induk' },
                { label: 'Alumni', icon: 'user-slash', href: '/alumni' },
            ],
        },
        {
            label: 'Data Ujian',
            icon: 'clipboard-check',
            items: [
                { label: 'Jenis Ujian', icon: 'clipboard-check', href: '/cbt/jenis' },
                { label: 'Sesi', icon: 'clock', href: '/cbt/sesi' },
                { label: 'Ruang', icon: 'door-open', href: '/cbt/ruang' },
                { label: 'Atur Ruang & Sesi', icon: 'th', href: '/cbt/kelas-ruang' },
                { label: 'Ruang & Sesi Siswa', icon: 'users', href: '/cbt/kelas-ruang/siswa' },
                { label: 'Nomor Peserta', icon: 'hashtag', href: '/cbt/nomor-peserta' },
                { label: 'Bank Soal', icon: 'book', href: '/cbt/bank-soal' },
                { label: 'Jadwal Ujian', icon: 'clock', href: '/cbt/jadwal' },
                { label: 'Kartu Ujian Global', icon: 'id-card', href: '/cbt/kartu-global' },
                { label: 'Pengawas', icon: 'user-check', href: '/cbt/pengawas' },
                { label: 'Token', icon: 'key', href: '/cbt/token' },
            ],
        },
        {
            label: 'Wali Kelas',
            icon: 'sitemap',
            items: [
                { label: 'Struktur Kelas', icon: 'sitemap', href: '/wali/struktur' },
                { label: 'Data Siswa', icon: 'users', href: '/wali/siswa' },
                { label: 'Catatan Kelas', icon: 'comment-dots', href: '/wali/catatan' },
            ],
        },
        {
            label: 'Pelaksanaan',
            icon: 'tv',
            items: [
                { label: 'Status Siswa', icon: 'users', href: '/cbt/status' },
                { label: 'Koreksi Esai', icon: 'pen', href: '/cbt/koreksi' },
                { label: 'Hasil Ujian', icon: 'chart-bar', href: '/cbt/hasil' },
                { label: 'Remedial', icon: 'redo', href: '/cbt/remedial' },
                { label: 'Analisis Soal', icon: 'file-excel', href: '/cbt/analisis' },
                { label: 'Rekap Nilai', icon: 'file-excel', href: '/cbt/rekap' },
            ],
        },
        {
            label: 'E-learning',
            icon: 'book-open',
            items: [
                { label: 'Jadwal Pelajaran', icon: 'calendar-alt', href: '/elearning/jadwal-pelajaran' },
                { label: 'Materi Belajar', icon: 'edit', href: '/elearning/materi' },
                { label: 'Tugas Kelas', icon: 'clipboard-list', href: '/elearning/tugas' },
                { label: 'Jadwal Materi', icon: 'calendar-alt', href: '/elearning/jadwal' },
            ],
        },
        {
            label: 'Hasil E-learning',
            icon: 'clipboard-check',
            items: [
                { label: 'Nilai Harian', icon: 'clipboard-check', href: '/elearning/nilai-harian' },
                { label: 'Kehadiran Harian', icon: 'user-check', href: '/elearning/kehadiran-harian' },
                { label: 'Kehadiran Bulanan', icon: 'calendar-alt', href: '/elearning/kehadiran-bulanan' },
                { label: 'Rekap Nilai', icon: 'file-excel', href: '/elearning/rekap-nilai' },
            ],
        },
        {
            label: 'Rapor',
            icon: 'file-excel',
            items: [
                { label: 'Pengaturan Rapor', icon: 'cog', href: '/rapor/pengaturan' },
                { label: 'KKM & Bobot', icon: 'clipboard-check', href: '/rapor/kkm' },
                { label: 'Input Nilai', icon: 'pen', href: '/rapor/nilai' },
                { label: 'Tarik Nilai CBT', icon: 'download', href: '/rapor/tarik-cbt' },
                { label: 'Data Kelas', icon: 'users', href: '/rapor/kelas' },
                { label: 'Cetak Rapor', icon: 'file-excel', href: '/rapor/cetak' },
                { label: 'DKN', icon: 'chart-bar', href: '/rapor/dkn' },
            ],
        },
        {
            label: 'Pengaturan',
            icon: 'cog',
            items: [
                { label: 'Profil Sekolah', icon: 'school', href: '/setting/sekolah' },
                { label: 'Kop Cetak Ujian', icon: 'print', href: '/setting/kop' },
                { label: 'Running Text', icon: 'bullhorn', href: '/setting/running-text' },
                { label: 'API & Sinkronisasi', icon: 'key', href: '/setting/api' },
                { label: 'Database & Backup', icon: 'database', href: '/setting/database' },
                { label: 'Jejak Audit', icon: 'history', href: '/setting/audit' },
                { label: 'Verifikasi Dokumen', icon: 'qrcode', href: '/setting/verifikasi' },
                { label: 'Akun', icon: 'user-cog', href: '/users/admin', match: '/users' },
            ],
        },
    ];
}