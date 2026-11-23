@php
    $alamat = collect([
        $sekolah['alamat'] ?? null,
        !empty($sekolah['desa']) ? 'Desa/Kel. ' . $sekolah['desa'] : null,
        !empty($sekolah['kecamatan']) ? 'Kec. ' . $sekolah['kecamatan'] : null,
        $sekolah['kota'] ?? null,
        $sekolah['provinsi'] ?? null,
    ])->filter()->implode(', ');

    $lahir = collect([$siswa['tempat_lahir'] ?? null, $siswa['tanggal_lahir'] ?? null])->filter()->implode(', ');
    $tandaTangan = collect([$sekolah['kota'] ?? null, $tanggal ?? null])->filter()->implode(', ');

    $keputusan = '';
    if ($naik !== null) {
        $keputusan = $kelas['akhir']
            ? ($naik === 1 ? 'LULUS' : 'TIDAK LULUS')
            : ($naik === 1 ? 'NAIK KE KELAS BERIKUTNYA' : 'TINGGAL DI KELAS');
    }

    $sikapLabel = ['A' => 'Sangat Baik', 'B' => 'Baik', 'C' => 'Cukup', 'D' => 'Perlu Bimbingan'];
    $sikapOf = function ($jenis) use ($sikap, $sikapLabel) {
        $row = $sikap[$jenis] ?? $sikap[(string) $jenis] ?? [];
        $predikat = $row['predikat'] ?? '';
        return [
            'predikat' => $sikapLabel[$predikat] ?? ($predikat ?: '-'),
            'deskripsi' => $row['deskripsi'] ?? '-',
        ];
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor {{ $siswa['nama'] }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #000; line-height: 1.35; }
        h1, h2, h3, p { margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border-bottom: 3px double #000; }
        .kop td { vertical-align: middle; padding: 0 4px 6px; }
        .kop-logo { width: 60px; text-align: center; }
        .kop-logo img { max-height: 56px; max-width: 60px; }
        .kop-text { text-align: center; }
        .kop-text h1 { font-size: 15px; text-transform: uppercase; letter-spacing: 0.4px; }
        .kop-text p { font-size: 9px; }
        .title { margin-top: 10px; text-align: center; }
        .title .main { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .title .sub { font-size: 10px; font-weight: bold; }
        table { border-collapse: collapse; }
        .identity { width: 100%; margin-top: 10px; font-size: 9.5px; }
        .identity td { padding: 1px 2px; vertical-align: top; }
        .identity .label { width: 17%; }
        .identity .colon { width: 2%; }
        .identity .value { width: 31%; font-weight: bold; }
        .identity .label2 { width: 16%; padding-left: 8px; }
        h2 { font-size: 10px; text-transform: uppercase; margin-top: 12px; }
        h3 { font-size: 9.5px; margin-top: 6px; }
        .grid { width: 100%; margin-top: 3px; }
        .grid th { border: 1px solid #000; padding: 3px 4px; background: #e9e9e9; text-align: center; font-size: 9px; }
        .grid td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #555; }
        .signature { width: 100%; margin-top: 22px; font-size: 9.5px; }
        .signature td { width: 50%; vertical-align: top; }
        .signature .line { margin-top: 42px; border-top: 1px solid #000; width: 65%; }
        .signature .right { text-align: right; }
        .signature .right .line { margin-left: auto; }
        .kepsek { margin-top: 18px; text-align: center; font-size: 9.5px; }
        .kepsek .line { margin: 40px auto 0; border-top: 1px solid #000; width: 45%; }
        .avoid { page-break-inside: avoid; }
        .verifikasi { width: 100%; margin-top: 14px; border-top: 1px dashed #777; padding-top: 6px; font-size: 8px; }
        .verifikasi td { vertical-align: middle; }
        .verifikasi .qr { width: 86px; }
        .verifikasi .qr svg { width: 80px; height: 80px; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if (!empty($logoKiriData))
                    <img src="{{ $logoKiriData }}" alt="Logo kiri">
                @endif
            </td>
            <td class="kop-text">
                <h1>{{ $sekolah['nama'] ?? 'Nama Sekolah' }}</h1>
                <p>{{ $alamat }}</p>
                <p>
                    @if (!empty($sekolah['npsn'])) NPSN: {{ $sekolah['npsn'] }} @endif
                    @if (!empty($sekolah['npsn']) && !empty($sekolah['nss'])) | @endif
                    @if (!empty($sekolah['nss'])) NSS: {{ $sekolah['nss'] }} @endif
                </p>
            </td>
            <td class="kop-logo">
                @if (!empty($logoKananData))
                    <img src="{{ $logoKananData }}" alt="Logo kanan">
                @endif
            </td>
        </tr>
    </table>

    <div class="title">
        <p class="main">{{ $judul ?? 'Laporan Hasil Belajar' }}</p>
        <p class="sub">{{ $sekolah['jenjang'] ?? '' }}</p>
    </div>

    <table class="identity">
        <tr>
            <td class="label">Nama</td>
            <td class="colon">:</td>
            <td class="value">{{ $siswa['nama'] }}</td>
            <td class="label2">Kelas</td>
            <td class="colon">:</td>
            <td class="value">{{ $kelas['nama_kelas'] }}</td>
        </tr>
        <tr>
            <td class="label">NIS / NISN</td>
            <td class="colon">:</td>
            <td class="value">{{ $siswa['nis'] ?: '-' }} / {{ $siswa['nisn'] ?: '-' }}</td>
            <td class="label2">Semester</td>
            <td class="colon">:</td>
            <td class="value">{{ $semester }}</td>
        </tr>
        <tr>
            <td class="label">Tempat, Tanggal Lahir</td>
            <td class="colon">:</td>
            <td>{{ $lahir ?: '-' }}</td>
            <td class="label2">Tahun Pelajaran</td>
            <td class="colon">:</td>
            <td class="value">{{ $tahun }}</td>
        </tr>
    </table>

    <h2>A. Sikap</h2>
    @foreach ([1 => '1. Sikap Spiritual', 2 => '2. Sikap Sosial'] as $jenisSikap => $label)
        @php($row = $sikapOf($jenisSikap))
        <h3>{{ $label }}</h3>
        <table class="grid">
            <tr>
                <th style="width: 22%">Predikat</th>
                <th>Deskripsi</th>
            </tr>
            <tr>
                <td class="center bold">{{ $row['predikat'] }}</td>
                <td>{{ $row['deskripsi'] }}</td>
            </tr>
        </table>
    @endforeach

    @if (($jenis ?? 'akhir') === 'pts')
        <div class="avoid">
            <h2>B. Nilai Penilaian Tengah Semester</h2>
            @if ($p_nilai)
                <p>Kriteria Ketuntasan Minimal (KKM): <span class="bold">{{ $p_nilai }}</span></p>
            @endif
            <table class="grid">
                <tr>
                    <th style="width: 5%">No</th>
                    <th style="width: 45%">Mata Pelajaran</th>
                    @if (!$p_nilai) <th style="width: 8%">KKM</th> @endif
                    <th style="width: 10%">Nilai</th>
                    <th style="width: 12%">Predikat</th>
                </tr>
                @forelse ($nilai as $index => $row)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $row['nama_mapel'] }}</td>
                        @if (!$p_nilai) <td class="center">{{ $row['kkm'] }}</td> @endif
                        <td class="center bold">{{ $row['nilai'] ?? '' }}</td>
                        <td class="center">{{ $row['predikat'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $p_nilai ? 4 : 5 }}" class="center muted">Belum ada nilai PTS.</td></tr>
                @endforelse
            </table>
        </div>
    @else
    <div class="avoid">
        <h2>B. Pengetahuan</h2>
        @if ($p_nilai)
            <p>Kriteria Ketuntasan Minimal (KKM): <span class="bold">{{ $p_nilai }}</span></p>
        @endif
        <table class="grid">
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 24%">Mata Pelajaran</th>
                @if (!$p_nilai) <th style="width: 7%">KKM</th> @endif
                <th style="width: 7%">Nilai</th>
                <th style="width: 9%">Predikat</th>
                <th>Deskripsi</th>
            </tr>
            @forelse ($nilai as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['nama_mapel'] }}</td>
                    @if (!$p_nilai) <td class="center">{{ $row['kkm'] }}</td> @endif
                    <td class="center bold">{{ $row['p_nilai'] ?? '' }}</td>
                    <td class="center">{{ $row['p_predikat'] }}</td>
                    <td>{{ $row['p_deskripsi'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $p_nilai ? 5 : 6 }}" class="center muted">Belum ada nilai pengetahuan.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="avoid">
        <h2>C. Keterampilan</h2>
        @if ($p_nilai)
            <p>Kriteria Ketuntasan Minimal (KKM): <span class="bold">{{ $p_nilai }}</span></p>
        @endif
        <table class="grid">
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 24%">Mata Pelajaran</th>
                @if (!$p_nilai) <th style="width: 7%">KKM</th> @endif
                <th style="width: 7%">Nilai</th>
                <th style="width: 9%">Predikat</th>
                <th>Deskripsi</th>
            </tr>
            @forelse ($nilai as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['nama_mapel'] }}</td>
                    @if (!$p_nilai) <td class="center">{{ $row['kkm'] }}</td> @endif
                    <td class="center bold">{{ $row['k_nilai'] ?? '' }}</td>
                    <td class="center">{{ $row['k_predikat'] }}</td>
                    <td>{{ $row['k_deskripsi'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $p_nilai ? 5 : 6 }}" class="center muted">Belum ada nilai keterampilan.</td></tr>
            @endforelse
        </table>
    </div>
    @endif

    <div class="avoid">
        <h2>{{ ($jenis ?? 'akhir') === 'pts' ? 'C' : 'D' }}. Ekstrakurikuler</h2>
        <table class="grid">
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 33%">Kegiatan</th>
                <th style="width: 15%">Predikat</th>
                <th>Keterangan</th>
            </tr>
            @forelse ($ekstra as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['nama_ekstra'] }}</td>
                    <td class="center">{{ $row['predikat'] }}</td>
                    <td>{{ $row['deskripsi'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted">Belum ada kegiatan ekstrakurikuler.</td></tr>
            @endforelse
        </table>

        <h3>Prestasi</h3>
        <table class="grid">
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 33%">Jenis Kegiatan</th>
                <th>Deskripsi</th>
            </tr>
            @forelse ($prestasi['items'] as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item['jenis'] }}</td>
                    <td>{{ $item['deskripsi'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="center muted">Belum ada prestasi.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="avoid">
        <h2>{{ ($jenis ?? 'akhir') === 'pts' ? 'D' : 'E' }}. Ketidakhadiran</h2>
        <table class="grid" style="width: 55%">
            <tr><td style="width: 70%">Sakit</td><td>{{ $absensi['s'] }} hari</td></tr>
            <tr><td>Izin</td><td>{{ $absensi['i'] }} hari</td></tr>
            <tr><td>Tanpa Keterangan</td><td>{{ $absensi['a'] }} hari</td></tr>
        </table>

        @if ($fisik && (int) ($fisik['tinggi'] ?? 0) + (int) ($fisik['berat'] ?? 0) + strlen((string) ($fisik['telinga'] ?? '')) + strlen((string) ($fisik['mata'] ?? '')) + strlen((string) ($fisik['gigi'] ?? '')) + strlen((string) ($fisik['lain'] ?? '')))
            <h3>Kondisi Fisik</h3>
            <table class="grid">
                <tr>
                    <td style="width: 25%">Tinggi Badan</td>
                    <td style="width: 25%">{{ $fisik['tinggi'] ? $fisik['tinggi'] . ' cm' : '-' }}</td>
                    <td style="width: 25%">Berat Badan</td>
                    <td>{{ $fisik['berat'] ? $fisik['berat'] . ' kg' : '-' }}</td>
                </tr>
                <tr>
                    <td>Pendengaran</td>
                    <td>{{ $fisik['telinga'] ?: '-' }}</td>
                    <td>Penglihatan</td>
                    <td>{{ $fisik['mata'] ?: '-' }}</td>
                </tr>
                <tr>
                    <td>Gigi</td>
                    <td>{{ $fisik['gigi'] ?: '-' }}</td>
                    <td>Lain-lain</td>
                    <td>{{ $fisik['lain'] ?: '-' }}</td>
                </tr>
            </table>
        @endif
    </div>

    <div class="avoid">
        <h2>{{ ($jenis ?? 'akhir') === 'pts' ? 'E' : 'F' }}. Catatan Wali Kelas</h2>
        <table class="grid">
            <tr>
                <td style="width: 18%">Peringkat ke</td>
                <td>{{ $prestasi['ranking'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Catatan</td>
                <td style="min-height: 30px">{{ $catatan ?: '-' }}</td>
            </tr>
            @if ($keputusan !== '')
                <tr>
                    <td>Keputusan</td>
                    <td>Berdasarkan pencapaian kompetensi, peserta didik ditetapkan: <span class="bold">{{ $keputusan }}</span>.</td>
                </tr>
            @endif
        </table>
    </div>

    <table class="signature">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p>Orang Tua / Wali</p>
                <div class="line"></div>
            </td>
            <td class="right">
                <p>{{ $tandaTangan }}</p>
                <p>Wali Kelas</p>
                <div class="line"></div>
                <p class="bold">{{ $kelas['wali'] ?: '' }}</p>
                <p>NIP. {{ $kelas['wali_nip'] ?: '-' }}</p>
            </td>
        </tr>
    </table>

    <div class="kepsek">
        <p>Mengetahui,</p>
        <p>Kepala Sekolah</p>
        <div class="line"></div>
        <p class="bold">{{ $sekolah['kepsek'] ?: '' }}</p>
        <p>NIP. {{ $sekolah['nip'] ?: '-' }}</p>
    </div>

    @if (!empty($verifikasi))
        <table class="verifikasi">
            <tr>
                <td class="qr">{!! $verifikasi['svg'] !!}</td>
                <td>
                    <p class="bold">Verifikasi dokumen</p>
                    <p>Kode: <span class="bold">{{ $verifikasi['kode'] }}</span></p>
                    <p>{{ $verifikasi['url'] }}</p>
                    <p class="muted">Pindai kode QR atau buka tautan untuk memeriksa keaslian dokumen ini.</p>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
