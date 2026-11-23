<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    siswa: { type: Object, required: true },
    kelas: { type: Object, required: true },
    sekolah: { type: Object, default: () => ({}) },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    tanggal: { type: String, default: '' },
    nilai: { type: Array, default: () => [] },
    p_nilai: { type: Number, default: null },
    sikap: { type: Object, default: () => ({}) },
    absensi: { type: Object, default: () => ({ s: 0, i: 0, a: 0 }) },
    catatan: { type: String, default: '' },
    prestasi: { type: Object, default: () => ({ ranking: null, items: [] }) },
    ekstra: { type: Array, default: () => [] },
    fisik: { type: Object, default: null },
    naik: { type: Number, default: null },
    siap: { type: Boolean, default: false },
    jenis: { type: String, default: 'akhir' },
    judul: { type: String, default: 'Laporan Hasil Belajar' },
    logoKiri: { type: String, default: null },
    logoKanan: { type: String, default: null },
    verifikasi: { type: Object, default: null },
});

const th = 'border border-black px-1.5 py-1 text-center font-semibold';
const td = 'border border-black px-1.5 py-1 align-top';

const alamatSekolah = computed(() =>
    [
        props.sekolah.alamat,
        props.sekolah.desa ? `Desa/Kel. ${props.sekolah.desa}` : null,
        props.sekolah.kecamatan ? `Kec. ${props.sekolah.kecamatan}` : null,
        props.sekolah.kota,
        props.sekolah.provinsi,
    ]
        .filter(Boolean)
        .join(', '),
);

const tempatTanggalLahir = computed(() =>
    [props.siswa.tempat_lahir, props.siswa.tanggal_lahir].filter(Boolean).join(', '),
);

const tanggalTandaTangan = computed(() => [props.sekolah.kota, props.tanggal].filter(Boolean).join(', '));

const pdfHref = computed(
    () => `/rapor/cetak/${props.kelas.id_kelas}/${props.siswa.id_siswa}/pdf${props.jenis === 'pts' ? '?jenis=pts' : ''}`,
);

const keputusan = computed(() => {
    if (props.naik === null) return '';
    if (props.kelas.akhir) return props.naik === 1 ? 'LULUS' : 'TIDAK LULUS';
    return props.naik === 1 ? 'NAIK KE KELAS BERIKUTNYA' : 'TINGGAL DI KELAS';
});

const fisikAda = computed(() => {
    if (!props.fisik) return false;
    return Boolean(
        Number(props.fisik.tinggi) ||
            Number(props.fisik.berat) ||
            props.fisik.telinga ||
            props.fisik.mata ||
            props.fisik.gigi ||
            props.fisik.lain,
    );
});

const prestasiItems = computed(() => (props.prestasi.items?.length ? props.prestasi.items : [{ jenis: '', deskripsi: '' }, { jenis: '', deskripsi: '' }]));

function sikapOf(jenis) {
    return props.sikap[jenis] ?? props.sikap[String(jenis)] ?? { predikat: '', deskripsi: '' };
}

const sikapLabel = { A: 'Sangat Baik', B: 'Baik', C: 'Cukup', D: 'Perlu Bimbingan' };

