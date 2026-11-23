<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import Modal from '@/Components/Modal.vue';
import RichContent from '@/Components/RichContent.vue';
import RichEditor from '@/Components/RichEditor.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    bank: { type: Object, required: true },
    soals: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
});

const page = usePage();
const importErrors = computed(() => page.props.flash?.import_errors ?? []);
const showImportErrors = ref(true);

const letters = ['a', 'b', 'c', 'd', 'e'];

const types = [
    { jenis: 1, key: 'pg', label: 'Pilihan Ganda', short: 'PG' },
    { jenis: 2, key: 'kompleks', label: 'PG Kompleks', short: 'Kompleks' },
    { jenis: 3, key: 'jodohkan', label: 'Menjodohkan', short: 'Jodohkan' },
    { jenis: 4, key: 'isian', label: 'Isian Singkat', short: 'Isian' },
    { jenis: 5, key: 'esai', label: 'Uraian', short: 'Uraian' },
];

const activeTab = ref(1);
const activeMeta = computed(() => types.find((type) => type.jenis === activeTab.value));
const filtered = computed(() => props.soals.filter((soal) => Number(soal.jenis) === activeTab.value));

const selected = reactive({});

watch(
    () => props.soals,
    (value) => {
        Object.keys(selected).forEach((key) => delete selected[key]);
        value.forEach((soal) => {
            selected[soal.id_soal] = Number(soal.tampilkan) === 1;
        });
    },
    { immediate: true },
);

function countsFor(jenis) {
    return props.soals.filter((soal) => Number(soal.jenis) === jenis).length;
}

function plannedFor(key) {
    return Number(props.bank['tampil_' + key] || 0);
}

const selectedTotal = computed(() => Object.values(selected).filter(Boolean).length);
const plannedTotal = computed(() =>
    types.reduce((total, type) => total + plannedFor(type.key), 0),
);

function saveSelection() {
    const ids = Object.entries(selected)
        .filter(([, value]) => value)
        .map(([key]) => Number(key));

    router.post(`/cbt/bank-soal/${props.bank.id_bank}/pilih`, { ids }, { preserveScroll: true });
}

const showEditor = ref(false);
const editing = ref(null);
const errors = ref({});
const processing = ref(false);
const forceDuplicate = ref(false);

const errorList = computed(() =>
    Object.entries(errors.value).flatMap(([key, value]) => {
        const messages = Array.isArray(value) ? value : [value];

        return messages
            .filter(Boolean)
            .map((message) => ({ key, message: String(message) }));
    }),
);

const showImport = ref(false);
const importForm = useForm({ file: null });

function openImport() {
    importForm.reset();
    importForm.clearErrors();
    showImport.value = true;
}

function pickImportFile(event) {
    importForm.file = event.target.files?.[0] ?? null;
}

function submitImport() {
    importForm.post(`/cbt/bank-soal/${props.bank.id_bank}/impor`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showImport.value = false;
            importForm.reset();
        },
    });
}

function insertOptionFormula(letter) {
    const key = 'opsi_' + letter;
    const input = document.getElementById(`opsi-${letter}`);
    const value = form[key] ?? '';
    const start = input?.selectionStart ?? value.length;
    const end = input?.selectionEnd ?? start;

    form[key] = value.slice(0, start) + '\\( \\)' + value.slice(end);

    nextTick(() => {
        input?.focus();
        input?.setSelectionRange(start + 3, start + 3);
    });
}

function blankForm(jenis) {
    return {
        jenis: Number(jenis),
        soal: '',
        deskripsi: '',
        kesulitan: 1,
        timer: false,
        timer_menit: 0,
        opsi_a: '',
        opsi_b: '',
        opsi_c: '',
        opsi_d: '',
        opsi_e: '',
        jawaban: 'a',
        jawabanMulti: [],
        pairs: [
            { kiri: '', kanan: '' },
            { kiri: '', kanan: '' },
        ],
        rubrik: [],
        file: null,
        file_a: null,
        file_b: null,
        file_c: null,
        file_d: null,
        file_e: null,
    };
}

const form = reactive(blankForm(1));

function openCreate() {
    editing.value = null;
    Object.assign(form, blankForm(activeTab.value));
    errors.value = {};
    forceDuplicate.value = false;
    showEditor.value = true;
}

