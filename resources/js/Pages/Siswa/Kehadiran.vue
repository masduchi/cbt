<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass, formatTanggal } from '@/ui';

const props = defineProps({
    student: { type: Object, required: true },
    bulan: { type: String, default: '' },
    bulanLabel: { type: String, default: '' },
    prevBulan: { type: String, default: '' },
    nextBulan: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    today: { type: Object, default: () => ({ date: '', items: [] }) },
    totals: { type: Object, default: () => ({ hadir: 0, sebagian: 0, alpa: 0, libur: 0 }) },
    hariEfektif: { type: Number, default: 0 },
    periode: { type: String, default: '-' },
});

const title = 'Kehadiran';
const statusLabels = {
    hadir: 'Hadir',
    sebagian: 'Sebagian',
    alpa: 'Tidak hadir',
    libur: 'Libur',
};

const statusClasses = {
    hadir: 'border-success/30 bg-success/10 text-success',
    sebagian: 'border-warning/30 bg-warning/10 text-warning',
    alpa: 'border-destructive/30 bg-destructive/10 text-destructive',
    libur: 'border-border bg-muted/60 text-muted-foreground',
};

const firstWeekday = computed(() => {
    if (!props.days.length) return 0;
    const date = new Date(`${props.days[0].date}T00:00:00`);
    return (date.getDay() + 6) % 7;
});

const calendarCells = computed(() => {
    const cells = Array.from({ length: firstWeekday.value }, () => null);
    return cells.concat(props.days);
});

function goMonth(bulan) {
    router.get('/siswa/kehadiran', { bulan }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="title">
                    <template #description>Rekap kehadiran e-learning Anda pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.student.nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.student.kelas || 'Belum ada kelas' }}</p>
                </div>
            </div>

            <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Bulan sebelumnya" @click="goMonth(props.prevBulan)">
                        <i class="fas fa-chevron-left h-4 w-4" ></i>
                    </button>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-foreground">{{ props.bulanLabel }}</p>
                        <p class="text-xs text-muted-foreground">{{ props.hariEfektif }} hari efektif</p>
                    </div>
                    <button type="button" class="inline-flex items-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Bulan berikutnya" @click="goMonth(props.nextBulan)">
                        <i class="fas fa-chevron-right h-4 w-4" ></i>
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full border border-success/30 bg-success/10 px-2.5 py-1 font-medium text-success">{{ props.totals.hadir }} hadir</span>
                    <span class="rounded-full border border-warning/30 bg-warning/10 px-2.5 py-1 font-medium text-warning">{{ props.totals.sebagian }} sebagian</span>
                    <span class="rounded-full border border-destructive/30 bg-destructive/10 px-2.5 py-1 font-medium text-destructive">{{ props.totals.alpa }} tidak hadir</span>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex items-center justify-between border-b border-border bg-muted/60 px-4 py-3">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-foreground">
                        <i class="fas fa-calendar-alt h-4 w-4 text-primary" ></i> Kalender kehadiran
                    </p>
                    <span class="text-xs text-muted-foreground">{{ props.bulanLabel }}</span>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-7 gap-1 text-center text-2xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <span v-for="nama in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="nama">{{ nama }}</span>
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-1">
                        <template v-for="(cell, index) in calendarCells" :key="cell ? cell.date : `empty-${index}`">
                            <div v-if="!cell" class="min-h-[64px] rounded-lg bg-transparent" aria-hidden="true"></div>
                            <div
                                v-else
                                class="min-h-[64px] rounded-lg border p-1.5 text-left"
                                :class="[statusClasses[cell.status], cell.date === props.today.date ? 'ring-2 ring-ring/60' : '']"
                            >
                                <p class="text-xs font-semibold">{{ cell.day }}</p>
                                <template v-if="cell.status !== 'libur'">
                                    <p class="mt-0.5 text-2xs font-medium">{{ statusLabels[cell.status] }}</p>
                                    <p class="text-2xs opacity-80">{{ cell.selesai }}/{{ cell.total }} selesai</p>
                                </template>
                                <span class="sr-only">{{ statusLabels[cell.status] }}, {{ cell.selesai }} dari {{ cell.total }} aktivitas selesai</span>
                            </div>
                        </template>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">Status dihitung dari aktivitas materi dan tugas yang dibuka atau diselesaikan pada hari tersebut.</p>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex items-center justify-between border-b border-border bg-muted/60 px-4 py-3">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-foreground">
                        <i class="fas fa-clock h-4 w-4 text-primary" ></i> Hari ini
                    </p>
                    <span class="text-xs text-muted-foreground">{{ formatTanggal(props.today.date) }}</span>
                </div>
                <div v-if="props.today.items.length" class="divide-y divide-border">
                    <div v-for="item in props.today.items" :key="item.id_kjm" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <span class="text-2xs font-medium uppercase">Jam</span>
                            <span class="text-sm font-bold">{{ item.jam ?? '-' }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ item.judul || '-' }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.mapel || '-' }} · {{ item.jenis === 2 ? 'Tugas' : 'Materi' }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium" :class="item.status === 'selesai' ? statusClasses.hadir : (item.status === 'dibuka' ? statusClasses.sebagian : statusClasses.alpa)">
                            <i :class="'fas fa-' + (item.status === 'selesai' ? 'clipboard-check' : (item.status === 'dibuka' ? 'user-check' : 'user-slash'))" class="h-3 w-3" ></i>
                            {{ item.status === 'selesai' ? 'Selesai' : (item.status === 'dibuka' ? 'Dibuka' : 'Belum dibuka') }}
                        </span>
                    </div>
                </div>
                <div v-else class="px-4 py-8 text-center">
                    <i class="fas fa-calendar-alt mx-auto mb-2 h-5 w-5 text-muted-foreground/50" ></i>
                    <p class="text-sm text-muted-foreground">Tidak ada jadwal materi atau tugas hari ini.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
