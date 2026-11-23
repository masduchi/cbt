<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnDanger, btnPrimary, btnSecondary, cardClass, formatTanggalWaktu, inputClass, labelClass } from '@/ui';
import { askConfirm } from '@/confirm';

const props = defineProps({
    driver: { type: String, required: true },
    migrations: { type: Object, required: true },
    tables: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    backups: { type: Array, default: () => [] },
    productionWarnings: { type: Array, default: () => [] },
});

const form = useForm({});
const query = ref('');

const numberFormat = new Intl.NumberFormat('id-ID');

const filteredTables = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return props.tables;
    }

    return props.tables.filter((table) => table.name.toLowerCase().includes(term));
});

const filteredRows = computed(() =>
    filteredTables.value.reduce((total, table) => total + (table.rows ?? 0), 0),
);

const latestBackup = computed(() => props.backups[0] ?? null);

const truncateForm = useForm({ table: '', confirm: '' });
const truncateOpen = ref(false);

const truncatable = computed(() => props.tables.filter((table) => table.category === 2));

const restoreForm = useForm({ confirm: '' });
const restoreOpen = ref(false);
const restoreTarget = ref(null);

function runBackup() {
    form.post('/setting/database/backup', { preserveScroll: true });
}

async function destroyBackup(backup) {
    if (!await askConfirm(`Hapus backup ${backup.name}? Berkas dump dan arsip di dalamnya ikut terhapus.`)) {
        return;
    }

    router.delete(`/setting/database/backup/${encodeURIComponent(backup.name)}`, { preserveScroll: true });
}

function openRestore(backup) {
    restoreTarget.value = backup;
    restoreForm.reset();
    restoreForm.clearErrors();
    restoreOpen.value = true;
}

/**
 * Nama backup harus diketik ulang; nilai konfirmasi tidak pernah diisi otomatis
 * agar satu klik tidak bisa menimpa seluruh database.
 */
function submitRestore() {
    if (!restoreTarget.value) {
        return;
    }

    restoreForm.post(`/setting/database/backup/${encodeURIComponent(restoreTarget.value.name)}/restore`, {
        preserveScroll: true,
        onSuccess: () => {
            restoreOpen.value = false;
            restoreTarget.value = null;
            restoreForm.reset();
        },
    });
}

function openTruncate() {
    truncateForm.reset();
    truncateForm.clearErrors();
    truncateOpen.value = true;
}

