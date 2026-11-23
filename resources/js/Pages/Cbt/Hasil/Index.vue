<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import { cardClass, inputClass } from '@/ui';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    peserta: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    periode: { type: String, default: '' },
    riwayat: { type: Array, default: () => [] },
    notifikasi_gagal: { type: Number, default: 0 },
});

const alasanLabel = {
    reset_otomatis: 'Reset otomatis (batas percobaan)',
    pengawas_mengulang: 'Diizinkan mengulang pengawas',
    jadwal_remedial_dihapus: 'Jadwal remedial dihapus',
};

function alasanText(value) {
    return alasanLabel[value] ?? value;
}

const komponenLabel = {
    pg: 'PG',
    kompleks: 'Kompleks',
    jodohkan: 'Jodohkan',
    isian: 'Isian',
    esai: 'Esai',
};

function changeJadwal(event) {
    router.get('/cbt/hasil', { jadwal: event.target.value }, { preserveState: false });
}

function ulangiNotifikasi() {
    if (!props.selected || !props.notifikasi_gagal) return;
    if (!window.confirm(`Kirim ulang ${props.notifikasi_gagal} notifikasi hasil yang gagal?`)) return;

    router.post(`/cbt/hasil/${props.selected}/notifikasi`, {}, { preserveScroll: true });
}

