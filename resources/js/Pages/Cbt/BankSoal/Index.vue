<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { computed, ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    banks: { type: Object, required: true },
    mapels: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
    kelasOptions: { type: Array, default: () => [] },
    gurus: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});

const search = ref(props.filters.search ?? '');
const mapelFilter = ref(props.filters.mapel ?? '');
const jenisFilter = ref(props.filters.jenis ?? '');

let searchTimer = null;

function applyFilters() {
    router.get(
        '/cbt/bank-soal',
        {
            search: search.value || undefined,
            mapel: mapelFilter.value || undefined,
            jenis: jenisFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([mapelFilter, jenisFilter], applyFilters);

const showModal = ref(false);
const editing = ref(null);

function blankForm() {
    return {
        bank_kode: '',
        bank_nama: '',
        bank_mapel_id: '',
        bank_jenis_id: '',
        bank_level: '',
        bank_kelas: [],
        guru_id: '',
        opsi: 5,
        kkm: 75,
        tampil_pg: 0,
        tampil_kompleks: 0,
        tampil_jodohkan: 0,
        tampil_isian: 0,
        tampil_esai: 0,
        bobot_pg: 1,
        bobot_kompleks: 1,
        bobot_jodohkan: 1,
        bobot_isian: 1,
        bobot_esai: 1,
        deskripsi: '',
        status: true,
    };
}

const form = useForm(blankForm());

const totalPlanned = computed(
    () =>
        Number(form.tampil_pg || 0) +
        Number(form.tampil_kompleks || 0) +
        Number(form.tampil_jodohkan || 0) +
        Number(form.tampil_isian || 0) +
        Number(form.tampil_esai || 0),
);

function openCreate() {
    editing.value = null;
    form.defaults(blankForm());
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(bank) {
    editing.value = bank;
    form.bank_kode = bank.bank_kode;
    form.bank_nama = bank.bank_nama;
    form.bank_mapel_id = bank.mapel_id;
    form.bank_jenis_id = bank.jenis_id;
    form.bank_level = bank.bank_level;
    form.bank_kelas = bank.bank_kelas ? String(bank.bank_kelas).split(',').filter(Boolean).map(Number) : [];
    form.guru_id = bank.guru_id ?? '';
    form.opsi = Number(bank.opsi) || 5;
    form.kkm = bank.kkm ?? 0;
    form.tampil_pg = Number(bank.tampil_pg) || 0;
    form.tampil_kompleks = Number(bank.tampil_kompleks) || 0;
    form.tampil_jodohkan = Number(bank.tampil_jodohkan) || 0;
    form.tampil_isian = Number(bank.tampil_isian) || 0;
    form.tampil_esai = Number(bank.tampil_esai) || 0;
    form.bobot_pg = Number(bank.bobot_pg) || 0;
    form.bobot_kompleks = Number(bank.bobot_kompleks) || 0;
    form.bobot_jodohkan = Number(bank.bobot_jodohkan) || 0;
    form.bobot_isian = Number(bank.bobot_isian) || 0;
    form.bobot_esai = Number(bank.bobot_esai) || 0;
    form.deskripsi = bank.deskripsi ?? '';
    form.status = bank.status === 1;
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
        form.put(`/cbt/bank-soal/${editing.value.id_bank}`, options);
    } else {
        form.post('/cbt/bank-soal', options);
    }
}

function copy(bank) {
    router.post(`/cbt/bank-soal/${bank.id_bank}/copy`, {}, { preserveScroll: true });
}

async function destroy(bank) {
    if (await askConfirm(`Hapus bank soal ${bank.bank_nama}? Semua soal di dalamnya ikut terhapus.`)) {
        router.delete(`/cbt/bank-soal/${bank.id_bank}`, { preserveScroll: true });
    }
}

const selected = ref([]);

function toggleSelect(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

async function destroySelected() {
    if (
        !selected.value.length ||
        !await askConfirm(`Hapus ${selected.value.length} bank soal terpilih? Bank yang masih dipakai jadwal akan dilewati.`)
    ) {
        return;
    }

    router.post(
        '/cbt/bank-soal/hapus-terpilih',
        { ids: selected.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

const types = [
    { key: 'pg', label: 'Pilihan Ganda', jenis: 1 },
    { key: 'kompleks', label: 'PG Kompleks', jenis: 2 },
    { key: 'jodohkan', label: 'Menjodohkan', jenis: 3 },
    { key: 'isian', label: 'Isian Singkat', jenis: 4 },
    { key: 'esai', label: 'Uraian', jenis: 5 },
];

const rakitOpen = ref(false);
const rakitBank = ref(null);

const rakitForm = useForm({
    komposisi: {},
});

function blankKomposisi() {
    return Object.fromEntries(
        types.map((type) => [type.jenis, { mudah: 0, sedang: 0, sulit: 0 }]),
    );
}

function openRakit(bank) {
    rakitBank.value = bank;
    rakitForm.defaults({ komposisi: blankKomposisi() });
    rakitForm.reset();
    rakitForm.clearErrors();
    rakitOpen.value = true;
}

const rakitTotal = computed(() =>
    Object.values(rakitForm.komposisi).reduce(
        (sum, row) => sum + Number(row.mudah || 0) + Number(row.sedang || 0) + Number(row.sulit || 0),
        0,
    ),
);

function submitRakit() {
    rakitForm.post(`/cbt/bank-soal/${rakitBank.value.id_bank}/rakit`, {
        preserveScroll: true,
        onSuccess: () => (rakitOpen.value = false),
    });
}
</script>

<template>
    <Head title="Bank Soal" />
    <AdminLayout title="Bank Soal">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Bank Soal">
                    <template #description>Kumpulan soal per mata pelajaran dan jenis ujian. Periode {{ periode }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" @click="openCreate">
                    <i class="fas fa-plus h-4 w-4" ></i> Buat Bank Soal
                </button>
            </div>

            <div class="flex flex-wrap gap-3">
                <input v-model="search" :class="inputClass" class="max-w-xs" placeholder="Cari nama atau kode bank…" aria-label="Cari nama atau kode bank" />
                <select v-model="mapelFilter" :class="inputClass" class="max-w-56" aria-label="Filter mata pelajaran">
                    <option value="">Semua mapel</option>
                    <option v-for="mapel in props.mapels" :key="mapel.id_mapel" :value="mapel.id_mapel">{{ mapel.nama_mapel }}</option>
                </select>
                <select v-model="jenisFilter" :class="inputClass" class="max-w-48" aria-label="Filter jenis ujian">
                    <option value="">Semua jenis</option>
                    <option v-for="jenis in props.jenisOptions" :key="jenis.id_jenis" :value="jenis.id_jenis">{{ jenis.nama_jenis }}</option>
                </select>
            </div>

            <div
                v-if="selected.length"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3"
            >
                <p class="text-sm font-medium text-destructive">{{ selected.length }} bank soal dipilih</p>
                <div class="flex items-center gap-2">
                    <button type="button" :class="btnSecondary" @click="selected = []">Batal pilih</button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-destructive px-3 py-2 text-sm font-medium text-white transition hover:bg-destructive/90"
                        @click="destroySelected"
                    >
                        <i class="fas fa-trash-alt h-4 w-4" ></i> Hapus terpilih
                    </button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-if="!props.banks.data.length" class="md:col-span-2 rounded-xl border border-dashed border-border bg-muted/60 px-4 py-16 text-center text-muted-foreground">
                    <i class="fas fa-book mx-auto mb-2 h-10 w-10 text-muted-foreground/50" ></i>
                    <p>Belum ada bank soal.</p>
                    <button type="button" :class="[btnPrimary, 'mt-4']" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Buat Bank Soal
                    </button>
                </div>

                <div v-for="bank in props.banks.data" :key="bank.id_bank" class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary"
 :aria-label="`Pilih bank ${bank.bank_nama}`"
 :checked="selected.includes(bank.id_bank)"
 @change="toggleSelect(bank.id_bank)" />
                                <span class="rounded bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ bank.bank_kode }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="bank.status_soal === 1 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'"
                                >
                                    {{ bank.status_soal === 1 ? 'Siap' : 'Belum lengkap' }}
                                </span>
                            </div>
                            <h3 class="mt-2 truncate font-semibold text-foreground">{{ bank.bank_nama }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ bank.mapel ?? 'Tanpa mapel' }} · {{ bank.jenis ?? 'Tanpa jenis' }}<span v-if="bank.guru"> · {{ bank.guru }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(bank)">
                                <i class="fas fa-pencil-alt h-4 w-4" ></i>
                            </button>
                            <button aria-label="Rakit soal otomatis" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Rakit soal otomatis" @click="openRakit(bank)">
                                <i class="fas fa-wand-magic-sparkles h-4 w-4"></i>
                            </button>
                            <button aria-label="Duplikasi" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Duplikasi" @click="copy(bank)">
                                <i class="fas fa-copy h-4 w-4" ></i>
                            </button>
                            <button aria-label="Hapus" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus" @click="destroy(bank)">
                                <i class="fas fa-trash-alt h-4 w-4" ></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-5 gap-2 text-center">
                        <div v-for="type in types" :key="type.key" class="rounded-lg bg-muted/60 px-1 py-2">
                            <div class="text-sm font-semibold text-foreground">
                                {{ bank.counts[type.key] }}<span class="text-xs font-normal text-muted-foreground">/{{ bank['tampil_' + type.key] }}</span>
                            </div>
                            <div class="mt-0.5 text-2xs leading-tight text-muted-foreground">{{ type.label }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">{{ bank.counts.selected }} soal dipilih untuk ujian</span>
                        <Link :href="`/cbt/bank-soal/${bank.id_bank}`" :class="btnSecondary" class="!px-2.5 !py-1.5 text-xs">
                            <i class="fas fa-sliders-h h-3.5 w-3.5" ></i> Kelola Soal
                        </Link>
                    </div>
                </div>
            </div>

            <Pagination v-if="props.banks.data.length" :paginator="props.banks" />
        </div>

        <Modal :show="rakitOpen" title="Rakit Soal Otomatis" max-width="max-w-2xl" @close="rakitOpen = false">
            <form class="space-y-5" @submit.prevent="submitRakit">
                <div>
                    <p class="text-sm text-muted-foreground">
                        Isi jumlah soal per tingkat kesulitan. Sistem memilih soal secara acak sesuai komposisi,
                        lalu menandai soal terpilih untuk ujian.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Bank: <b class="text-foreground">{{ rakitBank?.bank_nama }}</b>
                    </p>
                </div>

                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="data-table min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Jenis</th>
                                <th class="w-24 px-3 py-2">Mudah (1–3)</th>
                                <th class="w-24 px-3 py-2">Sedang (4–7)</th>
                                <th class="w-24 px-3 py-2">Sulit (8–10)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="type in types" :key="type.jenis">
                                <td class="px-3 py-2 text-foreground">{{ type.label }}</td>
                                <td class="px-3 py-2">
                                    <input v-model.number="rakitForm.komposisi[type.jenis].mudah" type="number" min="0" :class="inputClass" class="!py-1.5 text-center" :aria-label="`Jumlah soal mudah ${type.label}`" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model.number="rakitForm.komposisi[type.jenis].sedang" type="number" min="0" :class="inputClass" class="!py-1.5 text-center" :aria-label="`Jumlah soal sedang ${type.label}`" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model.number="rakitForm.komposisi[type.jenis].sulit" type="number" min="0" :class="inputClass" class="!py-1.5 text-center" :aria-label="`Jumlah soal sulit ${type.label}`" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-muted-foreground">Total soal dipilih: <b class="text-foreground">{{ rakitTotal }}</b></p>

                <div v-if="rakitForm.errors.komposisi" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                    <p v-if="Array.isArray(rakitForm.errors.komposisi)">
                        <span v-for="(message, index) in rakitForm.errors.komposisi" :key="index" class="block">{{ message }}</span>
                    </p>
                    <p v-else>{{ rakitForm.errors.komposisi }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="rakitOpen = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="rakitForm.processing || rakitTotal === 0">
                        Rakit sekarang
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="showModal" :title="editing ? 'Edit Bank Soal' : 'Buat Bank Soal'" max-width="max-w-3xl" @close="showModal = false">
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="kode-bank">Kode Bank</label>
                        <input v-model="form.bank_kode" :class="inputClass" placeholder="MTK-PTS-11" id="kode-bank" />
                        <p v-if="form.errors.bank_kode" class="mt-1 text-xs text-destructive">{{ form.errors.bank_kode }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="nama-bank">Nama Bank</label>
                        <input v-model="form.bank_nama" :class="inputClass" placeholder="Matematika PTS Ganjil" id="nama-bank" />
                        <p v-if="form.errors.bank_nama" class="mt-1 text-xs text-destructive">{{ form.errors.bank_nama }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="mata-pelajaran">Mata Pelajaran</label>
                        <select v-model="form.bank_mapel_id" :class="inputClass" id="mata-pelajaran">
                            <option value="">Pilih mapel</option>
                            <option v-for="mapel in props.mapels" :key="mapel.id_mapel" :value="mapel.id_mapel">{{ mapel.nama_mapel }}</option>
                        </select>
                        <p v-if="form.errors.bank_mapel_id" class="mt-1 text-xs text-destructive">{{ form.errors.bank_mapel_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="jenis-ujian">Jenis Ujian</label>
                        <select v-model="form.bank_jenis_id" :class="inputClass" id="jenis-ujian">
                            <option value="">Pilih jenis</option>
                            <option v-for="jenis in props.jenisOptions" :key="jenis.id_jenis" :value="jenis.id_jenis">{{ jenis.nama_jenis }}</option>
                        </select>
                        <p v-if="form.errors.bank_jenis_id" class="mt-1 text-xs text-destructive">{{ form.errors.bank_jenis_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="level-kelas">Level Kelas</label>
                        <select v-model="form.bank_level" :class="inputClass" id="level-kelas">
                            <option value="">Pilih level</option>
                            <option v-for="level in props.levels" :key="level.id_level" :value="level.id_level">Kelas {{ level.level }}</option>
                        </select>
                        <p v-if="form.errors.bank_level" class="mt-1 text-xs text-destructive">{{ form.errors.bank_level }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="pilihan-opsi-jawaban">Pilihan Opsi Jawaban</label>
                        <select v-model="form.opsi" :class="inputClass" id="pilihan-opsi-jawaban">
                            <option :value="4">4 opsi (A–D)</option>
                            <option :value="5">5 opsi (A–E)</option>
                        </select>
                    </div>
                    <div v-if="props.isAdmin">
                        <label :class="labelClass" for="guru-penyusun">Guru Penyusun</label>
                        <select v-model="form.guru_id" :class="inputClass" id="guru-penyusun">
                            <option value="">Tanpa guru</option>
                            <option v-for="guru in props.gurus" :key="guru.id_guru" :value="guru.id_guru">{{ guru.nama_guru }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">KKM</label>
                        <input v-model.number="form.kkm" type="number" min="0" max="100" :class="inputClass" aria-label="KKM bank soal" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="kelas-peserta">Kelas Peserta</label>
                        <select v-model="form.bank_kelas" multiple :class="inputClass" class="h-28" id="kelas-peserta">
                            <option v-for="kelas in props.kelasOptions" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                        </select>
                        <p class="mt-1 text-xs text-muted-foreground">Tekan Ctrl/Cmd untuk memilih beberapa kelas.</p>
                    </div>
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-semibold text-foreground">Komposisi Soal</h4>
                    <div class="overflow-x-auto rounded-lg border border-border">
                        <table class="data-table min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Jenis</th>
                                    <th class="w-28 px-3 py-2">Jumlah Tampil</th>
                                    <th class="w-28 px-3 py-2">Bobot / Soal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="type in types" :key="type.key">
                                    <td class="px-3 py-2 text-foreground">{{ type.label }}</td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="form[`tampil_${type.key}`]" type="number" min="0" :class="inputClass" class="!py-1.5 text-center" :aria-label="`Jumlah tampil ${type.label}`" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="form[`bobot_${type.key}`]" type="number" min="0" :class="inputClass" class="!py-1.5 text-center" :aria-label="`Bobot per soal ${type.label}`" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Total soal tampil: {{ totalPlanned }}</p>
                </div>

                <div>
                    <label :class="labelClass" for="deskripsi">Deskripsi</label>
                    <textarea v-model="form.deskripsi" rows="2" :class="inputClass" placeholder="Catatan bank soal (opsional)" id="deskripsi" />
                </div>

                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input v-model="form.status" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                    Bank soal aktif
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
