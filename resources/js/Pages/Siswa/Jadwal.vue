<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass, inputClass } from '@/ui';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    kelasId: { type: Number, default: 0 },
    days: { type: Array, default: () => [] },
    slots: { type: Array, default: () => [] },
    kbm: { type: Object, default: () => ({ jam_pel: 40, jam_mulai: '07:00', jml_mapel: 8 }) },
    student: { type: Object, required: true },
    periode: { type: String, default: '-' },
});

const title = 'Jadwal Pelajaran';
const hasSchedule = computed(() => props.slots.some((slot) => props.days.some((day) => slot.mapel[day.id])));

function pilihKelas(event) {
    router.get('/siswa/jadwal', { kelas: event.target.value }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />
    <AdminLayout :title="title">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="title">
                    <template #description>Jadwal kelas Anda pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.student.nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.student.kelas || 'Belum ada kelas' }}</p>
                </div>
            </div>

            <div v-if="props.classes.length > 1" :class="cardClass" class="p-4">
                <label for="kelas" class="mb-1 block text-xs font-medium text-muted-foreground">Pilih kelas</label>
                <select id="kelas" :class="inputClass" :value="props.kelasId" @change="pilihKelas">
                    <option v-for="kelas in props.classes" :key="kelas.id_kelas" :value="kelas.id_kelas">{{ kelas.nama_kelas }}</option>
                </select>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/60 px-4 py-3">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-foreground">
                        <i class="fas fa-calendar-alt h-4 w-4 text-primary" ></i> Jadwal mingguan
                    </p>
                    <p class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <i class="fas fa-clock h-3.5 w-3.5" ></i> {{ props.kbm.jml_mapel }} jam · {{ props.kbm.jam_pel }} menit per jam · mulai {{ props.kbm.jam_mulai }}
                    </p>
                </div>

                <div v-if="hasSchedule" class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <caption class="sr-only">Jadwal pelajaran mingguan kelas {{ props.student.kelas }}</caption>
                        <thead>
                            <tr class="bg-card">
                                <th scope="col" class="sticky left-0 z-10 border-b border-border bg-card px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Jam</th>
                                <th
                                    v-for="day in props.days"
                                    :key="day.id"
                                    scope="col"
                                    class="border-b border-border px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide"
                                    :class="day.today ? 'bg-primary/10 text-primary' : 'text-muted-foreground'"
                                >
                                    {{ day.nama }}
                                    <span v-if="day.today" class="block text-2xs font-medium normal-case text-primary">Hari ini</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="slot in props.slots" :key="slot.jam">
                                <tr class="align-top">
                                    <th scope="row" class="sticky left-0 z-10 border-b border-border bg-card px-3 py-3 text-left">
                                        <span class="block text-xs font-semibold text-foreground">Jam {{ slot.jam }}</span>
                                        <span class="block text-2xs font-normal text-muted-foreground">{{ slot.mulai }}–{{ slot.selesai }}</span>
                                    </th>
                                    <td
                                        v-for="day in props.days"
                                        :key="day.id"
                                        class="border-b border-border px-3 py-3 text-center"
                                        :class="day.today ? 'bg-primary/10/40' : ''"
                                    >
                                        <template v-if="slot.mapel[day.id]">
                                            <p class="font-medium text-foreground">{{ slot.mapel[day.id].nama_mapel }}</p>
                                            <p v-if="slot.mapel[day.id].kode" class="mt-0.5 font-mono text-2xs text-muted-foreground">{{ slot.mapel[day.id].kode }}</p>
                                        </template>
                                        <span v-else class="text-xs text-muted-foreground/50">-</span>
                                    </td>
                                </tr>
                                <tr v-if="slot.istirahat" :key="`ist-${slot.jam}`">
                                    <th scope="row" class="sticky left-0 z-10 border-b border-border bg-warning/10 px-3 py-1.5 text-left text-2xs font-medium text-warning">
                                        <span class="inline-flex items-center gap-1"><i class="fas fa-coffee h-3 w-3" ></i> Istirahat</span>
                                    </th>
                                    <td :colspan="props.days.length" class="border-b border-border bg-warning/10 px-3 py-1.5 text-xs text-warning">
                                        {{ slot.istirahat.mulai }}–{{ slot.istirahat.selesai }} · {{ slot.istirahat.durasi }} menit
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-4 py-10 text-center">
                    <i class="fas fa-calendar-alt mx-auto mb-2 h-6 w-6 text-muted-foreground/50" ></i>
                    <p class="text-sm font-medium text-muted-foreground">Jadwal belum tersedia.</p>
                    <p class="mt-1 text-xs text-muted-foreground">Jadwal pelajaran akan tampil setelah wali kelas atau admin menyusunnya.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
