<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass } from '@/ui';
import { ref, watch } from 'vue';
import { askAlert } from '@/confirm';

const props = defineProps({
    kelas: { type: Array, default: () => [] },
    selectedKelas: { type: Number, default: null },
    students: { type: Array, default: () => [] },
    ruang: { type: Array, default: () => [] },
    sesi: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const rows = ref([]);

watch(
    () => props.students,
    (value) => {
        rows.value = value.map((row) => ({
            id_siswa: row.id_siswa,
            nis: row.nis,
            nama: row.nama,
            ruang_id: row.ruang_id ?? '',
            sesi_id: row.sesi_id ?? '',
        }));
    },
    { immediate: true },
);

function changeKelas(event) {
    router.get('/cbt/kelas-ruang/siswa', { kelas: event.target.value || undefined }, { preserveState: false });
}

async function save() {
    const incomplete = rows.value.filter((row) => !row.ruang_id || !row.sesi_id);

    if (!rows.value.length) {
        await askAlert('Pilih kelas yang memiliki siswa terlebih dahulu.');
        return;
    }

    if (incomplete.length) {
        await askAlert(`Lengkapi ruang dan sesi untuk ${incomplete.length} siswa.`);
        return;
    }

    router.post(
        '/cbt/kelas-ruang/siswa',
        {
            kelas_id: props.selectedKelas,
            rows: rows.value.map((row) => ({
                id_siswa: row.id_siswa,
                ruang_id: Number(row.ruang_id),
                sesi_id: Number(row.sesi_id),
            })),
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Ruang & Sesi per Siswa" />
    <AdminLayout title="Ruang & Sesi per Siswa">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader
                    title="Atur Ruang &amp; Sesi per Siswa"
                    :back="{ href: '/cbt/kelas-ruang', label: 'Kembali ke penempatan kelas' }"
                >
                    <template #description>Menimpa penempatan kelas untuk siswa tertentu. Periode {{ props.periode }}.</template>
                </PageHeader>
                <div class="flex items-end gap-2">
                    <div class="w-56">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground" for="kelas">Kelas</label>
                        <select :class="inputClass" :value="props.selectedKelas ?? ''" @change="changeKelas" id="kelas">
                            <option value="">Pilih kelas</option>
                            <option v-for="item in props.kelas" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                        </select>
                    </div>
                    <button type="button" :class="btnPrimary" :disabled="!rows.length" @click="save">
                        <i class="fas fa-save h-4 w-4" ></i> Simpan
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3">Siswa</th>
                            <th scope="col" class="px-4 py-3">NIS</th>
                            <th scope="col" class="px-4 py-3">Ruang</th>
                            <th scope="col" class="px-4 py-3">Sesi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!rows.length">
                            <td colspan="4" class="px-4 py-12 text-center text-muted-foreground">
                                <i class="fas fa-th mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                {{ props.selectedKelas ? 'Tidak ada siswa pada kelas ini.' : 'Pilih kelas untuk mulai mengatur ruang & sesi.' }}
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                            <td class="px-4 py-3 font-medium text-foreground">{{ row.nama }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ row.nis }}</td>
                            <td class="px-4 py-3">
                                <select v-model="row.ruang_id" :class="inputClass" :aria-label="`Ruang ${row.nama}`">
                                    <option value="">Pilih ruang</option>
                                    <option v-for="item in props.ruang" :key="item.id_ruang" :value="item.id_ruang">{{ item.nama_ruang }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select v-model="row.sesi_id" :class="inputClass" :aria-label="`Sesi ${row.nama}`">
                                    <option value="">Pilih sesi</option>
                                    <option v-for="item in props.sesi" :key="item.id_sesi" :value="item.id_sesi">{{ item.nama_sesi }}</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-muted-foreground">
                Penempatan di halaman ini hanya berlaku untuk siswa yang diatur. Siswa lain mengikuti penempatan kelas.
            </p>
        </div>
    </AdminLayout>
</template>
