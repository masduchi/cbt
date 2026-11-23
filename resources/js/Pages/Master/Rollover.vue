<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { computed, reactive } from 'vue';

const props = defineProps({
    years: { type: Array, default: () => [] },
    semesters: { type: Array, default: () => [] },
    sourceTp: { type: Number, default: 0 },
    sourceSmt: { type: Number, default: 0 },
    suggestion: { type: String, default: '' },
    classes: { type: Array, default: () => [] },
});

const filter = reactive({
    sumber_tp: props.sourceTp,
    sumber_smt: props.sourceSmt,
});

const form = useForm({
    sumber_tp: props.sourceTp,
    sumber_smt: props.sourceSmt,
    tahun: props.suggestion,
    target_smt: props.sourceSmt || 1,
    classes: props.classes.map((row) => ({
        id_kelas: row.id_kelas,
        ikut: row.naik > 0 && row.level < 12,
        target_nama: row.target_nama,
        target_level: row.target_level,
    })),
    salin_pengampu: true,
    aktifkan: false,
});

const levels = [10, 11, 12];

const selected = computed(() => form.classes.filter((row) => row.ikut));
const totalSiswa = computed(() =>
    selected.value.reduce((sum, row) => {
        const source = props.classes.find((item) => item.id_kelas === row.id_kelas);
        return sum + (source?.naik ?? 0);
    }, 0),
);

function reload() {
    router.get(
        '/master/tahun/rollover',
        { sumber_tp: filter.sumber_tp, sumber_smt: filter.sumber_smt },
        { preserveScroll: true },
    );
}

function toggleAll(value) {
    form.classes.forEach((row) => {
        row.ikut = value;
    });
}

function submit() {
    form.post('/master/tahun/rollover', { preserveScroll: true });
}
</script>

