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
    sesi: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_sesi: '', kode_sesi: '', waktu_mulai: '07:00', waktu_akhir: '09:00', aktif: true });

const columns = [
    { key: 'no', label: 'No', class: 'w-12' },
    { key: 'nama_sesi', label: 'Sesi' },
    { key: 'kode_sesi', label: 'Kode', class: 'w-24' },
    { key: 'waktu', label: 'Waktu', class: 'w-40' },
    { key: 'aktif', label: 'Status', class: 'w-28' },
    { key: 'siswa_count', label: 'Siswa', class: 'w-24' },
    { key: 'aksi', label: 'Aksi', class: 'w-20 text-right' },
];

function openCreate() {
    editing.value = null;
    form.reset();
    form.aktif = true;
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nama_sesi = row.nama_sesi;
    form.kode_sesi = row.kode_sesi;
    form.waktu_mulai = row.waktu_mulai;
    form.waktu_akhir = row.waktu_akhir;
    form.aktif = row.aktif === 1;
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
        form.put(`/cbt/sesi/${editing.value.id_sesi}`, options);
    } else {
        form.post('/cbt/sesi', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus sesi ${row.nama_sesi}?`)) {
        router.delete(`/cbt/sesi/${row.id_sesi}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Sesi Ujian" />
    <AdminLayout title="Sesi Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Sesi Ujian" description="Atur pembagian waktu pelaksanaan ujian." />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Sesi
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.sesi" row-key="id_sesi" :empty-icon="'clock'" empty="Belum ada sesi ujian.">
                <template #cell-no="{ index }">
                    <span class="text-muted-foreground">{{ index + 1 }}</span>
                </template>
                <template #cell-nama_sesi="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_sesi }}</span>
                </template>
                <template #cell-kode_sesi="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.kode_sesi }}</span>
                </template>
                <template #cell-waktu="{ row }">
                    <span class="text-sm text-muted-foreground">{{ row.waktu_mulai }} – {{ row.waktu_akhir }}</span>
                </template>
                <template #cell-aktif="{ row }">
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.aktif === 1 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                    >
                        {{ row.aktif === 1 ? 'Aktif' : 'Nonaktif' }}
                    </span>
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
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Sesi' : 'Tambah Sesi'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="nama-sesi">Nama Sesi</label>
                        <input v-model="form.nama_sesi" :class="inputClass" placeholder="Sesi 1" id="nama-sesi" />
                        <p v-if="form.errors.nama_sesi" class="mt-1 text-xs text-destructive">{{ form.errors.nama_sesi }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kode">Kode</label>
                        <input v-model="form.kode_sesi" :class="inputClass" placeholder="S1" id="kode" />
                        <p v-if="form.errors.kode_sesi" class="mt-1 text-xs text-destructive">{{ form.errors.kode_sesi }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="waktu-mulai">Waktu Mulai</label>
                        <input v-model="form.waktu_mulai" type="time" :class="inputClass" id="waktu-mulai" />
                        <p v-if="form.errors.waktu_mulai" class="mt-1 text-xs text-destructive">{{ form.errors.waktu_mulai }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="waktu-akhir">Waktu Akhir</label>
                        <input v-model="form.waktu_akhir" type="time" :class="inputClass" id="waktu-akhir" />
                        <p v-if="form.errors.waktu_akhir" class="mt-1 text-xs text-destructive">{{ form.errors.waktu_akhir }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input v-model="form.aktif" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                    Sesi aktif
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
