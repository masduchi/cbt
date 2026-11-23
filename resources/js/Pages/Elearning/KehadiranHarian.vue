<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { inputClass, labelClass } from '@/ui';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: null },
    tanggal: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});

function applyFilter(event) {
    const field = event.target.id;
    const kelas = field === 'kelas' ? event.target.value : props.kelasId;
    const tanggal = field === 'tanggal' ? event.target.value : props.tanggal;

    router.get('/elearning/kehadiran-harian', { kelas, tanggal }, { preserveScroll: true });
}

const cellLabel = { selesai: 'Selesai', dibuka: 'Dibuka', kosong: 'Belum' };
const cellClass = {
    selesai: 'bg-success/10 text-success',
    dibuka: 'bg-warning/10 text-warning',
    kosong: 'bg-muted text-muted-foreground',
};
const badge = {
    hadir: 'bg-success/10 text-success',
    sebagian: 'bg-warning/10 text-warning',
    alpa: 'bg-destructive/10 text-destructive',
    libur: 'bg-muted text-muted-foreground',
};
const badgeLabel = { hadir: 'Hadir', sebagian: 'Sebagian', alpa: 'Alpa', libur: 'Tanpa jadwal' };
</script>

<template>
    <Head title="Kehadiran Harian" />
    <AdminLayout title="Kehadiran Harian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Kehadiran Harian">
                    <template #description>Pantau partisipasi siswa pada materi dan tugas yang terjadwal. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <div v-if="!props.classes.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-user-check mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
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
                        <label :class="labelClass" for="tanggal">Tanggal</label>
                        <input id="tanggal" :class="inputClass" type="date" :value="props.tanggal" @change="applyFilter" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Siswa</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.siswa ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Hadir penuh</p>
                        <p class="mt-1 text-xl font-semibold text-success">{{ props.summary.hadir ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Sebagian</p>
                        <p class="mt-1 text-xl font-semibold text-warning">{{ props.summary.sebagian ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Alpa</p>
                        <p class="mt-1 text-xl font-semibold text-destructive">{{ props.summary.alpa ?? 0 }}</p>
                    </div>
                </div>

                <div v-if="!props.items.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                    <p class="text-sm font-medium text-foreground">Tidak ada materi atau tugas terjadwal pada tanggal ini.</p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <caption class="sr-only">Kehadiran harian siswa</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="sticky left-0 bg-muted/60 px-4 py-3">Siswa</th>
                                <th v-for="item in props.items" :key="item.id_kjm" scope="col" class="px-3 py-3">
                                    <span class="block text-foreground">Jam {{ item.jam_ke }} · {{ item.jenis === 2 ? 'Tugas' : 'Materi' }}</span>
                                    <span class="block max-w-40 truncate font-normal normal-case text-muted-foreground">{{ item.judul }}</span>
                                </th>
                                <th scope="col" class="px-4 py-3">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <th scope="row" class="sticky left-0 bg-card px-4 py-3 text-left font-medium text-foreground">
                                    {{ row.nama }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ row.nis }}</span>
                                </th>
                                <td v-for="item in props.items" :key="item.id_kjm" class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="cellClass[row.cells[item.id_kjm]]">
                                        {{ cellLabel[row.cells[item.id_kjm]] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="badge[row.status]">{{ badgeLabel[row.status] }}</span>
                                    <span class="mt-1 block text-xs text-muted-foreground">{{ row.selesai }} dari {{ props.items.length }} selesai</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
