<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: null },
    tanggal: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});

const draftRows = ref([]);
const form = useForm({ kelas: props.kelasId, tanggal: props.tanggal, rows: [] });

function syncRows() {
    draftRows.value = props.rows.map((row) => ({
        id_siswa: row.id_siswa,
        nis: row.nis,
        nama: row.nama,
        cells: Object.fromEntries(
            Object.entries(row.cells ?? {}).map(([key, cell]) => [key, { ...cell, nilai: cell.nilai ?? '' }]),
        ),
    }));
}

watch(() => props.rows, syncRows, { immediate: true, deep: true });

function applyFilter(event) {
    const field = event.target.id;
    const kelas = field === 'kelas' ? event.target.value : props.kelasId;
    const tanggal = field === 'tanggal' ? event.target.value : props.tanggal;

    router.get('/elearning/nilai-harian', { kelas, tanggal }, { preserveScroll: true });
}

function save() {
    const payload = [];

    draftRows.value.forEach((row) => {
        props.items.forEach((item) => {
            const cell = row.cells[item.id_kjm];

            if (!cell) {
                return;
            }

            if (cell.nilai === '' && !cell.id_log && !cell.catatan) {
                return;
            }

            payload.push({
                id_siswa: row.id_siswa,
                id_kjm: item.id_kjm,
                nilai: cell.nilai === '' ? null : Number(cell.nilai),
                catatan: cell.catatan,
            });
        });
    });

    form.kelas = props.kelasId;
    form.tanggal = props.tanggal;
    form.rows = payload;
    form.post('/elearning/nilai-harian', { preserveScroll: true });
}

const statusLabel = { selesai: 'Selesai', dibuka: 'Dibuka', kosong: 'Belum' };
const statusClass = {
    selesai: 'text-success',
    dibuka: 'text-warning',
    kosong: 'text-muted-foreground',
};
</script>

<template>
    <Head title="Nilai Harian" />
    <AdminLayout title="Nilai Harian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Nilai Harian">
                    <template #description>Isi nilai materi dan tugas siswa per tanggal. Periode {{ periode }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" :disabled="!draftRows.length || form.processing" @click="save">
                    <i class="fas fa-save h-4 w-4" aria-hidden="true" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan Nilai' }}
                </button>
            </div>

            <div v-if="!props.classes.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-clipboard-check mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada kelas pada periode ini.</p>
            </div>

            <template v-else>
                <div class="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label :class="labelClass" for="kelas">Kelas</label>
                        <select id="kelas" :class="inputClass" :value="props.kelasId ?? ''" @change="applyFilter">
                            <option v-for="item in props.classes" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="tanggal">Tanggal</label>
                        <input id="tanggal" :class="inputClass" type="date" :value="props.tanggal" @change="applyFilter" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2">
                        <p class="text-xs text-muted-foreground">Keterangan status</p>
                        <p class="mt-1 text-sm text-muted-foreground">Selesai: siswa menuntaskan materi atau tugas. Dibuka: siswa membuka tetapi belum menuntaskan.</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Siswa</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.siswa ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Item</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.item ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Nilai terisi</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.terisi ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Rata-rata</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.rata ?? '-' }}</p>
                    </div>
                </div>

                <div v-if="!props.items.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                    <p class="text-sm font-medium text-foreground">Belum ada materi atau tugas terjadwal pada tanggal ini.</p>
                    <p class="mt-1 text-sm text-muted-foreground">Atur jadwal materi terlebih dahulu, lalu kembali ke halaman ini.</p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <caption class="sr-only">Nilai harian siswa</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="sticky left-0 bg-muted/60 px-4 py-3">Siswa</th>
                                <th v-for="item in props.items" :key="item.id_kjm" scope="col" class="px-3 py-3">
                                    <span class="block text-foreground">Jam {{ item.jam_ke }} · {{ item.jenis === 2 ? 'Tugas' : 'Materi' }}</span>
                                    <span class="block max-w-40 truncate font-normal normal-case text-muted-foreground">{{ item.judul }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in draftRows" :key="row.id_siswa" class="hover:bg-muted/40">
                                <th scope="row" class="sticky left-0 bg-card px-4 py-3 text-left font-medium text-foreground">
                                    {{ row.nama }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ row.nis }}</span>
                                </th>
                                <td v-for="item in props.items" :key="item.id_kjm" class="px-3 py-3">
                                    <label class="sr-only" :for="`nilai-${row.id_siswa}-${item.id_kjm}`">Nilai {{ row.nama }} untuk {{ item.judul }}</label>
                                    <input
 :id="`nilai-${row.id_siswa}-${item.id_kjm}`"
 v-model="row.cells[item.id_kjm].nilai"
 type="number"
 min="0"
 max="100"
 inputmode="numeric"
 placeholder="-"
 class="w-20 rounded-lg border border-border px-2 py-1.5 text-sm text-foreground shadow-card outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
 />
                                    <span class="mt-1 block text-xs" :class="statusClass[row.cells[item.id_kjm].status]">
                                        {{ statusLabel[row.cells[item.id_kjm].status] }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="form.errors.rows" role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">{{ form.errors.rows }}</p>
            </template>
        </div>
    </AdminLayout>
</template>
