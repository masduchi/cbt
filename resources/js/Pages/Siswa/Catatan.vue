<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';

const props = defineProps({
    student: { type: Object, required: true },
    notes: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, belum: 0, mapel: 0, wali: 0 }) },
    periode: { type: String, default: '-' },
});

const title = 'Catatan Guru';
const filter = ref('semua');
const processing = ref(null);

const filters = [
    { key: 'semua', label: 'Semua' },
    { key: 'belum', label: 'Belum dibaca' },
    { key: 'mapel', label: 'Mata pelajaran' },
    { key: 'wali', label: 'Wali kelas' },
];

const filtered = computed(() => {
    if (filter.value === 'belum') return props.notes.filter((note) => !note.read);
    if (filter.value === 'mapel' || filter.value === 'wali') return props.notes.filter((note) => note.table === filter.value);

    return props.notes;
});

const levelClass = {
    Saran: 'border-info/30 bg-info/10 text-info',
    Teguran: 'border-warning/30 bg-warning/10 text-warning',
    Peringatan: 'border-orange-200 bg-orange-50 text-orange-700',
    Sangsi: 'border-destructive/30 bg-destructive/10 text-destructive',
};

function markRead(note) {
    processing.value = `${note.table}-${note.id_catatan}`;
    router.post(`/siswa/catatan/${note.table}/${note.id_catatan}`, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = null;
        },
    });
}
</script>

<template>
    <Head :title="title" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="title">
                    <template #description>Catatan dari guru mata pelajaran dan wali kelas, periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.student.nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.student.kelas || 'Belum ada kelas' }}</p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total catatan</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ props.summary.total }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Belum dibaca</p>
                    <p class="mt-1 text-2xl font-semibold" :class="props.summary.belum ? 'text-destructive' : 'text-foreground'">{{ props.summary.belum }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Dari</p>
                    <p class="mt-1 text-sm text-foreground">{{ props.summary.mapel }} catatan mapel · {{ props.summary.wali }} catatan wali</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" role="group" aria-label="Saring catatan">
                <button
                    v-for="item in filters"
                    :key="item.key"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="filter === item.key ? 'border-primary/30 bg-primary/10 text-primary' : 'border-border bg-card text-muted-foreground hover:bg-muted/40'"
                    :aria-pressed="filter === item.key"
                    @click="filter = item.key"
                >
                    {{ item.label }}
                </button>
            </div>

            <div v-if="filtered.length" class="space-y-3">
                <article v-for="note in filtered" :key="`${note.table}-${note.id_catatan}`" :class="cardClass" class="overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-muted/60 px-4 py-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-foreground">
                                <i :class="'fas fa-' + (note.table === 'mapel' ? 'book-open' : 'shield-alt')" class="h-4 w-4 text-primary" ></i>
                                {{ note.source }}
                                <span class="rounded-full border px-2 py-0.5 text-2xs font-medium" :class="levelClass[note.level] || levelClass.Saran">{{ note.level }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ note.guru || (note.table === 'wali' ? 'Wali kelas' : 'Guru mapel') }} · {{ note.tanggal }}
                                <span v-if="note.type === 1"> · untuk seluruh kelas</span>
                            </p>
                        </div>
                        <span v-if="note.read" class="inline-flex items-center gap-1 rounded-full border border-success/30 bg-success/10 px-2.5 py-1 text-xs font-medium text-success">
                            <i class="fas fa-check-double h-3 w-3" ></i> Sudah dibaca
                        </span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted/40 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :disabled="processing === `${note.table}-${note.id_catatan}`"
                            @click="markRead(note)"
                        >
                            <i class="fas fa-check h-3 w-3" ></i> Tandai dibaca
                        </button>
                    </div>
                    <p class="whitespace-pre-line px-4 py-3 text-sm leading-relaxed text-foreground">{{ note.text }}</p>
                </article>
            </div>

            <div v-else :class="cardClass" class="px-4 py-10 text-center">
                <i class="fas fa-comment-dots mx-auto mb-2 h-6 w-6 text-muted-foreground/50" ></i>
                <p class="text-sm font-medium text-muted-foreground">Belum ada catatan.</p>
                <p class="mt-1 text-xs text-muted-foreground">Catatan dari guru atau wali kelas akan tampil di sini.</p>
            </div>
        </div>
    </AdminLayout>
</template>