function labelPredikat(value) {
    return sikapLabel[value] ?? (value || '-');
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Rapor ${siswa.nama}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                :href="`/rapor/cetak/${kelas.id_kelas}`"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali
            </Link>
            <p class="text-sm text-slate-500">
                Pratinjau {{ jenis === 'pts' ? 'nilai PTS' : 'rapor' }} <b class="text-slate-800">{{ siswa.nama }}</b> · {{ kelas.nama_kelas }} · {{ tahun }} ({{ semester }})
            </p>
            <div class="flex items-center gap-2">
                <a
                    :href="pdfHref"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="fas fa-download h-4 w-4" ></i> Unduh PDF
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    @click="printPage"
                >
                    <i class="fas fa-print h-4 w-4" ></i> Cetak
                </button>
            </div>
        </div>

        <div v-if="!siap" class="mx-auto mt-4 w-[210mm] max-w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
            Pengaturan rapor periode ini belum dibuat. KKM memakai nilai bawaan 75 dan tanggal pengesahan kosong.
        </div>

        <div class="print-sheet mx-auto my-6 w-[210mm] max-w-full bg-white p-[12mm] text-[11px] leading-relaxed shadow-lg print:my-0 print:w-auto print:max-w-none print:p-0 print:shadow-none">
            <header class="flex items-center gap-3 border-b-[3px] border-double border-black pb-2 text-center">
                <div class="w-[60px] shrink-0">
                    <img v-if="logoKiri" :src="logoKiri" alt="Logo kiri" class="mx-auto max-h-[56px] max-w-[60px]" />
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-[16px] font-bold uppercase tracking-wide">{{ sekolah.nama || 'Nama Sekolah' }}</h1>
                    <p class="mt-0.5 text-[10px]">{{ alamatSekolah }}</p>
                    <p class="text-[10px]">
                        <span v-if="sekolah.npsn">NPSN: {{ sekolah.npsn }}</span>
                        <span v-if="sekolah.npsn && sekolah.nss"> · </span>
                        <span v-if="sekolah.nss">NSS: {{ sekolah.nss }}</span>
                    </p>
                </div>
                <div class="w-[60px] shrink-0">
                    <img v-if="logoKanan" :src="logoKanan" alt="Logo kanan" class="mx-auto max-h-[56px] max-w-[60px]" />
                </div>
            </header>

            <div class="mt-3 text-center">
                <p class="text-[14px] font-bold uppercase tracking-wide">{{ judul }}</p>
                <p class="text-[11px] font-semibold">{{ sekolah.jenjang }}</p>
            </div>

            <table class="mt-3 w-full text-[10.5px]">
                <tbody>
                    <tr>
                        <td class="w-[17%] py-0.5">Nama</td>
                        <td class="w-[2%]">:</td>
                        <td class="w-[31%] font-semibold">{{ siswa.nama }}</td>
                        <td class="w-[16%] pl-3">Kelas</td>
                        <td class="w-[2%]">:</td>
                        <td class="font-semibold">{{ kelas.nama_kelas }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">NIS / NISN</td>
                        <td>:</td>
                        <td class="font-semibold">{{ siswa.nis || '-' }} / {{ siswa.nisn || '-' }}</td>
                        <td class="pl-3">Semester</td>
                        <td>:</td>
                        <td class="font-semibold">{{ semester }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Tempat, Tanggal Lahir</td>
                        <td>:</td>
                        <td>{{ tempatTanggalLahir || '-' }}</td>
                        <td class="pl-3">Tahun Pelajaran</td>
                        <td>:</td>
                        <td class="font-semibold">{{ tahun }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5">Nama Sekolah</td>
                        <td>:</td>
                        <td>{{ sekolah.nama }}</td>
                        <td class="pl-3">Alamat</td>
                        <td>:</td>
                        <td>{{ alamatSekolah }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="mt-4">
                <h2 class="text-[11px] font-bold uppercase">A. Sikap</h2>
                <p class="mt-1 text-[10.5px] font-semibold">1. Sikap Spiritual</p>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[22%]">Predikat</th>
                            <th :class="th">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td :class="td" class="text-center font-semibold">{{ labelPredikat(sikapOf(1).predikat) }}</td>
                            <td :class="td">{{ sikapOf(1).deskripsi || '-' }}</td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-2 text-[10.5px] font-semibold">2. Sikap Sosial</p>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[22%]">Predikat</th>
                            <th :class="th">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td :class="td" class="text-center font-semibold">{{ labelPredikat(sikapOf(2).predikat) }}</td>
                            <td :class="td">{{ sikapOf(2).deskripsi || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <template v-if="jenis !== 'pts'">
            <section class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">B. Pengetahuan</h2>
                <p v-if="p_nilai" class="text-[10px]">Kriteria Ketuntasan Minimal (KKM): <b>{{ p_nilai }}</b></p>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[5%]">No</th>
                            <th :class="th" class="w-[24%]">Mata Pelajaran</th>
                            <th v-if="!p_nilai" :class="th" class="w-[7%]">KKM</th>
                            <th :class="th" class="w-[7%]">Nilai</th>
                            <th :class="th" class="w-[8%]">Predikat</th>
                            <th :class="th">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in nilai" :key="`p-${row.id_mapel}`">
                            <td :class="td" class="text-center">{{ index + 1 }}</td>
                            <td :class="td">{{ row.nama_mapel }}</td>
                            <td v-if="!p_nilai" :class="td" class="text-center">{{ row.kkm }}</td>
                            <td :class="td" class="text-center font-semibold">{{ row.p_nilai ?? '' }}</td>
                            <td :class="td" class="text-center">{{ row.p_predikat }}</td>
                            <td :class="td">{{ row.p_deskripsi }}</td>
                        </tr>
                        <tr v-if="!nilai.length">
                            <td :colspan="p_nilai ? 5 : 6" :class="td" class="py-4 text-center text-slate-500">
                                Belum ada nilai pengetahuan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">C. Keterampilan</h2>
                <p v-if="p_nilai" class="text-[10px]">Kriteria Ketuntasan Minimal (KKM): <b>{{ p_nilai }}</b></p>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[5%]">No</th>
                            <th :class="th" class="w-[24%]">Mata Pelajaran</th>
                            <th v-if="!p_nilai" :class="th" class="w-[7%]">KKM</th>
                            <th :class="th" class="w-[7%]">Nilai</th>
                            <th :class="th" class="w-[8%]">Predikat</th>
                            <th :class="th">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in nilai" :key="`k-${row.id_mapel}`">
                            <td :class="td" class="text-center">{{ index + 1 }}</td>
                            <td :class="td">{{ row.nama_mapel }}</td>
                            <td v-if="!p_nilai" :class="td" class="text-center">{{ row.kkm }}</td>
                            <td :class="td" class="text-center font-semibold">{{ row.k_nilai ?? '' }}</td>
                            <td :class="td" class="text-center">{{ row.k_predikat }}</td>
                            <td :class="td">{{ row.k_deskripsi }}</td>
                        </tr>
                        <tr v-if="!nilai.length">
                            <td :colspan="p_nilai ? 5 : 6" :class="td" class="py-4 text-center text-slate-500">
                                Belum ada nilai keterampilan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            </template>

            <section v-if="jenis === 'pts'" class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">B. Nilai Penilaian Tengah Semester</h2>
                <p v-if="p_nilai" class="text-[10px]">Kriteria Ketuntasan Minimal (KKM): <b>{{ p_nilai }}</b></p>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[5%]">No</th>
                            <th :class="th" class="w-[45%]">Mata Pelajaran</th>
                            <th v-if="!p_nilai" :class="th" class="w-[8%]">KKM</th>
                            <th :class="th" class="w-[10%]">Nilai</th>
                            <th :class="th" class="w-[12%]">Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in nilai" :key="`pts-${row.id_mapel}`">
                            <td :class="td" class="text-center">{{ index + 1 }}</td>
                            <td :class="td">{{ row.nama_mapel }}</td>
                            <td v-if="!p_nilai" :class="td" class="text-center">{{ row.kkm }}</td>
                            <td :class="td" class="text-center font-semibold">{{ row.nilai ?? '' }}</td>
                            <td :class="td" class="text-center">{{ row.predikat }}</td>
                        </tr>
                        <tr v-if="!nilai.length">
                            <td :colspan="p_nilai ? 4 : 5" :class="td" class="py-4 text-center text-slate-500">
                                Belum ada nilai PTS.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">{{ jenis === 'pts' ? 'C' : 'D' }}. Ekstrakurikuler</h2>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[5%]">No</th>
                            <th :class="th" class="w-[33%]">Kegiatan Ekstrakurikuler</th>
                            <th :class="th" class="w-[15%]">Predikat</th>
                            <th :class="th">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in ekstra" :key="row.id_ekstra">
                            <td :class="td" class="text-center">{{ index + 1 }}</td>
                            <td :class="td">{{ row.nama_ekstra }}</td>
                            <td :class="td" class="text-center">{{ row.predikat }}</td>
                            <td :class="td">{{ row.deskripsi }}</td>
                        </tr>
                        <tr v-if="!ekstra.length">
                            <td :colspan="4" :class="td" class="py-3 text-center text-slate-500">Belum ada kegiatan ekstrakurikuler.</td>
                        </tr>
                    </tbody>
                </table>

                <h3 class="mt-3 text-[10.5px] font-semibold">Prestasi</h3>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[5%]">No</th>
                            <th :class="th" class="w-[33%]">Jenis Kegiatan</th>
                            <th :class="th">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in prestasiItems" :key="`prestasi-${index}`">
                            <td :class="td" class="text-center">{{ index + 1 }}</td>
                            <td :class="td">{{ item.jenis || '' }}</td>
                            <td :class="td">{{ item.deskripsi || '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">{{ jenis === 'pts' ? 'D' : 'E' }}. Ketidakhadiran</h2>
                <table class="mt-1 w-1/2 border-collapse text-[10px]">
                    <tbody>
                        <tr>
                            <td :class="td" class="w-[70%]">Sakit</td>
                            <td :class="td">{{ absensi.s }} hari</td>
                        </tr>
                        <tr>
                            <td :class="td">Izin</td>
                            <td :class="td">{{ absensi.i }} hari</td>
                        </tr>
                        <tr>
                            <td :class="td">Tanpa Keterangan</td>
                            <td :class="td">{{ absensi.a }} hari</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="fisikAda" class="mt-3">
                    <h3 class="text-[10.5px] font-semibold">Kondisi Fisik</h3>
                    <table class="mt-1 w-full border-collapse text-[10px]">
                        <tbody>
                            <tr>
                                <td :class="td" class="w-[25%]">Tinggi Badan</td>
                                <td :class="td" class="w-[25%]">{{ fisik.tinggi ? `${fisik.tinggi} cm` : '-' }}</td>
                                <td :class="td" class="w-[25%]">Berat Badan</td>
                                <td :class="td">{{ fisik.berat ? `${fisik.berat} kg` : '-' }}</td>
                            </tr>
                            <tr>
                                <td :class="td">Pendengaran</td>
                                <td :class="td">{{ fisik.telinga || '-' }}</td>
                                <td :class="td">Penglihatan</td>
                                <td :class="td">{{ fisik.mata || '-' }}</td>
                            </tr>
                            <tr>
                                <td :class="td">Gigi</td>
                                <td :class="td">{{ fisik.gigi || '-' }}</td>
                                <td :class="td">Lain-lain</td>
                                <td :class="td">{{ fisik.lain || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-4 break-inside-avoid">
                <h2 class="text-[11px] font-bold uppercase">{{ jenis === 'pts' ? 'E' : 'F' }}. Catatan Wali Kelas</h2>
                <table class="mt-1 w-full border-collapse text-[10px]">
                    <tbody>
                        <tr>
                            <td :class="td" class="w-[18%]">Peringkat ke</td>
                            <td :class="td">{{ prestasi.ranking ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td :class="td">Catatan</td>
                            <td :class="td" class="min-h-[36px]">{{ catatan || '-' }}</td>
                        </tr>
                        <tr v-if="keputusan">
                            <td :class="td">Keputusan</td>
                            <td :class="td">
                                Berdasarkan pencapaian kompetensi, peserta didik ditetapkan:
                                <b>{{ keputusan }}</b>.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div class="mt-8 grid grid-cols-2 gap-6 text-[10px]">
                <div>
                    <p>Mengetahui,</p>
                    <p>Orang Tua / Wali</p>
                    <div class="mt-16 w-3/4 border-b border-black"></div>
                </div>
                <div class="text-right">
                    <p>{{ tanggalTandaTangan }}</p>
                    <p>Wali Kelas</p>
                    <div class="ml-auto mt-16 w-3/4 border-b border-black"></div>
                    <p class="mt-1 font-semibold">{{ kelas.wali || '' }}</p>
                    <p>NIP. {{ kelas.wali_nip || '-' }}</p>
                </div>
            </div>

            <div class="mt-6 text-center text-[10px]">
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="mx-auto mt-16 w-1/2 border-b border-black"></div>
                <p class="mt-1 font-semibold">{{ sekolah.kepsek || '' }}</p>
                <p>NIP. {{ sekolah.nip || '-' }}</p>
            </div>

            <div
                v-if="verifikasi"
                class="mt-4 flex items-center gap-3 border-t border-dashed border-slate-400 pt-2 text-[8px] leading-tight text-slate-600"
            >
                <div v-if="verifikasi.svg" class="h-20 w-20 shrink-0" v-html="verifikasi.svg"></div>
                <div>
                    <p class="font-semibold text-slate-800">Verifikasi dokumen</p>
                    <p class="font-mono">Kode: {{ verifikasi.kode }}</p>
                    <p class="break-all">{{ verifikasi.url }}</p>
                    <p class="text-slate-500">Pindai kode QR atau buka tautan untuk memeriksa keaslian dokumen ini.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
