<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: 0 },
    kbm: { type: Object, default: () => ({}) },
    template: { type: Array, default: () => [] },
    mapels: { type: Array, default: () => [] },
    hari: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const form = useForm({
    kelas: props.kelasId,
    jam_pel: 40,
    jam_mulai: '07:00',
    jml_mapel: 8,
    istirahat: [],
    template: [],
});

const grid = ref([]);
const HARI = [1, 2, 3, 4, 5, 6];

function emptyBreaks() {
    return Array.from({ length: 4 }, () => ({ jam_ke: '', durasi: '' }));
}

function syncFromProps() {
    form.kelas = props.kelasId;
    form.jam_pel = props.kbm?.jam_pel ?? 40;
    form.jam_mulai = props.kbm?.jam_mulai ?? '07:00';
    form.jml_mapel = props.kbm?.jml_mapel ?? 8;

    const breaks = emptyBreaks();
    (props.kbm?.istirahat ?? []).slice(0, 4).forEach((item, index) => {
        breaks[index] = { jam_ke: item.jam_ke, durasi: item.durasi };
    });
    form.istirahat = breaks;

    rebuildGrid(new Map());
}

function rebuildGrid(existing) {
    const fromProps = new Map(
        (props.template ?? []).map((row) => [`${row.jam_ke}-${row.hari}`, row.id_mapel ?? '']),
    );
    const total = Math.min(12, Math.max(1, Number(form.jml_mapel) || 1));

    grid.value = Array.from({ length: total }, (_, index) => {
        const jam = index + 1;
        const mapel = {};

        for (const hari of HARI) {
            const key = `${jam}-${hari}`;
            mapel[hari] = existing.has(key) ? existing.get(key) : (fromProps.get(key) ?? '');
        }

        return { jam_ke: jam, mapel };
    });
}

function captureGrid() {
    const values = new Map();
    grid.value.forEach((row) => {
        for (const hari of HARI) {
            if (row.mapel[hari]) {
                values.set(`${row.jam_ke}-${hari}`, row.mapel[hari]);
            }
        }
    });

    return values;
}

watch(() => [props.kelasId, props.kbm, props.template], syncFromProps, { immediate: true, deep: true });
watch(() => form.jml_mapel, () => rebuildGrid(captureGrid()));

const breakMap = computed(() => {
    const map = new Map();
    (form.istirahat ?? []).forEach((item) => {
        const jam = Number(item.jam_ke);
        const durasi = Number(item.durasi);
        if (jam && durasi) {
            map.set(jam, durasi);
        }
    });

    return map;
});

function toMinutes(value) {
    const match = /^(\d{1,2}):(\d{2})/.exec(String(value ?? ''));
    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
}

function formatClock(total) {
    if (total === null) {
        return '';
    }

    const jam = Math.floor(total / 60) % 24;
    const menit = total % 60;
    return `${String(jam).padStart(2, '0')}:${String(menit).padStart(2, '0')}`;
}

const times = computed(() => {
    let cursor = toMinutes(form.jam_mulai);

    return grid.value.map((row) => {
        const istirahat = breakMap.value.get(row.jam_ke) ?? 0;

        if (cursor === null) {
            return istirahat ? `Istirahat ${istirahat} menit` : '';
        }

        cursor += istirahat;
        const mulai = cursor;
        cursor += Number(form.jam_pel) || 0;
        const waktu = `${formatClock(mulai)}-${formatClock(cursor)}`;

        return istirahat ? `${waktu} · istirahat ${istirahat} menit` : waktu;
    });
});

function pilihKelas(event) {
    if (!event.target.value) {
        return;
    }

    router.get('/elearning/jadwal-pelajaran', { kelas: event.target.value }, { preserveScroll: true });
}

function save() {
    form.kelas = props.kelasId;
    form.istirahat = form.istirahat.map((item) => ({
        jam_ke: Number(item.jam_ke) || null,
        durasi: Number(item.durasi) || null,
    }));
    form.template = grid.value.map((row) => ({
        jam_ke: row.jam_ke,
        mapel: Object.fromEntries(HARI.map((hari) => [hari, row.mapel[hari] ? Number(row.mapel[hari]) : null])),
    }));

    form.post('/elearning/jadwal-pelajaran', { preserveScroll: true });
}
</script>

