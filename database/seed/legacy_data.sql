INSERT INTO `bulan` (`id_bln`, `nama_bln`) VALUES
(1, 'Januari'),
(2, 'Februari'),
(3, 'Maret'),
(4, 'April'),
(5, 'Mei'),
(6, 'Juni'),
(7, 'Juli'),
(8, 'Agustus'),
(9, 'September'),
(10, 'Oktober'),
(11, 'November'),
(12, 'Desember');

INSERT INTO `cbt_jenis` (`id_jenis`, `nama_jenis`, `kode_jenis`) VALUES
(1, 'Penilaian Harian', 'PH'),
(2, 'Penilaian Tengah Semester', 'PTS'),
(3, 'Penilaian Akhir Semester', 'PAS'),
(4, 'Penilaian Akhir Tahun', 'PAT'),
(5, 'Ujian Madrasah Berbasis Komputer', 'UMBK'),
(6, 'Try Out', 'TO'),
(7, 'Simulasi', 'SIML');

INSERT INTO `cbt_ruang` (`id_ruang`, `nama_ruang`, `kode_ruang`) VALUES
(1, 'Ruang 1', 'LAB-KOM'),
(2, 'Ruang 2', 'R2'),
(3, 'Ruang 3', 'R3'),
(4, 'Ruang 4', 'R4'),
(5, 'Ruang 5', 'R5');

INSERT INTO `cbt_sesi` (`id_sesi`, `nama_sesi`, `kode_sesi`, `waktu_mulai`, `waktu_akhir`, `aktif`) VALUES
(1, 'Sesi 1', 'S1', '07:30:00', '09:30:00', 1),
(2, 'Sesi 2', 'S2', '09:00:00', '12:30:00', 1),
(3, 'Sesi 3', 'S3', '10:30:00', '14:00:00', 1);

INSERT INTO `cbt_token` (`token`, `auto`, `id_token`, `jarak`, `updated`) VALUES
('HILZCX', 0, 1, 0, '2022-03-25 08:05:15');

INSERT INTO `groups` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Administrator'),
(2, 'guru', 'Pembuat Soal dan ujian'),
(3, 'siswa', 'Peserta Ujian');

INSERT INTO `hari` (`id_hri`, `nama_hri`) VALUES
(1, 'Senin'),
(2, 'Selasa'),
(3, 'Rabu'),
(4, 'Kamis'),
(5, 'Jum\'at'),
(6, 'Sabtu'),
(7, 'Minggu');

INSERT INTO `level_guru` (`id_level`, `level`) VALUES
(1, 'Kepala Sekolah'),
(2, 'Wakil Kepala Sekolah'),
(3, 'Bimbingan Konseling'),
(4, 'Walikelas'),
(5, 'Guru');

INSERT INTO `level_kelas` (`id_level`, `level`) VALUES
(1, '1'),
(2, '2'),
(3, '3'),
(4, '4'),
(5, '5'),
(6, '6'),
(7, '7'),
(8, '8'),
(9, '9'),
(10, '10'),
(11, '11'),
(12, '12');

INSERT INTO `master_ekstra` (`id_ekstra`, `nama_ekstra`, `kode_ekstra`) VALUES
(1, 'Pramuka', 'PRAM'),
(2, 'Baca Tulis Al Quran', 'BTQ'),
(3, 'Tahfidz', 'TFZ');

INSERT INTO `master_jurusan` (`id_jurusan`, `nama_jurusan`, `kode_jurusan`, `mapel_peminatan`, `status`, `deletable`) VALUES
(0, 'NON JURUSAN', 'NON', NULL, 1, 0),
(1, 'IPA', 'IPA', NULL, 1, 0),
(2, 'IPS', 'IPS', NULL, 1, 0),
(3, 'BAHASA', 'BAHASA', NULL, 1, 0),
(4, 'KEAGAMAAN', 'AGAMA', NULL, 0, 1);

INSERT INTO `master_kelompok_mapel` (`id_kel_mapel`, `kode_kel_mapel`, `nama_kel_mapel`, `kategori`, `id_parent`) VALUES
(1, 'A', 'Kelompok A (Wajib)', 'WAJIB', 0),
(2, 'B', 'Kelompok B', 'WAJIB', 0),
(3, 'C', 'Kelompok C', 'PEMINATAN', 0),
(4, 'MULOK', 'Muatan Lokal', 'MULOK', 0),
(5, 'C1', 'Kelompok C1', 'PEMINATAN', 3),
(6, 'PAI', 'PAI', 'PAI (Kemenag)', 0);

