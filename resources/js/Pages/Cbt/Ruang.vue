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
    ruang: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_ruang: '', kode_ruang: '' });

const columns = [
    { key: 'no', label: 'No', class: 'w-12' },
    { key: 'nama_ruang', label: 'Ruang' },
    { key: 'kode_ruang', label: 'Kode', class: 'w-28' },
    { key: 'siswa_count', label: 'Siswa', class: 'w-24' },
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
    form.nama_ruang = row.nama_ruang;
    form.kode_ruang = row.kode_ruang;
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
        form.put(`/cbt/ruang/${editing.value.id_ruang}`, options);
    } else {
        form.post('/cbt/ruang', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus ruang ${row.nama_ruang}?`)) {
        router.delete(`/cbt/ruang/${row.id_ruang}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Ruang Ujian" />
    <AdminLayout title="Ruang Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Ruang Ujian" description="Daftar ruang tempat pelaksanaan ujian." />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Ruang
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.ruang" row-key="id_ruang" :empty-icon="'door-open'" empty="Belum ada ruang ujian.">
                <template #cell-no="{ index }">
                    <span class="text-muted-foreground">{{ index + 1 }}</span>
                </template>
                <template #cell-nama_ruang="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_ruang }}</span>
                </template>
                <template #cell-kode_ruang="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.kode_ruang }}</span>
                </template>
                <template #cell-siswa_count="{ row }">
                    <span class="text-sm text-muted-foreground">{{ row.siswa_count }}</span>
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
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Ruang
                    </button>
                </template>
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Ruang' : 'Tambah Ruang'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="nama-ruang">Nama Ruang</label>
                    <input v-model="form.nama_ruang" :class="inputClass" placeholder="Ruang 01" id="nama-ruang" />
                    <p v-if="form.errors.nama_ruang" class="mt-1 text-xs text-destructive">{{ form.errors.nama_ruang }}</p>
                </div>
                <div>
                    <label :class="labelClass" for="kode">Kode</label>
                    <input v-model="form.kode_ruang" :class="inputClass" placeholder="R01" id="kode" />
                    <p v-if="form.errors.kode_ruang" class="mt-1 text-xs text-destructive">{{ form.errors.kode_ruang }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
