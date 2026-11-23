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
    jurusan: { type: Array, default: () => [] },
    mapel: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ nama_jurusan: '', kode_jurusan: '', mapel: [] });

const columns = [
    { key: 'nama_jurusan', label: 'Jurusan' },
    { key: 'kode_jurusan', label: 'Kode', class: 'w-28' },
    { key: 'peminatan', label: 'Mapel Peminatan', class: 'w-64' },
    { key: 'aksi', label: 'Aksi', class: 'w-24 text-right' },
];

const visibleMapels = (row) => row.mapel_names.slice(0, 3);
const remainingMapels = (row) => Math.max(row.mapel_names.length - 3, 0);

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.nama_jurusan = row.nama_jurusan;
    form.kode_jurusan = row.kode_jurusan ?? '';
    form.mapel = [...row.mapel_ids];
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
        form.put(`/master/jurusan/${editing.value.id_jurusan}`, options);
    } else {
        form.post('/master/jurusan', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus jurusan ${row.nama_jurusan}?`)) {
        router.delete(`/master/jurusan/${row.id_jurusan}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Jurusan" />
    <AdminLayout title="Jurusan">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Data Jurusan" description="Kelola jurusan dan mata pelajaran peminatan." />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Jurusan
                </button>
            </div>

            <DataTable :columns="columns" :rows="props.jurusan" row-key="id_jurusan" :empty-icon="'project-diagram'" empty="Belum ada jurusan.">
                <template #cell-nama_jurusan="{ row }">
                    <span class="font-medium text-foreground">{{ row.nama_jurusan }}</span>
                </template>
                <template #cell-kode_jurusan="{ row }">
                    <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {{ row.kode_jurusan || '-' }}
                    </span>
                </template>
                <template #cell-peminatan="{ row }">
                    <div v-if="row.mapel_names.length" class="flex flex-wrap items-center gap-1">
                        <span
                            v-for="name in visibleMapels(row)"
                            :key="name"
                            class="rounded bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >
                            {{ name }}
                        </span>
                        <span v-if="remainingMapels(row)" class="text-xs text-muted-foreground">+{{ remainingMapels(row) }} lagi</span>
                    </div>
                    <span v-else class="text-xs text-muted-foreground">Belum dipilih</span>
                </template>
                <template #cell-aksi="{ row }">
                    <div class="flex items-center justify-end gap-1">
                        <button type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :aria-label="`Edit jurusan ${row.nama_jurusan}`"
                            title="Edit"
                            @click="openEdit(row)"
                        >
                            <i class="fas fa-pencil-alt h-4 w-4" aria-hidden="true" ></i>
                        </button>
                        <button type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                            :aria-label="`Hapus jurusan ${row.nama_jurusan}`"
                            title="Hapus"
                            @click="destroy(row)"
                        >
                            <i class="fas fa-trash-alt h-4 w-4" aria-hidden="true" ></i>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Jurusan' : 'Tambah Jurusan'" max-width="max-w-2xl" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    Periksa kembali isian yang ditandai.
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="jurusan_nama">Nama Jurusan</label>
                        <input
 id="jurusan_nama"
 v-model="form.nama_jurusan"
 :class="inputClass"
 placeholder="Contoh: MIPA"
 :aria-invalid="Boolean(form.errors.nama_jurusan)"
 :aria-describedby="form.errors.nama_jurusan ? 'jurusan_nama-error' : undefined"
 />
                        <p v-if="form.errors.nama_jurusan" id="jurusan_nama-error" role="alert" class="mt-1 text-xs text-destructive">
                            {{ form.errors.nama_jurusan }}
                        </p>
                    </div>
                    <div>
                        <label :class="labelClass" for="jurusan_kode">Kode Jurusan</label>
                        <input
 id="jurusan_kode"
 v-model="form.kode_jurusan"
 :class="inputClass"
 placeholder="Contoh: MIPA"
 :aria-invalid="Boolean(form.errors.kode_jurusan)"
 :aria-describedby="form.errors.kode_jurusan ? 'jurusan_kode-error' : undefined"
 />
                        <p v-if="form.errors.kode_jurusan" id="jurusan_kode-error" role="alert" class="mt-1 text-xs text-destructive">
                            {{ form.errors.kode_jurusan }}
                        </p>
                    </div>
                </div>

                <fieldset>
                    <legend :class="labelClass">Mata Pelajaran Peminatan</legend>
                    <div class="max-h-64 overflow-y-auto rounded-lg border border-border p-3" :aria-describedby="'mapel-help'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="m in props.mapel" :key="m.id_mapel" class="flex items-center gap-2 text-sm text-foreground">
                                <input
 v-model="form.mapel"
 type="checkbox"
 :value="m.id_mapel"
 class="rounded border-border text-primary focus:ring-ring"
 />
                                <span>{{ m.nama_mapel }}</span>
                                <span v-if="m.kelompok && m.kelompok !== '-'" class="rounded bg-muted px-1.5 py-0.5 text-2xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ m.kelompok }}
                                </span>
                            </label>
                        </div>
                        <p v-if="!props.mapel.length" class="py-4 text-center text-sm text-muted-foreground">Belum ada mata pelajaran.</p>
                    </div>
                    <p id="mapel-help" class="mt-1 text-xs text-muted-foreground">
                        {{ form.mapel.length }} mata pelajaran dipilih. Mapel ini dipakai sebagai peminatan jurusan.
                    </p>
                    <p v-if="form.errors.mapel" role="alert" class="mt-1 text-xs text-destructive">{{ form.errors.mapel }}</p>
                </fieldset>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
