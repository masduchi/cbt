<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ImportErrors from '@/Components/ImportErrors.vue';
import ImportModal from '@/Components/ImportModal.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass } from '@/ui';

const props = defineProps({
    kelas: { type: Object, required: true },
    siswas: { type: Array, default: () => [] },
    sikap: { type: Object, default: () => ({}) },
    catatan: { type: Object, default: () => ({}) },
    prestasi: { type: Object, default: () => ({}) },
    fisik: { type: Object, default: () => ({}) },
    ekstras: { type: Array, default: () => [] },
    nilaiEkstra: { type: Array, default: () => [] },
    naik: { type: Object, default: () => ({}) },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    kunci: { type: Object, default: () => ({ terkunci: false }) },
    isAdmin: { type: Boolean, default: false },
});

const activeTab = ref('sikap');
const predikatOptions = ['', 'A', 'B', 'C', 'D'];

const tabs = [
    { key: 'sikap', label: 'Sikap', icon: 'user-check' },
    { key: 'catatan', label: 'Absensi & Catatan', icon: 'users' },
    { key: 'prestasi', label: 'Ranking & Prestasi', icon: 'trophy' },
    { key: 'fisik', label: 'Fisik', icon: 'heart' },
    { key: 'ekstra', label: 'Ekstrakurikuler', icon: 'award' },
    { key: 'naik', label: 'Kenaikan Kelas', icon: 'save' },
];

const sikapRows = ref(
    props.siswas.flatMap((siswa) =>
        [1, 2].map((jenis) => ({
            id_siswa: siswa.id_siswa,
            nama: siswa.nama,
            jenis,
            predikat: props.sikap[siswa.id_siswa]?.[jenis]?.predikat ?? '',
            deskripsi: props.sikap[siswa.id_siswa]?.[jenis]?.deskripsi ?? '',
        })),
    ),
);

const catatanRows = ref(
    props.siswas.map((siswa) => ({
        id_siswa: siswa.id_siswa,
        nama: siswa.nama,
        s: props.catatan[siswa.id_siswa]?.s ?? 0,
        i: props.catatan[siswa.id_siswa]?.i ?? 0,
        a: props.catatan[siswa.id_siswa]?.a ?? 0,
        catatan: props.catatan[siswa.id_siswa]?.catatan ?? '',
    })),
);

const prestasiRows = ref(
    props.siswas.map((siswa) => ({
        id_siswa: siswa.id_siswa,
        nama: siswa.nama,
        ranking: props.prestasi[siswa.id_siswa]?.ranking ?? '',
        deskripsi: props.prestasi[siswa.id_siswa]?.deskripsi ?? '',
        p1: props.prestasi[siswa.id_siswa]?.p1 ?? '',
        p1_desk: props.prestasi[siswa.id_siswa]?.p1_desk ?? '',
        p2: props.prestasi[siswa.id_siswa]?.p2 ?? '',
        p2_desk: props.prestasi[siswa.id_siswa]?.p2_desk ?? '',
        p3: props.prestasi[siswa.id_siswa]?.p3 ?? '',
        p3_desk: props.prestasi[siswa.id_siswa]?.p3_desk ?? '',
    })),
);

const fisikRows = ref(
    props.siswas.map((siswa) => ({
        id_siswa: siswa.id_siswa,
        nama: siswa.nama,
        tinggi: props.fisik[siswa.id_siswa]?.tinggi ?? '',
        berat: props.fisik[siswa.id_siswa]?.berat ?? '',
        telinga: props.fisik[siswa.id_siswa]?.telinga ?? '',
        mata: props.fisik[siswa.id_siswa]?.mata ?? '',
        gigi: props.fisik[siswa.id_siswa]?.gigi ?? '',
        lain: props.fisik[siswa.id_siswa]?.lain ?? '',
    })),
);

const ekstraState = reactive({});
for (const ekstra of props.ekstras) {
    ekstraState[ekstra.id_ekstra] = props.siswas.map((siswa) => {
        const nilai = props.nilaiEkstra.find((item) => item.id_siswa === siswa.id_siswa && item.id_ekstra === ekstra.id_ekstra);
        return {
            id_siswa: siswa.id_siswa,
            id_ekstra: ekstra.id_ekstra,
            nama: siswa.nama,
            nilai: nilai?.nilai ?? '',
            predikat: nilai?.predikat ?? '',
            deskripsi: nilai?.deskripsi ?? '',
        };
    });
}
const selectedEkstra = ref(props.ekstras[0]?.id_ekstra ?? null);

