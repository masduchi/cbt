<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    kelas: { type: Object, required: true },
    mapels: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    sekolah: { type: String, default: '' },
    tanggal: { type: String, default: '' },
    kota: { type: String, default: '' },
    logoKiri: { type: String, default: null },
    logoKanan: { type: String, default: null },
});

const th = 'border border-black px-1 py-1 text-center font-semibold';
const td = 'border border-black px-1 py-1 text-center';
const tdNama = 'border border-black px-1.5 py-1 text-left';

function nilaiOf(row, idMapel) {
    const value = row.nilai[idMapel] ?? row.nilai[String(idMapel)];
    return value === null || value === undefined || value === '' ? '' : value;
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Leger ${kelas.nama_kelas}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                :href="`/rapor/cetak/${kelas.id_kelas}`"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali
            </Link>
            <p class="text-sm text-slate-500">
                Leger nilai <b class="text-slate-800">{{ kelas.nama_kelas }}</b> · {{ tahun }} ({{ semester }})
            </p>
            <div class="flex items-center gap-2">
                <a
                    :href="`/rapor/leger/${kelas.id_kelas}/pdf`"
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

        <div class="overflow-x-auto p-4 print:overflow-visible print:p-0">
            <div class="leger-sheet print-sheet mx-auto w-max min-w-[297mm] bg-white p-[8mm] text-[9px] shadow-lg print:w-full print:min-w-0 print:p-0 print:shadow-none">
                <header class="flex items-center gap-3 border-b-[3px] border-double border-black pb-1 text-center">
                    <div class="w-[48px] shrink-0">
                        <img v-if="logoKiri" :src="logoKiri" alt="Logo kiri" class="mx-auto max-h-[44px] max-w-[48px]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-[13px] font-bold uppercase">{{ sekolah || 'Nama Sekolah' }}</h1>
                        <p class="text-[10px] font-semibold uppercase tracking-wide">Leger Nilai Kelas {{ kelas.nama_kelas }}</p>
                        <p class="text-[9px]">Tahun Pelajaran {{ tahun }} · Semester {{ semester }}</p>
                    </div>
                    <div class="w-[48px] shrink-0">
                        <img v-if="logoKanan" :src="logoKanan" alt="Logo kanan" class="mx-auto max-h-[44px] max-w-[48px]" />
                    </div>
                </header>

                <table class="mt-2 w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-100">
                            <th :class="th" class="w-[26px]">No</th>
                            <th :class="th" class="min-w-[130px]">Nama Siswa</th>
                            <th :class="th" class="min-w-[66px]">NIS / NISN</th>
                            <th v-for="mapel in mapels" :key="mapel.id_mapel" :class="th" :title="`${mapel.nama_mapel} (KKM ${mapel.kkm})`">
                                <div class="mx-auto h-[86px] w-[18px] text-[7px] font-semibold leading-none" style="writing-mode: vertical-rl; transform: rotate(180deg)">
                                    {{ mapel.nama_mapel }}
                                </div>
                            </th>
                            <th :class="th" class="w-[42px]">Rata-rata</th>
                            <th :class="th" class="w-[38px]">Rank</th>
                            <th :class="th" class="w-[24px]" title="Sikap Spiritual">S</th>
                            <th :class="th" class="w-[24px]" title="Sikap Sosial">So</th>
                            <th :class="th" class="w-[24px]" title="Sakit">S</th>
                            <th :class="th" class="w-[24px]" title="Izin">I</th>
                            <th :class="th" class="w-[24px]" title="Alpa">A</th>
                            <th :class="th" class="w-[54px]">Naik / Tidak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id_siswa">
                            <td :class="td">{{ index + 1 }}</td>
                            <td :class="tdNama" class="font-medium">{{ row.nama }}</td>
                            <td :class="td" class="whitespace-nowrap">{{ row.nis }} / {{ row.nisn || '-' }}</td>
                            <td
                                v-for="mapel in mapels"
                                :key="`${row.id_siswa}-${mapel.id_mapel}`"
                                :class="[td, nilaiOf(row, mapel.id_mapel) !== '' && Number(nilaiOf(row, mapel.id_mapel)) < mapel.kkm ? 'font-semibold text-rose-600' : '']"
                            >
                                {{ nilaiOf(row, mapel.id_mapel) }}
                            </td>
                            <td :class="[td, 'font-semibold']">{{ row.rata_rata ?? '-' }}</td>
                            <td :class="td">{{ row.ranking || '-' }}</td>
                            <td :class="td">{{ row.sikap_spiritual || '-' }}</td>
                            <td :class="td">{{ row.sikap_sosial || '-' }}</td>
                            <td :class="td">{{ row.absensi.s }}</td>
                            <td :class="td">{{ row.absensi.i }}</td>
                            <td :class="td">{{ row.absensi.a }}</td>
                            <td :class="td">{{ row.naik === null ? '-' : row.naik === 1 ? 'Naik' : 'Tidak' }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="mapels.length + 10" :class="td" class="py-6 text-slate-500">
                                Belum ada siswa atau nilai akhir pada kelas ini.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="mt-2 text-[8px] text-slate-600 print:text-black">
                    Keterangan: nilai berwarna merah berada di bawah KKM mata pelajaran. S = sakit, I = izin, A = tanpa keterangan.
                    <span v-if="!mapels.length"> Belum ada nilai akhir mata pelajaran yang tersimpan.</span>
                </p>

                <div class="mt-5 flex justify-end text-[9px]">
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

    .leger-sheet table {
        font-size: 7px;
    }
}
</style>
