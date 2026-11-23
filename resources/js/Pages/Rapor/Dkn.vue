<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kelas: { type: Object, required: true },
    mapels: { type: Object, default: () => ({ reguler: [], mulok: [] }) },
    ekstra: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    sekolah: { type: String, default: '' },
    kota: { type: String, default: '' },
    tanggal: { type: String, default: '' },
    siap: { type: Boolean, default: false },
    logoKiri: { type: String, default: null },
    logoKanan: { type: String, default: null },
});

const allMapels = computed(() => [...props.mapels.reguler, ...props.mapels.mulok]);
const totalColumns = computed(
    () => 5 + allMapels.value.length * 2 + props.ekstra.length + 2 + 3 + 4,
);

const th = 'border border-black px-1 py-1 text-center align-middle font-semibold';
const td = 'border border-black px-1 py-1 text-center';
const tdNama = 'border border-black px-1.5 py-1 text-left';

function nilaiFor(row, idMapel) {
    const entry = row.nilai[idMapel] ?? row.nilai[String(idMapel)];
    return entry && typeof entry === 'object' ? entry : { nilai: entry ?? null, predikat: '' };
}

function ekstraFor(row, idEkstra) {
    const entry = row.ekstra[idEkstra] ?? row.ekstra[String(idEkstra)];
    return entry && typeof entry === 'object' ? entry : { nilai: entry ?? null };
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`DKN ${kelas.nama_kelas}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                href="/rapor/dkn"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <i class="fas fa-arrow-left h-4 w-4" aria-hidden="true" ></i> Kembali
            </Link>
            <p class="text-sm text-slate-500">
                DKN <b class="text-slate-800">{{ kelas.nama_kelas }}</b> · {{ tahun }} ({{ semester }})
            </p>
            <div class="flex items-center gap-2">
                <a
                    :href="`/rapor/dkn/${kelas.id_kelas}/pdf`"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <i class="fas fa-download h-4 w-4" aria-hidden="true" ></i> Unduh PDF
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-3.5 py-2 text-sm font-medium text-white transition hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    @click="printPage"
                >
                    <i class="fas fa-print h-4 w-4" aria-hidden="true" ></i> Cetak
                </button>
            </div>
        </div>

        <p v-if="!siap" class="mx-4 mt-3 rounded-lg border border-warning/40 bg-warning/10 px-4 py-2 text-sm text-warning print:hidden">
            Pengaturan rapor periode aktif belum dibuat. Predikat dihitung memakai KKM bawaan.
        </p>

        <div class="overflow-x-auto p-4 print:overflow-visible print:p-0">
            <div class="print-sheet mx-auto w-max min-w-[297mm] bg-white p-[8mm] text-[9px] text-black shadow-lg print:w-full print:min-w-0 print:p-0 print:shadow-none">
                <header class="flex items-center gap-3 border-b-[3px] border-double border-black pb-1 text-center">
                    <div class="w-[48px] shrink-0">
                        <img v-if="logoKiri" :src="logoKiri" alt="Logo kiri" class="mx-auto max-h-[44px] max-w-[48px]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-[13px] font-bold uppercase">{{ sekolah || 'Nama Sekolah' }}</h1>
                        <p class="text-[10px] font-semibold uppercase tracking-wide">Daftar Kumpulan Nilai Kelas {{ kelas.nama_kelas }}</p>
                        <p class="text-[9px]">Tahun Pelajaran {{ tahun }} · Semester {{ semester }}</p>
                    </div>
                    <div class="w-[48px] shrink-0">
                        <img v-if="logoKanan" :src="logoKanan" alt="Logo kanan" class="mx-auto max-h-[44px] max-w-[48px]" />
                    </div>
                </header>

                <table class="mt-2 w-full border-collapse">
                    <caption class="sr-only">
                        Daftar kumpulan nilai kelas {{ kelas.nama_kelas }} tahun pelajaran {{ tahun }} semester {{ semester }}
                    </caption>
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" rowspan="3" scope="col" class="w-[24px]">No</th>
                            <th :class="th" rowspan="3" scope="col" class="min-w-[62px]">NIS</th>
                            <th :class="th" rowspan="3" scope="col" class="min-w-[72px]">NISN</th>
                            <th :class="th" rowspan="3" scope="col" class="min-w-[120px]">Nama Siswa</th>
                            <th :class="th" rowspan="3" scope="col" class="w-[24px]">JK</th>
                            <th v-if="mapels.reguler.length" :class="th" scope="colgroup" :colspan="mapels.reguler.length * 2">Mata Pelajaran</th>
                            <th v-if="mapels.mulok.length" :class="th" scope="colgroup" :colspan="mapels.mulok.length * 2">Muatan Lokal</th>
                            <th v-if="ekstra.length" :class="th" scope="colgroup" :colspan="ekstra.length">Ekstrakurikuler</th>
                            <th :class="th" scope="colgroup" colspan="2">Sikap</th>
                            <th :class="th" scope="colgroup" colspan="3">Absensi</th>
                            <th :class="th" rowspan="3" scope="col" class="w-[42px]">Jumlah</th>
                            <th :class="th" rowspan="3" scope="col" class="w-[42px]">Rata</th>
                            <th :class="th" rowspan="3" scope="col" class="w-[36px]">Rank</th>
                            <th :class="th" rowspan="3" scope="col" class="w-[50px]">Naik</th>
                        </tr>
                        <tr class="bg-slate-100">
                            <th
                                v-for="mapel in allMapels"
                                :key="`head-${mapel.id_mapel}`"
                                :class="th"
                                scope="col"
                                colspan="2"
                                :title="`${mapel.nama_mapel} (KKM ${mapel.kkm})`"
                            >
                                <div class="mx-auto max-w-[92px] truncate text-[7.5px] leading-tight">{{ mapel.nama_mapel }}</div>
                                <div class="text-[7px] font-normal">KKM {{ mapel.kkm }}</div>
                            </th>
                            <th v-for="item in ekstra" :key="`head-ekstra-${item.id_ekstra}`" :class="th" scope="col">
                                <div class="mx-auto max-w-[64px] truncate text-[7.5px] leading-tight">{{ item.nama_ekstra }}</div>
                            </th>
                            <th :class="th" scope="col" class="w-[24px]" title="Sikap spiritual">S</th>
                            <th :class="th" scope="col" class="w-[24px]" title="Sikap sosial">So</th>
                            <th :class="th" scope="col" class="w-[24px]" title="Sakit">Skt</th>
                            <th :class="th" scope="col" class="w-[24px]" title="Izin">Ijn</th>
                            <th :class="th" scope="col" class="w-[24px]" title="Alpa">Alp</th>
                        </tr>
                        <tr class="bg-slate-100">
                            <template v-for="mapel in allMapels" :key="`sub-${mapel.id_mapel}`">
                                <th :class="th" scope="col" class="w-[24px] text-[7px]">N</th>
                                <th :class="th" scope="col" class="w-[20px] text-[7px]">P</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id_siswa">
                            <td :class="td">{{ index + 1 }}</td>
                            <td :class="td" class="whitespace-nowrap">{{ row.nis }}</td>
                            <td :class="td" class="whitespace-nowrap">{{ row.nisn || '-' }}</td>
                            <td :class="tdNama" class="font-medium">{{ row.nama }}</td>
                            <td :class="td">{{ row.jenis_kelamin || '-' }}</td>
                            <template v-for="mapel in allMapels" :key="`cell-${row.id_siswa}-${mapel.id_mapel}`">
                                <td
                                    :class="[
                                        td,
                                        nilaiFor(row, mapel.id_mapel).nilai !== null &&
                                        nilaiFor(row, mapel.id_mapel).nilai !== '' &&
                                        Number(nilaiFor(row, mapel.id_mapel).nilai) < mapel.kkm
                                            ? 'font-semibold text-destructive'
                                            : '',
                                    ]"
                                >
                                    {{ nilaiFor(row, mapel.id_mapel).nilai ?? '' }}
                                </td>
                                <td :class="td">{{ nilaiFor(row, mapel.id_mapel).predikat || '' }}</td>
                            </template>
                            <td v-for="item in ekstra" :key="`cell-ekstra-${row.id_siswa}-${item.id_ekstra}`" :class="td">
                                {{ ekstraFor(row, item.id_ekstra).nilai ?? '' }}
                            </td>
                            <td :class="td">{{ row.sikap_spiritual || '-' }}</td>
                            <td :class="td">{{ row.sikap_sosial || '-' }}</td>
                            <td :class="td">{{ row.absensi.s }}</td>
                            <td :class="td">{{ row.absensi.i }}</td>
                            <td :class="td">{{ row.absensi.a }}</td>
                            <td :class="[td, 'font-semibold']">{{ row.jumlah || '-' }}</td>
                            <td :class="td">{{ row.rata_rata ?? '-' }}</td>
                            <td :class="td">{{ row.ranking || '-' }}</td>
                            <td :class="td">{{ row.naik === null ? '-' : row.naik === 1 ? 'Naik' : 'Tidak' }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="totalColumns" :class="td" class="py-6 text-slate-500">
                                Belum ada siswa atau nilai akhir pada kelas ini.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-2 text-[8px] text-slate-500 print:text-black">
                    Keterangan: N = nilai akhir, P = predikat, JK = jenis kelamin. Nilai merah berada di bawah KKM.
                    S = sakit, Ijn = izin, Alp = tanpa keterangan.
                    <span v-if="!allMapels.length"> Belum ada nilai akhir mata pelajaran yang tersimpan.</span>
                </p>

                <div class="mt-5 flex justify-between text-[9px]">
                    <div class="text-center">
                        <p>Mengetahui,</p>
                        <p>Kepala Sekolah</p>
                        <div class="mx-auto mt-12 w-52 border-b border-black"></div>
                    </div>
                    <div class="text-center">
                        <p>{{ [kota, tanggal].filter(Boolean).join(', ') }}</p>
                        <p>Wali Kelas {{ kelas.nama_kelas }}</p>
                        <div class="mx-auto mt-12 w-52 border-b border-black"></div>
                        <p class="mt-1 font-semibold">{{ kelas.wali || '' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    table {
        font-size: 6.5px;
    }
}
</style>