function submitTruncate() {
    truncateForm.post('/setting/database/truncate', {
        preserveScroll: true,
        onSuccess: () => {
            truncateOpen.value = false;
            truncateForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Database & Backup" />
    <AdminLayout title="Database & Backup">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Database &amp; Backup" description="Pantau status migrasi, jumlah baris, ukuran tabel, buat atau hapus cadangan, dan kosongkan tabel transaksi bila perlu." /></div>

            <div v-if="productionWarnings.length" class="rounded-lg border border-warning/30 bg-warning/10 p-4">
                <p class="flex items-center gap-2 text-sm font-medium text-warning">
                    <i class="fas fa-exclamation-triangle h-4 w-4" aria-hidden="true"></i>
                    Konfigurasi produksi belum aman
                </p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-warning">
                    <li v-for="warning in productionWarnings" :key="warning">{{ warning }}</li>
                </ul>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tabel</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ numberFormat.format(tables.length) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Driver {{ driver }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total baris</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ numberFormat.format(totals.rows) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Seluruh tabel aplikasi</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ukuran data</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">{{ totals.size }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Data + indeks</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Migrasi</p>
                    <p class="mt-1 text-2xl font-semibold text-foreground">
                        {{ numberFormat.format(migrations.applied) }}/{{ numberFormat.format(migrations.total) }}
                    </p>
                    <p class="mt-1 text-xs" :class="migrations.pending.length ? 'text-warning' : 'text-success'">
                        {{ migrations.pending.length ? `${migrations.pending.length} belum diterapkan` : 'Semua sudah diterapkan' }}
                    </p>
                </div>
            </div>

            <section :class="cardClass">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-download h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Cadangan database</h2>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-muted-foreground">
                        Backup berisi dump database (<code class="rounded bg-muted px-1.5 py-0.5 text-xs">database.sql.gz</code>)
                        dan arsip berkas publik (<code class="rounded bg-muted px-1.5 py-0.5 text-xs">storage-public.zip</code>).
                        Backup lama otomatis dihapus setelah 14 hari.
                    </p>
                    <p v-if="latestBackup" class="text-xs text-muted-foreground">
                        Cadangan terakhir: <span class="font-medium text-foreground">{{ latestBackup.name }}</span>
                        ({{ latestBackup.size }}, {{ formatTanggalWaktu(latestBackup.created_at) }})
                        <span v-if="latestBackup.verified" class="text-success">· terverifikasi</span>
                        <span v-else class="text-warning">· belum diverifikasi</span>.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">Belum ada cadangan tersimpan.</p>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button type="button" :class="btnPrimary" :disabled="form.processing" @click="runBackup">
                            <i v-if="form.processing" class="fas fa-spinner h-4 w-4 animate-spin" aria-hidden="true" ></i>
                            <i v-else class="fas fa-download h-4 w-4" aria-hidden="true" ></i>
                            {{ form.processing ? 'Membuat backup…' : 'Jalankan backup sekarang' }}
                        </button>
                        <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <i class="fas fa-shield-alt h-3.5 w-3.5 text-success" aria-hidden="true" ></i>
                            Backup tidak mengubah data aplikasi.
                        </span>
                    </div>
                </div>

                <div v-if="backups.length" class="border-t border-border">
                    <ul class="divide-y divide-border">
                        <li v-for="backup in backups" :key="backup.name" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ backup.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatTanggalWaktu(backup.created_at) }} · {{ backup.size }}</p>
                                <p class="mt-1 text-xs">
                                    <span v-if="backup.verified" class="inline-flex items-center gap-1 text-success">
                                        <i class="fas fa-check-circle h-3 w-3" aria-hidden="true"></i> Terverifikasi dapat dipulihkan
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-warning">
                                        <i class="fas fa-circle-exclamation h-3 w-3" aria-hidden="true"></i> Belum diverifikasi
                                    </span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <ul class="flex flex-wrap gap-1.5">
                                    <li
                                        v-for="file in backup.files"
                                        :key="file"
                                        class="rounded bg-muted px-2 py-0.5 text-2xs text-muted-foreground"
                                    >
                                        {{ file }}
                                    </li>
                                </ul>
                                <button type="button" :class="btnSecondary" :aria-label="`Pulihkan backup ${backup.name}`" @click="openRestore(backup)">
                                    <i class="fas fa-rotate-left h-3.5 w-3.5" ></i> Pulihkan
                                </button>
                                <button type="button" :class="btnDanger" :aria-label="`Hapus backup ${backup.name}`" @click="destroyBackup(backup)">
                                    <i class="fas fa-trash-alt h-3.5 w-3.5" ></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <section :class="cardClass">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Kosongkan tabel transaksi</h2>
                        <p class="text-xs text-muted-foreground">Hanya tabel transaksi yang dapat dikosongkan. Tabel master dan sistem tetap terlindungi.</p>
                    </div>
                    <button type="button" :class="btnDanger" :disabled="!truncatable.length" @click="openTruncate">
                        <i class="fas fa-trash-alt h-4 w-4" ></i> Kosongkan tabel
                    </button>
                </div>
                <p class="px-5 py-4 text-sm text-muted-foreground">
                    {{ truncatable.length }} tabel transaksi tersedia (nilai rapor, nilai CBT, soal siswa, jurnal, dan sejenisnya).
                    Tindakan ini permanen dan sebaiknya didahului backup.
                </p>
            </section>

            <section :class="cardClass">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-tasks h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Status migrasi</h2>
                </div>
                <div class="space-y-3 p-5 text-sm">
                    <p class="text-muted-foreground">
                        {{ numberFormat.format(migrations.applied) }} migrasi sudah diterapkan dalam
                        {{ numberFormat.format(migrations.batches) }} batch, total
                        {{ numberFormat.format(migrations.total) }} berkas migrasi.
                    </p>
                    <div
                        v-if="migrations.pending.length"
                        class="rounded-lg border border-warning/30 bg-warning/10 p-4"
                    >
                        <p class="flex items-center gap-2 text-sm font-medium text-warning">
                            <i class="fas fa-exclamation-triangle h-4 w-4" aria-hidden="true" ></i>
                            {{ migrations.pending.length }} migrasi belum diterapkan
                        </p>
                        <ul class="mt-2 space-y-1 text-xs text-warning">
                            <li v-for="name in migrations.pending" :key="name" class="break-all font-mono">{{ name }}</li>
                        </ul>
                        <p class="mt-2 text-xs text-warning">
                            Jalankan <code class="rounded bg-warning/15 px-1.5 py-0.5">php artisan migrate</code> melalui terminal server.
                        </p>
                    </div>
                    <p v-else class="flex items-center gap-2 text-sm text-success">
                        <i class="fas fa-check-circle h-4 w-4" aria-hidden="true" ></i>
                        Semua migrasi sudah diterapkan.
                    </p>
                </div>
            </section>

            <section :class="cardClass">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <i class="fas fa-table h-4 w-4 text-primary" aria-hidden="true" ></i> Daftar tabel
                    </h2>
                    <div class="w-full sm:w-64">
                        <label for="table-search" class="sr-only">Cari nama tabel</label>
                        <div class="relative">
                            <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" ></i>
                            <input
 id="table-search"
 v-model="query"
 type="search"
 :class="inputClass"
 class="pl-9"
 placeholder="Cari nama tabel…"
 aria-describedby="table-search-hint"
 aria-label="Cari nama tabel" />
                        </div>
                    </div>
                </div>

                <p id="table-search-hint" class="sr-only">
                    Menyaring daftar tabel berdasarkan nama. Jumlah baris dan ukuran tetap ditampilkan per tabel.
                </p>

                <div class="max-h-[520px] overflow-auto">
                    <table class="data-table w-full text-left text-sm">
                        <caption class="sr-only">Nama tabel, jumlah baris, dan ukuran data</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">Nama tabel</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">Baris</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">Ukuran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="table in filteredTables" :key="table.name">
                                <td class="px-5 py-2.5 font-mono text-xs text-foreground">{{ table.name }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-foreground">
                                    {{ table.rows === null ? '-' : numberFormat.format(table.rows) }}
                                </td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-muted-foreground">{{ table.size }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border bg-muted/60 text-xs text-muted-foreground">
                            <tr>
                                <th scope="row" class="px-5 py-2.5 text-left font-medium">
                                    {{ numberFormat.format(filteredTables.length) }} tabel ditampilkan
                                </th>
                                <td class="px-5 py-2.5 text-right font-medium tabular-nums">{{ numberFormat.format(filteredRows) }}</td>
                                <td class="px-5 py-2.5 text-right font-medium tabular-nums">{{ totals.size }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-if="!filteredTables.length" class="px-5 py-10 text-center">
                    <i class="fas fa-database mx-auto h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                    <p class="mt-3 text-sm font-medium text-foreground">Tidak ada tabel yang cocok</p>
                    <p class="mt-1 text-xs text-muted-foreground">Ubah kata kunci pencarian untuk melihat tabel lain.</p>
                </div>

                <p class="border-t border-border px-5 py-3 text-xs text-muted-foreground">
                    Halaman ini tidak menyediakan perintah drop tabel maupun hapus data master; hanya tabel transaksi yang dapat dikosongkan.
                </p>
            </section>

            <Modal :show="truncateOpen" title="Kosongkan Tabel" @close="truncateOpen = false">
                <form class="space-y-4" @submit.prevent="submitTruncate">
                    <div>
                        <label :class="labelClass" for="tabel">Tabel</label>
                        <select v-model="truncateForm.table" :class="inputClass" id="tabel">
                            <option value="">Pilih tabel transaksi</option>
                            <option v-for="table in truncatable" :key="table.name" :value="table.name">
                                {{ table.name }} ({{ table.rows ?? '-' }} baris)
                            </option>
                        </select>
                        <p v-if="truncateForm.errors.table" class="mt-1 text-xs text-destructive">{{ truncateForm.errors.table }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">Ketik nama tabel untuk konfirmasi</label>
                        <input v-model="truncateForm.confirm" :class="inputClass" :placeholder="truncateForm.table || 'nama_tabel'" autocomplete="off" :aria-label="'Konfirmasi kosongkan tabel ' + (truncateForm.table || 'nama_tabel')" />
                        <p v-if="truncateForm.errors.confirm" class="mt-1 text-xs text-destructive">{{ truncateForm.errors.confirm }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Seluruh isi tabel akan dihapus permanen. Pastikan sudah membuat backup.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="btnSecondary" @click="truncateOpen = false">Batal</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-destructive px-3.5 py-2 text-sm font-medium text-white transition hover:bg-destructive/90 disabled:opacity-50"
                            :disabled="truncateForm.processing || truncateForm.confirm !== truncateForm.table || !truncateForm.table"
                        >
                            Kosongkan tabel
                        </button>
                    </div>
                </form>
            </Modal>
            <Modal :show="restoreOpen" title="Pulihkan Database" @close="restoreOpen = false">
                <form class="space-y-4" @submit.prevent="submitRestore">
                    <div class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <p class="font-medium">Seluruh data saat ini akan diganti dengan isi backup.</p>
                        <p class="mt-1 text-xs">
                            Backup pengaman dibuat otomatis sebelum pemulihan, tetapi tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                    <div>
                        <label :class="labelClass" for="restore-confirm">Ketik nama backup untuk konfirmasi</label>
                        <p class="mb-1 break-all rounded bg-muted px-2 py-1 font-mono text-xs text-foreground">{{ restoreTarget?.name }}</p>
                        <input
                            id="restore-confirm"
                            v-model="restoreForm.confirm"
                            :class="inputClass"
                            autocomplete="off"
                            placeholder="nama backup"
                            :aria-label="'Konfirmasi pulihkan backup ' + (restoreTarget?.name ?? '')"
                        />
                        <p v-if="restoreForm.errors.confirm" class="mt-1 text-xs text-destructive">{{ restoreForm.errors.confirm }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="btnSecondary" @click="restoreOpen = false">Batal</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-destructive px-3.5 py-2 text-sm font-medium text-white transition hover:bg-destructive/90 disabled:opacity-50"
                            :disabled="restoreForm.processing || !restoreTarget || restoreForm.confirm !== restoreTarget.name"
                        >
                            Pulihkan database
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    </AdminLayout>
</template>
