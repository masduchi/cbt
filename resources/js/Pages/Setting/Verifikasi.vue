<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { askConfirm } from '@/confirm';
import { btnDanger, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    dokumen: { type: Object, required: true },
    jenis: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statistik: { type: Object, default: () => ({ total: 0, aktif: 0, dicabut: 0 }) },
});

const form = reactive({
    q: props.filters.q ?? '',
    jenis: props.filters.jenis ?? '',
    status: props.filters.status ?? '',
    per_page: props.filters.per_page ?? 40,
});

const aktifTerpilih = computed(() => props.dokumen.data.filter((row) => row.aktif).length);

function apply() {
    router.get(
        '/setting/verifikasi',
        {
            q: form.q || undefined,
            jenis: form.jenis || undefined,
            status: form.status || undefined,
            per_page: form.per_page || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function reset() {
    form.q = '';
    form.jenis = '';
    form.status = '';
    form.per_page = 40;
    apply();
}

function ringkasanTeks(row) {
    return Object.values(row.ringkasan ?? {})
        .filter((value) => value !== null && value !== '')
        .join(' · ');
}

async function cabut(row) {
    const setuju = await askConfirm(
        `Cabut kode ${row.kode}? Dokumen yang sudah dicetak dengan kode ini tidak lagi terverifikasi, dan cetak ulang akan menerbitkan kode baru.`,
        { title: 'Cabut kode verifikasi', confirmLabel: 'Ya, cabut' },
    );

    if (!setuju) return;

    router.post(`/setting/verifikasi/${row.kode}/cabut`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Verifikasi Dokumen" />
    <AdminLayout title="Verifikasi Dokumen">
        <div class="page-stack space-y-6">
            <div class="-mx-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:px-[30px]">
                <PageHeader title="Verifikasi Dokumen">
                    <template #description>
                        Daftar kode QR pada dokumen cetak (kartu peserta, rapor, buku induk). Cabut kode yang bocor agar tidak lagi dianggap sah.
                    </template>
                </PageHeader>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total kode</p>
                    <p class="mt-1 text-2xl font-bold text-foreground">{{ statistik.total }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ statistik.aktif }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Dicabut</p>
                    <p class="mt-1 text-2xl font-bold text-destructive">{{ statistik.dicabut }}</p>
                </div>
            </div>

            <form :class="cardClass" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
                <div class="sm:col-span-2 lg:col-span-2">
                    <label :class="labelClass" for="verifikasi-cari">Cari</label>
                    <input v-model="form.q" :class="inputClass" placeholder="Kode, referensi, atau isi ringkasan" id="verifikasi-cari" />
                </div>
                <div>
                    <label :class="labelClass" for="verifikasi-jenis">Jenis dokumen</label>
                    <select v-model="form.jenis" :class="inputClass" id="verifikasi-jenis">
                        <option value="">Semua jenis</option>
                        <option v-for="item in props.jenis" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass" for="verifikasi-status">Status</label>
                    <select v-model="form.status" :class="inputClass" id="verifikasi-status">
                        <option value="">Semua status</option>
                        <option value="aktif">Aktif</option>
                        <option value="dicabut">Dicabut</option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass" for="verifikasi-per-page">Baris per halaman</label>
                    <select v-model.number="form.per_page" :class="inputClass" id="verifikasi-per-page" @change="apply">
                        <option :value="25">25</option>
                        <option :value="40">40</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white">
                        <i class="fas fa-filter h-3.5 w-3.5" aria-hidden="true"></i> Terapkan
                    </button>
                    <button type="button" :class="btnSecondary" @click="reset">Reset</button>
                    <p class="ml-auto self-center text-xs text-muted-foreground">{{ aktifTerpilih }} kode aktif di halaman ini.</p>
                </div>
            </form>

            <div :class="cardClass" class="overflow-x-auto">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Dokumen</th>
                            <th class="px-4 py-3">Ringkasan</th>
                            <th class="px-4 py-3">Diterbitkan</th>
                            <th class="px-4 py-3">Diperbarui</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in props.dokumen.data" :key="row.id" class="hover:bg-muted/40">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-mono text-xs font-semibold text-foreground">{{ row.kode }}</p>
                                <a :href="row.url" target="_blank" rel="noopener" class="text-2xs text-primary hover:underline">Buka halaman verifikasi</a>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ row.jenis_label }}</p>
                                <p class="text-xs text-muted-foreground">{{ row.ref }}</p>
                            </td>
                            <td class="max-w-xs px-4 py-3 text-xs text-muted-foreground">{{ ringkasanTeks(row) || '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ row.diterbitkan }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ row.diperbarui }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    v-if="row.aktif"
                                    class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700"
                                >Aktif</span>
                                <span v-else class="rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive">
                                    Dicabut {{ row.dicabut }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button v-if="row.aktif" type="button" :class="btnDanger" @click="cabut(row)">
                                    <i class="fas fa-ban h-3.5 w-3.5" aria-hidden="true"></i> Cabut
                                </button>
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </td>
                        </tr>
                        <tr v-if="!props.dokumen.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada kode verifikasi yang cocok dengan filter. Kode terbit otomatis saat dokumen dicetak.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="props.dokumen" />
        </div>
    </AdminLayout>
</template>
