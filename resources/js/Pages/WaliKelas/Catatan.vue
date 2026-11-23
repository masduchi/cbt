<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';
import { computed } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    filters: { type: Object, default: () => ({ kelas: null, mapel: null }) },
    mapels: { type: Array, default: () => [] },
    selectedMapel: { type: Number, default: null },
    students: { type: Array, default: () => [] },
    catatanKelasMapel: { type: Array, default: () => [] },
    catatanSiswaMapel: { type: Array, default: () => [] },
    catatanKelasWali: { type: Array, default: () => [] },
    catatanSiswaWali: { type: Array, default: () => [] },
    isWali: { type: Boolean, default: false },
    levels: { type: Array, default: () => [] },
});

const mapelForm = useForm({
    id_kelas: props.selected?.id_kelas ?? '',
    id_mapel: props.selectedMapel ?? '',
    type: '1',
    id_siswa: '',
    level: '1',
    text: '',
});

const waliForm = useForm({
    id_kelas: props.selected?.id_kelas ?? '',
    type: '1',
    id_siswa: '',
    level: '1',
    text: '',
});

function changeClass(event) {
    router.get('/wali/catatan', { kelas: event.target.value }, { preserveScroll: true });
}

function changeMapel(event) {
    router.get(
        '/wali/catatan',
        { kelas: props.selected?.id_kelas || undefined, mapel: event.target.value },
        { preserveScroll: true },
    );
}

function saveMapel() {
    mapelForm.post('/wali/catatan/mapel', {
        preserveScroll: true,
        onSuccess: () => mapelForm.reset('text', 'id_siswa'),
    });
}

function saveWali() {
    waliForm.post('/wali/catatan/wali', {
        preserveScroll: true,
        onSuccess: () => waliForm.reset('text', 'id_siswa'),
    });
}

async function hapusMapel(note) {
    const siapa = note.siswa ? ` milik ${note.siswa}` : '';

    if (await askConfirm(`Hapus catatan mata pelajaran${siapa} tanggal ${note.tgl}?`)) {
        router.delete(`/wali/catatan/mapel/${note.id_catatan}`, { preserveScroll: true });
    }
}

async function hapusWali(note) {
    const siapa = note.siswa ? ` milik ${note.siswa}` : '';

    if (await askConfirm(`Hapus catatan wali kelas${siapa} tanggal ${note.tgl}?`)) {
        router.delete(`/wali/catatan/wali/${note.id_catatan}`, { preserveScroll: true });
    }
}

function levelClass(level) {
    if (level === 4) return 'bg-destructive/10 text-destructive';
    if (level === 3) return 'bg-orange-50 text-orange-700';
    if (level === 2) return 'bg-warning/10 text-warning';
    return 'bg-muted text-muted-foreground';
}

const showMapelSiswa = computed(() => mapelForm.type === '2');
const showWaliSiswa = computed(() => waliForm.type === '2');
</script>

