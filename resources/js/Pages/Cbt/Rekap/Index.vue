<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import { btnSecondary, cardClass } from '@/ui';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

function formatNilai(value) {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="Rekap Nilai" />
    <AdminLayout title="Rekap Nilai">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px] print:hidden">
                <PageHeader title="Rekap Nilai">
                    <template #description>Matriks nilai seluruh ujian pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="flex items-center gap-2">
                    <ExportLink href="/cbt/rekap/export-xlsx" label="Ekspor Excel" icon="file-excel" />
                    <ExportLink href="/cbt/rekap/export" label="CSV" icon="download" />
                    <button type="button" :class="btnSecondary" @click="printPage">
                        <i class="fas fa-print h-4 w-4" ></i> Cetak
                    </button>
                </div>
            </div>

            <div v-if="!props.columns.length" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-table mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <div v-else :class="cardClass" class="overflow-x-auto print:border-0 print:shadow-none">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-muted/60 px-3 py-3">#</th>
                            <th class="sticky left-8 z-10 bg-muted/60 px-4 py-3">Peserta</th>
                            <th class="px-3 py-3">Kelas</th>
                            <th v-for="column in props.columns" :key="column.id_jadwal" class="px-3 py-3 text-center" :title="column.label">
                                {{ column.short }}
                            </th>
                            <th class="px-4 py-3 text-center">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(row, index) in props.rows" :key="row.id_siswa" class="hover:bg-muted/40">
                            <td class="sticky left-0 z-10 bg-card px-3 py-3 text-muted-foreground">{{ index + 1 }}</td>
                            <td class="sticky left-8 z-10 bg-card px-4 py-3">
                                <p class="font-medium text-foreground">{{ row.nama }}</p>
                                <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                            </td>
                            <td class="px-3 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                            <td v-for="column in props.columns" :key="column.id_jadwal" class="px-3 py-3 text-center">
                                <span v-if="row.nilai[column.id_jadwal] !== null" class="font-medium text-foreground">
                                    {{ formatNilai(row.nilai[column.id_jadwal]) }}
                                </span>
                                <span v-else class="text-muted-foreground/50">-</span>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-foreground">{{ formatNilai(row.rata_rata) }}</td>
                        </tr>
                        <tr v-if="!props.rows.length">
                            <td :colspan="props.columns.length + 4" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada nilai yang terkumpul.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="props.columns.length" class="flex flex-wrap gap-3 text-xs text-muted-foreground print:hidden">
                <span v-for="column in props.columns" :key="column.id_jadwal">
                    <b class="text-foreground">{{ column.short }}</b> = {{ column.label }}
                </span>
            </div>
        </div>
    </AdminLayout>
</template>
