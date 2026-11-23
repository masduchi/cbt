<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    alumni: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: null, search: '' }) },
    classes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ lulus: 0, pindah: 0, keluar: 0, total: 0 }) },
    tahunOptions: { type: Array, default: () => [] },
});

const statusLabels = { 2: 'Lulus', 3: 'Pindah', 4: 'Keluar' };

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ? String(props.filters.status) : '');
let timer = null;

watch([search, status], ([searchValue, statusValue]) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/alumni',
            { search: searchValue || undefined, status: statusValue || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const generateForm = useForm({
    kelas: '',
    status: '2',
    tahun_lulus: String(new Date().getFullYear()),
});

function generate() {
    generateForm.post('/alumni/generate', {
        preserveScroll: true,
        onSuccess: () => generateForm.reset('kelas'),
    });
}

const editOpen = ref(false);
const editing = ref(null);
const editForm = useForm({
    status: '2',
    tahun_lulus: '',
    kelas_akhir: '',
    no_ijazah: '',
    lanjut_ke: '',
    pindah_ke: '',
    alasan_pindah: '',
    tgl_pindah: '',
    bekerja_di: '',
    catatan_penting: '',
});

function openEdit(row) {
    editing.value = row;
    editForm.clearErrors();
    editForm.status = String(row.status ?? 2);
    editForm.tahun_lulus = row.tahun_lulus ?? '';
    editForm.kelas_akhir = row.kelas_akhir ?? '';
    editForm.no_ijazah = row.no_ijazah ?? '';
    editForm.lanjut_ke = row.lanjut_ke ?? '';
    editForm.pindah_ke = row.pindah_ke ?? '';
    editForm.alasan_pindah = row.alasan_pindah ?? '';
    editForm.tgl_pindah = row.tgl_pindah ?? '';
    editForm.bekerja_di = row.bekerja_di ?? '';
    editForm.catatan_penting = row.catatan_penting ?? '';
    editOpen.value = true;
}

function saveEdit() {
    editForm.put(`/alumni/${editing.value.id_siswa}`, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
        },
    });
}

async function cancelAlumni(row) {
    if (await askConfirm(`Batalkan status alumni ${row.nama}? Siswa kembali berstatus aktif.`)) {
        router.delete(`/alumni/${row.id_siswa}`, { preserveScroll: true });
    }
}