INSERT INTO `master_mapel` (`id_mapel`, `nama_mapel`, `kode`, `kelompok`, `bobot_p`, `bobot_k`, `jenjang`, `urutan`, `status`, `deletable`, `urutan_tampil`) VALUES
(1, 'Al Quran-Hadis', 'QH', 'PAI', 0, 0, 1, 1, 1, 0, 1),
(2, 'Fiqih', 'FQH', 'PAI', 0, 0, 1, 1, 1, 0, 3),
(3, 'Akidah Akhlak', 'AA', 'PAI', 0, 0, 1, 1, 1, 0, 2),
(4, 'Sejarah Kebudayaan Islam', 'SKI', 'PAI', 0, 0, 1, 1, 1, 0, 4),
(5, 'Bahasa Arab', 'BAR', 'A', 0, 0, 1, 2, 1, 0, 3),
(6, 'Bahasa Indonesia', 'BIND', 'A', 0, 0, 1, 2, 1, 0, 2),
(7, 'Bahasa Inggris', 'BING', 'A', 0, 0, 1, 2, 1, 0, 7),
(8, 'Matematika', 'MTK', 'A', 0, 0, 1, 2, 1, 0, 4),
(9, 'Ilmu Pengetahuan Alam', 'IPA', 'A', 0, 0, 1, 2, 1, 0, 5),
(10, 'Ilmu Pengetahuan Sosial', 'IPS', 'A', 0, 0, 1, 2, 1, 0, 6),
(11, 'Pendidikan Pancasila dan Kewarganegaraan', 'PPKn', 'A', 0, 0, 1, 2, 1, 0, 1),
(12, 'Pendidikan Jasmani Olah Raga dan Kesehatan', 'PJOK', 'B', 0, 0, 1, 3, 1, 0, 2),
(13, 'Seni Budaya', 'SB', 'B', 0, 0, 2, 3, 1, 0, 1),
(14, 'Prakarya', 'PRA', 'B', 0, 0, 2, 3, 1, 0, 3),
(15, 'SBdP', 'SBDP', 'B', 0, 0, 0, 3, 0, 0, 1),
(16, 'Akhlak', 'AK', 'C', 0, 0, 3, 0, 0, 0, 19),
(17, 'Antropologi', 'ANT', 'C1', 0, 0, 3, 0, 1, 0, 4),
(18, 'Bahasa Arab (Peminatan)', 'BAR-P', 'C', 0, 0, 3, 0, 1, 0, 3),
(19, 'Bahasa dan Sastra Asing Lainnya', 'BSAL', 'C', 0, 0, 3, 0, 1, 0, 16),
(20, 'Bahasa dan Sastra Indonesia', 'BSIN', 'C', 0, 0, 3, 0, 1, 0, 15),
(21, 'Bahasa dan Sastra Inggris', 'BSING', 'C', 0, 0, 3, 0, 1, 0, 14),
(22, 'Bahasa Jepang', 'JPN', 'C', 0, 0, 3, 0, 1, 0, 18),
(23, 'Bahasa Jerman', 'JRM', 'C', 0, 0, 3, 0, 1, 0, 12),
(24, 'Biologi', 'BIO', 'C', 0, 0, 3, 0, 1, 0, 2),
(25, 'Ekonomi', 'EKN', 'C', 0, 0, 3, 0, 1, 0, 11),
(26, 'Fikih (Peminatan)', 'FQH-P', 'C', 0, 0, 3, 0, 1, 0, 4),
(27, 'Fikih - Ushul Fikih', 'UFQH', 'C', 0, 0, 3, 0, 1, 0, 5),
(28, 'Fisika', 'FIS', 'C1', 0, 0, 3, 0, 1, 0, 3),
(29, 'Geografi', 'GEO', 'C', 0, 0, 3, 0, 1, 0, 10),
(30, 'Hadis - Ilmu Hadis', 'HA', 'C', 0, 0, 3, 0, 1, 0, 6),
(31, 'Ilmu Kalam', 'IK', 'C', 0, 0, 3, 0, 1, 0, 7),
(32, 'Informatika', 'INF', 'C', 0, 0, 3, 0, 0, 0, 13),
(33, 'Keterampilan', 'KTR', 'C', 0, 0, 3, 0, 0, 0, 17),
(34, 'Kimia', 'KIM', 'C1', 0, 0, 3, 0, 1, 0, 2),
(35, 'Prakarya dan Kewirausahaan', 'PK', 'B', 0, 0, 3, 0, 0, 0, 3),
(36, 'Sejarah', 'SEJ', 'C', 0, 0, 3, 0, 1, 0, 8),
(37, 'Sejarah Indonesia', 'SJI', 'A', 0, 0, 3, 0, 1, 0, 5),
(38, 'Sosiologi', 'SOS', 'C', 0, 0, 3, 0, 1, 0, 9),
(39, 'Tafsir - Ilmu Tafsir', 'TT', 'C1', 0, 0, 3, 0, 1, 0, 1),
(40, 'Bahasa Sunda', 'BSUND', 'MULOK', 0, 0, 1, 0, 1, 1, 1),
(41, 'Pendidikan Agama dan Budi Pekerti', 'PABP', 'A', 0, 0, 1, 1, 1, 0, 1),
(42, 'Matematika (Peminatan)', 'MTK-P', 'C', 0, 0, 3, 0, 1, 1, 1);

INSERT INTO `master_smt` (`id_smt`, `smt`, `nama_smt`, `active`) VALUES
(1, 'Ganjil', 'I (satu)', 1),
(2, 'Genap', 'II (dua)', 0);

INSERT INTO `master_tp` (`id_tp`, `tahun`, `active`) VALUES
(1, '2020/2021', 0),
(2, '2021/2022', 0),
(3, '2022/2023', 1),
(4, '2023/2024', 0);
