<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnIcon, btnIconDanger, btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { askConfirm } from '@/confirm';

const props = defineProps({
    jenis: { type: Number, required: true },
    items: { type: Array, default: () => [] },
    mapels: { type: Array, default: () => [] },
    kelasOptions: { type: Array, default: () => [] },
    gurus: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({ search: '', guru: '' }) },
    periode: { type: String, default: '' },
});

const title = computed(() => (props.jenis === 1 ? 'Materi Belajar' : 'Tugas Kelas'));
const basePath = computed(() => (props.jenis === 1 ? '/elearning/materi' : '/elearning/tugas'));

const search = ref(props.filters.search ?? '');
const guruFilter = ref(props.filters.guru ?? '');
const showModal = ref(false);
const editing = ref(null);
const fileInput = ref(null);

const form = useForm({
    judul: '',
    id_mapel: '',
    kelas: [],
    isi: '',
    link: '',
    youtube: '',
    tgl_mulai: '',
    tenggat: '',
    status: true,
    guru_id: '',
    files: [],
});

const filteredItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items.filter((row) => {
        if (guruFilter.value && String(row.guru_id) !== guruFilter.value) return false;
        if (!term) return true;

        return (
            row.judul_materi.toLowerCase().includes(term) ||
            row.kode_materi.toLowerCase().includes(term) ||
            (row.mapel ?? '').toLowerCase().includes(term)
        );
    });
});

function blank() {
    return {
        judul: '',
        id_mapel: '',
        kelas: props.kelasOptions.length === 1 ? [props.kelasOptions[0].id_kelas] : [],
        isi: '',
        link: '',
        youtube: '',
        tgl_mulai: '',
        tenggat: '',
        status: true,
        guru_id: '',
        files: [],
    };
}

function openCreate() {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.defaults({
        judul: row.judul_materi,
        id_mapel: row.id_mapel,
        kelas: [...row.kelas_ids],
        isi: row.isi_materi,
        link: row.link_file ?? '',
        youtube: row.youtube ?? '',
        tgl_mulai: row.tgl_mulai ?? '',
        tenggat: row.tenggat ? row.tenggat.replace(' ', 'T') : '',
        status: row.status === 1,
        guru_id: row.guru_id,
        files: [],
    });
    form.reset();
    form.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
    showModal.value = true;
}

function pickFiles(event) {
    form.files = Array.from(event.target.files ?? []);
}

function submit() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showModal.value = false;
        },
    };

    if (editing.value) {
        form.put(`/elearning/${editing.value.id_materi}`, options);
    } else {
        form.post(basePath.value, options);
    }
}

function toggleStatus(row) {
    router.post(`/elearning/${row.id_materi}/status`, {}, { preserveScroll: true });
}

