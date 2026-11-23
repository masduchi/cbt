<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass } from '@/ui';
import { ref, watch } from 'vue';
import { askAlert, askConfirm } from '@/confirm';

const props = defineProps({
    kelas: { type: Array, default: () => [] },
    ruang: { type: Array, default: () => [] },
    sesi: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const rows = ref([]);

function syncRows() {
    rows.value = props.kelas.map((row) => ({
        id_kelas: row.id_kelas,
        nama_kelas: row.nama_kelas,
        level: row.level,
        siswa_count: row.siswa_count,
        id_ruang: row.id_ruang ?? '',
        id_sesi: row.id_sesi ?? '',
    }));
}

watch(() => props.kelas, syncRows, { immediate: true });

async function save() {
    const assignments = rows.value
        .filter((row) => row.id_ruang && row.id_sesi)
        .map((row) => ({
            id_kelas: row.id_kelas,
            id_ruang: Number(row.id_ruang),
            id_sesi: Number(row.id_sesi),
        }));

    if (!assignments.length) {
        await askAlert('Pilih ruang dan sesi minimal untuk satu kelas.');
        return;
    }

    router.post('/cbt/kelas-ruang', { assignments }, { preserveScroll: true });
}

async function release(row) {
    if (await askConfirm(`Lepas penempatan ruang & sesi untuk ${row.nama_kelas}?`)) {
        router.delete(`/cbt/kelas-ruang/${row.id_kelas}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Atur Ruang & Sesi" />
    <AdminLayout title="Atur Ruang & Sesi">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Atur Ruang & Sesi">
                    <template #description>Tempatkan seluruh siswa kelas ke ruang dan sesi ujian. Periode {{ periode }}.</template>
                
                </PageHeader>
                <div class="flex items-center gap-2">
                    <Link href="/cbt/kelas-ruang/siswa" :class="btnSecondary">
                        <i class="fas fa-users h-4 w-4" ></i> Atur per Siswa
                    </Link>
                    <button type="button" :class="btnPrimary" :disabled="!rows.length" @click="save">
                        <i class="fas fa-save h-4 w-4" ></i> Simpan Penempatan
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3">Level</th>
                            <th class="px-4 py-3">Siswa</th>
                            <th class="px-4 py-3">Ruang</th>
                            <th class="px-4 py-3">Sesi</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!rows.length">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                <i class="fas fa-th mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                Belum ada kelas pada periode aktif.
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id_kelas" class="hover:bg-muted/40">
                            <td class="px-4 py-3 font-medium text-foreground">{{ row.nama_kelas }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.level }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.siswa_count }}</td>
                            <td class="px-4 py-3">
                                <select v-model="row.id_ruang" :class="inputClass" :aria-label="`Ruang untuk ${row.nama_kelas}`">
                                    <option value="">Pilih ruang</option>
                                    <option v-for="item in props.ruang" :key="item.id_ruang" :value="item.id_ruang">{{ item.nama_ruang }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select v-model="row.id_sesi" :class="inputClass" :aria-label="`Sesi untuk ${row.nama_kelas}`">
                                    <option value="">Pilih sesi</option>
                                    <option v-for="item in props.sesi" :key="item.id_sesi" :value="item.id_sesi">
                                        {{ item.nama_sesi }} ({{ item.waktu_mulai }}–{{ item.waktu_akhir }})
                                    </option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button"
                                    v-if="row.id_ruang || row.id_sesi"
                                    :class="btnSecondary"
                                    class="!px-2.5 !py-1.5 text-xs"
                                    @click="release(row)"
                                >
                                    <i class="fas fa-plug h-3.5 w-3.5" ></i> Lepas
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
