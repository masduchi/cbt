<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import { btnPrimary, btnSecondary, cardClass } from '@/ui';

const props = defineProps({
    mapel: { type: Object, required: true },
    kelas: { type: Object, required: true },
    siswas: { type: Array, default: () => [] },
    harian: { type: Object, default: () => ({}) },
    pts: { type: Object, default: () => ({}) },
    akhir: { type: Object, default: () => ({}) },
    kkm: { type: Object, default: () => ({}) },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
});

const bobot = computed(() => ({
    ph: Number(props.kkm?.bobot_ph ?? 40),
    pts: Number(props.kkm?.bobot_pts ?? 30),
    pas: Number(props.kkm?.bobot_pas ?? 30),
}));

const kkm = computed(() => Number(props.kkm?.kkm ?? 75));
const showImport = ref(false);

function predikat(value) {
    if (value === null || value === '') return '';
    const n = Math.round(Number(value));
    const c = kkm.value;
    const csd = Math.floor(c + (100 - c) / 3);
    const b = csd + 1;
    const bsd = Math.floor(b + (100 - b) / 2);
    if (n > bsd) return 'A';
    if (n > csd) return 'B';
    if (n > c - 1) return 'C';
    return 'D';
}

const rows = ref(
    props.siswas.map((siswa) => {
        const akhir = props.akhir[siswa.id_siswa] ?? null;

        return {
            id_siswa: siswa.id_siswa,
            nis: siswa.nisn || siswa.nis,
            nama: siswa.nama,
            nhar: props.harian[siswa.id_siswa] ?? '',
            npts: props.pts[siswa.id_siswa] ?? '',
            npas: akhir?.nilai ?? '',
            akhir: akhir?.akhir ?? null,
        };
    }),
);

function num(value) {
    return value === null || value === '' || value === undefined ? null : Number(value);
}

function hitungAkhir(row) {
    const h = num(row.nhar);
    const p = num(row.npts);
    const pa = num(row.npas);

    if (h === null && p === null && pa === null) return null;

    return Math.round(((h ?? 0) * bobot.value.ph + (p ?? 0) * bobot.value.pts + (pa ?? 0) * bobot.value.pas) / 100);
}

function save() {
    router.post(
        `/rapor/nilai/pas/${props.mapel.id_mapel}/${props.kelas.id_kelas}`,
        { rows: rows.value.map((row) => ({ id_siswa: row.id_siswa, nilai: row.npas })) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Nilai PAS ${mapel.nama_mapel}`" />
    <AdminLayout title="Nilai PAS">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="`Nilai Akhir: ${mapel.nama_mapel}`" :back="{ href: '/rapor/nilai', label: 'Kembali ke daftar nilai' }">
                    <template #description>
                        Kelas {{ kelas.nama_kelas }} · {{ tahun }} · Semester {{ semester }} · KKM {{ kkm }}.
                    </template>
                </PageHeader>
                <div class="flex items-center gap-2">
                    <button type="button" :class="btnSecondary" @click="showImport = true"><i class="fas fa-upload h-4 w-4" ></i> Impor Nilai</button>
                    <button type="button" :class="btnPrimary" @click="save"><i class="fas fa-save h-4 w-4" ></i> Simpan Nilai</button>
                </div>
            </div>

            <ImportErrors />

            <div class="flex items-start gap-2 rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info">
                <i class="fas fa-info-circle mt-0.5 h-4 w-4 shrink-0" ></i>
                <p>Nilai akhir = (PH × {{ bobot.ph }}%) + (PTS × {{ bobot.pts }}%) + (PAS × {{ bobot.pas }}%). Nilai PH dan PTS diambil otomatis dari halaman input masing-masing.</p>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[760px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-12 px-4 py-3">No</th>
                                <th class="px-3 py-3">Nama Siswa</th>
                                <th class="w-24 px-3 py-3 text-center">PH</th>
                                <th class="w-24 px-3 py-3 text-center">PTS</th>
                                <th class="w-28 px-3 py-3 text-center">PAS</th>
                                <th class="w-24 px-3 py-3 text-center">Akhir</th>
                                <th class="w-20 px-3 py-3 text-center">Pred</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(row, index) in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-4 py-2.5 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-center text-muted-foreground">{{ row.nhar ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center text-muted-foreground">{{ row.npts ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <input v-model.number="row.npas" type="number" min="0" max="100" class="w-24 rounded-md border border-border px-2 py-1.5 text-center text-sm focus:border-ring focus:outline-none" :aria-label="`Nilai akhir ${row.nama}`" />
                                </td>
                                <td class="px-3 py-2.5 text-center font-semibold text-foreground">{{ hitungAkhir(row) ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ predikat(hitungAkhir(row)) || '-' }}</span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">Belum ada siswa pada kelas ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" :class="btnPrimary" @click="save"><i class="fas fa-save h-4 w-4" ></i> Simpan Nilai</button>
            </div>
        </div>

        <ImportModal
            :show="showImport"
            title="Impor Nilai PAS"
            :action="`/rapor/nilai/pas/${mapel.id_mapel}/${kelas.id_kelas}/import`"
            :columns="['nisn', 'nis', 'nama', 'nilai']"
            description="Nilai PAS diimpor per siswa, lalu nilai akhir dan predikat dihitung otomatis dari PH, PTS, dan PAS."
            :template-url="`/rapor/nilai/pas/${mapel.id_mapel}/${kelas.id_kelas}/template`"
            @close="showImport = false"
        />
    </AdminLayout>
</template>