<template>
    <Head title="Jadwal Pelajaran" />
    <AdminLayout title="Jadwal Pelajaran">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Jadwal Pelajaran">
                    <template #description>Atur durasi jam, istirahat, dan mapel tiap hari. Periode {{ periode }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" :disabled="!props.classes.length || form.processing" @click="save">
                    <i class="fas fa-save h-4 w-4" aria-hidden="true" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan Jadwal' }}
                </button>
            </div>

            <div v-if="!props.classes.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-calendar-alt mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada kelas pada periode ini.</p>
                <p class="mt-1 text-sm text-muted-foreground">Tambahkan data kelas terlebih dahulu.</p>
            </div>

            <template v-else>
                <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <label :class="labelClass" for="kelas">Kelas</label>
                    <select id="kelas" :class="inputClass" :value="props.kelasId" @change="pilihKelas">
                        <option v-for="item in props.classes" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                    </select>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <h2 class="mb-3 text-sm font-semibold text-foreground">Pengaturan KBM</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label :class="labelClass" for="jam-pel">Durasi satu jam pelajaran (menit)</label>
                            <input id="jam-pel" v-model="form.jam_pel" type="number" min="5" max="120" :class="inputClass" />
                            <p v-if="form.errors.jam_pel" class="mt-1 text-xs text-destructive">{{ form.errors.jam_pel }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="jam-mulai">Jam mulai</label>
                            <input id="jam-mulai" v-model="form.jam_mulai" type="time" :class="inputClass" />
                            <p v-if="form.errors.jam_mulai" class="mt-1 text-xs text-destructive">{{ form.errors.jam_mulai }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="jml-mapel">Jumlah jam per hari</label>
                            <input id="jml-mapel" v-model="form.jml_mapel" type="number" min="1" max="12" :class="inputClass" />
                            <p v-if="form.errors.jml_mapel" class="mt-1 text-xs text-destructive">{{ form.errors.jml_mapel }}</p>
                        </div>
                    </div>

                    <fieldset class="mt-4">
                        <legend class="mb-2 text-xs font-medium text-muted-foreground">Istirahat (opsional, maksimal 4)</legend>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="(item, index) in form.istirahat" :key="index" class="rounded-lg border border-border p-3">
                                <p class="mb-2 text-xs font-medium text-muted-foreground">Istirahat {{ index + 1 }}</p>
                                <div class="space-y-2">
                                    <div>
                                        <label :class="labelClass" :for="`ist-jam-${index}`">Setelah jam ke</label>
                                        <input :id="`ist-jam-${index}`" v-model="item.jam_ke" type="number" min="1" max="12" :class="inputClass" />
                                    </div>
                                    <div>
                                        <label :class="labelClass" :for="`ist-dur-${index}`">Durasi (menit)</label>
                                        <input :id="`ist-dur-${index}`" v-model="item.durasi" type="number" min="5" max="120" :class="inputClass" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.istirahat" class="mt-2 text-xs text-destructive">{{ form.errors.istirahat }}</p>
                    </fieldset>
                </div>

                <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <caption class="sr-only">Template mata pelajaran per jam dan hari</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3">Jam</th>
                                <th scope="col" class="px-3 py-3">Waktu</th>
                                <th v-for="hari in props.hari" :key="hari.id" scope="col" class="px-3 py-3">{{ hari.nama }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in grid" :key="row.jam_ke" class="align-top hover:bg-muted/40">
                                <td class="px-3 py-3 font-medium text-foreground">
                                    {{ row.jam_ke }}
                                    <span v-if="breakMap.has(row.jam_ke)" class="mt-1 block w-fit rounded bg-warning/10 px-1.5 py-0.5 text-2xs font-medium uppercase tracking-wide text-warning">Istirahat</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs text-muted-foreground">{{ times[row.jam_ke - 1] }}</td>
                                <td v-for="hari in props.hari" :key="hari.id" class="px-3 py-3">
                                    <label class="sr-only" :for="`mapel-${row.jam_ke}-${hari.id}`">Mapel {{ hari.nama }} jam {{ row.jam_ke }}</label>
                                    <select :id="`mapel-${row.jam_ke}-${hari.id}`" v-model="row.mapel[hari.id]" :class="inputClass">
                                        <option value="">Kosong</option>
                                        <option v-for="mapel in props.mapels" :key="mapel.id_mapel" :value="mapel.id_mapel">
                                            {{ mapel.nama_mapel }}
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