function openEdit(soal) {
    editing.value = soal;
    Object.assign(form, blankForm(soal.jenis));
    errors.value = {};
    forceDuplicate.value = false;
    form.soal = soal.soal ?? '';
    form.deskripsi = soal.deskripsi ?? '';
    form.kesulitan = Number(soal.kesulitan) || 1;
    form.timer = Number(soal.timer) === 1;
    form.timer_menit = Number(soal.timer_menit) || 0;
    letters.forEach((letter) => {
        form['opsi_' + letter] = soal['opsi_' + letter] ?? '';
    });

    if (Number(soal.jenis) === 1) {
        form.jawaban = soal.jawaban || 'a';
    } else if (Number(soal.jenis) === 2) {
        form.jawabanMulti = Array.isArray(soal.jawaban) ? [...soal.jawaban] : [];
    } else if (Number(soal.jenis) === 3) {
        form.pairs = Array.isArray(soal.jawaban) && soal.jawaban.length
            ? soal.jawaban.map((pair) => ({ kiri: pair.kiri ?? '', kanan: pair.kanan ?? '' }))
            : [{ kiri: '', kanan: '' }, { kiri: '', kanan: '' }];
    } else {
        form.jawaban = soal.jawaban ?? '';
    }

    form.rubrik = Array.isArray(soal.rubrik)
        ? soal.rubrik.map((item) => ({
            id_rubrik: item.id_rubrik ?? null,
            kriteria: item.kriteria ?? '',
            skor_maks: Number(item.skor_maks) || 1,
        }))
        : [];

    errors.value = {};
    showEditor.value = true;
}

function addPair() {
    form.pairs.push({ kiri: '', kanan: '' });
}

function removePair(index) {
    if (form.pairs.length <= 2) return;
    form.pairs.splice(index, 1);
}

function onFile(key, event) {
    form[key] = event.target.files?.[0] ?? null;
}

function addRubrik() {
    if (form.rubrik.length >= 20) return;
    form.rubrik.push({ id_rubrik: null, kriteria: '', skor_maks: 1 });
}

function removeRubrik(index) {
    form.rubrik.splice(index, 1);
}

const totalRubrik = computed(() =>
    form.rubrik.reduce((total, item) => total + (Number(item.skor_maks) || 0), 0)
);

function buildPayload() {
    const data = {
        jenis: form.jenis,
        soal: form.soal,
        deskripsi: form.deskripsi,
        kesulitan: Number(form.kesulitan) || 1,
        timer: form.timer ? 1 : 0,
        timer_menit: form.timer ? Number(form.timer_menit) || 0 : 0,
        paksa: forceDuplicate.value ? 1 : 0,
    };

    if (form.file) data.file = form.file;

    if (form.jenis === 1 || form.jenis === 2) {
        letters.forEach((letter) => {
            data['opsi_' + letter] = form['opsi_' + letter];
        });
        if (form.jenis === 1) {
            letters.forEach((letter) => {
                if (form['file_' + letter]) data['file_' + letter] = form['file_' + letter];
            });
        }
    }

    if (form.jenis === 1) {
        data.jawaban = form.jawaban;
    } else if (form.jenis === 2) {
        data.jawaban = form.jawabanMulti;
    } else if (form.jenis === 3) {
        data.pairs = form.pairs.map((pair) => ({ kiri: pair.kiri, kanan: pair.kanan }));
    } else {
        data.jawaban = form.jawaban;
    }

    if (form.jenis === 5) {
        data.rubrik = form.rubrik.map((item) => ({
            id_rubrik: item.id_rubrik || null,
            kriteria: item.kriteria,
            skor_maks: Number(item.skor_maks) || 0,
        }));
    }

    return data;
}

