@php
    $allMapels = array_merge($mapels['reguler'], $mapels['mulok']);
    $totalColumns = 5 + count($allMapels) * 2 + count($ekstra) + 2 + 3 + 4;
    $tandaTangan = collect([$kota ?? null, $tanggal ?? null])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>DKN {{ $kelas['nama_kelas'] }}</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7px; color: #000; }
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
        th { border: 1px solid #000; padding: 2px; background: #ebebeb; font-size: 6.5px; text-align: center; vertical-align: middle; }
        td { border: 1px solid #000; padding: 1.5px 2px; font-size: 6.5px; }
        .center { text-align: center; }
        .nama { text-align: left; }
        .low { font-weight: bold; }
        .note { margin-top: 4px; font-size: 6.5px; }
        .signature { width: 100%; margin-top: 14px; font-size: 8.5px; }
        .signature td { border: none; width: 50%; vertical-align: top; text-align: center; padding: 0 20px; }
        .signature .line { margin-top: 34px; border-top: 1px solid #000; width: 60%; margin-left: auto; margin-right: auto; }
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
                <p class="sub">Daftar Kumpulan Nilai Kelas {{ $kelas['nama_kelas'] }}</p>
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
                <th rowspan="3" style="width: 3%">No</th>
                <th rowspan="3" style="width: 8%">NIS</th>
                <th rowspan="3" style="width: 9%">NISN</th>
                <th rowspan="3" style="width: 17%">Nama Siswa</th>
                <th rowspan="3" style="width: 3%">JK</th>
                @if (count($mapels['reguler']))
                    <th colspan="{{ count($mapels['reguler']) * 2 }}">Mata Pelajaran</th>
                @endif
                @if (count($mapels['mulok']))
                    <th colspan="{{ count($mapels['mulok']) * 2 }}">Muatan Lokal</th>
                @endif
                @if (count($ekstra))
                    <th colspan="{{ count($ekstra) }}">Ekstrakurikuler</th>
                @endif
                <th colspan="2">Sikap</th>
                <th colspan="3">Absensi</th>
                <th rowspan="3" style="width: 5%">Jumlah</th>
                <th rowspan="3" style="width: 5%">Rata</th>
                <th rowspan="3" style="width: 4%">Rank</th>
                <th rowspan="3" style="width: 6%">Naik</th>
            </tr>
            <tr>
                @foreach ($allMapels as $mapel)
                    <th colspan="2">{{ $mapel['nama_mapel'] }}<br><span style="font-weight: normal">KKM {{ $mapel['kkm'] }}</span></th>
                @endforeach
                @foreach ($ekstra as $item)
                    <th>{{ $item['nama_ekstra'] }}</th>
                @endforeach
                <th>S</th>
                <th>So</th>
                <th>Skt</th>
                <th>Ijn</th>
                <th>Alp</th>
            </tr>
            <tr>
                @foreach ($allMapels as $mapel)
                    <th style="width: 2.5%">N</th>
                    <th style="width: 2.5%">P</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">{{ $row['nis'] }}</td>
                    <td class="center">{{ $row['nisn'] ?: '-' }}</td>
                    <td class="nama">{{ $row['nama'] }}</td>
                    <td class="center">{{ $row['jenis_kelamin'] ?: '-' }}</td>
                    @foreach ($allMapels as $mapel)
                        @php($cell = $row['nilai'][$mapel['id_mapel']] ?? ['nilai' => null, 'predikat' => ''])
                        @php($angka = $cell['nilai'] ?? null)
                        <td class="center{{ $angka !== null && $angka !== '' && (int) $angka < $mapel['kkm'] ? ' low' : '' }}">
                            {{ ($angka === null || $angka === '') ? '' : $angka }}
                        </td>
                        <td class="center">{{ $cell['predikat'] ?? '' }}</td>
                    @endforeach
                    @foreach ($ekstra as $item)
                        @php($nilaiEkstra = $row['ekstra'][$item['id_ekstra']]['nilai'] ?? null)
                        <td class="center">{{ ($nilaiEkstra === null || $nilaiEkstra === '') ? '' : $nilaiEkstra }}</td>
                    @endforeach
                    <td class="center">{{ $row['sikap_spiritual'] ?: '-' }}</td>
                    <td class="center">{{ $row['sikap_sosial'] ?: '-' }}</td>
                    <td class="center">{{ $row['absensi']['s'] }}</td>
                    <td class="center">{{ $row['absensi']['i'] }}</td>
                    <td class="center">{{ $row['absensi']['a'] }}</td>
                    <td class="center low">{{ $row['jumlah'] ?: '-' }}</td>
                    <td class="center">{{ $row['rata_rata'] ?? '-' }}</td>
                    <td class="center">{{ $row['ranking'] ?: '-' }}</td>
                    <td class="center">{{ $row['naik'] === null ? '-' : ($row['naik'] === 1 ? 'Naik' : 'Tidak') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $totalColumns }}" class="center">Belum ada siswa atau nilai akhir pada kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">
        Keterangan: N = nilai akhir, P = predikat, JK = jenis kelamin. Nilai tercetak tebal berada di bawah KKM.
        S = sakit, Ijn = izin, Alp = tanpa keterangan.
    </p>

    <table class="signature">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="line"></div>
                <p>{{ $kepsek ?? '' }}</p>
            </td>
            <td>
                <p>{{ $tandaTangan }}</p>
                <p>Wali Kelas {{ $kelas['nama_kelas'] }}</p>
                <div class="line"></div>
                <p>{{ $kelas['wali'] ?: '' }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
