<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    jenis: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_jenis: '', kode_jenis: '' });

const columns = [
    { key: 'no', label: 'No', class: 'w-12' },
    { key: 'nama_jenis', label: 'Jenis Ujian' },
    { key: 'kode_jenis', label: 'Kode', class: 'w-28' },
    { key: 'bank_count', label: 'Bank Soal', class: 'w-28' },
    { key: 'jadwal_count', label: 'Jadwal', class: 'w-24' },
    { key: 'aksi', label: 'Aksi', class: 'w-20 text-right' },
];

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nama_jenis = row.nama_jenis;
    form.kode_jenis = row.kode_jenis;
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
        form.put(`/cbt/jenis/${editing.value.id_jenis}`, options);
    } else {
        form.post('/cbt/jenis', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus jenis ujian ${row.nama_jenis}?`)) {
        router.delete(`/cbt/jenis/${row.id_jenis}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Jenis Ujian" />
    <AdminLayout title="Jenis Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Jenis Ujian" description="PH, PTS, PAT, US dan jenis penilaian lainnya." />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Jenis
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.jenis" row-key="id_jenis" :empty-icon="'clipboard-list'" empty="Belum ada jenis ujian.">
                <template #cell-no="{ index }">
                    <span class="text-muted-foreground">{{ index + 1 }}</span>
                </template>
                <template #cell-nama_jenis="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_jenis }}</span>
                </template>
                <template #cell-kode_jenis="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.kode_jenis }}</span>
                </template>
                <template #cell-bank_count="{ row }">
                    <span class="text-sm text-muted-foreground">{{ row.bank_count }}</span>
                </template>
                <template #cell-jadwal_count="{ row }">
                    <span class="text-sm text-muted-foreground">{{ row.jadwal_count }}</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                            <i class="fas fa-pencil-alt h-4 w-4" ></i>
                        </button>
                        <button aria-label="Hapus" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus" @click="destroy(row)">
                            <i class="fas fa-trash-alt h-4 w-4" ></i>
                        </button>
                    </div>
                </template>
                <template #empty-action>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Jenis
                    </button>
                </template>
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Jenis Ujian' : 'Tambah Jenis Ujian'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="nama-jenis">Nama Jenis</label>
                    <input v-model="form.nama_jenis" :class="inputClass" placeholder="Contoh: Penilaian Tengah Semester" id="nama-jenis" />
                    <p v-if="form.errors.nama_jenis" class="mt-1 text-xs text-destructive">{{ form.errors.nama_jenis }}</p>
                </div>
                <div>
                    <label :class="labelClass" for="kode">Kode</label>
                    <input v-model="form.kode_jenis" :class="inputClass" placeholder="PTS" id="kode" />
                    <p v-if="form.errors.kode_jenis" class="mt-1 text-xs text-destructive">{{ form.errors.kode_jenis }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
