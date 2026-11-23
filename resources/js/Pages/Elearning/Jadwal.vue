<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: 0 },
    days: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    weekStart: { type: String, default: '' },
    weekLabel: { type: String, default: '' },
    prevWeek: { type: String, default: '' },
    nextWeek: { type: String, default: '' },
    jamMulai: { type: String, default: null },
    jamPel: { type: Number, default: 0 },
    isAdmin: { type: Boolean, default: false },
    periode: { type: String, default: '' },
});

const showModal = ref(false);
const cell = ref(null);
const selected = ref('');

const slots = computed(() => props.days[0]?.slots ?? []);
const materiOptions = computed(() => props.items.filter((item) => item.jenis === 1));
const tugasOptions = computed(() => props.items.filter((item) => item.jenis === 2));
const options = computed(() => (cell.value?.jenis === 1 ? materiOptions.value : tugasOptions.value));

const totalTerisi = computed(() =>
    props.days.reduce((total, day) => total + day.slots.reduce((sub, slot) => sub + (slot.materi ? 1 : 0) + (slot.tugas ? 1 : 0), 0), 0),
);

function slotFor(day, jam) {
    return day.slots.find((slot) => slot.jam === jam);
}

function goClass(event) {
    router.get('/elearning/jadwal', { kelas: event.target.value, week: props.weekStart }, { preserveState: true, preserveScroll: true });
}

function goWeek(week) {
    router.get('/elearning/jadwal', { kelas: props.kelasId, week }, { preserveState: true, preserveScroll: true });
}

