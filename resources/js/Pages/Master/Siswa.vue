<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ExportLink from '@/Components/ExportLink.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, inputClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    siswa: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', kelas: '', arsip: false }) },
    kelasOptions: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

const search = ref(props.filters.search ?? '');
const kelas = ref(props.filters.kelas ?? '');
const arsip = ref(Boolean(props.filters.arsip));
const showImport = ref(false);
let timer = null;

watch([search, kelas, arsip], ([searchValue, kelasValue, arsipValue]) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/master/siswa',
            { search: searchValue || undefined, kelas: kelasValue || undefined, arsip: arsipValue || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const columns = [
    { key: 'siswa', label: 'Siswa' },
    { key: 'nisn', label: 'NISN', class: 'hidden sm:table-cell' },
    { key: 'kelas', label: 'Kelas', class: 'hidden md:table-cell' },
    { key: 'username', label: 'Username', class: 'hidden lg:table-cell' },
    { key: 'aksi', label: 'Aksi', class: 'text-right' },
];

async function destroy(row) {
    if (await askConfirm(`Hapus siswa ${row.nama}? Siswa dengan riwayat nilai akan diarsipkan, bukan dihapus permanen.`)) {
        router.delete(`/master/siswa/${row.id_siswa}`, { preserveScroll: true });
    }
}

function restore(row) {
    router.post(`/master/siswa/${row.id_siswa}/restore`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Data Siswa" />
    <AdminLayout title="Data Siswa">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Data Siswa">
                    <template #description>{{ props.siswa.total }} siswa terdaftar.</template>
                
                </PageHeader>
                <div v-if="isAdmin" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                    <ExportLink href="/master/siswa/ekspor" label="Ekspor Excel" />
                    <ExportLink href="/master/siswa/ekspor/dapodik" label="Ekspor Dapodik" />
                    <ExportLink href="/master/siswa/ekspor/emis" label="Ekspor EMIS" />
                    <button type="button" :class="btnSecondary" @click="showImport = true">
                        <i class="fas fa-upload h-4 w-4" ></i> Impor Excel/CSV
                    </button>
                    <Link href="/master/siswa/create" :class="btnPrimary" class="col-span-2 sm:col-auto">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Siswa
                    </Link>
                </div>
            </div>

            <ImportErrors />

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama, NIS, NISN, username…" aria-label="Cari nama, NIS, NISN, username" />
                </div>
                <select v-model="kelas" :class="[inputClass, 'sm:max-w-56']" aria-label="Filter kelas">
                    <option value="">Semua kelas</option>
                    <option v-for="option in props.kelasOptions" :key="option.id_kelas" :value="option.id_kelas">
                        {{ option.nama_kelas }}
                    </option>
                </select>
                <button
                    type="button"
                    :class="arsip ? btnPrimary : btnSecondary"
                    class="w-full sm:w-auto"
                    :aria-pressed="arsip"
                    @click="arsip = !arsip"
                >
                    <i class="fas fa-archive h-4 w-4" ></i> {{ arsip ? 'Lihat aktif' : 'Lihat arsip' }}
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.siswa.data" row-key="id_siswa" :empty-icon="'users'" empty="Belum ada data siswa.">
                <template #cell-siswa="{ row }">
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                            {{ row.nama.charAt(0).toUpperCase() }}
                            <img
                                v-if="row.foto_url"
                                :src="row.foto_url"
                                :alt="row.nama"
                                class="absolute inset-0 h-9 w-9 rounded-full object-cover ring-1 ring-border"
                                @error="$event.target.remove()"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-foreground">{{ row.nama }}</p>
                            <p class="text-xs text-muted-foreground">NIS {{ row.nis }}<template v-if="row.jenis_kelamin"> · {{ row.jenis_kelamin }}</template></p>
                        </div>
                    </div>
                </template>
                <template #cell-nisn="{ row }">
                    <span class="text-muted-foreground">{{ row.nisn }}</span>
                </template>
                <template #cell-kelas="{ row }">
                    <span v-if="row.kelas" class="whitespace-nowrap rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                        {{ row.kelas }}
                    </span>
                    <span v-else class="whitespace-nowrap text-xs text-muted-foreground">Belum ada kelas</span>
                </template>
                <template #cell-username="{ row }">
                    <span class="text-muted-foreground">{{ row.username }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div v-if="isAdmin" class="flex items-center justify-end gap-1">
                        <button aria-label="Pulihkan dari arsip" type="button"
                            v-if="row.arsip"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-success/10 hover:text-success focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                            title="Pulihkan dari arsip"
                            @click="restore(row)"
                        >
                            <i class="fas fa-undo-alt h-4 w-4" ></i>
                        </button>
                        <template v-else>
                            <Link :href="`/master/siswa/${row.id_siswa}/edit`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground" title="Edit">
                                <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            </Link>
                            <button aria-label="Hapus atau arsipkan" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus atau arsipkan" @click="destroy(row)">
                                <i class="fas fa-trash-alt h-4 w-4" ></i>
                            </button>
                        </template>
                    </div>
                </template>
                <template #empty-action>
                    <Link v-if="isAdmin" href="/master/siswa/create" :class="btnPrimary">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Siswa
                    </Link>
                </template>
            </DataTable>

            <Pagination :paginator="props.siswa" />
        </div>

        <ImportModal
            :show="showImport"
            title="Impor Siswa"
            action="/master/siswa/import"
            :columns="['nisn', 'nis', 'nama', 'jenis_kelamin', 'username', 'password', 'kelas_awal', 'tahun_masuk', 'email', 'hp', 'nik', 'warga_negara']"
            description="Baris dengan NISN atau username yang sudah terdaftar akan dilewati."
            template-url="/master/siswa/import-template"
            @close="showImport = false"
        />
    </AdminLayout>
</template>
