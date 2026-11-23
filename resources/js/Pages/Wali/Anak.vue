<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';
import { computed } from 'vue';

const props = defineProps({
    siswa: { type: Object, required: true },
    nilai: { type: Array, default: () => [] },
    kehadiran: { type: Object, default: () => ({ hadir: 0, alpa: 0, total: 0, persen: null }) },
    catatan: { type: Array, default: () => [] },
    jadwal: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const rata = computed(() => {
    const values = props.nilai.map((row) => row.total).filter((value) => value !== null);

    return values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null;
});

const tuntas = computed(() => props.nilai.filter((row) => row.tuntas).length);

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(value) || 0);
}
</script>

<template>
    <Head :title="`Wali Murid · ${props.siswa.nama}`" />
    <AdminLayout :title="props.siswa.nama">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-3 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="props.siswa.nama">
                    <template #description>
                        {{ props.siswa.nis }} · {{ props.siswa.kelas || 'Kelas belum diatur' }} · Periode {{ props.periode }}
                    </template>
                </PageHeader>
                <Link href="/wali-murid" class="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm font-medium text-foreground hover:bg-muted/40">
                    <i class="fas fa-arrow-left h-4 w-4" aria-hidden="true"></i> Anak Saya
                </Link>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div :class="cardClass" class="p-4">
                    <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Rata nilai</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ rata === null ? '-' : formatNumber(rata) }}</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Ujian tuntas</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ tuntas }}<span class="text-xs font-normal text-muted-foreground">/{{ props.nilai.length }}</span></p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Kehadiran bulan ini</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ props.kehadiran.persen === null ? '-' : `${formatNumber(props.kehadiran.persen)}%` }}</p>
                    <p class="text-2xs text-muted-foreground">{{ props.kehadiran.hadir }} hadir · {{ props.kehadiran.alpa }} alpa · {{ props.kehadiran.total }} sesi</p>
                </div>
                <div :class="cardClass" class="p-4">
                    <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Ujian mendatang</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ props.jadwal.length }}</p>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-foreground">Nilai Ujian</h2>
                    <p class="mt-1 text-2xs text-muted-foreground">Termasuk ujian remedial bila ada.</p>
                </div>
                <div v-if="props.nilai.length" class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Ujian</th>
                                <th class="px-4 py-3">Mapel</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Nilai</th>
                                <th class="px-4 py-3">KKM</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.nilai" :key="`${row.id_jadwal}-${row.tgl}`" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.bank_nama }}</p>
                                    <span v-if="row.remedial" class="mt-1 inline-block rounded bg-primary/10 px-1.5 py-0.5 text-2xs font-semibold text-primary">Remedial</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.mapel }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.tgl }}</td>
                                <td class="px-4 py-3 font-semibold text-foreground">{{ formatNumber(row.total) }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.kkm || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="row.kkm === 0"
                                        class="text-xs text-muted-foreground"
                                    >KKM belum diisi</span>
                                    <span v-else-if="row.tuntas" class="rounded-full border border-success/30 bg-success/10 px-2.5 py-1 text-xs font-medium text-success">Tuntas</span>
                                    <span v-else class="rounded-full border border-destructive/30 bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive">Belum tuntas</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                    Belum ada nilai ujian pada periode ini.
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div :class="cardClass" class="overflow-hidden">
                    <div class="border-b border-border px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Jadwal Ujian Mendatang</h2>
                    </div>
                    <div v-if="props.jadwal.length" class="divide-y divide-border">
                        <div v-for="item in props.jadwal" :key="item.id_jadwal" class="px-5 py-3">
                            <p class="truncate text-sm font-medium text-foreground">
                                {{ item.bank_nama }}
                                <span v-if="item.remedial" class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-2xs font-semibold text-primary">Remedial</span>
                            </p>
                            <p class="text-xs text-muted-foreground">{{ item.jenis }} · {{ item.mulai }} – {{ item.selesai }}</p>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-muted-foreground">Belum ada jadwal ujian mendatang.</div>
                </div>

                <div :class="cardClass" class="overflow-hidden">
                    <div class="border-b border-border px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Catatan Guru & Wali Kelas</h2>
                    </div>
                    <div v-if="props.catatan.length" class="divide-y divide-border">
                        <div v-for="item in props.catatan" :key="item.id_catatan" class="px-5 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded-full bg-muted px-2 py-0.5 text-2xs font-semibold text-muted-foreground">{{ item.level }}</span>
                                <span class="text-2xs text-muted-foreground">{{ item.tanggal }} · {{ item.untuk }}</span>
                            </div>
                            <p class="mt-1 text-sm text-foreground">{{ item.text }}</p>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-muted-foreground">Belum ada catatan.</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