function submit(force = false) {
    processing.value = true;
    errors.value = {};

    if (force) {
        forceDuplicate.value = true;
    }

    const url = editing.value
        ? `/cbt/bank-soal/${props.bank.id_bank}/soal/${editing.value.id_soal}`
        : `/cbt/bank-soal/${props.bank.id_bank}/soal`;

    router.post(url, buildPayload(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEditor.value = false;
            forceDuplicate.value = false;
        },
        onError: (errorBag) => {
            errors.value = errorBag;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

async function destroy(soal) {
    if (await askConfirm(`Hapus soal nomor ${soal.nomor_soal}?`)) {
        router.delete(`/cbt/bank-soal/${props.bank.id_bank}/soal/${soal.id_soal}`, { preserveScroll: true });
    }
}

function mediaUrl(path) {
    return path ? `/storage/${path}` : null;
}

function toggleMulti(letter) {
    const index = form.jawabanMulti.indexOf(letter);
    if (index === -1) {
        form.jawabanMulti.push(letter);
    } else {
        form.jawabanMulti.splice(index, 1);
    }
}
</script>

<template>
    <Head :title="`Bank Soal ${props.bank.bank_kode}`" />
    <AdminLayout title="Bank Soal">
        <div class="page-stack space-y-6">
            <div>
                <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                    <PageHeader
                        :title="props.bank.bank_nama"
                        :back="{ href: '/cbt/bank-soal', label: 'Kembali ke daftar bank soal' }"
                    >
                        <template #meta>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ props.bank.bank_kode }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="props.bank.status_soal === 1 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'"
                                >
                                    {{ props.bank.status_soal === 1 ? 'Siap digunakan' : 'Belum lengkap' }}
                                </span>
                            </div>
                        </template>
                        <template #description>{{ props.bank.mapel }} · {{ props.bank.jenis }} · KKM {{ props.bank.kkm }}</template>
                    </PageHeader>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                            Dipilih {{ selectedTotal }} / {{ plannedTotal }} soal
                        </span>
                        <button type="button" :class="btnSecondary" @click="saveSelection">
                            <i class="fas fa-save h-4 w-4" ></i> Simpan Pilihan
                        </button>
                        <button type="button" :class="btnPrimary" @click="openCreate">
                            <i class="fas fa-plus h-4 w-4" ></i> Tambah Soal
                        </button>
                        <ExportLink
                            :href="`/cbt/bank-soal/${props.bank.id_bank}/ekspor`"
                            label="Excel"
                            icon="file-excel"
                        />
                        <ExportLink
                            :href="`/cbt/bank-soal/${props.bank.id_bank}/ekspor?format=docx`"
                            label="Word"
                            icon="file-alt"
                        />
                        <button type="button" :class="btnSecondary" @click="openImport">
                            <i class="fas fa-upload h-4 w-4" ></i> Impor
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showImportErrors && importErrors.length" class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-warning">
                            <i class="fas fa-exclamation-triangle h-4 w-4" ></i> Sebagian baris tidak diimpor
                        </p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-warning">
                            <li v-for="(error, index) in importErrors" :key="index">{{ error }}</li>
                        </ul>
                        <p class="mt-2 text-xs text-warning">Perbaiki baris tersebut lalu unggah ulang. Soal yang sudah masuk tidak akan terduplikasi.</p>
                    </div>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md text-warning hover:bg-warning/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" aria-label="Tutup peringatan" @click="showImportErrors = false">
                        <i class="fas fa-times h-4 w-4" ></i>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button"
                    v-for="type in types"
                    :key="type.jenis"
                    class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                    :class="activeTab === type.jenis ? 'border-primary bg-primary/10 text-primary-700 dark:text-primary-200' : 'border-border bg-card text-muted-foreground hover:bg-muted/40'"
                    @click="activeTab = type.jenis"
                >
                    {{ type.label }}
                    <span class="rounded-full bg-card px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                        {{ countsFor(type.jenis) }}/{{ plannedFor(type.key) }}
                    </span>
                </button>
            </div>

            <div class="space-y-3">
                <div v-if="!filtered.length" class="rounded-xl border border-dashed border-border bg-muted/60 px-4 py-14 text-center text-muted-foreground">
                    <i class="fas fa-book mx-auto mb-2 h-9 w-9 text-muted-foreground/50" ></i>
                    Belum ada soal {{ activeMeta.label.toLowerCase() }}.
                </div>

                <div v-for="soal in filtered" :key="soal.id_soal" class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex items-center gap-2">
                            <input
                                v-model="selected[soal.id_soal]"
                                type="checkbox"
                                class="h-4 w-4 rounded border-border text-primary"
                                :aria-label="`Pilih soal nomor ${soal.nomor_soal}`"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span class="font-semibold text-foreground">Soal {{ soal.nomor_soal }}</span>
                                <span class="rounded bg-muted px-1.5 py-0.5">Kesulitan {{ soal.kesulitan }}</span>
                                <span v-if="Number(soal.timer) === 1" class="rounded bg-warning/10 px-1.5 py-0.5 text-warning">Timer {{ soal.timer_menit }} menit</span>
                                <span v-if="Number(soal.tampilkan) === 1" class="rounded bg-success/10 px-1.5 py-0.5 text-success">Dipilih</span>
                            </div>
                            <RichContent :html="soal.soal" class="mt-1 line-clamp-2 text-sm text-foreground" />

                            <div v-if="mediaUrl(soal.file)" class="mt-2">
                                <img v-if="/\.(jpg|jpeg|png|gif)$/i.test(soal.file)" :src="mediaUrl(soal.file)" class="h-20 rounded-lg border border-border object-cover" />
                                <span v-else class="text-xs text-muted-foreground">Lampiran: {{ soal.file }}</span>
                            </div>

                            <div v-if="Number(soal.jenis) === 1 || Number(soal.jenis) === 2" class="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                <div v-for="letter in letters" :key="letter" class="truncate">
                                    <span class="font-semibold uppercase">{{ letter }}.</span>
                                    <RichContent :html="soal['opsi_' + letter]" class="inline" />
                                    <span v-if="Number(soal.jenis) === 1 && soal.jawaban === letter" class="ml-1 font-semibold text-success">✓</span>
                                    <span v-else-if="Number(soal.jenis) === 2 && Array.isArray(soal.jawaban) && soal.jawaban.includes(letter)" class="ml-1 font-semibold text-success">✓</span>
                                </div>
                            </div>
                            <div v-else-if="Number(soal.jenis) === 3" class="mt-2 space-y-0.5 text-xs text-muted-foreground">
                                <div v-for="(pair, index) in soal.jawaban" :key="index" class="flex flex-wrap items-center gap-1">
                                    <RichContent :html="pair.kiri" class="inline" />
                                    <span>→</span>
                                    <RichContent :html="pair.kanan" class="inline" />
                                </div>
                            </div>
                            <div v-else class="mt-2 text-xs text-muted-foreground">
                                <span class="font-semibold">Kunci:</span> {{ soal.jawaban || '-' }}
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button aria-label="Edit soal" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit soal" @click="openEdit(soal)">
                                <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            </button>
                            <button aria-label="Hapus soal" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus soal" @click="destroy(soal)">
                                <i class="fas fa-trash-alt h-4 w-4" ></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showEditor" :title="editing ? `Edit Soal ${activeMeta.label}` : `Tambah Soal ${activeMeta.label}`" max-width="max-w-3xl" @close="showEditor = false">
            <form class="space-y-4" @submit.prevent="submit()">
                <div v-if="errorList.length" role="alert" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2">
                    <p class="text-sm font-medium text-destructive">Periksa kembali isian Anda:</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs text-destructive">
                        <li v-for="item in errorList" :key="`${item.key}-${item.message}`">{{ item.message }}</li>
                    </ul>
                </div>
                <div>
                    <label :class="labelClass" for="soal-pertanyaan">Pertanyaan</label>
                    <RichEditor id="soal-pertanyaan" v-model="form.soal" placeholder="Tulis pertanyaan. Gunakan sigma untuk rumus, tempel gambar langsung dari papan klip." min-height="110px" />
                    <p v-if="errors.soal" class="mt-1 text-xs text-destructive">{{ errors.soal }}</p>
                    <div v-if="form.soal.trim()" class="mt-2 rounded-lg border border-border bg-muted/60 px-3 py-2">
                        <p class="text-2xs font-semibold uppercase tracking-wide text-muted-foreground">Pratinjau</p>
                        <RichContent :html="form.soal" class="mt-1 text-sm text-foreground" />
                    </div>
                </div>

                <div>
                    <label :class="labelClass" for="lampiran-soal-gambar-audio-video">Lampiran Soal (gambar / audio / video)</label>
                    <input type="file" accept=".jpg,.jpeg,.png,.gif,.mp3,.wav,.mp4" class="block w-full text-sm text-muted-foreground" @change="onFile('file', $event)" id="lampiran-soal-gambar-audio-video" />
                    <p v-if="editing && editing.file" class="mt-1 text-xs text-muted-foreground">Lampiran saat ini: {{ editing.file }}</p>
                    <p v-if="errors.file" class="mt-1 text-xs text-destructive">{{ errors.file }}</p>
                </div>

                <div v-if="form.jenis === 1 || form.jenis === 2" class="space-y-2">
                    <label :class="labelClass">
                        Opsi Jawaban
                        <span v-if="form.jenis === 1" class="font-normal text-muted-foreground">(pilih satu jawaban benar)</span>
                        <span v-else class="font-normal text-muted-foreground">(centang jawaban benar, minimal dua)</span>
                    </label>
                    <div v-for="letter in letters" :key="letter" class="flex items-start gap-2">
                        <input
 v-if="form.jenis === 1"
 v-model="form.jawaban"
 type="radio"
 :value="letter"
 class="mt-2 h-4 w-4 border-border text-primary" :aria-label="`Jawaban benar opsi ${letter.toUpperCase()}`" />
                        <input
 v-else
 :checked="form.jawabanMulti.includes(letter)"
 type="checkbox"
 class="mt-2 h-4 w-4 rounded border-border text-primary"
 @change="toggleMulti(letter)" :aria-label="`Tandai opsi ${letter.toUpperCase()} sebagai jawaban benar`" />
                        <span class="mt-1 w-5 text-sm font-semibold uppercase text-muted-foreground">{{ letter }}</span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <input :id="`opsi-${letter}`" v-model="form['opsi_' + letter]" :class="inputClass" :placeholder="`Opsi ${letter.toUpperCase()}`" :aria-label="`Teks opsi ${letter.toUpperCase()}`" />
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground transition hover:bg-muted/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                    title="Sisipkan rumus LaTeX"
                                    :aria-label="`Sisipkan rumus pada opsi ${letter.toUpperCase()}`"
                                    @click="insertOptionFormula(letter)"
                                >
                                    <i class="fas fa-sigma h-4 w-4" ></i>
                                </button>
                            </div>
                            <p v-if="letter === 'a'" class="mt-1 text-2xs text-muted-foreground">Rumus ditulis di antara \( dan \), misalnya \(x^2\).</p>
                            <input
 v-if="form.jenis === 1"
 type="file"
 accept=".jpg,.jpeg,.png,.gif"
 class="mt-1 block w-full text-xs text-muted-foreground"
 @change="onFile('file_' + letter, $event)" :aria-label="`Berkas opsi ${letter.toUpperCase()}`" />
                            <p v-if="errors['opsi_' + letter]" class="mt-1 text-xs text-destructive">
                                {{ errors['opsi_' + letter] }}
                            </p>
                        </div>
                    </div>
                    <p v-if="errors.jawaban" class="text-xs text-destructive">{{ errors.jawaban }}</p>
                </div>

                <div v-if="form.jenis === 3" class="space-y-2">
                    <label :class="labelClass">Pasangan Menjodohkan</label>
                    <div v-for="(pair, index) in form.pairs" :key="index" class="flex items-center gap-2">
                        <input v-model="pair.kiri" :class="inputClass" placeholder="Pernyataan kiri" :aria-label="`Pernyataan kiri pasangan ${index + 1}`" />
                        <span class="text-muted-foreground">→</span>
                        <input v-model="pair.kanan" :class="inputClass" placeholder="Pasangan kanan" :aria-label="`Pasangan kanan ${index + 1}`" />
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" aria-label="Hapus pasangan" @click="removePair(index)">
                            <i class="fas fa-times h-4 w-4" ></i>
                        </button>
                    </div>
                    <button type="button" class="text-sm font-medium text-primary hover:text-primary" @click="addPair">+ Tambah pasangan</button>
                    <p v-if="errors.pairs" class="text-xs text-destructive">{{ errors.pairs }}</p>
                </div>

                <div v-if="form.jenis === 4">
                    <label :class="labelClass" for="kunci-jawaban-isian">Kunci Jawaban Isian</label>
                    <input v-model="form.jawaban" :class="inputClass" placeholder="Jawaban benar" id="kunci-jawaban-isian" />
                    <p v-if="errors.jawaban" class="mt-1 text-xs text-destructive">{{ errors.jawaban }}</p>
                </div>

                <div v-if="form.jenis === 5">
                    <label :class="labelClass" for="kunci-rambu-rambu-jawaban">Kunci / Rambu-rambu Jawaban</label>
                    <textarea v-model="form.jawaban" rows="2" :class="inputClass" placeholder="Panduan penilaian uraian (opsional)" id="kunci-rambu-rambu-jawaban" />
                </div>

                <div v-if="form.jenis === 5" class="space-y-2 rounded-lg border border-border p-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p :class="labelClass">Rubrik Penilaian (opsional)</p>
                            <p class="text-xs text-muted-foreground">
                                Skor tiap kriteria otomatis diskalakan ke bobot soal. Total skor rubrik saat ini {{ totalRubrik }}.
                            </p>
                        </div>
                        <button type="button" :class="btnSecondary" @click="addRubrik">+ Tambah kriteria</button>
                    </div>

                    <p v-if="!form.rubrik.length" class="text-xs text-muted-foreground">
                        Belum ada kriteria. Korektor akan memberi satu skor total untuk jawaban ini.
                    </p>

                    <div v-for="(item, index) in form.rubrik" :key="index" class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <input
                                v-model="item.kriteria"
                                :class="inputClass"
                                placeholder="Kriteria, mis. Ketepatan konsep"
                                :aria-label="`Kriteria rubrik ${index + 1}`"
                            />
                        </div>
                        <div class="w-24 shrink-0">
                            <input
                                v-model.number="item.skor_maks"
                                type="number"
                                min="0.01"
                                step="0.5"
                                :class="inputClass"
                                :aria-label="`Skor maksimal kriteria ${index + 1}`"
                                title="Skor maksimal kriteria"
                            />
                        </div>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            aria-label="Hapus kriteria rubrik"
                            @click="removeRubrik(index)"
                        >
                            <i class="fas fa-times h-4 w-4"></i>
                        </button>
                    </div>

                    <p v-if="errors.rubrik" class="text-xs text-destructive">{{ errors.rubrik }}</p>
                    <p v-for="(message, key) in errors" :key="key" v-show="key.startsWith('rubrik.')" class="text-xs text-destructive">
                        {{ message }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="kesulitan-1-10">Kesulitan (1–10)</label>
                        <input v-model.number="form.kesulitan" type="number" min="1" max="10" :class="inputClass" id="kesulitan-1-10" />
                        <p v-if="errors.kesulitan" class="mt-1 text-xs text-destructive">{{ errors.kesulitan }}</p>
                    </div>
                    <label class="mt-6 flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.timer" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                        Timer per soal
                    </label>
                    <div>
                        <label :class="labelClass" for="menit-timer">Menit Timer</label>
                        <input v-model.number="form.timer_menit" type="number" min="0" :class="inputClass" :disabled="!form.timer" id="menit-timer" />
                        <p v-if="errors.timer_menit" class="mt-1 text-xs text-destructive">{{ errors.timer_menit }}</p>
                    </div>
                </div>

                <div>
                    <label :class="labelClass" for="soal-deskripsi">Catatan / Pembahasan (opsional)</label>
                    <RichEditor id="soal-deskripsi" v-model="form.deskripsi" placeholder="Pembahasan atau rambu-rambu penilaian" min-height="80px" />
                    <p v-if="errors.deskripsi" class="mt-1 text-xs text-destructive">{{ errors.deskripsi }}</p>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showEditor = false">Batal</button>
                    <button
                        v-if="errors.soal?.includes('paksa')"
                        type="button"
                        :class="btnSecondary"
                        :disabled="processing"
                        @click="submit(true)"
                    >
                        Paksa simpan
                    </button>
                    <button type="submit" :class="btnPrimary" :disabled="processing">Simpan Soal</button>
                </div>
            </form>
        </Modal>

        <Modal :show="showImport" title="Impor Soal" max-width="max-w-xl" @close="showImport = false">
            <form class="space-y-4" @submit.prevent="submitImport">
                <div class="space-y-2 rounded-lg border border-border bg-muted/60 p-3 text-xs text-muted-foreground">
                    <p class="font-semibold text-foreground">Format berkas</p>
                    <p>Excel (.xlsx): gunakan sheet <b>Soal</b> dengan kolom jenis, soal, opsi_a sampai opsi_e, jawaban, deskripsi, kesulitan, dan timer_menit.</p>
                    <p>Word (.docx): tulis "1. Pertanyaan", opsi "A. ..." sampai "D. ...", lalu "Jawaban: A". Impor Word hanya untuk soal pilihan ganda.</p>
                    <a :href="`/cbt/bank-soal/${props.bank.id_bank}/ekspor?template=1`" class="inline-flex items-center gap-1 font-medium text-primary hover:text-primary">
                        <i class="fas fa-download h-3.5 w-3.5" ></i> Unduh templat Excel
                    </a>
                </div>

                <div>
                    <label :class="labelClass" for="impor-file">Berkas soal</label>
                    <input id="impor-file" type="file" accept=".xlsx,.xls,.docx" class="block w-full text-sm text-muted-foreground" @change="pickImportFile" />
                    <p v-if="importForm.file" class="mt-1 text-xs text-muted-foreground">{{ importForm.file.name }}</p>
                    <p v-if="importForm.errors.file" class="mt-1 text-xs text-destructive">{{ importForm.errors.file }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">Soal yang sama dengan koleksi bank ini akan dilewati otomatis.</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showImport = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="importForm.processing || !importForm.file">
                        <i class="fas fa-upload h-4 w-4" ></i>
                        {{ importForm.processing ? 'Mengimpor…' : 'Impor Soal' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