const importMeta = {
    sikap: {
        title: 'Impor Sikap Spiritual & Sosial',
        columns: ['nisn', 'nis', 'nama', 'predikat_spiritual', 'deskripsi_spiritual', 'predikat_sosial', 'deskripsi_sosial'],
        description: 'Predikat diisi A, B, C, atau D. Kolom kosong tidak mengubah data yang sudah ada.',
    },
    catatan: {
        title: 'Impor Absensi & Catatan Wali',
        columns: ['nisn', 'nis', 'nama', 'sakit', 'izin', 'alpa', 'catatan'],
        description: 'Jumlah sakit, izin, dan alpa berupa angka 0 sampai 366. Kolom kosong mempertahankan data lama.',
    },
    prestasi: {
        title: 'Impor Ranking & Prestasi',
        columns: ['nisn', 'nis', 'nama', 'ranking', 'deskripsi', 'p1', 'p1_desk', 'p2', 'p2_desk', 'p3', 'p3_desk'],
        description: 'Ranking berupa angka 0 sampai 999. Kolom prestasi dan keterangan dapat dikosongkan.',
    },
    fisik: {
        title: 'Impor Kondisi Fisik',
        columns: ['nisn', 'nis', 'nama', 'tinggi', 'berat', 'telinga', 'mata', 'gigi', 'lain'],
        description: 'Tinggi dan berat berupa angka 0 sampai 300. Kondisi diisi teks singkat seperti Normal.',
    },
    ekstra: {
        title: 'Impor Nilai Ekstrakurikuler',
        columns: ['nisn', 'nis', 'nama', 'nilai', 'predikat', 'deskripsi'],
        description: 'Data diimpor untuk ekstrakurikuler yang sedang dipilih. Predikat A, B, C, atau D.',
    },
};

const showImport = ref(false);
const importJenis = ref('sikap');
const importInfo = computed(() => importMeta[importJenis.value] ?? importMeta.sikap);
const importAction = computed(() => `/rapor/kelas/${props.kelas.id_kelas}/import/${importJenis.value}`);
const importParams = computed(() => (importJenis.value === 'ekstra' && selectedEkstra.value ? { id_ekstra: selectedEkstra.value } : {}));
const importTemplate = computed(() => {
    const base = `/rapor/kelas/${props.kelas.id_kelas}/template/${importJenis.value}`;

    return importJenis.value === 'ekstra' && selectedEkstra.value ? `${base}?ekstra=${selectedEkstra.value}` : base;
});

function openImport(jenis) {
    if (jenis === 'ekstra' && !selectedEkstra.value) {
        return;
    }

    importJenis.value = jenis;
    showImport.value = true;
}

const naikRows = ref(
    props.siswas.map((siswa) => ({
        id_siswa: siswa.id_siswa,
        nama: siswa.nama,
        naik: props.naik[siswa.id_siswa] ?? 1,
    })),
);

const spiritual = computed(() => sikapRows.value.filter((row) => row.jenis === 1));
const sosial = computed(() => sikapRows.value.filter((row) => row.jenis === 2));

function saveSikap() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/sikap`, { rows: sikapRows.value.map(({ id_siswa, jenis, predikat, deskripsi }) => ({ id_siswa, jenis, predikat, deskripsi })) }, { preserveScroll: true });
}

function saveCatatan() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/catatan`, { rows: catatanRows.value }, { preserveScroll: true });
}

function savePrestasi() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/prestasi`, { rows: prestasiRows.value }, { preserveScroll: true });
}

function hitungPeringkat() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/peringkat`, {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const prestasi = page.props.prestasi ?? {};
            prestasiRows.value.forEach((row) => {
                row.ranking = prestasi[row.id_siswa]?.ranking ?? '';
            });
        },
    });
}

function saveFisik() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/fisik`, { rows: fisikRows.value }, { preserveScroll: true });
}

function saveEkstra() {
    if (!selectedEkstra.value) return;

    router.post(`/rapor/kelas/${props.kelas.id_kelas}/ekstra`, { rows: ekstraState[selectedEkstra.value] }, { preserveScroll: true });
}

function saveNaik() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/naik`, { rows: naikRows.value }, { preserveScroll: true });
}

function kunciRapor() {
    router.post(`/rapor/kelas/${props.kelas.id_kelas}/kunci`, {}, { preserveScroll: true });
}

