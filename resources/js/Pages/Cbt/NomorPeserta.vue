<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    kelasOptions: { type: Array, default: () => [] },
    selectedKelas: { type: Number, default: null },
    siswa: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const selected = ref(props.selectedKelas ?? '');
const rows = ref([]);

watch(
    () => props.siswa,
    (value) => {
        rows.value = value.map((row) => ({ ...row }));
    },
    { immediate: true },
);

watch(
    () => props.selectedKelas,
    (value) => {
        selected.value = value ?? '';
    },
);

function loadKelas() {
    router.get(
        '/cbt/nomor-peserta',
        { kelas: selected.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function save() {
    router.post(
        '/cbt/nomor-peserta',
        {
            items: rows.value.map((row) => ({
                id_siswa: row.id_siswa,
                nomor_peserta: row.nomor_peserta,
            })),
        },
        { preserveScroll: true },
    );
}

function generate() {
    if (!selected.value) return;
    router.post('/cbt/nomor-peserta/generate', { id_kelas: Number(selected.value) }, { preserveScroll: true });
}

async function reset() {
    if (!selected.value) return;

    const kelas = props.kelasOptions.find((item) => item.id_kelas === Number(selected.value))?.nama_kelas;

    if (await askConfirm(`Kosongkan nomor peserta${kelas ? ` kelas ${kelas}` : ''}? Nomor yang sudah tercetak tidak berlaku lagi.`)) {
        router.post('/cbt/nomor-peserta/reset', { id_kelas: Number(selected.value) }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Nomor Peserta" />
    <AdminLayout title="Nomor Peserta">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Nomor Peserta">
                    <template #description>Generate dan atur nomor peserta ujian per kelas. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-56">
                        <label :class="labelClass" for="kelas">Kelas</label>
                        <select v-model="selected" :class="inputClass" @change="loadKelas" id="kelas">
                            <option value="">Pilih kelas</option>
                            <option v-for="kelas in props.kelasOptions" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                        </select>
                    </div>
                    <button type="button" :class="btnSecondary" :disabled="!selected" @click="generate">
                        <i class="fas fa-magic h-4 w-4" ></i> Generate Otomatis
                    </button>
                    <button type="button" :class="btnSecondary" :disabled="!selected" @click="reset">
                        <i class="fas fa-sync-alt h-4 w-4" ></i> Kosongkan
                    </button>
                </div>
            </div>

            <div v-if="rows.length" class="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <span class="text-sm font-medium text-foreground">{{ rows.length }} siswa</span>
                    <button type="button" :class="btnPrimary" @click="save">
                        <i class="fas fa-save h-4 w-4" ></i> Simpan Nomor
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-16 px-4 py-3">No</th>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3">NISN</th>
                                <th class="w-48 px-4 py-3">Nomor Peserta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(row, index) in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-4 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-foreground">{{ row.nama }}</div>
                                    <div class="text-xs text-muted-foreground">NIS {{ row.nis }}</div>
                                </td>
                                <td class="px-4 py-2 text-muted-foreground">{{ row.nisn }}</td>
                                <td class="px-4 py-2">
                                    <input v-model="row.nomor_peserta" :class="inputClass" placeholder="0001" :aria-label="`Nomor peserta ${row.nama}`" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="selected" class="rounded-xl border border-dashed border-border bg-muted/60 px-4 py-12 text-center text-muted-foreground">
                <i class="fas fa-hashtag mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                Kelas ini belum memiliki siswa.
            </div>
        </div>
    </AdminLayout>
</template>
