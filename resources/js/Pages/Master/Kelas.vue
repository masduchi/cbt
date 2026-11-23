<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    kelas: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    jurusan: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
    arsip: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';
const arsip = ref(props.arsip);
const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(arsip, (value) => {
    router.get('/master/kelas', { arsip: value || undefined, search: search.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
});

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/master/kelas', { arsip: arsip.value || undefined, search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_kelas: '', kode_kelas: '', level_id: null, jurusan_id: null, guru_id: null });

const columns = [
    { key: 'nama_kelas', label: 'Kelas' },
    { key: 'level', label: 'Tingkat', class: 'w-24' },
    { key: 'jurusan', label: 'Jurusan', class: 'w-40' },
    { key: 'wali', label: 'Wali Kelas' },
    { key: 'jumlah', label: 'Siswa', class: 'w-24' },
    { key: 'aksi', label: 'Aksi', class: 'w-32 text-right' },
];

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nama_kelas = row.nama_kelas;
    form.kode_kelas = row.kode_kelas ?? '';
    form.level_id = row.level_id;
    form.jurusan_id = row.jurusan_id ?? null;
    form.guru_id = row.guru_id ?? null;
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
        form.put(`/master/kelas/${editing.value.id_kelas}`, options);
    } else {
        form.post('/master/kelas', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus kelas ${row.nama_kelas}? Kelas dengan riwayat akan diarsipkan.`)) {
        router.delete(`/master/kelas/${row.id_kelas}`, { preserveScroll: true });
    }
}

function restore(row) {
    router.post(`/master/kelas/${row.id_kelas}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kelas / Rombel" />
    <AdminLayout title="Kelas / Rombel">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Kelas / Rombel">
                    <template #description>Periode {{ props.periode }} · {{ props.kelas.length }} kelas.</template>
                
                </PageHeader>
                <div class="flex items-center gap-2">
                    <button v-if="isAdmin" type="button" :class="arsip ? btnPrimary : btnSecondary" :aria-pressed="arsip" @click="arsip = !arsip">
                        <i class="fas fa-archive h-4 w-4" ></i> {{ arsip ? 'Lihat aktif' : 'Lihat arsip' }}
                    </button>
                    <button type="button" v-if="isAdmin" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Kelas
                    </button>
                </div>
            </div>

            <div class="relative w-full max-w-xs">
                <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama atau kode kelas…" aria-label="Cari kelas" />
            </div>

            <DataTable
                :columns="columns"
                :rows="props.kelas"
                row-key="id_kelas"
                :empty-icon="'school'"
                :empty="props.filters.search ? 'Tidak ada kelas yang cocok dengan pencarian.' : 'Belum ada kelas pada periode aktif.'"
            >
                <template #cell-nama_kelas="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_kelas }}</span>
                    <span v-if="row.kode_kelas" class="ml-2 rounded bg-muted px-1.5 py-0.5 text-2xs font-medium text-muted-foreground">{{ row.kode_kelas }}</span>
                </template>
                <template #cell-level="{ row }">
                    <span class="text-muted-foreground">{{ row.level?.level ?? '-' }}</span>
                </template>
                <template #cell-jurusan="{ row }">
                    <span class="text-muted-foreground">{{ row.jurusan?.nama_jurusan ?? '-' }}</span>
                </template>
                <template #cell-wali="{ row }">
                    <span class="text-muted-foreground">{{ row.wali_kelas?.nama_guru ?? '-' }}</span>
                </template>
                <template #cell-jumlah="{ row }">
                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">{{ row.siswa_count }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button aria-label="Pulihkan dari arsip" type="button" v-if="arsip && isAdmin" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-success/10 hover:text-success focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Pulihkan dari arsip" @click="restore(row)">
                            <i class="fas fa-undo-alt h-4 w-4" ></i>
                        </button>
                        <template v-else>
                            <Link :href="`/master/kelas/${row.id_kelas}/anggota`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary" title="Anggota kelas">
                                <i class="fas fa-users h-4 w-4" ></i>
                            </Link>
                            <template v-if="isAdmin">
                                <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                                    <i class="fas fa-pencil-alt h-4 w-4" ></i>
                                </button>
                                <button aria-label="Hapus atau arsipkan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus atau arsipkan" @click="destroy(row)">
                                    <i class="fas fa-trash-alt h-4 w-4" ></i>
                                </button>
                            </template>
                        </template>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Kelas' : 'Tambah Kelas'" max-width="max-w-xl" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="nama-kelas">Nama Kelas *</label>
                        <input v-model="form.nama_kelas" :class="inputClass" placeholder="Contoh: X MIPA 1" id="nama-kelas" />
                        <p v-if="form.errors.nama_kelas" class="mt-1 text-xs text-destructive">{{ form.errors.nama_kelas }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kode-kelas">Kode Kelas</label>
                        <input v-model="form.kode_kelas" :class="inputClass" placeholder="XMIPA1" id="kode-kelas" />
                    </div>
                    <div>
                        <label :class="labelClass" for="tingkat">Tingkat *</label>
                        <select v-model.number="form.level_id" :class="inputClass" id="tingkat">
                            <option :value="null" disabled>Pilih tingkat</option>
                            <option v-for="level in props.levels" :key="level.id_level" :value="level.id_level">Kelas {{ level.level }}</option>
                        </select>
                        <p v-if="form.errors.level_id" class="mt-1 text-xs text-destructive">{{ form.errors.level_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="jurusan">Jurusan</label>
                        <select v-model="form.jurusan_id" :class="inputClass" id="jurusan">
                            <option :value="null">Tanpa jurusan</option>
                            <option v-for="item in props.jurusan" :key="item.id_jurusan" :value="item.id_jurusan">{{ item.nama_jurusan }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="wali-kelas">Wali Kelas</label>
                        <select v-model="form.guru_id" :class="inputClass" id="wali-kelas">
                            <option :value="null">Belum ditentukan</option>
                            <option v-for="item in props.guru" :key="item.id_guru" :value="item.id_guru">{{ item.nama_guru }}</option>
                        </select>
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
