<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { inputClass, labelClass } from '@/ui';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: null },
    mapels: { type: Array, default: () => [] },
    mapelId: { type: Number, default: null },
    items: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});

function applyFilter(event) {
    const field = event.target.id;
    const kelas = field === 'kelas' ? event.target.value : props.kelasId;
    const mapel = field === 'mapel' ? event.target.value : (props.mapelId ?? '');

    router.get('/elearning/rekap-nilai', { kelas, mapel }, { preserveScroll: true });
}

function show(value, suffix = '') {
    return value === null || value === undefined ? '-' : `${value}${suffix}`;
}
</script>

<template>
    <Head title="Rekap Nilai E-learning" />
    <AdminLayout title="Rekap Nilai E-learning">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Rekap Nilai">
                    <template #description>Rata-rata nilai materi dan tugas per siswa. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <div v-if="!props.classes.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-file-excel mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada kelas pada periode ini.</p>
            </div>

            <template v-else>
                <div class="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-card sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="kelas">Kelas</label>
                        <select id="kelas" :class="inputClass" :value="props.kelasId ?? ''" @change="applyFilter">
                            <option v-for="item in props.classes" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="mapel">Mata pelajaran</label>
                        <select id="mapel" :class="inputClass" :value="props.mapelId ?? ''" @change="applyFilter">
                            <option value="">Pilih mata pelajaran</option>
                            <option v-for="item in props.mapels" :key="item.id_mapel" :value="item.id_mapel">{{ item.nama_mapel }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Siswa</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.siswa ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Item dinilai</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.item ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Rata-rata kelas</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ show(props.summary.rata) }}</p>
                    </div>
                </div>

                <div v-if="!props.mapelId" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                    <p class="text-sm font-medium text-foreground">Pilih mata pelajaran untuk menampilkan rekap nilai.</p>
                </div>

                <div v-else-if="!props.items.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                    <p class="text-sm font-medium text-foreground">Belum ada materi atau tugas untuk mata pelajaran ini.</p>
                    <p class="mt-1 text-sm text-muted-foreground">Jadwalkan materi pada menu Jadwal Materi, lalu kembali ke halaman ini.</p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <caption class="sr-only">Rekap nilai e-learning siswa per item</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="sticky left-0 bg-muted/60 px-4 py-3 text-left">Siswa</th>
                                <th v-for="item in props.items" :key="item.id_kjm" scope="col" class="px-2 py-3 text-center">
                                    <span class="block text-foreground">{{ item.jenis === 2 ? 'Tugas' : 'Materi' }} J{{ item.jam_ke }}</span>
                                    <span class="block max-w-28 truncate font-normal normal-case text-muted-foreground" :title="item.judul">{{ item.judul }}</span>
                                </th>
                                <th scope="col" class="px-3 py-3 text-center">Materi</th>
                                <th scope="col" class="px-3 py-3 text-center">Tugas</th>
                                <th scope="col" class="px-3 py-3 text-center">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <th scope="row" class="sticky left-0 bg-card px-4 py-3 text-left font-medium text-foreground">
                                    {{ row.nama }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ row.nis }}</span>
                                </th>
                                <td v-for="item in props.items" :key="item.id_kjm" class="px-2 py-3 text-center text-foreground">
                                    {{ show(row.nilai[item.id_kjm]) }}
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-foreground">{{ show(row.rata_materi) }}</td>
                                <td class="px-3 py-3 text-center font-medium text-foreground">{{ show(row.rata_tugas) }}</td>
                                <td class="px-3 py-3 text-center font-semibold text-foreground">{{ show(row.rata) }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="props.items.length + 4" class="px-4 py-10 text-center text-muted-foreground">
                                    Belum ada siswa pada kelas ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