const columns = [
    { key: 'nama', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-28' },
    { key: 'tahun_lulus', label: 'Tahun lulus', class: 'w-28' },
    { key: 'kelas_akhir', label: 'Kelas akhir', class: 'w-32' },
    { key: 'tujuan', label: 'Keterangan' },
    { key: 'aksi', label: 'Aksi', class: 'w-24 text-right' },
];

function tujuan(row) {
    if (row.status === 2) return row.lanjut_ke || '-';
    if (row.status === 3) return row.pindah_ke || row.alasan_pindah || '-';
    return row.bekerja_di || '-';
}
</script>

<template>
    <Head title="Data Alumni" />
    <AdminLayout title="Data Alumni">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Data Alumni" description="Siswa lulus, pindah, dan keluar tetap tercatat di buku induk." /></div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <p class="text-xs font-medium text-muted-foreground">Total alumni</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ props.stats.total }}</p>
                </div>
                <div class="rounded-xl border border-success/30 bg-success/10 p-4">
                    <p class="text-xs font-medium text-success">Lulus</p>
                    <p class="mt-1 text-2xl font-semibold text-success">{{ props.stats.lulus }}</p>
                </div>
                <div class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                    <p class="text-xs font-medium text-warning">Pindah</p>
                    <p class="mt-1 text-2xl font-semibold text-warning">{{ props.stats.pindah }}</p>
                </div>
                <div class="rounded-xl border border-border bg-muted/60 p-4">
                    <p class="text-xs font-medium text-muted-foreground">Keluar</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ props.stats.keluar }}</p>
                </div>
            </div>

            <form class="rounded-xl border border-border bg-card p-4 shadow-card" @submit.prevent="generate">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-52 flex-1">
                        <label for="alumni-kelas" :class="labelClass">Kelas</label>
                        <select id="alumni-kelas" v-model="generateForm.kelas" :class="inputClass" required>
                            <option value="" disabled>Pilih kelas</option>
                            <option v-for="kelas in props.classes" :key="kelas.id_kelas" :value="String(kelas.id_kelas)">
                                {{ kelas.nama_kelas }} ({{ kelas.jumlah }} siswa)
                            </option>
                        </select>
                        <p v-if="generateForm.errors.kelas" class="mt-1 text-xs text-destructive">{{ generateForm.errors.kelas }}</p>
                    </div>
                    <div class="w-40">
                        <label for="alumni-status" :class="labelClass">Status</label>
                        <select id="alumni-status" v-model="generateForm.status" :class="inputClass">
                            <option value="2">Lulus</option>
                            <option value="3">Pindah</option>
                            <option value="4">Keluar</option>
                        </select>
                        <p v-if="generateForm.errors.status" class="mt-1 text-xs text-destructive">{{ generateForm.errors.status }}</p>
                    </div>
                    <div class="w-36">
                        <label for="alumni-tahun" :class="labelClass">Tahun lulus</label>
                        <input
 id="alumni-tahun"
 v-model="generateForm.tahun_lulus"
 type="number"
 min="1900"
 max="2200"
 :class="inputClass"
 required
 />
                        <p v-if="generateForm.errors.tahun_lulus" class="mt-1 text-xs text-destructive">{{ generateForm.errors.tahun_lulus }}</p>
                    </div>
                    <button type="submit" :class="btnPrimary" :disabled="generateForm.processing">
                        <i class="fas fa-user-slash h-4 w-4" ></i>
                        {{ generateForm.processing ? 'Memproses…' : 'Jadikan alumni' }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    Seluruh siswa di kelas ini ditandai sebagai alumni dan dicatat di buku induk.
                </p>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama, NIS, NISN…" aria-label="Cari alumni" />
                </div>
                <select v-model="status" :class="[inputClass, 'w-auto min-w-40']" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="2">Lulus</option>
                    <option value="3">Pindah</option>
                    <option value="4">Keluar</option>
                </select>
            </div>

            <DataTable :columns="columns" :rows="props.alumni.data" row-key="id_siswa" :empty-icon="'graduation-cap'" empty="Belum ada data alumni.">
                <template #cell-nama="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-foreground">{{ row.nama }}</p>
                        <p class="text-xs text-muted-foreground">NIS {{ row.nis || '-' }} · NISN {{ row.nisn || '-' }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="row.status === 2
                            ? 'bg-success/10 text-success'
                            : row.status === 3
                                ? 'bg-warning/10 text-warning'
                                : 'bg-muted text-muted-foreground'"
                    >
                        {{ statusLabels[row.status] }}
                    </span>
                </template>
                <template #cell-tujuan="{ row }">
                    <span class="text-muted-foreground">{{ tujuan(row) }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button aria-label="Edit data alumni"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                            title="Edit data alumni"
                            @click="openEdit(row)"
                        >
                            <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            <span class="sr-only">Edit {{ row.nama }}</span>
                        </button>
                        <button aria-label="Batalkan status alumni"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                            title="Batalkan status alumni"
                            @click="cancelAlumni(row)"
                        >
                            <i class="fas fa-user-minus h-4 w-4" ></i>
                            <span class="sr-only">Batalkan alumni {{ row.nama }}</span>
                        </button>
                    </div>
                </template>
            </DataTable>

            <Pagination :paginator="props.alumni" />
        </div>

        <Modal :show="editOpen" title="Edit data alumni" max-width="max-w-2xl" @close="editOpen = false">
            <form id="alumni-edit" class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveEdit">
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium text-foreground">{{ editing?.nama }}</p>
                    <p class="text-xs text-muted-foreground">NIS {{ editing?.nis || '-' }} · {{ editing?.kelas_akhir || 'Tanpa kelas akhir' }}</p>
                </div>
                <div>
                    <label for="edit-status" :class="labelClass">Status</label>
                    <select id="edit-status" v-model="editForm.status" :class="inputClass">
                        <option value="2">Lulus</option>
                        <option value="3">Pindah</option>
                        <option value="4">Keluar</option>
                    </select>
                    <p v-if="editForm.errors.status" class="mt-1 text-xs text-destructive">{{ editForm.errors.status }}</p>
                </div>
                <div>
                    <label for="edit-tahun" :class="labelClass">Tahun lulus</label>
                    <input id="edit-tahun" v-model="editForm.tahun_lulus" type="number" min="1900" max="2200" :class="inputClass" />
                    <p v-if="editForm.errors.tahun_lulus" class="mt-1 text-xs text-destructive">{{ editForm.errors.tahun_lulus }}</p>
                </div>
                <div>
                    <label for="edit-kelas" :class="labelClass">Kelas akhir</label>
                    <input id="edit-kelas" v-model="editForm.kelas_akhir" :class="inputClass" />
                    <p v-if="editForm.errors.kelas_akhir" class="mt-1 text-xs text-destructive">{{ editForm.errors.kelas_akhir }}</p>
                </div>
                <div>
                    <label for="edit-ijazah" :class="labelClass">Nomor ijazah</label>
                    <input id="edit-ijazah" v-model="editForm.no_ijazah" :class="inputClass" />
                    <p v-if="editForm.errors.no_ijazah" class="mt-1 text-xs text-destructive">{{ editForm.errors.no_ijazah }}</p>
                </div>
                <div>
                    <label for="edit-lanjut" :class="labelClass">Melanjutkan ke</label>
                    <input id="edit-lanjut" v-model="editForm.lanjut_ke" :class="inputClass" />
                    <p v-if="editForm.errors.lanjut_ke" class="mt-1 text-xs text-destructive">{{ editForm.errors.lanjut_ke }}</p>
                </div>
                <div>
                    <label for="edit-pindah" :class="labelClass">Pindah ke</label>
                    <input id="edit-pindah" v-model="editForm.pindah_ke" :class="inputClass" />
                    <p v-if="editForm.errors.pindah_ke" class="mt-1 text-xs text-destructive">{{ editForm.errors.pindah_ke }}</p>
                </div>
                <div>
                    <label for="edit-alasan" :class="labelClass">Alasan pindah</label>
                    <input id="edit-alasan" v-model="editForm.alasan_pindah" :class="inputClass" />
                    <p v-if="editForm.errors.alasan_pindah" class="mt-1 text-xs text-destructive">{{ editForm.errors.alasan_pindah }}</p>
                </div>
                <div>
                    <label for="edit-tgl" :class="labelClass">Tanggal pindah</label>
                    <input id="edit-tgl" v-model="editForm.tgl_pindah" type="date" :class="inputClass" />
                    <p v-if="editForm.errors.tgl_pindah" class="mt-1 text-xs text-destructive">{{ editForm.errors.tgl_pindah }}</p>
                </div>
                <div>
                    <label for="edit-kerja" :class="labelClass">Bekerja di</label>
                    <input id="edit-kerja" v-model="editForm.bekerja_di" :class="inputClass" />
                    <p v-if="editForm.errors.bekerja_di" class="mt-1 text-xs text-destructive">{{ editForm.errors.bekerja_di }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="edit-catatan" :class="labelClass">Catatan penting</label>
                    <textarea id="edit-catatan" v-model="editForm.catatan_penting" rows="3" :class="inputClass" />
                    <p v-if="editForm.errors.catatan_penting" class="mt-1 text-xs text-destructive">{{ editForm.errors.catatan_penting }}</p>
                </div>
            </form>
            <template #footer>
                <button type="button" :class="btnSecondary" @click="editOpen = false">Batal</button>
                <button type="submit" form="alumni-edit" :class="btnPrimary" :disabled="editForm.processing">
                    {{ editForm.processing ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </template>
        </Modal>
    </AdminLayout>
</template>
