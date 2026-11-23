<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass } from '@/ui';

const props = defineProps({
    mapel: { type: Object, required: true },
    kelas: { type: Object, required: true },
    siswas: { type: Array, default: () => [] },
    nilai: { type: Object, default: () => ({}) },
    kikd: { type: Object, default: () => ({ p: [], k: [] }) },
    kkm: { type: Object, default: () => ({}) },
    setting: { type: Object, default: null },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
});

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

function rata(values) {
    const filled = values.filter((v) => v !== '' && v !== null && v !== undefined).map(Number);
    if (filled.length < 2) return null;
    return Math.round(filled.reduce((total, v) => total + v, 0) / filled.length);
}

const kikd = ref({ p: [...(props.kikd.p ?? [])], k: [...(props.kikd.k ?? [])] });

const rows = ref(
    props.siswas.map((siswa) => {
        const nilai = props.nilai[siswa.id_siswa] ?? {};
        const row = {
            id_siswa: siswa.id_siswa,
            nis: siswa.nisn || siswa.nis,
            nama: siswa.nama,
            p_deskripsi: nilai.p_deskripsi ?? '',
            k_deskripsi: nilai.k_deskripsi ?? '',
        };

        for (let i = 1; i <= 8; i++) {
            row[`p${i}`] = nilai[`p${i}`] ?? '';
            row[`k${i}`] = nilai[`k${i}`] ?? '';
        }

        return row;
    }),
);

function pValues(row) {
    return Array.from({ length: 8 }, (_, i) => row[`p${i + 1}`]);
}

function kValues(row) {
    return Array.from({ length: 8 }, (_, i) => row[`k${i + 1}`]);
}

function pRata(row) {
    return rata(pValues(row));
}

function kRata(row) {
    return rata(kValues(row));
}

