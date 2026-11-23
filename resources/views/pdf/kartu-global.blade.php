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
    <title>Kartu Ujian Global</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #000; line-height: 1.28; }
        p { margin: 0; }
        table { border-collapse: collapse; }
        .sheet { width: 100%; }
        .sheet td { width: 50%; padding: 3px; vertical-align: top; }
        .card { border: 1.2px solid #000; padding: 6px; page-break-inside: avoid; }
        .head { width: 100%; border-bottom: 1.5px solid #000; padding-bottom: 3px; }
        .head td { vertical-align: middle; padding: 0 2px 3px; }
        .logo { width: 38px; text-align: center; }
        .logo img { max-height: 34px; max-width: 38px; }
        .koptext { text-align: center; }
        .kop-main { font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .kop-sub { font-size: 7px; }
        .title { margin-top: 4px; text-align: center; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
        .subtitle { text-align: center; font-size: 7px; }
        .identity { width: 100%; margin-top: 4px; font-size: 7.5px; }
        .identity td { padding: 0.5px 1px; vertical-align: top; }
        .identity .label { width: 18%; color: #333; }
        .identity .value { font-weight: bold; }
        .identity .narrow { width: 15%; color: #333; white-space: nowrap; }
        .c { text-align: center; }
        .foot { width: 100%; margin-top: 4px; border-top: 1px dashed #666; padding-top: 3px; }
        .foot td { vertical-align: bottom; }
        .qr { width: 78px; padding: 5px; background: #fff; }
        .qr img { width: 68px; height: 68px; image-rendering: pixelated; }
        .verify { font-size: 5.8px; color: #444; line-height: 1.2; }
        .verify .kode { font-size: 7px; font-weight: bold; color: #000; }
        .sign { width: 82px; text-align: center; font-size: 6.5px; }
        .sign .line { margin-top: 18px; border-top: 1px solid #000; }
    </style>
</head>
<body>
    <table class="sheet">
        @foreach (collect($kartu)->chunk(2) as $baris)
            <tr>
                @foreach ($baris as $siswa)
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
                            <p class="subtitle">{{ $tahun }} / {{ $semester }}</p>

                            <table class="identity">
                                <tr>
                                    <td class="label">No. Peserta</td>
                                    <td class="value">{{ $siswa['nomor_peserta'] ?: '-' }}</td>
                                    <td class="narrow">Kelas</td>
                                    <td class="value">{{ $siswa['kelas'] }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Nama</td>
                                    <td class="value" colspan="3">{{ $siswa['nama'] }}</td>
                                </tr>
                                <tr>
                                    <td class="label">NIS / NISN</td>
                                    <td class="value">{{ $siswa['nis'] ?: '-' }} / {{ $siswa['nisn'] ?: '-' }}</td>
                                    <td class="narrow">Ruang/Sesi</td>
                                    <td class="value">{{ $siswa['ruang'] ?: '-' }} / {{ $siswa['sesi'] ?: '-' }}</td>
                                </tr>
                            </table>

                            <table class="foot">
                                <tr>
                                    @if (!empty($siswa['verifikasi']['png']))
                                        <td class="qr"><img src="{{ $siswa['verifikasi']['png'] }}" alt="QR verifikasi" width="68" height="68"></td>
                                        <td class="verify">
                                            <p class="kode">Verifikasi: {{ $siswa['verifikasi']['kode'] }}</p>
                                            <p>{{ $siswa['verifikasi']['url'] }}</p>
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

    @if (empty($kartu) || count($kartu) === 0)
        <p style="text-align: center; margin-top: 20px; color: #555;">Tidak ada siswa dengan jadwal ujian pada filter ini.</p>
    @endif
</body>
</html>
