<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';

const props = defineProps({
    jenis: { type: Number, required: true },
    days: { type: Array, default: () => [] },
    student: { type: Object, required: true },
    weekStart: { type: String, default: '' },
    weekLabel: { type: String, default: '' },
    prevWeek: { type: String, default: '' },
    nextWeek: { type: String, default: '' },
    summary: { type: Object, default: () => ({ total: 0, selesai: 0 }) },
    periode: { type: String, default: '' },
});

const title = computed(() => (props.jenis === 1 ? 'Materi Belajar' : 'Tugas'));
const basePath = computed(() => (props.jenis === 1 ? '/siswa/elearning/materi' : '/siswa/elearning/tugas'));

function goWeek(week) {
    router.get(basePath.value, { week }, { preserveState: true, preserveScroll: true });
}

function statusOf(item) {
    if (item.nilai !== null && item.nilai !== undefined && String(item.nilai) !== '' && String(item.nilai) !== '0') {
        return { label: `Dinilai ${item.nilai}`, class: 'border-success/30 bg-success/10 text-success' };
    }
    if (item.selesai) {
        return { label: 'Selesai', class: 'border-success/30 bg-success/10 text-success' };
    }

    return { label: 'Belum dikerjakan', class: 'border-border bg-muted text-muted-foreground' };
}
</script>

<template>
    <Head :title="title" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="title">
                    <template #description>Jadwal {{ jenis === 1 ? 'materi' : 'tugas' }} kelas Anda pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.student.nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.student.kelas || 'Belum ada kelas' }}</p>
                </div>
            </div>

            <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 sm:h-9 sm:w-9" aria-label="Minggu sebelumnya" @click="goWeek(props.prevWeek)">
                        <i class="fas fa-chevron-left h-4 w-4" aria-hidden="true" ></i>
                    </button>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-foreground">{{ props.weekLabel }}</p>
                        <p class="text-xs text-muted-foreground">Minggu berjalan</p>
                    </div>
                    <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 sm:h-9 sm:w-9" aria-label="Minggu berikutnya" @click="goWeek(props.nextWeek)">
                        <i class="fas fa-chevron-right h-4 w-4" aria-hidden="true" ></i>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded-full border border-success/30 bg-success/10 px-2.5 py-1 font-medium text-success">{{ props.summary.selesai }} selesai</span>
                    <span class="rounded-full border border-border bg-muted px-2.5 py-1 font-medium text-muted-foreground">{{ props.summary.total }} total</span>
                </div>
            </div>

            <div class="space-y-4">
                <div v-for="day in props.days" :key="day.date" :class="cardClass" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border bg-muted/60 px-4 py-2.5">
                        <p class="text-sm font-semibold text-foreground">{{ day.label }}</p>
                        <span class="text-xs text-muted-foreground">{{ day.items.length }} item</span>
                    </div>
                    <div v-if="day.items.length" class="divide-y divide-border">
                        <Link
                            v-for="item in day.items"
                            :key="item.id_kjm"
                            :href="`${basePath}/${item.id_kjm}`"
                            class="flex flex-wrap items-center gap-3 px-4 py-3 transition hover:bg-muted/40"
                        >
                            <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <span class="text-2xs font-medium uppercase">Jam</span>
                                <span class="text-sm font-bold">{{ item.jam ?? '-' }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ item.judul }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-book-open h-3 w-3" ></i> {{ item.mapel || '-' }}</span>
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-user h-3 w-3" ></i> {{ item.guru || '-' }}</span>
                                    <span class="font-mono">{{ item.kode }}</span>
                                </p>
                                <p v-if="item.tenggat" class="mt-0.5 inline-flex items-center gap-1 text-xs" :class="item.late ? 'font-medium text-warning' : 'text-muted-foreground'">
                                    <i class="fas fa-clock h-3 w-3" ></i> Tenggat {{ item.tenggat }}
                                </p>
                            </div>
                            <span v-if="item.late" class="rounded-full border border-warning/30 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning">Terlambat</span>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium" :class="statusOf(item).class">{{ statusOf(item).label }}</span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-primary">
                                Buka <i class="fas fa-chevron-right h-3.5 w-3.5" ></i>
                            </span>
                        </Link>
                    </div>
                    <div v-else class="px-4 py-6 text-center text-xs text-muted-foreground">
                        <i class="fas fa-clock mx-auto mb-1 h-5 w-5 text-muted-foreground/50" ></i>
                        Tidak ada {{ jenis === 1 ? 'materi' : 'tugas' }} pada hari ini.
                    </div>
                </div>
            </div>

            <div :class="cardClass" class="p-4 text-xs text-muted-foreground">
                <p class="flex items-center gap-2 font-medium text-foreground">
                    <i :class="'fas fa-' + (jenis === 1 ? 'check-circle' : 'clipboard-list')" class="h-4 w-4 text-primary" ></i>
                    {{ jenis === 1 ? 'Baca materi lalu tandai selesai agar guru tahu Anda sudah mempelajarinya.' : 'Kumpulkan tugas sebelum batas waktu yang ditentukan guru.' }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
