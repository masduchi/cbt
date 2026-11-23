@php
    $tandaTangan = collect([$kota ?? null, $tanggal ?? null])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Leger {{ $kelas['nama_kelas'] }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #000; }
        h1, p { margin: 0; }
        .kop { width: 100%; border-collapse: collapse; border-bottom: 3px double #000; margin: 0; }
        .kop td { vertical-align: middle; padding: 0 4px 5px; }
        .kop-logo { width: 48px; text-align: center; }
        .kop-logo img { max-height: 44px; max-width: 48px; }
        .kop-text { text-align: center; }
        .kop-text h1 { font-size: 13px; text-transform: uppercase; }
        .kop-text .sub { font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .kop-text .meta { font-size: 8px; }
        table { border-collapse: collapse; width: 100%; margin-top: 6px; }
        th { border: 1px solid #000; padding: 3px 2px; background: #e9e9e9; font-size: 7px; text-align: center; }
        td { border: 1px solid #000; padding: 2px 3px; font-size: 7.5px; }
        .center { text-align: center; }
        .nama { text-align: left; }
        .signature { width: 100%; margin-top: 16px; font-size: 8.5px; }
        .signature td { border: none; width: 50%; vertical-align: top; }
        .signature .right { text-align: right; }
        .signature .line { margin-top: 36px; border-top: 1px solid #000; width: 55%; }
        .signature .right .line { margin-left: auto; }
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
                <h1>{{ $sekolah ?: 'Nama Sekolah' }}</h1>
                <p class="sub">Leger Nilai Kelas {{ $kelas['nama_kelas'] }}</p>
                <p class="meta">Tahun Pelajaran {{ $tahun }} | Semester {{ $semester }}</p>
            </td>
            <td class="kop-logo">
                @if (!empty($logoKananData))
                    <img src="{{ $logoKananData }}" alt="Logo kanan">
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 3%">No</th>
                <th style="width: 16%">Nama Siswa</th>
                <th style="width: 9%">NIS / NISN</th>
                @foreach ($mapels as $mapel)
                    <th>{{ $mapel['nama_mapel'] }}<br><span style="font-weight: normal">KKM {{ $mapel['kkm'] }}</span></th>
                @endforeach
                <th style="width: 5%">Rata</th>
                <th style="width: 4%">Rank</th>
                <th style="width: 3%">S</th>
                <th style="width: 3%">So</th>
                <th style="width: 3%">Skt</th>
                <th style="width: 3%">Ijn</th>
                <th style="width: 3%">Alp</th>
                <th style="width: 8%">Naik</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="nama">{{ $row['nama'] }}</td>
                    <td class="center">{{ $row['nis'] ?: '-' }} / {{ $row['nisn'] ?: '-' }}</td>
                    @foreach ($mapels as $mapel)
                        @php($nilai = $row['nilai'][$mapel['id_mapel']] ?? $row['nilai'][(string) $mapel['id_mapel']] ?? null)
                        <td class="center">{{ ($nilai === null || $nilai === '') ? '' : $nilai }}</td>
                    @endforeach
                    <td class="center">{{ $row['rata_rata'] ?? '' }}</td>
                    <td class="center">{{ $row['ranking'] ?: '' }}</td>
                    <td class="center">{{ $row['sikap_spiritual'] }}</td>
                    <td class="center">{{ $row['sikap_sosial'] }}</td>
                    <td class="center">{{ $row['absensi']['s'] }}</td>
                    <td class="center">{{ $row['absensi']['i'] }}</td>
                    <td class="center">{{ $row['absensi']['a'] }}</td>
                    <td class="center">
                        {{ $row['naik'] === null ? '' : ($row['naik'] === 1 ? 'Naik' : 'Tidak') }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ 11 + count($mapels) }}" class="center">Belum ada data siswa.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="line"></div>
            </td>
            <td class="right">
                <p>{{ $tandaTangan }}</p>
                <p>Wali Kelas {{ $kelas['nama_kelas'] }}</p>
                <div class="line"></div>
                <p>{{ $kelas['wali'] ?: '' }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
