<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, inputClass } from '@/ui';
import { ref, watch } from 'vue';

const props = defineProps({
    siswa: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', kelas: null }) },
    kelasOptions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, terisi: 0, kosong: 0 }) },
});

const search = ref(props.filters.search ?? '');
const kelas = ref(props.filters.kelas ? String(props.filters.kelas) : '');
let timer = null;

watch([search, kelas], ([searchValue, kelasValue]) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/master/buku-induk',
            { search: searchValue || undefined, kelas: kelasValue || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const columns = [
    { key: 'nama', label: 'Siswa' },
    { key: 'kelas', label: 'Kelas', class: 'w-40' },
    { key: 'status', label: 'Status', class: 'w-32' },
    { key: 'tahun_lulus', label: 'Tahun lulus', class: 'w-28' },
    { key: 'aksi', label: 'Aksi', class: 'w-28 text-right' },
];
</script>

<template>
    <Head title="Buku Induk" />
    <AdminLayout title="Buku Induk">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Buku Induk" description="Data induk siswa, orang tua, dan riwayat kelulusan." />
                <Link href="/alumni" :class="btnPrimary">
                    <i class="fas fa-address-book h-4 w-4" ></i> Data alumni
                </Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <p class="text-xs font-medium text-muted-foreground">Total siswa</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ props.stats.total }}</p>
                </div>
                <div class="rounded-xl border border-success/30 bg-success/10 p-4">
                    <p class="text-xs font-medium text-success">Buku induk terisi</p>
                    <p class="mt-1 text-2xl font-semibold text-success">{{ props.stats.terisi }}</p>
                </div>
                <div class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                    <p class="text-xs font-medium text-warning">Belum diisi</p>
                    <p class="mt-1 text-2xl font-semibold text-warning">{{ props.stats.kosong }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama, NIS, NISN…" aria-label="Cari siswa" />
                </div>
                <select v-model="kelas" :class="[inputClass, 'w-auto min-w-44']" aria-label="Filter kelas">
                    <option value="">Semua kelas</option>
                    <option v-for="option in props.kelasOptions" :key="option.id_kelas" :value="String(option.id_kelas)">
                        {{ option.nama_kelas }}
                    </option>
                </select>
            </div>

            <DataTable :columns="columns" :rows="props.siswa.data" row-key="id_siswa" :empty-icon="'address-book'" empty="Belum ada data siswa.">
                <template #cell-nama="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-foreground">{{ row.nama }}</p>
                        <p class="text-xs text-muted-foreground">NIS {{ row.nis || '-' }} · NISN {{ row.nisn || '-' }}</p>
                    </div>
                </template>
                <template #cell-kelas="{ row }">
                    <span v-if="row.kelas" class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                        {{ row.kelas }}
                    </span>
                    <span v-else class="text-xs text-muted-foreground">Belum ada kelas</span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="row.status === 2
                            ? 'bg-success/10 text-success'
                            : row.status === 3
                                ? 'bg-warning/10 text-warning'
                                : row.status === 4
                                    ? 'bg-muted text-muted-foreground'
                                    : row.terisi
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'"
                    >
                        {{ row.status_label }}
                    </span>
                </template>
                <template #cell-tahun_lulus="{ row }">
                    <span class="text-muted-foreground">{{ row.tahun_lulus || '-' }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <a
                            :href="`/master/buku-induk/${row.id_siswa}/pdf`"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            title="Unduh PDF buku induk"
                        >
                            <i class="fas fa-file-download h-4 w-4" ></i>
                            <span class="sr-only">Unduh buku induk {{ row.nama }}</span>
                        </a>
                        <Link
                            :href="`/master/buku-induk/${row.id_siswa}`"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            :title="row.terisi ? 'Edit buku induk' : 'Lengkapi buku induk'"
                        >
                            <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            <span class="sr-only">{{ row.terisi ? 'Edit' : 'Lengkapi' }} buku induk {{ row.nama }}</span>
                        </Link>
                    </div>
                </template>
            </DataTable>

            <Pagination :paginator="props.siswa" />
        </div>
    </AdminLayout>
</template>
