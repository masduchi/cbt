<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    jenis: { type: Number, required: true },
    item: { type: Object, required: true },
    log: { type: Object, required: true },
    backUrl: { type: String, required: true },
});

const title = computed(() => (props.jenis === 1 ? 'Materi' : 'Tugas'));
const submitPath = computed(() => `/siswa/elearning/${props.jenis === 1 ? 'materi' : 'tugas'}/${props.item.id_kjm}/selesai`);
const fileInput = ref(null);

const form = useForm({
    text: props.log.text ?? '',
    files: [],
});

function pickFiles(event) {
    form.files = Array.from(event.target.files ?? []);
}

function submit() {
    form.post(submitPath.value, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            if (fileInput.value) fileInput.value.value = '';
            form.files = [];
        },
    });
}
</script>

<template>
    <Head :title="`${title} · ${props.item.judul}`" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="props.backUrl" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-primary">
                    <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali ke daftar {{ title.toLowerCase() }}
                </Link>
                <div class="flex items-center gap-2">
                    <span v-if="props.log.finish_time" class="inline-flex items-center gap-1 rounded-full border border-success/30 bg-success/10 px-2.5 py-1 text-xs font-medium text-success">
                        <i class="fas fa-check-circle h-3.5 w-3.5" ></i> Dikumpulkan {{ props.log.finish_time }}
                    </span>
                    <span v-if="props.log.late" class="inline-flex items-center gap-1 rounded-full border border-warning/30 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning">
                        <i class="fas fa-clock h-3.5 w-3.5" ></i> Terlambat
                    </span>
                    <span v-if="props.log.nilai" class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                        Nilai {{ props.log.nilai }}
                    </span>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border bg-muted/60 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/20 text-primary">
                            <i class="fas fa-user h-5 w-5" ></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-foreground">{{ props.item.guru || 'Guru Pengampu' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ props.item.mapel || '-' }}
                                <span v-if="props.item.kelas"> · Kelas {{ props.item.kelas }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">
                        <p class="font-mono">{{ props.item.kode }}</p>
                        <p>{{ props.item.date_label }}<span v-if="props.item.jam"> · Jam {{ props.item.jam }}</span></p>
                    </div>
                </div>

                <div class="px-5 py-5">
                    <h1 class="text-xl font-semibold tracking-tight text-foreground">{{ props.item.judul }}</h1>
                    <p v-if="props.item.tenggat" class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium" :class="props.log.late ? 'text-warning' : 'text-muted-foreground'">
                        <i class="fas fa-clock h-3.5 w-3.5" ></i>
                        Tenggat pengumpulan: {{ props.item.tenggat }}
                    </p>
                    <div v-if="props.item.isi" class="mt-4 whitespace-pre-line text-sm leading-7 text-foreground">{{ props.item.isi }}</div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">Tidak ada deskripsi {{ title.toLowerCase() }}.</p>

                    <div v-if="props.item.link || props.item.youtube" class="mt-5 flex flex-wrap gap-2">
                        <a v-if="props.item.link" :href="props.item.link" target="_blank" rel="noopener" :class="btnSecondary">
                            <i class="fas fa-external-link-alt h-4 w-4" ></i> Tautan pendukung
                        </a>
                        <a v-if="props.item.youtube" :href="props.item.youtube" target="_blank" rel="noopener" :class="btnSecondary">
                            <i class="fas fa-youtube h-4 w-4 text-destructive" ></i> Video YouTube
                        </a>
                    </div>
                </div>

                <div v-if="props.item.files.length" class="border-t border-border px-5 py-4">
                    <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <i class="fas fa-paperclip h-3.5 w-3.5" ></i> Lampiran
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <a v-for="file in props.item.files" :key="file.url" :href="file.url" target="_blank" rel="noopener"
                            class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-foreground transition hover:border-primary/30 hover:bg-primary/10">
                            <i class="fas fa-file-alt h-4 w-4 shrink-0 text-primary" ></i>
                            <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                            <i class="fas fa-download h-4 w-4 shrink-0 text-muted-foreground" ></i>
                        </a>
                    </div>
                </div>
            </div>

            <div :class="cardClass" class="p-5">
                <h2 class="text-sm font-semibold text-foreground">Hasil {{ title }}</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ jenis === 1 ? 'Tulis catatan belajar atau hal yang ingin ditanyakan, lalu tandai selesai.' : 'Tulis jawaban atau tautan pekerjaan, lalu kumpulkan.' }}
                </p>

                <div v-if="props.log.graded" class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-sm font-semibold text-success">
                        <i class="fas fa-comment-dots h-4 w-4" ></i> Umpan balik guru
                    </p>
                    <p class="mt-1 text-sm text-success">
                        Nilai: <span class="font-semibold">{{ props.log.nilai ?? '-' }}</span>
                    </p>
                    <p v-if="props.log.catatan" class="mt-1 whitespace-pre-line text-sm text-success">{{ props.log.catatan }}</p>
                    <p v-else class="mt-1 text-sm text-success">Tidak ada catatan tambahan.</p>
                    <p class="mt-2 text-xs text-success">Hasil yang sudah dinilai tidak dapat diubah.</p>
                </div>

                <div v-if="props.log.files.length" class="mt-4">
                    <p class="mb-1 text-xs font-medium text-muted-foreground">File yang sudah Anda unggah</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <a v-for="file in props.log.files" :key="file.url" :href="file.url" target="_blank" rel="noopener"
                            class="flex items-center gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success">
                            <i class="fas fa-file-alt h-4 w-4 shrink-0" ></i>
                            <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                            <i class="fas fa-download h-4 w-4 shrink-0 text-success" ></i>
                        </a>
                    </div>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label :class="labelClass">{{ jenis === 1 ? 'Catatan belajar' : 'Jawaban / keterangan' }}</label>
                        <textarea v-model="form.text" rows="7" :class="inputClass" :disabled="props.log.graded"
 :placeholder="jenis === 1 ? 'Tulis ringkasan atau pertanyaan…' : 'Tulis jawaban atau tautan hasil pekerjaan…'" :aria-label="jenis === 1 ? 'Ringkasan atau pertanyaan' : 'Jawaban atau tautan hasil pekerjaan'"></textarea>
                        <p v-if="form.errors.text" class="mt-1 text-xs text-destructive">{{ form.errors.text }}</p>
                    </div>

                    <div v-if="!props.log.graded">
                        <label :class="labelClass" for="tambah-file-opsional-maks-20-mb-per-file">Tambah file (opsional, maks. 20 MB per file)</label>
                        <input ref="fileInput" type="file" multiple class="block w-full text-xs text-muted-foreground" @change="pickFiles" id="tambah-file-opsional-maks-20-mb-per-file" />
                        <p v-if="form.errors.files" class="mt-1 text-xs text-destructive">{{ form.errors.files }}</p>
                    </div>

                    <div v-if="!props.log.graded" class="flex justify-end">
                        <button type="submit" :class="btnPrimary" :disabled="form.processing">
                            <i class="fas fa-check-circle h-4 w-4" ></i>
                            {{ form.processing ? 'Menyimpan…' : (props.log.finish_time ? 'Simpan perubahan' : (jenis === 1 ? 'Tandai selesai' : 'Kumpulkan tugas')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
