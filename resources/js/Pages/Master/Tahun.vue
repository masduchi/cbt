<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { computed, ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    tahun: { type: Array, default: () => [] },
    semester: { type: Array, default: () => [] },
    bulan: { type: Array, default: () => [] },
    hariEfektif: { type: Object, default: () => ({}) },
});

const hariForm = useForm({ hari: {} });

function syncHari() {
    hariForm.defaults({
        hari: Object.fromEntries(props.bulan.map((item) => [item.id_bln, Number(props.hariEfektif?.[item.id_bln] ?? 0)])),
    });
    hariForm.reset();
}

syncHari();

const totalHari = computed(() => Object.values(hariForm.hari).reduce((total, value) => total + Number(value || 0), 0));

function saveHari() {
    hariForm.post('/master/tahun/hari-efektif', { preserveScroll: true });
}

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ tahun: '' });

const columns = [
    { key: 'tahun', label: 'Tahun Pelajaran' },
    { key: 'status', label: 'Status', class: 'w-28' },
    { key: 'aksi', label: 'Aksi', class: 'w-36 text-right' },
];

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.tahun = row.tahun;
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
        form.put(`/master/tahun/${editing.value.id_tp}`, options);
    } else {
        form.post('/master/tahun', options);
    }
}

function activate(row) {
    router.post(`/master/tahun/${row.id_tp}/aktif`, {}, { preserveScroll: true });
}

async function destroy(row) {
    if (await askConfirm(`Hapus tahun pelajaran ${row.tahun}?`)) {
        router.delete(`/master/tahun/${row.id_tp}`, { preserveScroll: true });
    }
}

function activateSemester(row) {
    router.post(`/master/semester/${row.id_smt}/aktif`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Tahun Pelajaran" />
    <AdminLayout title="Tahun Pelajaran">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Tahun Pelajaran & Semester" description="Atur periode akademik yang digunakan aplikasi." />
                <div class="flex flex-wrap items-center gap-2">
                    <Link href="/master/tahun/rollover" :class="btnSecondary">
                        <i class="fas fa-magic h-4 w-4" ></i> Wizard Tahun Ajaran
                    </Link>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Tahun
                    </button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <section class="min-w-0 space-y-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-alt h-4 w-4 text-muted-foreground" ></i>
                        <h2 class="text-sm font-semibold text-foreground">Tahun Pelajaran</h2>
                    </div>
                    <DataTable :columns="columns" :rows="props.tahun" row-key="id_tp" empty="Belum ada tahun pelajaran.">
                        <template #cell-tahun="{ row }">
                            <span class="font-medium text-foreground">{{ row.tahun }}</span>
                        </template>
                        <template #cell-status="{ row }">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="row.active == 1 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                            >
                                <i v-if="row.active == 1" class="fas fa-check-circle h-3.5 w-3.5" ></i>
                                {{ row.active == 1 ? 'Aktif' : 'Tidak aktif' }}
                            </span>
                        </template>
                        <template #cell-aksi="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button"
                                    v-if="row.active != 1"
                                    class="rounded-lg px-2 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/10"
                                    @click="activate(row)"
                                >
                                    Aktifkan
                                </button>
                                <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                                    <i class="fas fa-pencil-alt h-4 w-4" ></i>
                                </button>
                                <button aria-label="Hapus" type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                    :disabled="row.active == 1"
                                    title="Hapus"
                                    @click="destroy(row)"
                                >
                                    <i class="fas fa-trash-alt h-4 w-4" ></i>
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </section>

                <section class="min-w-0 space-y-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-alt h-4 w-4 text-muted-foreground" ></i>
                        <h2 class="text-sm font-semibold text-foreground">Semester</h2>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                        <div class="divide-y divide-border">
                            <div v-for="row in props.semester" :key="row.id_smt" class="flex items-center justify-between gap-3 px-4 py-3.5">
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ row.smt }}</p>
                                    <p class="text-xs text-muted-foreground">Semester {{ row.nama_smt }}</p>
                                </div>
                                <span
                                    v-if="row.active == 1"
                                    class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-1 text-xs font-medium text-success"
                                >
                                    <i class="fas fa-check-circle h-3.5 w-3.5" ></i> Aktif
                                </span>
                                <button type="button"
                                    v-else
                                    class="rounded-lg px-2 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/10"
                                    @click="activateSemester(row)"
                                >
                                    Aktifkan
                                </button>
                            </div>
                            <div v-if="!props.semester.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada data semester.
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <section :class="cardClass">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Hari Efektif per Bulan</h2>
                        <p class="text-xs text-muted-foreground">Dipakai untuk perhitungan kehadiran dan kalender akademik. Total {{ totalHari }} hari.</p>
                    </div>
                    <button type="button" :class="btnPrimary" :disabled="hariForm.processing" @click="saveHari">
                        Simpan Hari Efektif
                    </button>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 lg:grid-cols-6">
                    <div v-for="item in props.bulan" :key="item.id_bln">
                        <label :class="labelClass">{{ item.nama_bln }}</label>
                        <input v-model.number="hariForm.hari[item.id_bln]" type="number" min="0" max="31" :class="inputClass" :aria-label="`Jumlah hari ${item.nama_bln}`" />
                    </div>
                </div>
            </section>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Tahun Pelajaran' : 'Tambah Tahun Pelajaran'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="tahun-pelajaran">Tahun Pelajaran</label>
                    <input v-model="form.tahun" :class="inputClass" placeholder="Contoh: 2026/2027" id="tahun-pelajaran" />
                    <p v-if="form.errors.tahun" class="mt-1 text-xs text-destructive">{{ form.errors.tahun }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
