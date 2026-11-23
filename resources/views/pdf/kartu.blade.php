@php
    $kopLines = collect([
        $kop['header_1'] ?? null,
        $kop['header_2'] ?? null,
        $kop['header_3'] ?? null,
        $kop['header_4'] ?? null,
    ])->filter()->values();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Peserta {{ $ujian['mapel'] ?? '' }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; color: #000; line-height: 1.3; }
        p { margin: 0; }
        table { border-collapse: collapse; }
        .sheet { width: 100%; }
        .sheet td { width: 50%; padding: 3px; vertical-align: top; }
        .card { border: 1.2px solid #000; padding: 6px; page-break-inside: avoid; }
        .head { width: 100%; border-bottom: 1.5px solid #000; padding-bottom: 3px; }
        .head td { vertical-align: middle; padding: 0 2px 3px; }
        .logo { width: 42px; text-align: center; }
        .logo img { max-height: 38px; max-width: 42px; }
        .koptext { text-align: center; }
        .kop-main { font-size: 9.5px; font-weight: bold; text-transform: uppercase; }
        .kop-sub { font-size: 7.5px; }
        .title { margin-top: 4px; text-align: center; font-size: 9.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
        .subtitle { text-align: center; font-size: 7.5px; }
        .identity { width: 100%; margin-top: 5px; font-size: 8px; }
        .identity td { padding: 1px 1px; vertical-align: top; }
        .identity .label { width: 20%; color: #333; }
        .identity .value { font-weight: bold; }
        .identity .narrow { width: 11%; color: #333; }
        .mapel { width: 100%; margin-top: 4px; font-size: 8px; }
        .mapel td { padding: 1px 0; }
        .mapel .label { width: 20%; color: #333; }
        .foot { width: 100%; margin-top: 5px; border-top: 1px dashed #666; padding-top: 4px; }
        .foot td { vertical-align: bottom; }
        .qr { width: 48px; }
        .qr svg { width: 46px; height: 46px; }
        .verify { font-size: 6px; color: #444; line-height: 1.25; }
        .verify .kode { font-size: 7.5px; font-weight: bold; color: #000; }
        .sign { width: 90px; text-align: center; font-size: 7px; }
        .sign .line { margin-top: 22px; border-top: 1px solid #000; }
    </style>
</head>
<body>
    <table class="sheet">
        @foreach (array_chunk($peserta, 2) as $baris)
            <tr>
                @foreach ($baris as $row)
                    <td>
                        <div class="card">
                            <table class="head">
                                <tr>
                                    <td class="logo">
                                        @if (!empty($logoKiriData))
                                            <img src="{{ $logoKiriData }}" alt="Logo kiri">
                                        @endif
                                    </td>
                                    <td class="koptext">
                                        @forelse ($kopLines as $index => $line)
                                            <p class="{{ $index === 0 ? 'kop-main' : 'kop-sub' }}">{{ $line }}</p>
                                        @empty
                                            <p class="kop-main">{{ $sekolah ?: 'Nama Sekolah' }}</p>
                                            <p class="kop-sub">{{ $alamat }}</p>
                                        @endforelse
                                    </td>
                                    <td class="logo">
                                        @if (!empty($logoKananData))
                                            <img src="{{ $logoKananData }}" alt="Logo kanan">
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p class="title">Kartu Peserta Ujian</p>
                            <p class="subtitle">{{ $ujian['jenis'] ?: 'Ujian' }} · {{ $tahun }} / {{ $semester }}</p>

                            <table class="identity">
                                <tr>
                                    <td class="label">No. Peserta</td>
                                    <td class="value">{{ $row['nomor_peserta'] ?: '-' }}</td>
                                    <td class="narrow">Kelas</td>
                                    <td class="value">{{ $row['kelas'] }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Nama</td>
                                    <td class="value" colspan="3">{{ $row['nama'] }}</td>
                                </tr>
                                <tr>
                                    <td class="label">NIS / NISN</td>
                                    <td class="value">{{ $row['nis'] ?: '-' }} / {{ $row['nisn'] ?: '-' }}</td>
                                    <td class="narrow">Ruang / Sesi</td>
                                    <td class="value">{{ $row['ruang'] ?: '-' }} / {{ $row['sesi'] ?: '-' }}</td>
                                </tr>
                            </table>

                            <table class="mapel">
                                <tr>
                                    <td class="label">Mata Pelajaran</td>
                                    <td class="value">{{ $ujian['mapel'] ?: ($ujian['bank'] ?? '-') }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Tanggal</td>
                                    <td>{{ $ujian['tanggal'] }} · {{ $ujian['mulai'] }} s.d. {{ $ujian['selesai'] }}</td>
                                </tr>
                            </table>

                            <table class="foot">
                                <tr>
                                    @if (!empty($row['verifikasi']['svg']))
                                        <td class="qr">{!! $row['verifikasi']['svg'] !!}</td>
                                        <td class="verify">
                                            <p class="kode">Verifikasi: {{ $row['verifikasi']['kode'] }}</p>
                                            <p>{{ $row['verifikasi']['url'] }}</p>
                                            <p>Pindai QR untuk memeriksa keaslian kartu ini.</p>
                                        </td>
                                    @else
                                        <td class="verify">Simpan kartu ini selama ujian berlangsung.</td>
                                    @endif
                                    <td class="sign">
                                        <p>Tanda tangan</p>
                                        <div class="line"></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach
                @if (count($baris) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>

    @if (empty($peserta))
        <p style="text-align: center; margin-top: 20px; color: #555;">Belum ada peserta pada jadwal ini.</p>
    @endif
</body>
</html>
