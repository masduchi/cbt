<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    kelompok: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
    kategoriOptions: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    kode_kel_mapel: '',
    nama_kel_mapel: '',
    kategori: 'WAJIB',
    id_parent: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.kategori = 'WAJIB';
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.kode_kel_mapel = row.kode_kel_mapel;
    form.nama_kel_mapel = row.nama_kel_mapel;
    form.kategori = row.kategori;
    form.id_parent = row.id_parent || '';
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
        form.put(`/master/kelompok-mapel/${editing.value.id_kel_mapel}`, options);
    } else {
        form.post('/master/kelompok-mapel', options);
    }
}

async function destroy(row) {
    if (await askConfirm(`Hapus kelompok ${row.nama_kel_mapel}?`)) {
        router.delete(`/master/kelompok-mapel/${row.id_kel_mapel}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelompok Mata Pelajaran" />
    <AdminLayout title="Kelompok Mata Pelajaran">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader
                    title="Kelompok &amp; Sub-kelompok Mapel"
                    description="Kelompok dipakai sebagai kode pada form mata pelajaran (A, B, C, MULOK, dst.)."
                    :back="{ href: '/master/mapel', label: 'Kembali ke mata pelajaran' }"
                />
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Tambah Kelompok
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3">Kode</th>
                            <th scope="col" class="px-4 py-3">Nama Kelompok</th>
                            <th scope="col" class="px-4 py-3">Kategori</th>
                            <th scope="col" class="px-4 py-3">Induk</th>
                            <th scope="col" class="px-4 py-3">Mapel</th>
                            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!props.kelompok.length">
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                <i class="fas fa-folder-tree mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                Belum ada kelompok mata pelajaran.
                            </td>
                        </tr>
                        <tr v-for="row in props.kelompok" :key="row.id_kel_mapel" class="hover:bg-muted/40">
                            <td class="px-4 py-3">
                                <span class="rounded bg-muted px-2 py-0.5 text-xs font-semibold text-foreground">{{ row.kode_kel_mapel }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">
                                <span v-if="row.id_parent" class="mr-1 text-muted-foreground/50">↳</span>{{ row.nama_kel_mapel }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.kategori }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.parent || '-' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.mapel_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                                        <i class="fas fa-pencil-alt h-4 w-4" ></i>
                                    </button>
                                    <button aria-label="Hapus" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus" @click="destroy(row)">
                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Kelompok' : 'Tambah Kelompok'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="kode">Kode</label>
                        <input v-model="form.kode_kel_mapel" :class="inputClass" placeholder="A" id="kode" />
                        <p v-if="form.errors.kode_kel_mapel" class="mt-1 text-xs text-destructive">{{ form.errors.kode_kel_mapel }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kategori">Kategori</label>
                        <select v-model="form.kategori" :class="inputClass" id="kategori">
                            <option v-for="item in props.kategoriOptions" :key="item" :value="item">{{ item }}</option>
                        </select>
                        <p v-if="form.errors.kategori" class="mt-1 text-xs text-destructive">{{ form.errors.kategori }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="nama-kelompok">Nama Kelompok</label>
                        <input v-model="form.nama_kel_mapel" :class="inputClass" placeholder="Kelompok A (Wajib)" id="nama-kelompok" />
                        <p v-if="form.errors.nama_kel_mapel" class="mt-1 text-xs text-destructive">{{ form.errors.nama_kel_mapel }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="sub-dari-kelompok">Sub dari Kelompok</label>
                        <select v-model="form.id_parent" :class="inputClass" id="sub-dari-kelompok">
                            <option value="">Kelompok utama</option>
                            <option v-for="item in props.parents" :key="item.id_kel_mapel" :value="item.id_kel_mapel">
                                {{ item.nama_kel_mapel }}
                            </option>
                        </select>
                        <p v-if="form.errors.id_parent" class="mt-1 text-xs text-destructive">{{ form.errors.id_parent }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
