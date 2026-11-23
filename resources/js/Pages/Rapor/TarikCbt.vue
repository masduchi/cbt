<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
    target: { type: String, default: 'pts' },
    preview: { type: Object, default: null },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
});

const jadwal = ref(props.selectedId ?? '');
const target = ref(props.target);
const selected = ref([]);

watch(
    () => props.preview,
    (preview) => {
        selected.value = preview
            ? preview.rows.filter((row) => row.status === 'baru' || row.status === 'berubah').map((row) => row.id_siswa)
            : [];
    },
    { immediate: true }
);

const form = useForm({ id_jadwal: null, target: 'pts', rows: [] });

function load(jadwalId = jadwal.value, targetValue = target.value) {
    if (!jadwalId) return;

    router.get(
        '/rapor/tarik-cbt',
        { jadwal: jadwalId, target: targetValue },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function changeTarget(value) {
    target.value = value;

    if (jadwal.value) load(jadwal.value, value);
}

function toggleAll(checked) {
    selected.value = checked ? selectableRows.value.map((row) => row.id_siswa) : [];
}

function apply() {
    if (!selected.value.length) return;

    form.id_jadwal = jadwal.value;
    form.target = target.value;
    form.rows = selected.value.map((id) => ({ id_siswa: id }));
    form.post('/rapor/tarik-cbt', { preserveScroll: true });
}

const selectableRows = ref([]);
watch(
    () => props.preview,
    (preview) => {
        selectableRows.value = preview
            ? preview.rows.filter((row) => row.nilai !== null)
            : [];
    },
    { immediate: true }
);

const allSelected = ref(false);
watch(selected, (rows) => {
    allSelected.value = rows.length > 0 && rows.length === selectableRows.value.length;
});
</script>

<template>
    <Head title="Tarik Nilai CBT" />
    <AdminLayout title="Tarik Nilai CBT ke Rapor">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Tarik Nilai CBT ke Rapor">
                <template #description>
                    Pilih ujian, tinjau nilai siswa, lalu simpan ke komponen PTS atau PAS. Nilai rapor yang sudah ada tidak berubah
                    sampai Anda mencentang siswanya. Bila siswa pernah remedial, nilai terbaik yang ditarik. Periode {{ props.tahun }} · Semester {{ props.semester }}.
                </template>
            </PageHeader></div>

            <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="jadwal">Jadwal ujian</label>
                        <select id="jadwal" v-model="jadwal" :class="inputClass" @change="load()">
                            <option value="">Pilih jadwal ujian</option>
                            <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                                {{ item.label }} · {{ item.jenis_nama || item.jenis || 'Ujian' }}
                            </option>
                        </select>
                        <p v-if="!props.jadwalOptions.length" class="mt-2 text-xs text-muted-foreground">
                            Belum ada jadwal ujian pada periode ini.
                        </p>
                    </div>
                    <div>
                        <span :class="labelClass">Komponen rapor tujuan</span>
                        <div class="inline-flex rounded-lg border border-border bg-muted/60 p-1" role="group" aria-label="Komponen rapor tujuan">
                            <button
                                type="button"
                                class="rounded-md px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
                                :class="target === 'pts' ? 'bg-card text-primary shadow-card' : 'text-muted-foreground hover:text-foreground'"
                                :aria-pressed="target === 'pts'"
                                @click="changeTarget('pts')"
                            >
                                Nilai PTS
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-4 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
                                :class="target === 'pas' ? 'bg-card text-primary shadow-card' : 'text-muted-foreground hover:text-foreground'"
                                :aria-pressed="target === 'pas'"
                                @click="changeTarget('pas')"
                            >
                                Nilai PAS
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="props.preview" class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <i class="fas fa-clipboard-list h-3.5 w-3.5" ></i> {{ props.preview.mapel || 'Mata pelajaran belum diisi' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                        <i class="fas fa-users h-3.5 w-3.5" ></i> {{ props.preview.rows.length }} siswa
                    </span>
                    <span class="rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success">
                        {{ props.preview.summary.baru }} baru
                    </span>
                    <span class="rounded-full bg-warning/10 px-3 py-1 text-xs font-medium text-warning">
                        {{ props.preview.summary.berubah }} berubah
                    </span>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                        {{ props.preview.summary.sama }} sama
                    </span>
                </div>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                    <div class="overflow-x-auto">
                        <table class="data-table min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="w-12 px-4 py-3">
                                        <span class="sr-only">Pilih</span>
                                        <input
 type="checkbox"
 class="rounded border-border text-primary focus:ring-ring"
 :checked="allSelected"
 :disabled="selectableRows.length === 0"
 aria-label="Pilih semua siswa"
 @change="toggleAll($event.target.checked)"
 />
                                    </th>
                                    <th class="px-4 py-3">Siswa</th>
                                    <th class="px-4 py-3">Kelas</th>
                                    <th class="px-4 py-3 text-right">Nilai CBT</th>
                                    <th class="px-4 py-3 text-right">Nilai rapor</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="row in props.preview.rows" :key="row.id_siswa" class="hover:bg-muted/40">
                                    <td class="px-4 py-3">
                                        <input
 v-model="selected"
 type="checkbox"
 :value="row.id_siswa"
 :disabled="row.nilai === null"
 class="rounded border-border text-primary focus:ring-ring disabled:opacity-40"
 :aria-label="`Pilih ${row.nama}`" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">
                                            {{ row.nama }}
                                            <span
                                                v-if="row.remedial"
                                                class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-2xs font-semibold text-primary"
                                            >Nilai remedial</span>
                                        </p>
                                        <p class="text-xs text-muted-foreground">NIS {{ row.nis || '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ row.kelas }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-foreground">{{ row.nilai ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-muted-foreground">{{ row.lama ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-success/10 text-success': row.status === 'baru',
                                                'bg-warning/10 text-warning': row.status === 'berubah',
                                                'bg-muted text-muted-foreground': row.status === 'sama' || row.status === 'kosong',
                                            }"
                                        >
                                            {{ { baru: 'Baru', berubah: 'Berubah', sama: 'Sama', kosong: 'Belum ujian' }[row.status] }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        {{ selected.length }} siswa dipilih. Nilai yang sudah sama dilewati otomatis.
                    </p>
                    <div class="flex gap-2">
                        <button type="button" :class="btnSecondary" @click="selected = []">Bersihkan pilihan</button>
                        <button type="button" :class="btnPrimary" :disabled="!selected.length || form.processing" @click="apply">
                            <i class="fas fa-download h-4 w-4" ></i> Simpan ke rapor
                        </button>
                    </div>
                </div>

                <p v-if="props.preview.summary.kosong > 0" class="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-xs text-warning">
                    <i class="fas fa-exclamation-circle mt-0.5 h-4 w-4 shrink-0" ></i>
                    {{ props.preview.summary.kosong }} siswa belum memiliki nilai ujian dan tidak bisa ditarik.
                </p>
            </div>

            <div v-else class="rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center">
                <i class="fas fa-clipboard-list mx-auto h-8 w-8 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada pratinjau</p>
                <p class="mt-1 text-xs text-muted-foreground">Pilih jadwal ujian untuk melihat nilai yang akan ditarik.</p>
            </div>
        </div>
    </AdminLayout>
</template>
