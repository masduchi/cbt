<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    ujian: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    kop: { type: Object, default: null },
    sekolah: { type: String, default: '' },
    kota: { type: String, default: '' },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
});

const kopLines = computed(() =>
    ['header_1', 'header_2', 'header_3', 'header_4'].map((key) => props.kop?.[key]).filter(Boolean),
);

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Daftar Hadir ${ujian.mapel || ''}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                href="/cbt/jadwal"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali
            </Link>
            <p class="text-sm text-slate-500">
                Daftar hadir <b class="text-slate-800">{{ ujian.mapel }}</b> · {{ peserta.length }} peserta
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                @click="printPage"
            >
                <i class="fas fa-print h-4 w-4" ></i> Cetak / Simpan PDF
            </button>
        </div>

        <div class="mx-auto max-w-[297mm] p-4 print:p-0">
            <div class="print-sheet bg-white p-[10mm] text-[10px] shadow-lg print:shadow-none">
                <header class="border-b-[3px] border-double border-black pb-2 text-center">
                    <template v-if="kopLines.length">
                        <p
                            v-for="(line, index) in kopLines"
                            :key="index"
                            :class="index === 0 ? 'text-base font-bold uppercase' : 'text-[11px] leading-snug'"
                        >
                            {{ line }}
                        </p>
                    </template>
                    <h1 v-else class="text-base font-bold uppercase">{{ sekolah || 'Nama Sekolah' }}</h1>
                    <p class="text-sm font-semibold uppercase tracking-wide">Daftar Hadir Peserta Ujian</p>
                    <p class="mt-1 text-[10px]">
                        {{ ujian.jenis || 'Ujian' }} · {{ ujian.mapel || '-' }} · {{ tahun }} (Semester {{ semester }})
                    </p>
                    <p class="text-[10px]">Tanggal {{ ujian.tanggal }} · Pukul {{ ujian.mulai }} s.d. {{ ujian.selesai }}</p>
                </header>

                <table class="mt-3 w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-black px-2 py-1 text-center font-semibold">No</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">No. Peserta</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">NIS</th>
                            <th class="border border-black px-2 py-1 text-left font-semibold">Nama Siswa</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">Kelas</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">Ruang</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">Sesi</th>
                            <th class="border border-black px-2 py-1 text-center font-semibold">Tanda Tangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in peserta" :key="row.id_siswa">
                            <td class="border border-black px-2 py-1 text-center">{{ index + 1 }}</td>
                            <td class="border border-black px-2 py-1 text-center font-mono">{{ row.nomor_peserta || '-' }}</td>
                            <td class="border border-black px-2 py-1 text-center">{{ row.nis || '-' }}</td>
                            <td class="border border-black px-2 py-1">{{ row.nama }}</td>
                            <td class="border border-black px-2 py-1 text-center">{{ row.kelas }}</td>
                            <td class="border border-black px-2 py-1 text-center">{{ row.ruang || '-' }}</td>
                            <td class="border border-black px-2 py-1 text-center">{{ row.sesi || '-' }}</td>
                            <td class="h-8 border border-black px-2 py-1"></td>
                        </tr>
                        <tr v-if="!peserta.length">
                            <td colspan="8" class="border border-black px-2 py-6 text-center text-slate-500">
                                Belum ada peserta pada jadwal ini.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-8 flex justify-between text-[10px]">
                    <div class="text-center">
                        <p>Pengawas</p>
                        <p class="mt-1 font-semibold">{{ props.kop?.pengawas_1 || '..............................' }}</p>
                        <div class="mt-10 w-48 border-b border-black" />
                        <p v-if="props.kop?.pengawas_2" class="mt-6">Pengawas 2</p>
                        <p v-if="props.kop?.pengawas_2" class="mt-1 font-semibold">{{ props.kop.pengawas_2 }}</p>
                        <div v-if="props.kop?.pengawas_2" class="mt-10 w-48 border-b border-black" />
                    </div>
                    <div class="text-center">
                        <p>{{ kota || 'Kota' }}, {{ ujian.tanggal }}</p>
                        <p>Kepala Sekolah</p>
                        <div class="mt-14 w-48 border-b border-black" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