function formatNilai(value) {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function statusLabel(row) {
    if (row.status === 1) return 'Mengerjakan';
    if (row.status === 2) return 'Selesai';
    return 'Belum mulai';
}

function statusClass(row) {
    if (row.status === 1) return 'bg-warning/10 text-warning border-warning/30';
    if (row.status === 2) return 'bg-success/10 text-success border-success/30';
    return 'bg-muted text-muted-foreground border-border';
}
</script>

<template>
    <Head title="Hasil Ujian" />
    <AdminLayout title="Hasil Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Hasil Ujian">
                    <template #description>Rekap nilai peserta per jadwal ujian. Periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="flex w-full max-w-md items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-hasil">Jadwal Ujian</label>
                        <select id="jadwal-hasil" :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal">
                            <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                                {{ item.label }} · {{ item.tgl_mulai }}
                            </option>
                        </select>
                    </div>
                    <button
                        v-if="props.selected && props.notifikasi_gagal"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-warning/40 bg-warning/10 px-3 text-xs font-medium text-warning transition hover:bg-warning/20"
                        :title="`${props.notifikasi_gagal} notifikasi gagal dikirim`"
                        @click="ulangiNotifikasi"
                    >
                        <i class="fas fa-paper-plane h-3.5 w-3.5" ></i>
                        Kirim ulang ({{ props.notifikasi_gagal }})
                    </button>
                    <ExportLink
                        v-if="props.selected"
                        :href="`/cbt/hasil/export?jadwal=${props.selected}`"
                        label="Excel"
                        icon="file-excel"
                    />
                </div>
            </div>

            <div v-if="!props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-chart-bar mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else>
                <div :class="cardClass" class="p-4">
                    <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.jadwal.jenis }} · {{ props.jadwal.tgl_mulai }} · KKM {{ props.jadwal.kkm || '-' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-2xs text-muted-foreground">
                        <span v-for="(label, key) in komponenLabel" :key="key" class="rounded-full bg-muted px-2.5 py-1">
                            {{ label }}: bobot {{ props.jadwal.bobot[key] }}
                        </span>
                    </div>
                </div>

                <div v-if="props.summary" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div :class="cardClass" class="p-4">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground"><i class="fas fa-users h-4 w-4" ></i> Peserta</div>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.peserta }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground"><i class="fas fa-check-circle h-4 w-4" ></i> Selesai</div>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.summary.selesai }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Rata-rata</div>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ formatNilai(props.summary.rata_rata) }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Tertinggi</div>
                        <p class="mt-1 text-xl font-semibold text-success">{{ formatNilai(props.summary.tertinggi) }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Terendah</div>
                        <p class="mt-1 text-xl font-semibold text-destructive">{{ formatNilai(props.summary.terendah) }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground"><i class="fas fa-trophy h-4 w-4" ></i> Tuntas</div>
                        <p class="mt-1 text-xl font-semibold text-foreground">
                            {{ props.summary.tuntas === null ? '-' : `${props.summary.tuntas}/${props.summary.selesai}` }}
                        </p>
                    </div>
                </div>

                <div :class="cardClass" class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-3">#</th>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-3 py-3">Kelas</th>
                                <th v-for="(label, key) in komponenLabel" :key="key" class="px-3 py-3 text-center">{{ label }}</th>
                                <th class="px-4 py-3 text-center">Total</th>
                                <th class="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.peserta" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-3 py-3 text-muted-foreground">{{ row.rank ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">
                                        {{ row.nama }}
                                        <span
                                            v-if="row.remedial"
                                            class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-2xs font-semibold text-primary"
                                        >Nilai remedial</span>
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}<span v-if="row.no_peserta"> · {{ row.no_peserta }}</span></p>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                                <template v-if="row.komponen">
                                    <td v-for="(label, key) in komponenLabel" :key="key" class="px-3 py-3 text-center text-foreground">
                                        {{ formatNilai(row.komponen[key]) }}
                                    </td>
                                </template>
                                <template v-else>
                                    <td v-for="(label, key) in komponenLabel" :key="key" class="px-3 py-3 text-center text-muted-foreground/50">-</td>
                                </template>
                                <td class="px-4 py-3 text-center" :title="row.remedial ? 'Nilai terbaik dari sesi remedial' : 'Nilai ujian utama'">
                                    <span v-if="row.total !== null" class="font-semibold" :class="row.total >= props.jadwal.kkm && props.jadwal.kkm > 0 ? 'text-success' : 'text-foreground'">
                                        {{ formatNilai(row.total) }}
                                    </span>
                                    <span v-else class="text-muted-foreground/50">-</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full border px-2 py-0.5 text-2xs font-medium" :class="statusClass(row)">
                                        {{ statusLabel(row) }}
                                    </span>
                                    <span v-if="row.status === 2 && !row.dikoreksi" class="ml-1 inline-flex items-center text-2xs text-warning">
                                        <i class="fas fa-hourglass-half mr-0.5 h-3 w-3" ></i> esai
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!props.peserta.length">
                                <td colspan="10" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Belum ada peserta pada jadwal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.riwayat.length" :class="cardClass" class="overflow-x-auto">
                    <div class="border-b border-border px-4 py-3">
                        <p class="text-sm font-semibold text-foreground">Riwayat percobaan</p>
                        <p class="text-xs text-muted-foreground">Percobaan yang direset, diulang, atau ikut terhapus tetap tersimpan untuk audit, termasuk percobaan pada sesi remedial jadwal ini.</p>
                    </div>
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-3 py-3">Percobaan</th>
                                <th class="px-3 py-3 text-center">Total</th>
                                <th class="px-3 py-3">Sumber</th>
                                <th class="px-4 py-3">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.riwayat" :key="row.id" class="hover:bg-muted/40">
                                <td class="px-4 py-3 text-muted-foreground">{{ row.waktu || '-' }}</td>
                                <td class="px-4 py-3 font-medium text-foreground">{{ row.nama }}</td>
                                <td class="px-3 py-3 text-muted-foreground">ke-{{ row.percobaan + 1 }}</td>
                                <td class="px-3 py-3 text-center font-semibold text-foreground">{{ formatNilai(row.total) }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-2xs font-medium"
                                        :class="row.sumber === 'Remedial' ? 'border-primary/30 bg-primary/10 text-primary' : 'border-border bg-muted text-muted-foreground'"
                                    >{{ row.sumber }}</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ alasanText(row.alasan) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