<template>
    <Head title="Wizard Tahun Ajaran" />
    <AdminLayout title="Wizard Tahun Ajaran">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Wizard Tahun Ajaran" description="Salin kelas, kenaikan siswa, dan penugasan guru dari periode sebelumnya." />
                <Link href="/master/tahun" :class="btnSecondary">
                    <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali ke Tahun Pelajaran
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section :class="cardClass">
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="text-sm font-semibold text-foreground">1. Periode sumber</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Pilih periode yang datanya akan dipindahkan.</p>
                    </div>
                    <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="sumber-tp">Tahun pelajaran sumber</label>
                            <select id="sumber-tp" v-model.number="filter.sumber_tp" :class="inputClass" @change="reload">
                                <option v-for="row in props.years" :key="row.id_tp" :value="row.id_tp">
                                    {{ row.tahun }}{{ row.active == 1 ? ' (aktif)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass" for="sumber-smt">Semester sumber</label>
                            <select id="sumber-smt" v-model.number="filter.sumber_smt" :class="inputClass" @change="reload">
                                <option v-for="row in props.semesters" :key="row.id_smt" :value="row.id_smt">
                                    {{ row.smt }}{{ row.active == 1 ? ' (aktif)' : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </section>

                <section :class="cardClass">
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="text-sm font-semibold text-foreground">2. Periode tujuan</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Tahun baru akan dibuat, bukan menimpa tahun yang sudah ada.</p>
                    </div>
                    <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="tahun-baru">Tahun pelajaran baru</label>
                            <input id="tahun-baru" v-model="form.tahun" :class="inputClass" placeholder="Contoh: 2027/2028" />
                            <p v-if="form.errors.tahun" class="mt-1 text-xs text-destructive">{{ form.errors.tahun }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="target-smt">Semester tujuan</label>
                            <select id="target-smt" v-model.number="form.target_smt" :class="inputClass">
                                <option v-for="row in props.semesters" :key="row.id_smt" :value="row.id_smt">{{ row.smt }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-2 border-t border-border px-5 py-4">
                        <label class="flex items-start gap-2.5 text-sm text-foreground">
                            <input v-model="form.salin_pengampu" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring" />
                            <span>
                                Salin penugasan guru pengampu
                                <span class="block text-xs text-muted-foreground">Kelas pada penugasan ikut dipetakan ke kelas tujuan.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2.5 text-sm text-foreground">
                            <input v-model="form.aktifkan" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring" />
                            <span>
                                Aktifkan tahun baru setelah dibuat
                                <span class="block text-xs text-muted-foreground">Tahun aktif saat ini akan dinonaktifkan.</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section :class="cardClass">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">3. Pemetaan kelas</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Siswa dengan status naik dipindahkan ke kelas tujuan. Siswa tinggal kelas tidak disertakan.
                            </p>
                        </div>
                        <div v-if="form.classes.length" class="flex items-center gap-2">
                            <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/10" @click="toggleAll(true)">
                                Pilih semua
                            </button>
                            <button type="button" class="rounded-lg px-2 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted" @click="toggleAll(false)">
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <div v-if="!form.classes.length" class="px-5 py-12 text-center">
                        <i class="fas fa-calendar-plus mx-auto h-6 w-6 text-muted-foreground/50" ></i>
                        <p class="mt-2 text-sm font-medium text-foreground">Belum ada kelas pada periode sumber.</p>
                        <p class="mt-1 text-xs text-muted-foreground">Tambahkan kelas terlebih dahulu, lalu jalankan wizard ini lagi.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="data-table min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="w-12 px-4 py-3 text-left font-medium">Ikut</th>
                                    <th class="px-4 py-3 text-left font-medium">Kelas sumber</th>
                                    <th class="px-4 py-3 text-left font-medium">Wali kelas</th>
                                    <th class="px-4 py-3 text-right font-medium">Siswa</th>
                                    <th class="px-4 py-3 text-left font-medium">Nama kelas tujuan</th>
                                    <th class="w-28 px-4 py-3 text-left font-medium">Level</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                <tr v-for="(row, index) in form.classes" :key="row.id_kelas" :class="row.ikut ? '' : 'opacity-60'">
                                    <td class="px-4 py-3">
                                        <input
 v-model="row.ikut"
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
 :aria-label="`Sertakan kelas ${props.classes[index]?.nama ?? ''}`" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">{{ props.classes[index]?.nama }}</p>
                                        <p class="text-xs text-muted-foreground">Level {{ props.classes[index]?.level }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i class="fas fa-graduation-cap h-4 w-4 text-muted-foreground" ></i>
                                            {{ props.classes[index]?.wali || 'Belum ditentukan' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center gap-1 text-foreground">
                                            <i class="fas fa-users h-3.5 w-3.5 text-muted-foreground" ></i>
                                            {{ props.classes[index]?.naik }} / {{ props.classes[index]?.total }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
 v-model="row.target_nama"
 :class="inputClass"
 :disabled="!row.ikut"
 :aria-label="`Nama kelas tujuan untuk ${props.classes[index]?.nama ?? ''}`" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <select v-model.number="row.target_level" :class="inputClass" :disabled="!row.ikut" :aria-label="`Jenjang tujuan ${props.classes[index]?.nama ?? ''}`">
                                            <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="form.errors.classes" class="border-t border-destructive/15 bg-destructive/10 px-5 py-3 text-xs text-destructive">
                        {{ form.errors.classes }}
                    </p>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                        <i class="fas fa-check-circle h-4 w-4 text-success" ></i>
                        {{ selected.length }} kelas dan {{ totalSiswa }} siswa akan dipindahkan.
                    </p>
                    <div class="flex items-center gap-2">
                        <Link href="/master/tahun" :class="btnSecondary">Batal</Link>
                        <button type="submit" :class="btnPrimary" :disabled="form.processing || !selected.length || !form.tahun">
                            <i class="fas fa-arrow-right h-4 w-4" ></i> Buat Tahun Ajaran
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
