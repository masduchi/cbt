<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    ujian: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    pengawas: { type: Array, default: () => [] },
    kop: { type: Object, default: null },
    sekolah: { type: String, default: '' },
    kota: { type: String, default: '' },
    kepala_sekolah: { type: String, default: '' },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    tanggal: { type: String, default: '' },
});

const kopLines = computed(() =>
    ['header_1', 'header_2', 'header_3', 'header_4'].map((key) => props.kop?.[key]).filter(Boolean),
);
const hadir = computed(() => props.peserta.filter((row) => row.hadir).length);
const tidakHadir = computed(() => props.peserta.length - hadir.value);

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Berita Acara ${ujian.mapel || ''}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                href="/cbt/jadwal"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali
            </Link>
            <p class="text-sm text-slate-500">Berita acara <b class="text-slate-800">{{ ujian.mapel }}</b></p>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                @click="printPage"
            >
                <i class="fas fa-print h-4 w-4" ></i> Cetak / Simpan PDF
            </button>
        </div>

        <div class="mx-auto max-w-[210mm] p-4 print:p-0">
            <div class="print-sheet bg-white p-[15mm] text-[11px] leading-relaxed shadow-lg print:shadow-none">
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
                    <p class="text-sm font-semibold uppercase tracking-wide">Berita Acara Pelaksanaan Ujian</p>
                    <p class="text-[10px]">Tahun Pelajaran {{ tahun }} · Semester {{ semester }}</p>
                </header>

                <p class="mt-4">
                    Pada hari ini, {{ tanggal }}, telah dilaksanakan ujian
                    <b>{{ ujian.jenis || 'Ujian' }}</b> mata pelajaran
                    <b>{{ ujian.mapel || '-' }}</b> dengan rincian sebagai berikut.
                </p>

                <dl class="mt-3 grid gap-1 sm:grid-cols-[140px_1fr]">
                    <dt class="font-semibold">Nama ujian</dt>
                    <dd>{{ ujian.bank || '-' }} ({{ ujian.bank_kode || '-' }})</dd>
                    <dt class="font-semibold">Waktu</dt>
                    <dd>{{ ujian.tanggal }}, pukul {{ ujian.mulai }} s.d. {{ ujian.selesai }} ({{ ujian.durasi }} menit)</dd>
                    <dt class="font-semibold">Jumlah peserta</dt>
                    <dd>{{ peserta.length }} siswa</dd>
                    <dt class="font-semibold">Hadir</dt>
                    <dd>{{ hadir }} siswa</dd>
                    <dt class="font-semibold">Tidak hadir</dt>
                    <dd>{{ tidakHadir }} siswa</dd>
                </dl>

                <div class="mt-4">
                    <p class="font-semibold">Pengawas</p>
                    <table class="mt-1 w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="border border-black px-2 py-1 text-center font-semibold">No</th>
                                <th class="border border-black px-2 py-1 text-left font-semibold">Nama Pengawas</th>
                                <th class="border border-black px-2 py-1 text-center font-semibold">Ruang</th>
                                <th class="border border-black px-2 py-1 text-center font-semibold">Sesi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in pengawas" :key="index">
                                <td class="border border-black px-2 py-1 text-center">{{ index + 1 }}</td>
                                <td class="border border-black px-2 py-1">{{ row.nama }}</td>
                                <td class="border border-black px-2 py-1 text-center">{{ row.ruang || '-' }}</td>
                                <td class="border border-black px-2 py-1 text-center">{{ row.sesi || '-' }}</td>
                            </tr>
                            <tr v-if="!pengawas.length">
                                <td colspan="4" class="border border-black px-2 py-3 text-center text-slate-500">
                                    Pengawas belum ditetapkan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4">
                    Ujian berlangsung dengan tertib dan lancar. Demikian berita acara ini dibuat untuk dipergunakan
                    sebagaimana mestinya.
                </p>

                <div class="mt-8 flex justify-between text-[11px]">
                    <div class="text-center">
                        <p>Pengawas</p>
                        <div class="mt-16 w-52 border-b border-black" />
                    </div>
                    <div class="text-center">
                        <p>{{ kota || 'Kota' }}, {{ tanggal }}</p>
                        <p>Kepala Sekolah</p>
                        <p class="mt-14 font-semibold underline">{{ kepala_sekolah || '..............................' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
