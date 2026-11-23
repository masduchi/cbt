<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { reactive } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    q: props.filters.q ?? '',
    aksi: props.filters.aksi ?? '',
    user: props.filters.user ?? '',
    dari: props.filters.dari ?? '',
    sampai: props.filters.sampai ?? '',
    per_page: props.filters.per_page ?? 40,
});

function apply() {
    router.get('/setting/audit', {
        q: form.q || undefined,
        aksi: form.aksi || undefined,
        user: form.user || undefined,
        dari: form.dari || undefined,
        sampai: form.sampai || undefined,
        per_page: form.per_page || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
    form.q = '';
    form.aksi = '';
    form.user = '';
    form.dari = '';
    form.sampai = '';
    form.per_page = 40;
    apply();
}

function exportUrl() {
    const params = new URLSearchParams();
    const pairs = {
        q: form.q,
        aksi: form.aksi,
        user: form.user,
        dari: form.dari,
        sampai: form.sampai,
    };

    Object.entries(pairs).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });

    const query = params.toString();

    return `/setting/audit/export${query ? `?${query}` : ''}`;
}

function roleLabel(role) {
    return { admin: 'Administrator', guru: 'Guru', siswa: 'Siswa', orangtua: 'Wali Murid' }[role] ?? (role || '-');
}
</script>

<template>
    <Head title="Jejak Audit" />
    <AdminLayout title="Jejak Audit">
        <div class="page-stack space-y-6">
            <div class="-mx-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:px-[30px]">
                <PageHeader title="Jejak Audit">
                    <template #description>
                        Catatan aksi sensitif: perubahan akun, nilai rapor, ujian, dan database. Hanya administrator.
                    </template>
                </PageHeader>
            </div>

            <form :class="cardClass" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label :class="labelClass" for="audit-cari">Cari</label>
                    <input v-model="form.q" :class="inputClass" placeholder="Deskripsi, username, IP" id="audit-cari" />
                </div>
                <div>
                    <label :class="labelClass" for="audit-aksi">Aksi</label>
                    <select v-model="form.aksi" :class="inputClass" id="audit-aksi">
                        <option value="">Semua aksi</option>
                        <option v-for="action in props.actions" :key="action.code" :value="action.code">{{ action.label }}</option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass" for="audit-user">Pengguna</label>
                    <select v-model="form.user" :class="inputClass" id="audit-user">
                        <option value="">Semua pengguna</option>
                        <option v-for="user in props.users" :key="user.id" :value="user.id">{{ user.label }}</option>
                    </select>
                </div>
                <div>
                    <label :class="labelClass" for="audit-dari">Dari tanggal</label>
                    <input v-model="form.dari" type="date" :class="inputClass" id="audit-dari" />
                </div>
                <div>
                    <label :class="labelClass" for="audit-sampai">Sampai tanggal</label>
                    <input v-model="form.sampai" type="date" :class="inputClass" id="audit-sampai" />
                </div>
                <div>
                    <label :class="labelClass" for="audit-per-page">Baris per halaman</label>
                    <select v-model.number="form.per_page" :class="inputClass" id="audit-per-page" @change="apply">
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
                    <ExportLink :href="exportUrl()" label="Ekspor Excel" icon="file-excel" />
                </div>
            </form>

            <div :class="cardClass" class="overflow-x-auto">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Pengguna</th>
                            <th class="px-4 py-3">Aksi</th>
                            <th class="px-4 py-3">Detail</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in props.logs.data" :key="row.id_log" class="hover:bg-muted/40">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ row.waktu }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ row.user }}</p>
                                <p class="text-xs text-muted-foreground">{{ roleLabel(row.role) }}<span v-if="row.username"> · {{ row.username }}</span></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">{{ row.aksi }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.detail || '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-muted-foreground">{{ row.ip || '-' }}</td>
                        </tr>
                        <tr v-if="!props.logs.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada catatan audit yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="props.logs" />
        </div>
    </AdminLayout>
</template>
