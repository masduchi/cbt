@php
    $tinggal = [
        '1' => 'Orang tua',
        '2' => 'Saudara',
        '3' => 'Wali',
        '4' => 'Asrama/pesantren',
        '5' => 'Kost',
        '6' => 'Lainnya',
    ];
    $yatim = [0 => 'Ada orang tua', 1 => 'Yatim', 2 => 'Yatim piatu'];
    $val = fn ($value) => filled($value) ? $value : '-';
    $alamatSekolah = collect([
        $setting?->alamat,
        $setting?->desa ? 'Desa/Kel. '.$setting->desa : null,
        $setting?->kecamatan ? 'Kec. '.$setting->kecamatan : null,
        $setting?->kota,
        $setting?->provinsi,
    ])->filter()->implode(', ');
    $tandaTangan = collect([$setting?->kota, $tanggal])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Induk {{ $siswa->nama }}</title>
    <style>
        @page { margin: 13mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #111; line-height: 1.35; }
        h1, h2, p { margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border-bottom: 3px double #111; }
        .kop td { vertical-align: middle; padding: 0 4px 6px; }
        .kop-logo { width: 60px; text-align: center; }
        .kop-logo img { max-height: 56px; max-width: 60px; }
        .kop-text { text-align: center; }
        .kop-text h1 { font-size: 15px; text-transform: uppercase; letter-spacing: 0.4px; }
        .kop-text p { font-size: 9px; }
        .title { margin-top: 10px; text-align: center; }
        .title .main { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .title .sub { font-size: 10px; }
        h2 { margin-top: 12px; padding: 3px 5px; background: #e9e9e9; border: 1px solid #111; font-size: 10px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .data td { padding: 2px 4px; vertical-align: top; border-bottom: 1px solid #ddd; }
        .data .label { width: 24%; color: #444; }
        .data .colon { width: 2%; }
        .data .value { font-weight: bold; }
        .muted { color: #555; font-weight: normal; }
        .signature { width: 100%; margin-top: 24px; font-size: 9.5px; }
        .signature td { width: 50%; vertical-align: top; text-align: center; }
        .signature .line { margin: 44px auto 0; border-top: 1px solid #111; width: 66%; }
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
                <h1>{{ $setting?->sekolah ?? 'Nama Sekolah' }}</h1>
                <p>{{ $alamatSekolah }}</p>
                <p>
                    @if ($setting?->npsn) NPSN: {{ $setting->npsn }} @endif
                    @if ($setting?->npsn && $setting?->nss) | @endif
                    @if ($setting?->nss) NSS: {{ $setting->nss }} @endif
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
        <p class="main">Buku Induk Siswa</p>
        <p class="sub">Tahun Pelajaran {{ now()->year }}/{{ now()->year + 1 }}</p>
    </div>

    <div class="avoid">
        <h2>A. Keterangan Siswa</h2>
        <table class="data">
            <tr><td class="label">Nama lengkap</td><td class="colon">:</td><td class="value">{{ $val($siswa->nama) }}</td>
                <td class="label">Kelas</td><td class="colon">:</td><td class="value">{{ $val($kelas) }}</td></tr>
            <tr><td class="label">NIS / NISN</td><td class="colon">:</td><td class="value">{{ $val($siswa->nis) }} / {{ $val($siswa->nisn) }}</td>
                <td class="label">Jenis kelamin</td><td class="colon">:</td><td class="value">{{ $val($siswa->jenis_kelamin) }}</td></tr>
            <tr><td class="label">Tempat, tanggal lahir</td><td class="colon">:</td><td class="value">{{ $val($siswa->tempat_lahir) }}, {{ $val($siswa->tanggal_lahir) }}</td>
                <td class="label">Agama</td><td class="colon">:</td><td class="value">{{ $val($siswa->agama) }}</td></tr>
            <tr><td class="label">Alamat</td><td class="colon">:</td><td class="value" colspan="3">{{ $val($siswa->alamat) }}</td></tr>
            <tr><td class="label">Nama panggilan</td><td class="colon">:</td><td class="value">{{ $val($buku->nama_panggilan) }}</td>
                <td class="label">Bahasa sehari-hari</td><td class="colon">:</td><td class="value">{{ $val($buku->bahasa) }}</td></tr>
            <tr><td class="label">Rombel awal</td><td class="colon">:</td><td class="value">{{ $val($buku->rombel_awal) }}</td>
                <td class="label">Status</td><td class="colon">:</td><td class="value">{{ $statusLabel }}</td></tr>
            <tr><td class="label">Saudara kandung / tiri / angkat</td><td class="colon">:</td><td class="value">{{ (int) $buku->jml_saudara_kandung }} / {{ (int) $buku->jml_saudara_tiri }} / {{ (int) $buku->jml_saudara_angkat }}</td>
                <td class="label">Status orang tua</td><td class="colon">:</td><td class="value">{{ $yatim[(int) $buku->yatim] ?? '-' }}</td></tr>
            <tr><td class="label">Tinggal bersama</td><td class="colon">:</td><td class="value">{{ $tinggal[(string) $buku->tinggal_bersama] ?? '-' }}</td>
                <td class="label">Jarak ke sekolah</td><td class="colon">:</td><td class="value">{{ $val($buku->jarak) }}</td></tr>
            <tr><td class="label">Golongan darah</td><td class="colon">:</td><td class="value">{{ $val($buku->gol_darah) }}</td>
                <td class="label">Kelainan fisik</td><td class="colon">:</td><td class="value">{{ $val($buku->kelainan_fisik) }}</td></tr>
            <tr><td class="label">Riwayat penyakit</td><td class="colon">:</td><td class="value" colspan="3">{{ $val($buku->penyakit) }}</td></tr>
            <tr><td class="label">Kegemaran</td><td class="colon">:</td><td class="value" colspan="3">{{ $val($buku->kegemaran) }}</td></tr>
            <tr><td class="label">Beasiswa</td><td class="colon">:</td><td class="value" colspan="3">{{ $val($buku->beasiswa) }}</td></tr>
            <tr><td class="label">Ijazah sebelumnya</td><td class="colon">:</td><td class="value">{{ $val($buku->no_ijazah_sebelumnya) }}</td>
                <td class="label">Tahun lulus</td><td class="colon">:</td><td class="value">{{ $val($buku->tahun_lulus_sebelumnya) }}</td></tr>
            <tr><td class="label">Pindahan dari</td><td class="colon">:</td><td class="value">{{ $val($buku->pindahan_dari) }}</td>
                <td class="label">Alasan kepindahan</td><td class="colon">:</td><td class="value">{{ $val($buku->alasan_kepindahan) }}</td></tr>
        </table>
    </div>

    <div class="avoid">
        <h2>B. Data Orang Tua dan Wali</h2>
        <table class="data">
            <tr><td class="label">Ayah</td><td class="colon">:</td><td class="value">{{ $val($siswa->nama_ayah) }} <span class="muted">({{ $val($siswa->pekerjaan_ayah) }})</span></td>
                <td class="label">Agama / kewarganegaraan</td><td class="colon">:</td><td class="value">{{ $val($buku->agama_ayah) }} / {{ $val($buku->wn_ayah) }}</td></tr>
            <tr><td class="label">Tempat lahir ayah</td><td class="colon">:</td><td class="value">{{ $val($buku->tempat_lahir_ayah) }}</td>
                <td class="label">Penghasilan / status</td><td class="colon">:</td><td class="value">{{ $val($buku->penghasilan_ayah) }} / {{ $val($buku->hidup_meninggal_ayah) }}</td></tr>
            <tr><td class="label">Ibu</td><td class="colon">:</td><td class="value">{{ $val($siswa->nama_ibu) }} <span class="muted">({{ $val($siswa->pekerjaan_ibu) }})</span></td>
                <td class="label">Agama / kewarganegaraan</td><td class="colon">:</td><td class="value">{{ $val($buku->agama_ibu) }} / {{ $val($buku->wn_ibu) }}</td></tr>
            <tr><td class="label">Tempat lahir ibu</td><td class="colon">:</td><td class="value">{{ $val($buku->tempat_lahir_ibu) }}</td>
                <td class="label">Penghasilan / status</td><td class="colon">:</td><td class="value">{{ $val($buku->penghasilan_ibu) }} / {{ $val($buku->hidup_meninggal_ibu) }}</td></tr>
            <tr><td class="label">Wali</td><td class="colon">:</td><td class="value">{{ $val($siswa->nama_wali) }} <span class="muted">({{ $val($siswa->pekerjaan_wali) }})</span></td>
                <td class="label">Agama / kewarganegaraan</td><td class="colon">:</td><td class="value">{{ $val($buku->agama_wali) }} / {{ $val($buku->wn_wali) }}</td></tr>
            <tr><td class="label">Tempat lahir wali</td><td class="colon">:</td><td class="value">{{ $val($buku->tempat_lahir_wali) }}</td>
                <td class="label">Penghasilan</td><td class="colon">:</td><td class="value">{{ $val($buku->penghasilan_wali) }}</td></tr>
        </table>
    </div>

    <div class="avoid">
        <h2>C. Kelulusan dan Mutasi</h2>
        <table class="data">
            <tr><td class="label">Kelas akhir</td><td class="colon">:</td><td class="value">{{ $val($buku->kelas_akhir) }}</td>
                <td class="label">Tahun lulus</td><td class="colon">:</td><td class="value">{{ $val($buku->tahun_lulus) }}</td></tr>
            <tr><td class="label">Nomor ijazah</td><td class="colon">:</td><td class="value">{{ $val($buku->no_ijazah) }}</td>
                <td class="label">Melanjutkan ke</td><td class="colon">:</td><td class="value">{{ $val($buku->lanjut_ke) }}</td></tr>
            <tr><td class="label">Pindah ke</td><td class="colon">:</td><td class="value">{{ $val($buku->pindah_ke) }}</td>
                <td class="label">Tanggal pindah</td><td class="colon">:</td><td class="value">{{ $val($buku->tgl_pindah) }}</td></tr>
            <tr><td class="label">Alasan pindah</td><td class="colon">:</td><td class="value">{{ $val($buku->alasan_pindah) }}</td>
                <td class="label">Bekerja di</td><td class="colon">:</td><td class="value">{{ $val($buku->bekerja_di) }}</td></tr>
            <tr><td class="label">Catatan penting</td><td class="colon">:</td><td class="value" colspan="3">{{ $val($buku->catatan_penting) }}</td></tr>
        </table>
    </div>

    <table class="signature">
        <tr>
            <td>
                Mengetahui,<br>Kepala Sekolah
                <div class="line"></div>
                <strong>{{ $val($setting?->kepsek) }}</strong><br>
                <span class="muted">NIP. {{ $val($setting?->nip) }}</span>
            </td>
            <td>
                {{ $tandaTangan }}<br>Wali Kelas
                <div class="line"></div>
                <strong>&nbsp;</strong><br>
                <span class="muted">&nbsp;</span>
            </td>
        </tr>
    </table>

    @if (!empty($verifikasi))
        <table class="verifikasi">
            <tr>
                <td class="qr">{!! $verifikasi['svg'] !!}</td>
                <td>
                    <p><strong>Verifikasi dokumen</strong></p>
                    <p>Kode: <strong>{{ $verifikasi['kode'] }}</strong></p>
                    <p>{{ $verifikasi['url'] }}</p>
                    <p class="muted">Pindai kode QR atau buka tautan untuk memeriksa keaslian dokumen ini.</p>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
