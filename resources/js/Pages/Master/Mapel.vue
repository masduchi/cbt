<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { computed, ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    mapel: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    kelompokOptions: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/master/mapel', { search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const showImport = ref(false);
const editing = ref(null);
const form = useForm({
    nama_mapel: '',
    kode: '',
    kelompok: '-',
    bobot_p: 1,
    bobot_k: 1,
    urutan: null,
    status: true,
});

const columns = computed(() => [
    { key: 'no', label: 'No', class: 'w-12' },
    { key: 'nama_mapel', label: 'Mata Pelajaran' },
    { key: 'kode', label: 'Kode', class: 'w-24' },
    { key: 'kelompok', label: 'Kelompok', class: 'w-24' },
    { key: 'bobot', label: 'Bobot P/K', class: 'w-28' },
    { key: 'status', label: 'Status', class: 'w-24' },
    ...(isAdmin ? [{ key: 'aksi', label: 'Aksi', class: 'w-20 text-right' }] : []),
]);

function openCreate() {
    editing.value = null;
    form.reset();
    form.status = true;
    form.kelompok = '-';
    form.bobot_p = 1;
    form.bobot_k = 1;
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nama_mapel = row.nama_mapel;
    form.kode = row.kode ?? '';
    form.kelompok = row.kelompok ?? '-';
    form.bobot_p = row.bobot_p ?? 1;
    form.bobot_k = row.bobot_k ?? 1;
    form.urutan = row.urutan ?? null;
    form.status = row.status == 1;
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    };

    if (editing.value) {
        form.put(`/master/mapel/${editing.value.id_mapel}`, options);
    } else {
        form.post('/master/mapel', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus mata pelajaran ${row.nama_mapel}? Mapel yang sudah dipakai akan dinonaktifkan.`)) {
        router.delete(`/master/mapel/${row.id_mapel}`, { preserveScroll: true });
    }
}

function restore(row) {
    router.post(`/master/mapel/${row.id_mapel}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mata Pelajaran" />
    <AdminLayout title="Mata Pelajaran">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Data Mata Pelajaran">
                    <template #description>{{ props.mapel.total }} mata pelajaran terdaftar.</template>
                
                </PageHeader>
                <div v-if="isAdmin" class="flex flex-wrap items-center gap-2">
                    <Link href="/master/kelompok-mapel" :class="btnSecondary">
                        <i class="fas fa-folder-tree h-4 w-4" ></i> Kelompok Mapel
                    </Link>
                    <button type="button" :class="btnSecondary" @click="showImport = true">
                        <i class="fas fa-upload h-4 w-4" ></i> Impor Excel/CSV
                    </button>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Mapel
                    </button>
                </div>
            </div>

            <ImportErrors />

            <div class="relative w-full max-w-xs">
                <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama atau kode mapel…" aria-label="Cari mata pelajaran" />
            </div>

            <DataTable
                :columns="columns"
                :rows="props.mapel.data"
                row-key="id_mapel"
                :empty-icon="'book-open'"
                :empty="props.filters.search ? 'Tidak ada mata pelajaran yang cocok dengan pencarian.' : 'Belum ada mata pelajaran.'"
            >
                <template #cell-no="{ index }">
                    <span class="text-muted-foreground">{{ index + 1 }}</span>
                </template>
                <template #cell-nama_mapel="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_mapel }}</span>
                </template>
                <template #cell-kode="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.kode || '-' }}</span>
                </template>
                <template #cell-kelompok="{ row }">
                    <span class="text-muted-foreground">{{ row.kelompok || '-' }}</span>
                </template>
                <template #cell-bobot="{ row }">
                    <span class="text-muted-foreground">{{ row.bobot_p }} / {{ row.bobot_k }}</span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="row.status == 1 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                    >
                        {{ row.status == 1 ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button aria-label="Aktifkan kembali" type="button" v-if="row.status != 1" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-success/10 hover:text-success focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Aktifkan kembali" @click="restore(row)">
                            <i class="fas fa-undo-alt h-4 w-4" ></i>
                        </button>
                        <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                            <i class="fas fa-pencil-alt h-4 w-4" ></i>
                        </button>
                        <button aria-label="Hapus atau nonaktifkan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus atau nonaktifkan" @click="destroy(row)">
                            <i class="fas fa-trash-alt h-4 w-4" ></i>
                        </button>
                    </div>
                </template>
                <template #empty-action>
                    <button v-if="isAdmin" type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Mapel
                    </button>
                </template>
            </DataTable>

            <Pagination :paginator="props.mapel" />
        </div>

        <ImportModal
            :show="showImport"
            title="Impor Mata Pelajaran"
            action="/master/mapel/import"
            :columns="['nama_mapel', 'kode', 'kelompok', 'bobot_p', 'bobot_k', 'urutan', 'status']"
            description="Mapel dengan nama yang sudah terdaftar akan dilewati. Kolom urutan boleh kosong agar terisi otomatis."
            template-url="/master/mapel/import-template"
            @close="showImport = false"
        />

        <Modal :show="showModal" :title="editing ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'" max-width="max-w-2xl" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="nama-mata-pelajaran">Nama Mata Pelajaran</label>
                        <input v-model="form.nama_mapel" :class="inputClass" placeholder="Contoh: Matematika" id="nama-mata-pelajaran" />
                        <p v-if="form.errors.nama_mapel" class="mt-1 text-xs text-destructive">{{ form.errors.nama_mapel }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kode">Kode</label>
                        <input v-model="form.kode" :class="inputClass" placeholder="MTK" id="kode" />
                    </div>
                    <div>
                        <label :class="labelClass" for="kelompok">Kelompok</label>
                        <select v-model="form.kelompok" :class="inputClass" id="kelompok">
                            <option value="-">Tanpa kelompok</option>
                            <option v-for="item in props.kelompokOptions" :key="item.id_kel_mapel" :value="item.kode_kel_mapel">
                                {{ item.kode_kel_mapel }} · {{ item.nama_kel_mapel }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-pilihan-ganda">Bobot Pilihan Ganda</label>
                        <input v-model.number="form.bobot_p" type="number" min="0" max="100" :class="inputClass" id="bobot-pilihan-ganda" />
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-uraian">Bobot Uraian</label>
                        <input v-model.number="form.bobot_k" type="number" min="0" max="100" :class="inputClass" id="bobot-uraian" />
                    </div>
                    <div>
                        <label :class="labelClass" for="urutan">Urutan</label>
                        <input v-model.number="form.urutan" type="number" min="0" :class="inputClass" placeholder="Otomatis" id="urutan" />
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input v-model="form.status" type="checkbox" class="rounded border-border text-primary focus:ring-ring" />
                            Aktif
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
