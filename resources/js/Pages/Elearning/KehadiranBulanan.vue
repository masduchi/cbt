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
    bulan: { type: String, default: '' },
    bulanLabel: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});

function applyFilter(event) {
    const field = event.target.id;
    const kelas = field === 'kelas' ? event.target.value : props.kelasId;
    const mapel = field === 'mapel' ? event.target.value : (props.mapelId ?? '');
    const bulan = field === 'bulan' ? event.target.value : props.bulan;

    router.get('/elearning/kehadiran-bulanan', { kelas, mapel, bulan }, { preserveScroll: true });
}

const cellText = { hadir: 'H', sebagian: 'S', alpa: 'A', libur: '-' };
const cellClass = {
    hadir: 'bg-success/15 text-success',
    sebagian: 'bg-warning/15 text-warning',
    alpa: 'bg-destructive/15 text-destructive',
    libur: 'bg-muted text-muted-foreground',
};
const cellLabel = { hadir: 'Hadir', sebagian: 'Sebagian', alpa: 'Alpa', libur: 'Tanpa jadwal' };
</script>

<template>
    <Head title="Kehadiran Bulanan" />
    <AdminLayout title="Kehadiran Bulanan">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Kehadiran Bulanan">
                    <template #description>Rekap kehadiran {{ props.bulanLabel }} per hari. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <div v-if="!props.classes.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-calendar-alt mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada kelas pada periode ini.</p>
            </div>

            <template v-else>
                <div class="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-card sm:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="kelas">Kelas</label>
                        <select id="kelas" :class="inputClass" :value="props.kelasId ?? ''" @change="applyFilter">
                            <option v-for="item in props.classes" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="mapel">Mata pelajaran</label>
                        <select id="mapel" :class="inputClass" :value="props.mapelId ?? ''" @change="applyFilter">
                            <option value="">Semua mapel</option>
                            <option v-for="item in props.mapels" :key="item.id_mapel" :value="item.id_mapel">{{ item.nama_mapel }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="bulan">Bulan</label>
                        <input id="bulan" :class="inputClass" type="month" :value="props.bulan" @change="applyFilter" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 items-center justify-center rounded bg-success/15 font-semibold text-success">H</span> Hadir penuh</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 items-center justify-center rounded bg-warning/15 font-semibold text-warning">S</span> Sebagian</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 items-center justify-center rounded bg-destructive/15 font-semibold text-destructive">A</span> Alpa</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 items-center justify-center rounded bg-muted font-semibold text-muted-foreground">-</span> Tanpa jadwal</span>
                    <span class="ml-auto">{{ props.summary.siswa ?? 0 }} siswa · {{ props.summary.hariEfektif ?? 0 }} hari efektif</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <caption class="sr-only">Kehadiran bulanan siswa</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="sticky left-0 bg-muted/60 px-4 py-3 text-left">Siswa</th>
                                <th v-for="day in props.days" :key="day.date" scope="col" class="px-1 py-2 text-center">
                                    <span class="block">{{ day.day }}</span>
                                    <span class="block text-2xs font-normal normal-case text-muted-foreground">{{ day.weekday }}</span>
                                </th>
                                <th scope="col" class="px-3 py-3 text-center">H</th>
                                <th scope="col" class="px-3 py-3 text-center">S</th>
                                <th scope="col" class="px-3 py-3 text-center">A</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <th scope="row" class="sticky left-0 bg-card px-4 py-3 text-left font-medium text-foreground">
                                    {{ row.nama }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ row.nis }}</span>
                                </th>
                                <td v-for="day in props.days" :key="day.date" class="px-1 py-2 text-center">
                                    <span
                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-xs font-semibold"
                                        :class="cellClass[row.cells[day.date]?.status ?? 'libur']"
                                        :title="`${day.date}: ${cellLabel[row.cells[day.date]?.status ?? 'libur']}${row.cells[day.date]?.total ? ` (${row.cells[day.date].selesai}/${row.cells[day.date].total} selesai)` : ''}`"
                                    >
                                        {{ cellText[row.cells[day.date]?.status ?? 'libur'] }}
                                    </span>
                                    <span class="sr-only">{{ day.date }} {{ cellLabel[row.cells[day.date]?.status ?? 'libur'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-success">{{ row.totals.hadir }}</td>
                                <td class="px-3 py-3 text-center font-medium text-warning">{{ row.totals.sebagian }}</td>
                                <td class="px-3 py-3 text-center font-medium text-destructive">{{ row.totals.alpa }}</td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td :colspan="props.days.length + 4" class="px-4 py-10 text-center text-muted-foreground">
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