<template>
    <Head title="Catatan Kelas" />
    <AdminLayout title="Catatan Kelas">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Catatan Guru" description="Catatan per mata pelajaran dan catatan wali kelas untuk siswa." /></div>

            <div v-if="!props.classes.length" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm font-medium text-warning">
                Belum ada kelas yang dapat Anda kelola pada periode ini.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-full max-w-xs">
                        <label for="filter-kelas" :class="labelClass">Kelas</label>
                        <select id="filter-kelas" :value="props.selected?.id_kelas" :class="inputClass" @change="changeClass">
                            <option v-for="kelas in props.classes" :key="kelas.id_kelas" :value="kelas.id_kelas">
                                {{ kelas.nama_kelas }}
                            </option>
                        </select>
                    </div>
                    <div class="w-full max-w-xs">
                        <label for="filter-mapel" :class="labelClass">Mata pelajaran</label>
                        <select
 id="filter-mapel"
 :value="props.selectedMapel ?? ''"
 :class="inputClass"
 :disabled="!props.mapels.length"
 @change="changeMapel"
 >
                            <option v-if="!props.mapels.length" value="">Tidak ada mapel</option>
                            <option v-for="mapel in props.mapels" :key="mapel.id_mapel" :value="mapel.id_mapel">
                                {{ mapel.nama_mapel }}
                            </option>
                        </select>
                    </div>
                    <p v-if="props.selected?.wali" class="pb-2 text-xs text-muted-foreground">Wali kelas: {{ props.selected.wali }}</p>
                </div>

                <section class="rounded-xl border border-border bg-card shadow-card">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-4">
                        <i class="fas fa-clipboard-list h-4 w-4 text-primary" ></i>
                        <h2 class="text-sm font-semibold text-foreground">Catatan mata pelajaran</h2>
                    </div>
                    <div class="space-y-5 px-5 py-5">
                        <div v-if="!props.mapels.length" class="rounded-lg border border-border bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                            Anda belum memiliki mata pelajaran pada kelas ini.
                        </div>
                        <form v-else class="rounded-xl border border-border bg-muted/60 p-4" @submit.prevent="saveMapel">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="mapel-jenis" :class="labelClass">Berlaku untuk</label>
                                    <select id="mapel-jenis" v-model="mapelForm.type" :class="inputClass">
                                        <option value="1">Seluruh kelas</option>
                                        <option value="2">Siswa tertentu</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="mapel-level" :class="labelClass">Jenis catatan</label>
                                    <select id="mapel-level" v-model="mapelForm.level" :class="inputClass">
                                        <option v-for="level in props.levels" :key="level.value" :value="String(level.value)">
                                            {{ level.label }}
                                        </option>
                                    </select>
                                </div>
                                <div v-if="showMapelSiswa">
                                    <label for="mapel-siswa" :class="labelClass">Siswa</label>
                                    <select id="mapel-siswa" v-model="mapelForm.id_siswa" :class="inputClass" required>
                                        <option value="" disabled>Pilih siswa</option>
                                        <option v-for="siswa in props.students" :key="siswa.id_siswa" :value="String(siswa.id_siswa)">
                                            {{ siswa.nama }} ({{ siswa.nis }})
                                        </option>
                                    </select>
                                    <p v-if="mapelForm.errors.id_siswa" class="mt-1 text-xs text-destructive">{{ mapelForm.errors.id_siswa }}</p>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="mapel-text" :class="labelClass">Isi catatan</label>
                                    <textarea id="mapel-text" v-model="mapelForm.text" rows="3" :class="inputClass" required />
                                    <p v-if="mapelForm.errors.text" class="mt-1 text-xs text-destructive">{{ mapelForm.errors.text }}</p>
                                    <p v-if="mapelForm.errors.id_kelas || mapelForm.errors.id_mapel" class="mt-1 text-xs text-destructive">
                                        Kelas atau mata pelajaran tidak valid.
                                    </p>
                                    <p v-if="mapelForm.errors.level" class="mt-1 text-xs text-destructive">{{ mapelForm.errors.level }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button type="submit" :class="btnPrimary" :disabled="mapelForm.processing">
                                    {{ mapelForm.processing ? 'Menyimpan…' : 'Tambah catatan' }}
                                </button>
                            </div>
                        </form>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Catatan seluruh kelas</h3>
                                <div class="mt-2 overflow-x-auto rounded-lg border border-border">
                                    <table class="data-table w-full text-sm">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2">Tanggal</th>
                                                <th class="px-3 py-2">Jenis</th>
                                                <th class="px-3 py-2">Catatan</th>
                                                <th class="px-3 py-2 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr v-for="note in props.catatanKelasMapel" :key="note.id_catatan">
                                                <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ note.tgl }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="levelClass(note.level)">
                                                        {{ note.level_label }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <p class="whitespace-pre-line text-foreground">{{ note.text }}</p>
                                                    <p v-if="note.guru" class="mt-1 text-xs text-muted-foreground">{{ note.guru }}</p>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <button aria-label="Hapus catatan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus catatan" @click="hapusMapel(note)">
                                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                                        <span class="sr-only">Hapus catatan</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.catatanKelasMapel.length">
                                                <td colspan="4" class="px-3 py-8 text-center text-sm text-muted-foreground">Belum ada catatan kelas.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Catatan per siswa</h3>
                                <div class="mt-2 overflow-x-auto rounded-lg border border-border">
                                    <table class="data-table w-full text-sm">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2">Siswa</th>
                                                <th class="px-3 py-2">Tanggal</th>
                                                <th class="px-3 py-2">Jenis</th>
                                                <th class="px-3 py-2">Catatan</th>
                                                <th class="px-3 py-2 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr v-for="note in props.catatanSiswaMapel" :key="note.id_catatan">
                                                <td class="whitespace-nowrap px-3 py-2 font-medium text-foreground">{{ note.siswa }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ note.tgl }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="levelClass(note.level)">
                                                        {{ note.level_label }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <p class="whitespace-pre-line text-foreground">{{ note.text }}</p>
                                                    <p v-if="note.guru" class="mt-1 text-xs text-muted-foreground">{{ note.guru }}</p>
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <button aria-label="Hapus catatan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus catatan" @click="hapusMapel(note)">
                                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                                        <span class="sr-only">Hapus catatan {{ note.siswa }}</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.catatanSiswaMapel.length">
                                                <td colspan="5" class="px-3 py-8 text-center text-sm text-muted-foreground">Belum ada catatan siswa.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="props.isWali" class="rounded-xl border border-border bg-card shadow-card">
                    <div class="flex items-center gap-2 border-b border-border px-5 py-4">
                        <i class="fas fa-comment-dots h-4 w-4 text-success" ></i>
                        <h2 class="text-sm font-semibold text-foreground">Catatan wali kelas</h2>
                    </div>
                    <div class="space-y-5 px-5 py-5">
                        <form class="rounded-xl border border-border bg-muted/60 p-4" @submit.prevent="saveWali">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="wali-jenis" :class="labelClass">Berlaku untuk</label>
                                    <select id="wali-jenis" v-model="waliForm.type" :class="inputClass">
                                        <option value="1">Seluruh kelas</option>
                                        <option value="2">Siswa tertentu</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="wali-level" :class="labelClass">Jenis catatan</label>
                                    <select id="wali-level" v-model="waliForm.level" :class="inputClass">
                                        <option v-for="level in props.levels" :key="level.value" :value="String(level.value)">
                                            {{ level.label }}
                                        </option>
                                    </select>
                                </div>
                                <div v-if="showWaliSiswa">
                                    <label for="wali-siswa" :class="labelClass">Siswa</label>
                                    <select id="wali-siswa" v-model="waliForm.id_siswa" :class="inputClass" required>
                                        <option value="" disabled>Pilih siswa</option>
                                        <option v-for="siswa in props.students" :key="siswa.id_siswa" :value="String(siswa.id_siswa)">
                                            {{ siswa.nama }} ({{ siswa.nis }})
                                        </option>
                                    </select>
                                    <p v-if="waliForm.errors.id_siswa" class="mt-1 text-xs text-destructive">{{ waliForm.errors.id_siswa }}</p>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="wali-text" :class="labelClass">Isi catatan</label>
                                    <textarea id="wali-text" v-model="waliForm.text" rows="3" :class="inputClass" required />
                                    <p v-if="waliForm.errors.text" class="mt-1 text-xs text-destructive">{{ waliForm.errors.text }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button type="submit" :class="btnPrimary" :disabled="waliForm.processing">
                                    {{ waliForm.processing ? 'Menyimpan…' : 'Tambah catatan' }}
                                </button>
                            </div>
                        </form>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Catatan seluruh kelas</h3>
                                <div class="mt-2 overflow-x-auto rounded-lg border border-border">
                                    <table class="data-table w-full text-sm">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2">Tanggal</th>
                                                <th class="px-3 py-2">Jenis</th>
                                                <th class="px-3 py-2">Catatan</th>
                                                <th class="px-3 py-2 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr v-for="note in props.catatanKelasWali" :key="note.id_catatan">
                                                <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ note.tgl }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="levelClass(note.level)">
                                                        {{ note.level_label }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-pre-line px-3 py-2 text-foreground">{{ note.text }}</td>
                                                <td class="px-3 py-2 text-right">
                                                    <button aria-label="Hapus catatan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus catatan" @click="hapusWali(note)">
                                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                                        <span class="sr-only">Hapus catatan</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.catatanKelasWali.length">
                                                <td colspan="4" class="px-3 py-8 text-center text-sm text-muted-foreground">Belum ada catatan kelas.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Catatan per siswa</h3>
                                <div class="mt-2 overflow-x-auto rounded-lg border border-border">
                                    <table class="data-table w-full text-sm">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2">Siswa</th>
                                                <th class="px-3 py-2">Tanggal</th>
                                                <th class="px-3 py-2">Jenis</th>
                                                <th class="px-3 py-2">Catatan</th>
                                                <th class="px-3 py-2 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr v-for="note in props.catatanSiswaWali" :key="note.id_catatan">
                                                <td class="whitespace-nowrap px-3 py-2 font-medium text-foreground">{{ note.siswa }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ note.tgl }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="levelClass(note.level)">
                                                        {{ note.level_label }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-pre-line px-3 py-2 text-foreground">{{ note.text }}</td>
                                                <td class="px-3 py-2 text-right">
                                                    <button aria-label="Hapus catatan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus catatan" @click="hapusWali(note)">
                                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                                        <span class="sr-only">Hapus catatan {{ note.siswa }}</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.catatanSiswaWali.length">
                                                <td colspan="5" class="px-3 py-8 text-center text-sm text-muted-foreground">Belum ada catatan siswa.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AdminLayout>
</template>
