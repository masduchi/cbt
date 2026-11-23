<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    guru: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', arsip: false }) },
});

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

const search = ref(props.filters.search ?? '');
const arsip = ref(Boolean(props.filters.arsip));
const showImport = ref(false);
let timer = null;

watch([search, arsip], ([value, arsipValue]) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/master/guru', { search: value || undefined, arsip: arsipValue || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    nip: '',
    nama_guru: '',
    kode_guru: '',
    jenis_kelamin: '',
    agama: '',
    tempat_lahir: '',
    tgl_lahir: '',
    email: '',
    no_hp: '',
    alamat_jalan: '',
    status_aktif: 'Aktif',
    username: '',
    password: '',
});

const columns = [
    { key: 'nama_guru', label: 'Guru' },
    { key: 'kontak', label: 'Kontak' },
    { key: 'username', label: 'Username', class: 'w-36' },
    { key: 'status', label: 'Status', class: 'w-24' },
    { key: 'aksi', label: 'Aksi', class: 'w-24 text-right' },
];

function openCreate() {
    editing.value = null;
    form.reset();
    form.status_aktif = 'Aktif';
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nip = row.nip ?? '';
    form.nama_guru = row.nama_guru ?? '';
    form.kode_guru = row.kode_guru ?? '';
    form.jenis_kelamin = row.jenis_kelamin ?? '';
    form.agama = row.agama ?? '';
    form.tempat_lahir = row.tempat_lahir ?? '';
    form.tgl_lahir = row.tgl_lahir ?? '';
    form.email = row.email ?? '';
    form.no_hp = row.no_hp ?? '';
    form.alamat_jalan = row.alamat_jalan ?? '';
    form.status_aktif = row.status_aktif ?? 'Aktif';
    form.username = row.username ?? '';
    form.password = '';
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
        form.put(`/master/guru/${editing.value.id_guru}`, options);
    } else {
        form.post('/master/guru', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus guru ${row.nama_guru}? Guru yang memiliki riwayat akan diarsipkan dan akunnya dinonaktifkan.`)) {
        router.delete(`/master/guru/${row.id_guru}`, { preserveScroll: true });
    }
}

function restore(row) {
    router.post(`/master/guru/${row.id_guru}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Data Guru" />
    <AdminLayout title="Data Guru">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Data Guru">
                    <template #description>{{ props.guru.total }} guru terdaftar.</template>
                
                </PageHeader>
                <div v-if="isAdmin" class="flex items-center gap-2">
                    <button type="button" :class="btnSecondary" @click="showImport = true">
                        <i class="fas fa-upload h-4 w-4" ></i> Impor Excel/CSV
                    </button>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Guru
                    </button>
                </div>
            </div>

            <ImportErrors />

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama, NIP, username…" aria-label="Cari nama, NIP, username" />
                </div>
                <button type="button" :class="arsip ? btnPrimary : btnSecondary" :aria-pressed="arsip" @click="arsip = !arsip">
                    <i class="fas fa-archive h-4 w-4" ></i> {{ arsip ? 'Lihat aktif' : 'Lihat arsip' }}
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.guru.data" row-key="id_guru" :empty-icon="'user-cog'" empty="Belum ada data guru.">
                <template #cell-nama_guru="{ row }">
                    <p class="text-sm font-medium text-foreground">{{ row.nama_guru }}</p>
                    <p class="text-xs text-muted-foreground">NIP {{ row.nip }} · {{ row.jenis_kelamin || '-' }}</p>
                </template>
                <template #cell-kontak="{ row }">
                    <p class="text-xs text-muted-foreground">{{ row.email || '-' }}</p>
                    <p class="text-xs text-muted-foreground">{{ row.no_hp || '-' }}</p>
                </template>
                <template #cell-username="{ row }">
                    <span class="text-muted-foreground">{{ row.username || '-' }}</span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="row.status_aktif === 'Nonaktif' ? 'bg-muted text-muted-foreground' : 'bg-success/10 text-success'"
                    >
                        {{ row.status_aktif || 'Aktif' }}
                    </span>
                </template>
                <template #cell-aksi="{ row }">
                    <div v-if="isAdmin" class="flex items-center justify-end gap-1">
                        <button aria-label="Pulihkan dari arsip" type="button" v-if="props.filters.arsip" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-success/10 hover:text-success focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Pulihkan dari arsip" @click="restore(row)">
                            <i class="fas fa-undo-alt h-4 w-4" ></i>
                        </button>
                        <template v-else>
                            <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                                <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            </button>
                            <button aria-label="Hapus atau arsipkan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus atau arsipkan" @click="destroy(row)">
                                <i class="fas fa-trash-alt h-4 w-4" ></i>
                            </button>
                        </template>
                    </div>
                </template>
                <template #empty-action>
                    <button v-if="isAdmin" type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Guru
                    </button>
                </template>
            </DataTable>

            <Pagination :paginator="props.guru" />
        </div>

        <ImportModal
            :show="showImport"
            title="Impor Guru"
            action="/master/guru/import"
            :columns="['nip', 'nama_guru', 'jenis_kelamin', 'email', 'username', 'password', 'no_hp', 'kode_guru', 'status_aktif']"
            description="Akun login guru dibuat otomatis. Jika kolom password kosong, password awal memakai NIP dan guru diminta menggantinya saat login."
            template-url="/master/guru/import-template"
            @close="showImport = false"
        />

        <Modal :show="showModal" :title="editing ? 'Edit Guru' : 'Tambah Guru'" max-width="max-w-2xl" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="nama-guru">Nama Guru *</label>
                        <input v-model="form.nama_guru" :class="inputClass" id="nama-guru" />
                        <p v-if="form.errors.nama_guru" class="mt-1 text-xs text-destructive">{{ form.errors.nama_guru }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="nip">NIP *</label>
                        <input v-model="form.nip" :class="inputClass" id="nip" />
                        <p v-if="form.errors.nip" class="mt-1 text-xs text-destructive">{{ form.errors.nip }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kode-guru">Kode Guru</label>
                        <input v-model="form.kode_guru" :class="inputClass" maxlength="6" id="kode-guru" />
                    </div>
                    <div>
                        <label :class="labelClass" for="jenis-kelamin">Jenis Kelamin</label>
                        <select v-model="form.jenis_kelamin" :class="inputClass" id="jenis-kelamin">
                            <option value="">Pilih</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="email">Email</label>
                        <input v-model="form.email" type="email" :class="inputClass" id="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="no-hp">No. HP</label>
                        <input v-model="form.no_hp" :class="inputClass" id="no-hp" />
                    </div>
                    <div>
                        <label :class="labelClass" for="tempat-lahir">Tempat Lahir</label>
                        <input v-model="form.tempat_lahir" :class="inputClass" id="tempat-lahir" />
                    </div>
                    <div>
                        <label :class="labelClass" for="tanggal-lahir">Tanggal Lahir</label>
                        <input v-model="form.tgl_lahir" type="date" :class="inputClass" id="tanggal-lahir" />
                        <p v-if="form.errors.tgl_lahir" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_lahir }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="alamat">Alamat</label>
                        <textarea v-model="form.alamat_jalan" rows="2" :class="inputClass" id="alamat" />
                    </div>
                    <div>
                        <label :class="labelClass" for="username">Username *</label>
                        <input v-model="form.username" :class="inputClass" autocomplete="off" id="username" />
                        <p v-if="form.errors.username" class="mt-1 text-xs text-destructive">{{ form.errors.username }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">Password {{ editing ? '(opsional)' : '*' }}</label>
                        <input v-model="form.password" type="password" :class="inputClass" autocomplete="new-password" aria-label="Password baru" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-destructive">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="status">Status</label>
                        <select v-model="form.status_aktif" :class="inputClass" id="status">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
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
