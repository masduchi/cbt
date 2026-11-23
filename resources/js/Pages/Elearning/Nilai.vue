<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    item: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, submitted: 0, late: 0, graded: 0 }) },
});

const onlySubmitted = ref(false);
const search = ref('');

const form = useForm({
    rows: props.rows.map((row) => ({
        id_siswa: row.id_siswa,
        nilai: row.nilai ?? '',
        catatan: row.catatan ?? '',
    })),
});

const visibleRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        if (onlySubmitted.value && !row.submitted) return false;
        if (!term) return true;

        return row.nama.toLowerCase().includes(term) || String(row.nis).toLowerCase().includes(term);
    });
});

function valueFor(idSiswa) {
    return form.rows.find((row) => row.id_siswa === idSiswa);
}

function clamp(row) {
    if (row.nilai === '' || row.nilai === null) return;
    const num = Number(row.nilai);
    if (Number.isNaN(num)) {
        row.nilai = '';
        return;
    }
    row.nilai = Math.max(0, Math.min(100, Math.round(num)));
}

function submit() {
    form.post(`/elearning/tugas/${props.item.id_materi}/nilai`, {
        preserveScroll: true,
        onSuccess: () => form.defaults({ rows: form.rows }),
    });
}

const summaryCards = computed(() => [
    { label: 'Total siswa', value: props.summary.total, icon: 'users', tone: 'text-muted-foreground bg-muted' },
    { label: 'Mengumpulkan', value: props.summary.submitted, icon: 'paper-plane', tone: 'text-primary bg-primary/10' },
    { label: 'Terlambat', value: props.summary.late, icon: 'clock', tone: 'text-warning bg-warning/10' },
    { label: 'Sudah dinilai', value: props.summary.graded, icon: 'check-double', tone: 'text-success bg-success/10' },
]);
</script>

<template>
    <Head :title="`Nilai ${props.item.judul}`" />
    <AdminLayout :title="`Nilai Tugas`">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="props.item.judul" :back="{ href: '/elearning/tugas', label: 'Kembali ke daftar tugas' }">
                    <template #description>
                        {{ props.item.mapel ?? 'Tanpa mapel' }}
                        <span v-if="props.item.guru"> / {{ props.item.guru }}</span>
                        <span v-if="props.item.kelas.length"> / {{ props.item.kelas.join(', ') }}</span>
                        <span v-if="props.item.tenggat"> · Tenggat: {{ props.item.tenggat }}</span>
                    </template>
                </PageHeader>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="card in summaryCards" :key="card.label" :class="[cardClass, 'flex items-center gap-3 p-4']">
                    <span :class="['flex h-10 w-10 items-center justify-center rounded-lg', card.tone]">
                        <i :class="'fas fa-' + (card.icon)" class="h-5 w-5" ></i>
                    </span>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                        <p class="text-xl font-semibold text-foreground">{{ card.value }}</p>
                    </div>
                </div>
            </div>

            <div :class="[cardClass, 'p-4']">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="flex-1">
                        <label :class="labelClass" for="cari-siswa">Cari siswa</label>
                        <input id="cari-siswa" v-model="search" type="search" :class="inputClass" placeholder="Nama atau NIS" aria-label="Nama atau NIS" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-foreground">
                        <input v-model="onlySubmitted" type="checkbox" class="rounded border-border" />
                        Hanya yang sudah mengumpulkan
                    </label>
                </div>

                <p v-if="props.rows.length === 0" class="mt-6 rounded-lg border border-dashed border-border bg-muted/60 p-8 text-center text-sm text-muted-foreground">
                    Belum ada siswa pada kelas tugas ini.
                </p>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-3 py-2">Siswa</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Jawaban</th>
                                <th class="w-28 px-3 py-2">Nilai</th>
                                <th class="w-64 px-3 py-2">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in visibleRows" :key="row.id_siswa" class="align-top">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }} / {{ row.kelas }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span
                                            v-if="!row.submitted"
                                            class="inline-flex w-fit items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            Belum mengumpulkan
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex w-fit items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success"
                                        >
                                            <i class="fas fa-check-double h-3 w-3" ></i>
                                            Terkumpul
                                        </span>
                                        <span v-if="row.late" class="inline-flex w-fit items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
                                            <i class="fas fa-clock h-3 w-3" ></i>
                                            Terlambat
                                        </span>
                                        <span v-if="row.graded" class="inline-flex w-fit items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                            Sudah dinilai
                                        </span>
                                    </div>
                                    <p v-if="row.finish_time" class="mt-1 text-xs text-muted-foreground">Kumpul: {{ row.finish_time }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p v-if="row.text" class="max-w-xs whitespace-pre-line break-words text-foreground">{{ row.text }}</p>
                                    <ul v-if="row.files.length" class="mt-1 space-y-1">
                                        <li v-for="file in row.files" :key="file.src">
                                            <a
                                                :href="`/storage/${file.src}`"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary"
                                            >
                                                <i class="fas fa-file-alt h-3 w-3" ></i>
                                                {{ file.name ?? 'Lampiran' }}
                                            </a>
                                        </li>
                                    </ul>
                                    <p v-if="!row.text && !row.files.length" class="text-xs text-muted-foreground">Tidak ada jawaban</p>
                                </td>
                                <td class="px-3 py-3">
                                    <label :for="`nilai-${row.id_siswa}`" class="sr-only">Nilai {{ row.nama }}</label>
                                    <input
 :id="`nilai-${row.id_siswa}`"
 v-model="valueFor(row.id_siswa).nilai"
 type="number"
 min="0"
 max="100"
 :class="inputClass"
 :disabled="!row.submitted"
 @blur="clamp(valueFor(row.id_siswa))"
 />
                                    <p v-if="form.errors[`rows.${form.rows.findIndex((r) => r.id_siswa === row.id_siswa)}.nilai`]" class="mt-1 text-xs text-destructive">
                                        Nilai harus angka 0 sampai 100.
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    <label :for="`catatan-${row.id_siswa}`" class="sr-only">Catatan untuk {{ row.nama }}</label>
                                    <textarea
 :id="`catatan-${row.id_siswa}`"
 v-model="valueFor(row.id_siswa).catatan"
 rows="2"
 :class="inputClass"
 :disabled="!row.submitted"
 placeholder="Umpan balik untuk siswa"
 ></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="visibleRows.length === 0 && props.rows.length" class="mt-4 rounded-lg bg-muted/60 p-4 text-center text-sm text-muted-foreground">
                    Tidak ada siswa yang cocok dengan filter.
                </p>

                <div class="mt-5 flex justify-end">
                    <button type="button" :class="btnPrimary" :disabled="form.processing || props.rows.length === 0" @click="submit">
                        <i class="fas fa-check-double h-4 w-4" ></i>
                        {{ form.processing ? 'Menyimpan…' : 'Simpan penilaian' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
