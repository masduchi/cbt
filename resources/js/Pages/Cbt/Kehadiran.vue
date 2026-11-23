<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass, inputClass } from '@/ui';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    peserta: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({ peserta: 0, hadir: 0, belum: 0 }) },
    periode: { type: String, default: '' },
});

const kode = ref('');
const processing = ref(false);
const inputRef = ref(null);

const belum = computed(() => props.peserta.filter((row) => !row.hadir));

function changeJadwal(event) {
    router.get('/cbt/kehadiran', { jadwal: event.target.value }, { preserveState: false });
}

function tandai() {
    const value = kode.value.trim();

    if (!value || !props.selected) return;

    router.post(
        `/cbt/kehadiran/${props.selected}`,
        { kode: value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => {
                kode.value = '';
                nextTick(() => inputRef.value?.focus());
            },
        },
    );
}

function batal(row) {
    if (!props.selected) return;

    router.delete(`/cbt/kehadiran/${props.selected}/${row.id_siswa}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kehadiran Peserta" />
    <AdminLayout title="Kehadiran Peserta">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Kehadiran Peserta">
                    <template #description>
                        Pindai QR kartu peserta atau ketik nomor peserta/NIS, lalu tekan Enter. Periode {{ props.periode }}.
                    </template>
                </PageHeader>
                <div class="w-full max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-kehadiran">Jadwal Ujian</label>
                    <select id="jadwal-kehadiran" :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal">
                        <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                            {{ item.label }} · {{ item.tgl_mulai }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="!props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-user-check mx-auto h-10 w-10 text-muted-foreground/50"></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else>
                <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                        <p class="text-xs text-muted-foreground">{{ props.jadwal.jenis }} · {{ props.jadwal.tgl_mulai }} – {{ props.jadwal.tgl_selesai }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-muted px-3 py-1 text-muted-foreground">Peserta: <strong class="text-foreground">{{ props.ringkasan.peserta }}</strong></span>
                        <span class="rounded-full bg-success/10 px-3 py-1 text-success">Hadir: <strong>{{ props.ringkasan.hadir }}</strong></span>
                        <span class="rounded-full bg-warning/10 px-3 py-1 text-warning">Belum: <strong>{{ props.ringkasan.belum }}</strong></span>
                    </div>
                </div>

                <form :class="cardClass" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end" @submit.prevent="tandai">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground" for="kode-peserta">QR kartu / nomor peserta / NIS</label>
                        <input
                            id="kode-peserta"
                            ref="inputRef"
                            v-model="kode"
                            :class="inputClass"
                            autocomplete="off"
                            autofocus
                            placeholder="Pindai QR kartu atau ketik nomor peserta lalu Enter"
                        />
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            QR kartu peserta (kartu per jadwal maupun kartu global) dan nomor peserta/NIS sama-sama diterima.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="processing || !kode.trim()"
                    >
                        <i class="fas" :class="processing ? 'fa-circle-notch animate-spin' : 'fa-check'" aria-hidden="true"></i>
                        Tandai hadir
                    </button>
                </form>

                <div :class="cardClass" class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th class="px-4 py-3">No. Peserta</th>
                                <th class="px-4 py-3">Ruang / Sesi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.peserta" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.no_peserta || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.ruang || '-' }} / {{ row.sesi || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
                                        :class="row.hadir ? 'border-success/30 bg-success/10 text-success' : 'border-border bg-muted text-muted-foreground'"
                                    >
                                        {{ row.hadir ? `Hadir ${row.waktu ?? ''}` : 'Belum hadir' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="row.hadir"
                                        type="button"
                                        :class="btnSecondary"
                                        :aria-label="`Batalkan kehadiran ${row.nama}`"
                                        @click="batal(row)"
                                    >
                                        <i class="fas fa-rotate-left h-3.5 w-3.5"></i> Batalkan
                                    </button>
                                    <span v-else class="text-xs text-muted-foreground">-</span>
                                </td>
                            </tr>
                            <tr v-if="!props.peserta.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Belum ada peserta terdaftar pada jadwal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="belum.length" class="text-xs text-muted-foreground">
                    {{ belum.length }} peserta belum ditandai hadir.
                </p>
            </template>
        </div>
    </AdminLayout>
</template>