function jamRange(jam) {
    if (!props.jamMulai || !props.jamPel) return null;

    const [h, m] = String(props.jamMulai).split(':').map(Number);
    const start = h * 60 + (m || 0) + (jam - 1) * props.jamPel;
    const end = start + props.jamPel;
    const fmt = (total) => `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;

    return `${fmt(start)}–${fmt(end)}`;
}

function openSlot(day, slot, jenis, existing) {
    cell.value = { date: day.date, label: day.label, jam: slot.jam, jenis, existing };
    selected.value = existing ? String(existing.id_materi) : '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    cell.value = null;
}

function save() {
    if (!cell.value) return;

    router.post(
        '/elearning/jadwal',
        {
            kelas: props.kelasId,
            date: cell.value.date,
            jam: cell.value.jam,
            jenis: cell.value.jenis,
            id_materi: selected.value ? Number(selected.value) : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        },
    );
}

function clearSlot() {
    selected.value = '';
    save();
}
</script>

<template>
    <Head title="Jadwal E-learning" />
    <AdminLayout title="Jadwal E-learning">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Jadwal Materi & Tugas" description="Atur materi dan tugas yang muncul untuk siswa pada setiap jam pelajaran." />
                <div class="flex flex-wrap items-center gap-2">
                    <select v-if="props.classes.length" :value="props.kelasId" :class="inputClass" class="max-w-44" @change="goClass" aria-label="Pilih kelas">
                        <option v-for="kelas in props.classes" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                    </select>
                    <p v-else class="text-xs text-muted-foreground">Belum ada kelas pada periode ini.</p>
                </div>
            </div>

            <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex items-center gap-2">
                    <button type="button" :class="btnSecondary" aria-label="Minggu sebelumnya" @click="goWeek(props.prevWeek)"><i class="fas fa-chevron-left h-4 w-4" aria-hidden="true" ></i></button>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-foreground">{{ props.weekLabel }}</p>
                        <p class="text-xs text-muted-foreground">{{ props.periode }}</p>
                    </div>
                    <button type="button" :class="btnSecondary" aria-label="Minggu berikutnya" @click="goWeek(props.nextWeek)"><i class="fas fa-chevron-right h-4 w-4" aria-hidden="true" ></i></button>
                </div>
                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1"><i class="fas fa-calendar-alt h-3.5 w-3.5" ></i> {{ totalTerisi }} jadwal terisi</span>
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-success/100"></span> Materi
                        <span class="h-2.5 w-2.5 rounded-full bg-primary/100"></span> Tugas
                    </span>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div v-if="props.kelasId && props.days.length" class="overflow-x-auto">
                    <table class="data-table w-full min-w-[960px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-24 px-3 py-3">Jam</th>
                                <th v-for="day in props.days" :key="day.date" class="px-3 py-3">{{ day.label }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in slots" :key="row.jam" class="align-top">
                                <td class="bg-muted/60 px-3 py-3">
                                    <p class="font-semibold text-foreground">Jam {{ row.jam }}</p>
                                    <p v-if="jamRange(row.jam)" class="text-2xs text-muted-foreground">{{ jamRange(row.jam) }}</p>
                                </td>
                                <td v-for="day in props.days" :key="`${day.date}-${row.jam}`" class="px-2 py-2">
                                    <template v-for="slot in [slotFor(day, row.jam)]" :key="`${day.date}-${row.jam}-${slot.mapel ?? slot.materi?.kode ?? 'kosong'}`">
                                        <p v-if="slot.mapel" class="mb-1 truncate text-2xs font-medium text-muted-foreground">{{ slot.mapel }}</p>
                                        <div class="space-y-1.5">
                                            <div v-if="slot.materi" class="group rounded-lg border border-success/30 bg-success/10 p-2">
                                                <p class="line-clamp-2 text-xs font-medium text-success">{{ slot.materi.judul }}</p>
                                                <div class="mt-1 flex items-center justify-between gap-1">
                                                    <span class="text-2xs text-success">{{ slot.materi.kode }}</span>
                                                    <button type="button" class="text-2xs font-medium text-success hover:underline" @click="openSlot(day, slot, 1, slot.materi)">Ubah</button>
                                                </div>
                                            </div>
                                            <button v-else type="button" class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-border px-2 py-1.5 text-2xs text-muted-foreground transition hover:border-success/40 hover:text-success" @click="openSlot(day, slot, 1, null)">
                                                <i class="fas fa-plus h-3 w-3" ></i> Materi
                                            </button>

                                            <div v-if="slot.tugas" class="group rounded-lg border border-primary/30 bg-primary/10 p-2">
                                                <p class="line-clamp-2 text-xs font-medium text-primary-900">{{ slot.tugas.judul }}</p>
                                                <div class="mt-1 flex items-center justify-between gap-1">
                                                    <span class="text-2xs text-primary">{{ slot.tugas.kode }}</span>
                                                    <button type="button" class="text-2xs font-medium text-primary hover:underline" @click="openSlot(day, slot, 2, slot.tugas)">Ubah</button>
                                                </div>
                                            </div>
                                            <button v-else type="button" class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-border px-2 py-1.5 text-2xs text-muted-foreground transition hover:border-primary/40 hover:text-primary" @click="openSlot(day, slot, 2, null)">
                                                <i class="fas fa-plus h-3 w-3" ></i> Tugas
                                            </button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-4 py-12 text-center text-sm text-muted-foreground">
                    Pilih kelas untuk mulai mengatur jadwal.
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Hanya materi dan tugas yang berstatus aktif serta ditujukan untuk kelas ini yang dapat dijadwalkan.
            </p>
        </div>

        <Modal :show="showModal" max-width="max-w-xl" :title="`Atur ${cell?.jenis === 1 ? 'Materi' : 'Tugas'}`" @close="closeModal">
            <form v-if="cell" class="space-y-4" @submit.prevent="save">
                <div class="rounded-lg bg-muted/60 px-3 py-2 text-sm text-muted-foreground">
                    {{ cell.label }} · Jam {{ cell.jam }}
                </div>

                <div>
                    <label :class="labelClass">{{ cell.jenis === 1 ? 'Materi' : 'Tugas' }}</label>
                    <select v-model="selected" :class="inputClass" :disabled="!options.length" aria-label="Pilih jadwal pelajaran">
                        <option value="">{{ cell.existing ? 'Kosongkan jadwal' : 'Pilih' }}</option>
                        <option v-for="item in options" :key="item.id_materi" :value="String(item.id_materi)">
                            {{ item.judul }} · {{ item.mapel }}
                        </option>
                    </select>
                    <p v-if="!options.length" class="mt-1 text-xs text-warning">
                        Belum ada {{ cell.jenis === 1 ? 'materi' : 'tugas' }} aktif untuk kelas ini. Buat terlebih dahulu di menu {{ cell.jenis === 1 ? 'Materi Belajar' : 'Tugas Kelas' }}.
                    </p>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-border pt-4">
                    <button v-if="cell.existing" type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/10" @click="clearSlot">
                        <i class="fas fa-trash-alt h-3.5 w-3.5" ></i> Kosongkan
                    </button>
                    <span v-else></span>
                    <div class="flex gap-2">
                        <button type="button" :class="btnSecondary" @click="closeModal">Batal</button>
                        <button type="submit" :class="btnPrimary" :disabled="!selected && !cell.existing">Simpan</button>
                    </div>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