function save() {
    const payload = rows.value.map((row) => {
        const item = { id_siswa: row.id_siswa, p_deskripsi: row.p_deskripsi, k_deskripsi: row.k_deskripsi };
        for (let i = 1; i <= 8; i++) {
            item[`p${i}`] = row[`p${i}`];
            item[`k${i}`] = row[`k${i}`];
        }
        return item;
    });

    router.post(`/rapor/nilai/harian/${props.mapel.id_mapel}/${props.kelas.id_kelas}`, { rows: payload, kikd: kikd.value }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Nilai Harian ${mapel.nama_mapel}`" />
    <AdminLayout title="Nilai Harian">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="`Nilai Harian: ${mapel.nama_mapel}`" :back="{ href: '/rapor/nilai', label: 'Kembali ke daftar nilai' }">
                    <template #description>
                        Kelas {{ kelas.nama_kelas }} · {{ tahun }} · Semester {{ semester }} · KKM {{ kkm }}.
                    </template>
                </PageHeader>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showImport = true"><i class="fas fa-upload h-4 w-4" ></i> Impor Nilai</button>
                    <button type="button" :class="btnPrimary" @click="save"><i class="fas fa-save h-4 w-4" ></i> Simpan Nilai</button>
                </div>
            </div>

            <ImportErrors />

            <div class="flex items-start gap-2 rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info">
                <i class="fas fa-info-circle mt-0.5 h-4 w-4 shrink-0" ></i>
                <p>Nilai rata-rata dihitung jika minimal 2 nilai terisi. Deskripsi otomatis dibuat dari indikator KD jika kolom deskripsi dibiarkan kosong.</p>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                    <i class="fas fa-book-open h-4 w-4 text-primary" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Indikator KD (ringkasan materi)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[760px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-16 px-4 py-2">No</th>
                                <th class="px-3 py-2">Pengetahuan (P)</th>
                                <th class="px-3 py-2">Keterampilan (K)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="i in 8" :key="i">
                                <td class="px-4 py-2 font-medium text-muted-foreground">P{{ i }} / K{{ i }}</td>
                                <td class="px-3 py-2"><input v-model="kikd.p[i - 1]" :class="inputClass" placeholder="Materi yang dinilai" :aria-label="`Materi yang dinilai ${i}`" /></td>
                                <td class="px-3 py-2"><input v-model="kikd.k[i - 1]" :class="inputClass" placeholder="Praktik / proyek yang dinilai" :aria-label="`Praktik atau proyek yang dinilai ${i}`" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[2200px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-muted/60 px-3 py-2 text-left">No</th>
                                <th class="sticky left-12 z-10 bg-muted/60 px-3 py-2 text-left">Nama Siswa</th>
                                <th v-for="i in 8" :key="`ph${i}`" class="w-16 px-1 py-2 text-center">P{{ i }}</th>
                                <th class="w-16 px-2 py-2 text-center">Rata P</th>
                                <th class="w-16 px-2 py-2 text-center">Pred P</th>
                                <th class="w-64 px-2 py-2 text-left">Deskripsi Pengetahuan</th>
                                <th v-for="i in 8" :key="`kh${i}`" class="w-16 px-1 py-2 text-center">K{{ i }}</th>
                                <th class="w-16 px-2 py-2 text-center">Rata K</th>
                                <th class="w-16 px-2 py-2 text-center">Pred K</th>
                                <th class="w-64 px-2 py-2 text-left">Deskripsi Keterampilan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(row, index) in rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="sticky left-0 z-10 bg-card px-3 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                <td class="sticky left-12 z-10 bg-card px-3 py-2">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td v-for="i in 8" :key="`p${i}`" class="px-1 py-2">
                                    <input v-model.number="row[`p${i}`]" type="number" min="0" max="100" class="w-14 rounded-md border border-border px-1.5 py-1 text-center text-sm focus:border-ring focus:outline-none" :aria-label="`Nilai praktik ${i} ${row.nama}`" />
                                </td>
                                <td class="px-2 py-2 text-center font-semibold text-foreground">{{ pRata(row) ?? '-' }}</td>
                                <td class="px-2 py-2 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ predikat(pRata(row)) || '-' }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <textarea v-model="row.p_deskripsi" rows="2" class="w-60 rounded-md border border-border px-2 py-1 text-xs focus:border-ring focus:outline-none" placeholder="Otomatis jika kosong" :aria-label="`Deskripsi praktik ${row.nama}`"></textarea>
                                </td>
                                <td v-for="i in 8" :key="`k${i}`" class="px-1 py-2">
                                    <input v-model.number="row[`k${i}`]" type="number" min="0" max="100" class="w-14 rounded-md border border-border px-1.5 py-1 text-center text-sm focus:border-ring focus:outline-none" :aria-label="`Nilai proyek ${i} ${row.nama}`" />
                                </td>
                                <td class="px-2 py-2 text-center font-semibold text-foreground">{{ kRata(row) ?? '-' }}</td>
                                <td class="px-2 py-2 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">{{ predikat(kRata(row)) || '-' }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <textarea v-model="row.k_deskripsi" rows="2" class="w-60 rounded-md border border-border px-2 py-1 text-xs focus:border-ring focus:outline-none" placeholder="Otomatis jika kosong" :aria-label="`Deskripsi proyek ${row.nama}`"></textarea>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="24" class="px-4 py-10 text-center text-sm text-muted-foreground">Belum ada siswa pada kelas ini.</td>
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
            title="Impor Nilai Harian"
            :action="`/rapor/nilai/harian/${mapel.id_mapel}/${kelas.id_kelas}/import`"
            :columns="[...Array.from({ length: 8 }, (_, i) => `p${i + 1}`), ...Array.from({ length: 8 }, (_, i) => `k${i + 1}`)]"
            description="Gunakan NISN atau NIS untuk mencocokkan siswa. Nilai kosong mempertahankan nilai lama, deskripsi otomatis bila dibiarkan kosong."
            :template-url="`/rapor/nilai/harian/${mapel.id_mapel}/${kelas.id_kelas}/template`"
            @close="showImport = false"
        />
    </AdminLayout>
</template>
