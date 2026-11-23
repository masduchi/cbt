<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, cardClass, inputClass } from '@/ui';
import { askAlert } from '@/confirm';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    kelasOptions: { type: Array, default: () => [] },
    setting: { type: Object, default: null },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
});

const data = ref(props.rows.map((row) => ({ ...row })));
const search = ref('');
const kelasFilter = ref('');

const filtered = computed(() =>
    data.value.filter((row) => {
        const matchSearch = !search.value || row.nama_mapel.toLowerCase().includes(search.value.toLowerCase());
        const matchKelas = !kelasFilter.value || row.id_kelas === Number(kelasFilter.value);
        return matchSearch && matchKelas;
    }),
);

const invalidRows = computed(() => filtered.value.filter((row) => Number(row.bobot_ph) + Number(row.bobot_pts) + Number(row.bobot_pas) !== 100));

async function save() {
    if (invalidRows.value.length) {
        await askAlert('Total bobot PH + PTS + PAS harus 100 pada setiap baris.');
        return;
    }

    router.post('/rapor/kkm', { rows: data.value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="KKM & Bobot" />
    <AdminLayout title="KKM & Bobot">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="KKM & Bobot Nilai">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" @click="save"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
            </div>

            <div v-if="setting?.kkm_tunggal == 1" class="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                <i class="fas fa-exclamation-triangle mt-0.5 h-4 w-4 shrink-0" ></i>
                <p>KKM tunggal sedang aktif (KKM {{ setting.kkm }}). Nilai per mata pelajaran di bawah disimpan, tetapi perhitungan predikat memakai KKM tunggal.</p>
            </div>

            <div :class="cardClass" class="flex flex-wrap items-end gap-3 p-4">
                <div class="relative">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="inputClass" class="max-w-64 pl-9" placeholder="Cari mata pelajaran…" aria-label="Cari mata pelajaran" />
                </div>
                <select v-if="isAdmin" v-model="kelasFilter" :class="inputClass" class="max-w-48" aria-label="Filter kelas">
                    <option value="">Semua kelas</option>
                    <option v-for="kelas in kelasOptions" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                </select>
                <span class="ml-auto text-xs text-muted-foreground">{{ filtered.length }} mata pelajaran</span>
            </div>

            <div v-if="filtered.length" :class="cardClass" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[900px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Mata Pelajaran</th>
                                <th class="px-3 py-3">Kelas</th>
                                <th class="w-24 px-3 py-3">KKM</th>
                                <th class="w-24 px-3 py-3">PH</th>
                                <th class="w-24 px-3 py-3">PTS</th>
                                <th class="w-24 px-3 py-3">PAS</th>
                                <th class="w-24 px-3 py-3">Absen</th>
                                <th class="w-28 px-3 py-3">Jam</th>
                                <th class="w-20 px-3 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in filtered" :key="`${row.id_mapel}-${row.id_kelas}`" class="hover:bg-muted/40">
                                <td class="px-4 py-2.5 font-medium text-foreground">{{ row.nama_mapel }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ row.nama_kelas }}</td>
                                <td class="px-3 py-2"><input v-model.number="row.kkm" type="number" min="0" max="100" :class="inputClass" :aria-label="`KKM ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2"><input v-model.number="row.bobot_ph" type="number" min="0" max="100" :class="inputClass" :aria-label="`Bobot PH ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2"><input v-model.number="row.bobot_pts" type="number" min="0" max="100" :class="inputClass" :aria-label="`Bobot PTS ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2"><input v-model.number="row.bobot_pas" type="number" min="0" max="100" :class="inputClass" :aria-label="`Bobot PAS ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2"><input v-model.number="row.bobot_absen" type="number" min="0" max="100" :class="inputClass" :aria-label="`Bobot kehadiran ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2"><input v-model.number="row.beban_jam" type="number" min="0" max="24" :class="inputClass" :aria-label="`Beban jam ${row.nama_mapel} ${row.nama_kelas}`" /></td>
                                <td class="px-3 py-2 text-right">
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-2xs font-medium"
                                        :class="Number(row.bobot_ph) + Number(row.bobot_pts) + Number(row.bobot_pas) === 100 ? 'bg-success/10 text-success' : 'bg-destructive/10 text-destructive'"
                                    >
                                        {{ Number(row.bobot_ph) + Number(row.bobot_pts) + Number(row.bobot_pas) }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else :class="cardClass" class="px-6 py-14 text-center">
                <i class="fas fa-chart-line mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada mata pelajaran yang diampu.</p>
                <p class="mt-1 text-sm text-muted-foreground">Minta administrator mengatur guru pengampu terlebih dahulu.</p>
            </div>
        </div>
    </AdminLayout>
</template>