function bukaKunci() {
    router.delete(`/rapor/kelas/${props.kelas.id_kelas}/kunci`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Data Rapor ${kelas.nama_kelas}`" />
    <AdminLayout title="Data Rapor Kelas">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader :title="`Data Rapor: ${kelas.nama_kelas}`" :back="{ href: '/rapor/kelas', label: 'Kembali ke daftar kelas' }">
                <template #description>
                    Wali kelas: {{ kelas.wali ?? 'belum ditentukan' }} · {{ tahun }} · Semester {{ semester }}.
                </template>
            </PageHeader></div>

            <div
                v-if="kunci.terkunci"
                class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                <div class="flex items-start gap-2">
                    <i class="fas fa-lock mt-0.5 h-4 w-4 shrink-0" ></i>
                    <div>
                        <p class="font-semibold">Rapor kelas ini dikunci.</p>
                        <p class="text-xs">Dikunci oleh {{ kunci.oleh }} pada {{ kunci.pada }}. Nilai tidak dapat diubah sampai kunci dibuka.</p>
                    </div>
                </div>
                <button
                    v-if="isAdmin"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-destructive/40 bg-card px-3 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/15"
                    @click="bukaKunci"
                >
                    <i class="fas fa-unlock-alt h-3.5 w-3.5" ></i> Buka Kunci
                </button>
            </div>
            <div
                v-else-if="isAdmin"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card"
            >
                <p class="text-muted-foreground">
                    Kunci rapor setelah seluruh nilai dan data kelas final agar tidak ada perubahan tak sengaja.
                </p>
                <button
                    type="button"
                    :class="btnSecondary"
                    @click="kunciRapor"
                >
                    <i class="fas fa-lock h-3.5 w-3.5" aria-hidden="true"></i> Kunci Rapor
                </button>
            </div>

            <ImportErrors />

            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition"
                    :class="activeTab === tab.key ? 'bg-primary text-white shadow-card' : 'bg-card text-muted-foreground ring-1 ring-border hover:bg-muted/40'"
                    @click="activeTab = tab.key"
                >
                    <i :class="'fas fa-' + (tab.icon)" class="h-4 w-4" ></i>
                    {{ tab.label }}
                </button>
            </div>

            <fieldset
                :disabled="kunci.terkunci"
                class="min-w-0 space-y-5"
                :class="kunci.terkunci ? 'pointer-events-none opacity-60' : ''"
            >
            <template v-if="activeTab === 'sikap'">
                <div class="flex justify-end">
                    <button type="button" :class="btnSecondary" @click="openImport('sikap')"><i class="fas fa-upload h-3.5 w-3.5" ></i> Impor Sikap</button>
                </div>
                <div v-for="group in [{ label: 'Sikap Spiritual', rows: spiritual, jenis: 1 }, { label: 'Sikap Sosial', rows: sosial, jenis: 2 }]" :key="group.jenis" :class="cardClass" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-foreground">{{ group.label }}</h2>
                        <button type="button" :class="btnSecondary" @click="saveSikap"><i class="fas fa-save h-3.5 w-3.5" ></i> Simpan Sikap</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[720px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="w-12 px-4 py-2">No</th>
                                    <th class="px-3 py-2">Nama Siswa</th>
                                    <th class="w-28 px-3 py-2">Predikat</th>
                                    <th class="px-3 py-2">Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="(row, index) in group.rows" :key="row.id_siswa">
                                    <td class="px-4 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-foreground">{{ row.nama }}</td>
                                    <td class="px-3 py-2">
                                        <select v-model="row.predikat" :class="inputClass" class="max-w-24" :aria-label="`Predikat sikap ${row.nama}`">
                                            <option v-for="option in predikatOptions" :key="option" :value="option">{{ option || '-' }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <textarea v-model="row.deskripsi" rows="2" :class="inputClass" placeholder="Deskripsi sikap siswa" :aria-label="`Deskripsi sikap ${row.nama}`"></textarea>
                                    </td>
                                </tr>
                                <tr v-if="!group.rows.length">
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'catatan'">
                <div :class="cardClass" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Absensi & Catatan Wali</h2>
                            <p class="text-xs text-muted-foreground">Jumlah hari sakit, izin, alpa, dan catatan untuk rapor.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" :class="btnSecondary" @click="openImport('catatan')"><i class="fas fa-upload h-3.5 w-3.5" ></i> Impor</button>
                            <button type="button" :class="btnPrimary" @click="saveCatatan"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[900px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="w-12 px-4 py-2">No</th>
                                    <th class="px-3 py-2">Nama Siswa</th>
                                    <th class="w-20 px-2 py-2 text-center">Sakit</th>
                                    <th class="w-20 px-2 py-2 text-center">Izin</th>
                                    <th class="w-20 px-2 py-2 text-center">Alpa</th>
                                    <th class="px-3 py-2">Catatan Wali</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="(row, index) in catatanRows" :key="row.id_siswa">
                                    <td class="px-4 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-foreground">{{ row.nama }}</td>
                                    <td class="px-2 py-2"><input v-model.number="row.s" type="number" min="0" :class="inputClass" :aria-label="`Jumlah sakit ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model.number="row.i" type="number" min="0" :class="inputClass" :aria-label="`Jumlah izin ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model.number="row.a" type="number" min="0" :class="inputClass" :aria-label="`Jumlah alpa ${row.nama}`" /></td>
                                    <td class="px-3 py-2"><textarea v-model="row.catatan" rows="2" :class="inputClass" placeholder="Catatan untuk siswa" :aria-label="`Catatan wali kelas ${row.nama}`"></textarea></td>
                                </tr>
                                <tr v-if="!catatanRows.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'prestasi'">
                <div :class="cardClass" class="overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Ranking & Prestasi</h2>
                            <p class="text-xs text-muted-foreground">Peringkat dapat dihitung otomatis dari total nilai akhir seluruh mapel.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" :class="btnSecondary" @click="openImport('prestasi')"><i class="fas fa-upload h-3.5 w-3.5" ></i> Impor</button>
                            <button type="button" :class="btnSecondary" @click="hitungPeringkat"><i class="fas fa-trophy h-3.5 w-3.5" ></i> Hitung Peringkat</button>
                            <button type="button" :class="btnPrimary" @click="savePrestasi"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[1200px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2">Nama Siswa</th>
                                    <th class="w-24 px-2 py-2">Ranking</th>
                                    <th class="w-56 px-3 py-2">Deskripsi</th>
                                    <th class="w-40 px-2 py-2">Prestasi 1</th>
                                    <th class="w-48 px-2 py-2">Keterangan 1</th>
                                    <th class="w-40 px-2 py-2">Prestasi 2</th>
                                    <th class="w-48 px-2 py-2">Keterangan 2</th>
                                    <th class="w-40 px-2 py-2">Prestasi 3</th>
                                    <th class="w-48 px-2 py-2">Keterangan 3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in prestasiRows" :key="row.id_siswa">
                                    <td class="px-3 py-2 font-medium text-foreground">{{ row.nama }}</td>
                                    <td class="px-2 py-2"><input v-model.number="row.ranking" type="number" min="0" :class="inputClass" :aria-label="`Ranking ${row.nama}`" /></td>
                                    <td class="px-3 py-2"><input v-model="row.deskripsi" :class="inputClass" placeholder="Deskripsi ranking" :aria-label="`Deskripsi ranking ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p1" :class="inputClass" :aria-label="`Prestasi 1 ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p1_desk" :class="inputClass" :aria-label="`Keterangan prestasi 1 ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p2" :class="inputClass" :aria-label="`Prestasi 2 ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p2_desk" :class="inputClass" :aria-label="`Keterangan prestasi 2 ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p3" :class="inputClass" :aria-label="`Prestasi 3 ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.p3_desk" :class="inputClass" :aria-label="`Keterangan prestasi 3 ${row.nama}`" /></td>
                                </tr>
                                <tr v-if="!prestasiRows.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'fisik'">
                <div :class="cardClass" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Kondisi Fisik</h2>
                            <p class="text-xs text-muted-foreground">Tinggi badan (cm), berat badan (kg), dan kondisi kesehatan.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" :class="btnSecondary" @click="openImport('fisik')"><i class="fas fa-upload h-3.5 w-3.5" ></i> Impor</button>
                            <button type="button" :class="btnPrimary" @click="saveFisik"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[1000px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2">Nama Siswa</th>
                                    <th class="w-20 px-2 py-2">Tinggi</th>
                                    <th class="w-20 px-2 py-2">Berat</th>
                                    <th class="px-2 py-2">Telinga</th>
                                    <th class="px-2 py-2">Mata</th>
                                    <th class="px-2 py-2">Gigi</th>
                                    <th class="px-2 py-2">Lain-lain</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in fisikRows" :key="row.id_siswa">
                                    <td class="px-3 py-2 font-medium text-foreground">{{ row.nama }}</td>
                                    <td class="px-2 py-2"><input v-model.number="row.tinggi" type="number" min="0" :class="inputClass" :aria-label="`Tinggi badan ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model.number="row.berat" type="number" min="0" :class="inputClass" :aria-label="`Berat badan ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.telinga" :class="inputClass" placeholder="Normal" :aria-label="`Kondisi telinga ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.mata" :class="inputClass" placeholder="Normal" :aria-label="`Kondisi mata ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.gigi" :class="inputClass" placeholder="Normal" :aria-label="`Kondisi gigi ${row.nama}`" /></td>
                                    <td class="px-2 py-2"><input v-model="row.lain" :class="inputClass" :aria-label="`Keterangan kondisi lain ${row.nama}`" /></td>
                                </tr>
                                <tr v-if="!fisikRows.length">
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'ekstra'">
                <div v-if="!ekstras.length" :class="cardClass" class="px-4 py-10 text-center text-sm text-muted-foreground">
                    Belum ada ekstrakurikuler. Tambahkan melalui menu Data Master → Ekstrakurikuler.
                </div>
                <div v-else :class="cardClass" class="overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Nilai Ekstrakurikuler</h2>
                            <p class="text-xs text-muted-foreground">Pilih ekstrakurikuler lalu isi nilai setiap siswa.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model.number="selectedEkstra" :class="inputClass" class="max-w-56" aria-label="Pilih kegiatan ekstrakurikuler">
                                <option v-for="ekstra in ekstras" :key="ekstra.id_ekstra" :value="ekstra.id_ekstra">{{ ekstra.nama_ekstra }}</option>
                            </select>
                            <button type="button" :class="btnSecondary" :disabled="!selectedEkstra" @click="openImport('ekstra')"><i class="fas fa-upload h-3.5 w-3.5" ></i> Impor</button>
                            <button type="button" :class="btnPrimary" @click="saveEkstra"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[900px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2">Nama Siswa</th>
                                    <th class="w-24 px-2 py-2">Nilai</th>
                                    <th class="w-24 px-2 py-2">Predikat</th>
                                    <th class="px-3 py-2">Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in ekstraState[selectedEkstra] ?? []" :key="row.id_siswa">
                                    <td class="px-3 py-2 font-medium text-foreground">{{ row.nama }}</td>
                                    <td class="px-2 py-2"><input v-model.number="row.nilai" type="number" min="0" max="100" :class="inputClass" :aria-label="`Nilai ekstrakurikuler ${row.nama}`" /></td>
                                    <td class="px-2 py-2">
                                        <select v-model="row.predikat" :class="inputClass" :aria-label="`Predikat ekstrakurikuler ${row.nama}`">
                                            <option v-for="option in predikatOptions" :key="option" :value="option">{{ option || '-' }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2"><textarea v-model="row.deskripsi" rows="2" :class="inputClass" placeholder="Deskripsi kegiatan ekstrakurikuler" :aria-label="`Deskripsi ekstrakurikuler ${row.nama}`"></textarea></td>
                                </tr>
                                <tr v-if="!(ekstraState[selectedEkstra] ?? []).length">
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <template v-if="activeTab === 'naik'">
                <div :class="cardClass" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Kenaikan Kelas</h2>
                            <p class="text-xs text-muted-foreground">Tentukan status naik/tidak naik setiap siswa.</p>
                        </div>
                        <button type="button" :class="btnPrimary" @click="saveNaik"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                    </div>
                    <div class="divide-y divide-border">
                        <div v-for="row in naikRows" :key="row.id_siswa" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm font-medium text-foreground">{{ row.nama }}</span>
                            <select v-model.number="row.naik" :class="inputClass" class="max-w-40" :aria-label="`Kenaikan kelas ${row.nama}`">
                                <option :value="1">Naik Kelas</option>
                                <option :value="0">Tinggal Kelas</option>
                            </select>
                        </div>
                        <p v-if="!naikRows.length" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa.</p>
                    </div>
                </div>
            </template>
            </fieldset>
        </div>

        <ImportModal
            :show="showImport"
            :title="importInfo.title"
            :action="importAction"
            :columns="importInfo.columns"
            :description="importInfo.description"
            :template-url="importTemplate"
            :params="importParams"
            @close="showImport = false"
        />
    </AdminLayout>
</template>
