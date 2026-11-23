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
    ekstra: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_ekstra: '', kode_ekstra: '' });

const columns = [
    { key: 'no', label: 'No', class: 'w-12' },
    { key: 'nama_ekstra', label: 'Ekstrakurikuler' },
    { key: 'kode_ekstra', label: 'Kode', class: 'w-28' },
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
    form.nama_ekstra = row.nama_ekstra;
    form.kode_ekstra = row.kode_ekstra;
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
        form.put(`/master/ekstra/${editing.value.id_ekstra}`, options);
    } else {
        form.post('/master/ekstra', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus ekstrakurikuler ${row.nama_ekstra}?`)) {
        router.delete(`/master/ekstra/${row.id_ekstra}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Ekstrakurikuler" />
    <AdminLayout title="Ekstrakurikuler">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Data Ekstrakurikuler" description="Kelola kegiatan ekstrakurikuler sekolah." />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Ekstrakurikuler
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.ekstra" row-key="id_ekstra" :empty-icon="'graduation-cap'" empty="Belum ada ekstrakurikuler.">
                <template #cell-no="{ index }">
                    <span class="text-muted-foreground">{{ index + 1 }}</span>
                </template>
                <template #cell-nama_ekstra="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_ekstra }}</span>
                </template>
                <template #cell-kode_ekstra="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.kode_ekstra }}</span>
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
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Ekstrakurikuler' : 'Tambah Ekstrakurikuler'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="nama-ekstrakurikuler">Nama Ekstrakurikuler</label>
                    <input v-model="form.nama_ekstra" :class="inputClass" placeholder="Contoh: Pramuka" id="nama-ekstrakurikuler" />
                    <p v-if="form.errors.nama_ekstra" class="mt-1 text-xs text-destructive">{{ form.errors.nama_ekstra }}</p>
                </div>
                <div>
                    <label :class="labelClass" for="kode">Kode</label>
                    <input v-model="form.kode_ekstra" :class="inputClass" placeholder="PRM" id="kode" />
                    <p v-if="form.errors.kode_ekstra" class="mt-1 text-xs text-destructive">{{ form.errors.kode_ekstra }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
