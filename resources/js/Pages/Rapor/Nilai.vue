<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass, inputClass } from '@/ui';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    kelasOptions: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
});

const search = ref('');
const kelasFilter = ref('');

const filtered = computed(() =>
    props.rows.filter((row) => {
        const matchSearch = !search.value || row.nama_mapel.toLowerCase().includes(search.value.toLowerCase()) || row.guru.toLowerCase().includes(search.value.toLowerCase());
        const matchKelas = !kelasFilter.value || row.id_kelas === Number(kelasFilter.value);
        return matchSearch && matchKelas;
    }),
);

function badgeClass(count) {
    return count > 0 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Input Nilai Rapor" />
    <AdminLayout title="Input Nilai Rapor">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Input Nilai Rapor">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }}. Pilih mata pelajaran dan kelas untuk mengisi nilai.</template>
                
                </PageHeader></div>

            <div :class="cardClass" class="flex flex-wrap items-end gap-3 p-4">
                <div class="relative">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="inputClass" class="max-w-72 pl-9" placeholder="Cari mapel atau guru…" aria-label="Cari mata pelajaran atau guru" />
                </div>
                <select v-model="kelasFilter" :class="inputClass" class="max-w-48" aria-label="Filter kelas">
                    <option value="">Semua kelas</option>
                    <option v-for="kelas in kelasOptions" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                </select>
                <span class="ml-auto text-xs text-muted-foreground">{{ filtered.length }} mata pelajaran</span>
            </div>

            <div v-if="filtered.length" :class="cardClass" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[900px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Mata Pelajaran</th>
                                <th class="px-3 py-3">Kelas</th>
                                <th class="px-3 py-3">Guru Pengampu</th>
                                <th class="px-3 py-3 text-center">Harian</th>
                                <th class="px-3 py-3 text-center">PTS</th>
                                <th class="px-3 py-3 text-center">PAS</th>
                                <th class="px-4 py-3 text-right">Input Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in filtered" :key="`${row.id_mapel}-${row.id_kelas}`" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-book-open h-4 w-4 text-primary" ></i>
                                        <span class="font-medium text-foreground">{{ row.nama_mapel }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">{{ row.nama_kelas }}</td>
                                <td class="px-3 py-3 text-muted-foreground">{{ row.guru || '-' }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-1 text-2xs font-medium" :class="badgeClass(row.harian)">{{ row.harian }} siswa</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-1 text-2xs font-medium" :class="badgeClass(row.pts)">{{ row.pts }} siswa</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-1 text-2xs font-medium" :class="badgeClass(row.pas)">{{ row.pas }} siswa</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link :href="`/rapor/nilai/harian/${row.id_mapel}/${row.id_kelas}`" :class="btnSecondary">
                                            <i class="fas fa-pen h-3.5 w-3.5" ></i> Harian
                                        </Link>
                                        <Link :href="`/rapor/nilai/pts/${row.id_mapel}/${row.id_kelas}`" :class="btnSecondary">
                                            <i class="fas fa-clipboard-check h-3.5 w-3.5" ></i> PTS
                                        </Link>
                                        <Link :href="`/rapor/nilai/pas/${row.id_mapel}/${row.id_kelas}`" :class="btnSecondary">
                                            <i class="fas fa-file-excel h-3.5 w-3.5" ></i> PAS
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else :class="cardClass" class="px-6 py-14 text-center">
                <i class="fas fa-book-open mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada mata pelajaran.</p>
                <p class="mt-1 text-sm text-muted-foreground">Administrator perlu mengatur guru pengampu terlebih dahulu.</p>
            </div>
        </div>
    </AdminLayout>
</template>