async function destroy(row) {
    if (await askConfirm(`${props.jenis === 1 ? 'Hapus materi' : 'Hapus tugas'} "${row.judul_materi}"? Jadwal dan hasil siswa terkait juga akan dihapus.`)) {
        router.delete(`/elearning/${row.id_materi}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="title" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="title">
                    <template #description>{{ jenis === 1 ? 'Kelola bahan ajar digital' : 'Kelola penugasan untuk kelas' }} pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Buat {{ jenis === 1 ? 'Materi' : 'Tugas' }}
                </button>
            </div>

            <div :class="cardClass" class="flex flex-wrap items-center gap-3 p-4">
                <input v-model="search" type="search" :class="inputClass" class="max-w-xs" :placeholder="`Cari judul atau kode ${jenis === 1 ? 'materi' : 'tugas'}…`" :aria-label="`Cari judul atau kode ${jenis === 1 ? 'materi' : 'tugas'}…`" />
                <select v-if="props.isAdmin" v-model="guruFilter" :class="inputClass" class="max-w-xs" aria-label="Filter guru">
                    <option value="">Semua guru</option>
                    <option v-for="guru in props.gurus" :key="guru.id_guru" :value="String(guru.id_guru)">{{ guru.nama_guru }}</option>
                </select>
                <span class="ml-auto text-xs text-muted-foreground">{{ filteredItems.length }} data</span>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Judul</th>
                                <th class="px-4 py-3">Mapel</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th v-if="props.isAdmin" class="px-4 py-3">Guru</th>
                                <th class="px-4 py-3">Jadwal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in filteredItems" :key="row.id_materi" class="align-top hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.judul_materi }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ row.kode_materi }}</p>
                                    <p class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <i class="fas fa-paperclip h-3 w-3" ></i> {{ row.files.length }} lampiran
                                        <span v-if="row.created_on"> · {{ row.created_on }}</span>
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.mapel || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="kelas in row.kelas" :key="kelas" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ kelas }}</span>
                                        <span v-if="!row.kelas.length" class="text-xs text-muted-foreground">-</span>
                                    </div>
                                </td>
                                <td v-if="props.isAdmin" class="px-4 py-3 text-muted-foreground">{{ row.guru || '-' }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    <p>{{ row.jadwal_count }} jadwal</p>
                                    <p>{{ row.selesai }}/{{ row.dibuka }} selesai</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 1 ? 'border-success/30 bg-success/10 text-success' : 'border-border bg-muted text-muted-foreground'">
                                        {{ row.status === 1 ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link v-if="row.jenis === 2" :href="`/elearning/tugas/${row.id_materi}`" :class="btnIcon" class="text-success hover:bg-success/10" title="Nilai tugas" aria-label="Nilai tugas">
                                            <i class="fas fa-clipboard-check h-3.5 w-3.5" ></i>
                                        </Link>
                                        <Link :href="`/elearning/jadwal?kelas=${row.kelas_ids[0] ?? ''}`" :class="btnIcon" class="text-primary hover:bg-primary/10" title="Atur jadwal" aria-label="Atur jadwal">
                                            <i class="fas fa-calendar-alt h-3.5 w-3.5" ></i>
                                        </Link>
                                        <button type="button" :class="btnIcon" class="text-warning hover:bg-warning/10" :title="row.status === 1 ? 'Nonaktifkan' : 'Aktifkan'" :aria-label="row.status === 1 ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleStatus(row)">
                                            <i class="fas fa-power-off h-3.5 w-3.5" ></i>
                                        </button>
                                        <button type="button" :class="btnIcon" class="text-primary hover:bg-primary/10" title="Ubah" aria-label="Ubah" @click="openEdit(row)">
                                            <i class="fas fa-pencil-alt h-3.5 w-3.5" ></i>
                                        </button>
                                        <button type="button" :class="btnIconDanger" title="Hapus" aria-label="Hapus" @click="destroy(row)">
                                            <i class="fas fa-trash-alt h-3.5 w-3.5" ></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredItems.length">
                                <td :colspan="props.isAdmin ? 7 : 6" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                    <i :class="'fas fa-' + (jenis === 1 ? 'edit' : 'clipboard-list')" class="mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                    <p>Belum ada {{ jenis === 1 ? 'materi' : 'tugas' }}. Klik tombol di kanan atas untuk membuat.</p>
                                    <button type="button" :class="[btnPrimary, 'mt-4']" @click="openCreate">
                                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i>
                                        Buat {{ jenis === 1 ? 'Materi' : 'Tugas' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showModal" max-width="max-w-3xl" :title="editing ? `Ubah ${jenis === 1 ? 'Materi' : 'Tugas'}` : `Buat ${jenis === 1 ? 'Materi' : 'Tugas'}`" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="judul">Judul</label>
                        <input v-model="form.judul" type="text" :class="inputClass" required id="judul" />
                        <p v-if="form.errors.judul" class="mt-1 text-xs text-destructive">{{ form.errors.judul }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="mata-pelajaran">Mata Pelajaran</label>
                        <select v-model="form.id_mapel" :class="inputClass" required id="mata-pelajaran">
                            <option value="" disabled>Pilih mapel</option>
                            <option v-for="mapel in props.mapels" :key="mapel.id_mapel" :value="mapel.id_mapel">
                                {{ mapel.nama_mapel }} ({{ mapel.kode }})
                            </option>
                        </select>
                        <p v-if="form.errors.id_mapel" class="mt-1 text-xs text-destructive">{{ form.errors.id_mapel }}</p>
                    </div>
                    <div v-if="props.isAdmin">
                        <label :class="labelClass" for="guru-pengampu">Guru Pengampu</label>
                        <select v-model="form.guru_id" :class="inputClass" id="guru-pengampu">
                            <option value="">Pilih guru</option>
                            <option v-for="guru in props.gurus" :key="guru.id_guru" :value="guru.id_guru">{{ guru.nama_guru }}</option>
                        </select>
                        <p v-if="form.errors.guru_id" class="mt-1 text-xs text-destructive">{{ form.errors.guru_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="tanggal-mulai-opsional">Tanggal Mulai (opsional)</label>
                        <input v-model="form.tgl_mulai" type="date" :class="inputClass" id="tanggal-mulai-opsional" />
                    </div>
                    <div v-if="props.jenis === 2">
                        <label :class="labelClass" for="tenggat-pengumpulan-opsional">Tenggat Pengumpulan (opsional)</label>
                        <input v-model="form.tenggat" type="datetime-local" :class="inputClass" id="tenggat-pengumpulan-opsional" />
                        <p class="mt-1 text-xs text-muted-foreground">Pengumpulan setelah tenggat akan ditandai terlambat.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass">Kelas</label>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border border-border p-3 sm:grid-cols-4">
                            <label v-for="kelas in props.kelasOptions" :key="kelas.id_kelas" class="flex items-center gap-2 text-sm text-foreground">
                                <input v-model="form.kelas" type="checkbox" :value="kelas.id_kelas" class="rounded border-border" />
                                {{ kelas.nama_kelas }}
                            </label>
                            <p v-if="!props.kelasOptions.length" class="col-span-full text-xs text-muted-foreground">Belum ada kelas pada periode aktif.</p>
                        </div>
                        <p v-if="form.errors.kelas" class="mt-1 text-xs text-destructive">{{ form.errors.kelas }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass">{{ jenis === 1 ? 'Isi Materi' : 'Instruksi Tugas' }}</label>
                        <textarea v-model="form.isi" rows="8" :class="inputClass" :placeholder="jenis === 1 ? 'Tulis ringkasan atau penjelasan materi…' : 'Tulis petunjuk pengerjaan tugas…'" :aria-label="jenis === 1 ? 'Ringkasan materi' : 'Petunjuk pengerjaan tugas'"></textarea>
                        <p v-if="form.errors.isi" class="mt-1 text-xs text-destructive">{{ form.errors.isi }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="tautan-pendukung-opsional">Tautan Pendukung (opsional)</label>
                        <input v-model="form.link" type="text" :class="inputClass" placeholder="https://…" id="tautan-pendukung-opsional" />
                    </div>
                    <div>
                        <label :class="labelClass" for="youtube-opsional">YouTube (opsional)</label>
                        <input v-model="form.youtube" type="text" :class="inputClass" placeholder="https://youtu.be/…" id="youtube-opsional" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="lampiran-maks-20-mb-per-file">Lampiran (maks. 20 MB per file)</label>
                        <input ref="fileInput" type="file" multiple class="block w-full text-xs text-muted-foreground" @change="pickFiles" id="lampiran-maks-20-mb-per-file" />
                        <p v-if="editing && editing.files.length" class="mt-2 text-xs text-muted-foreground">
                            {{ editing.files.length }} lampiran tersimpan. Unggah file baru untuk menambahkan.
                        </p>
                        <p v-if="form.errors.files" class="mt-1 text-xs text-destructive">{{ form.errors.files }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input v-model="form.status" type="checkbox" class="rounded border-border" /> Aktifkan untuk siswa
                </label>

                <div class="flex justify-end gap-2 border-t border-border pt-4">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
