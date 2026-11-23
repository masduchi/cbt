<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass } from '@/ui';
import RichContent from '@/Components/RichContent.vue';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    butir: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: null },
    lintas: { type: Boolean, default: false },
    lintasButir: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const jenisLabel = {
    1: 'Pilihan Ganda',
    2: 'PG Kompleks',
    3: 'Menjodohkan',
    4: 'Isian Singkat',
    5: 'Uraian',
};

function changeJadwal(event) {
    router.get('/cbt/analisis', { jadwal: event.target.value }, { preserveState: false });
}

function toggleLintas(value) {
    router.get('/cbt/analisis', value ? { lintas: 1 } : {}, { preserveState: false });
}

const flagLabel = {
    kunci_curiga: 'Kunci perlu dicek',
    revisi: 'Perlu revisi',
    pengecoh_kuat: 'Pengecoh kuat',
};

function flagClass(flag) {
    return flag === 'kunci_curiga'
        ? 'bg-destructive/10 text-destructive border-destructive/30'
        : 'bg-warning/10 text-warning border-warning/30';
}

function kr20Label(value) {
    if (value === null || value === undefined) return 'Tidak tersedia';

    return `${value}`;
}

function kesulitanClass(label) {
    if (label === 'Mudah') return 'bg-success/10 text-success border-success/30';
    if (label === 'Sedang') return 'bg-warning/10 text-warning border-warning/30';
    return 'bg-destructive/10 text-destructive border-destructive/30';
}
</script>

<template>
    <Head title="Analisis Soal" />
    <AdminLayout title="Analisis Soal">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Analisis Butir Soal">
                    <template #description>Statistik jawaban peserta per soal. Periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="flex w-full flex-wrap items-end justify-end gap-2 lg:max-w-2xl">
                    <button type="button" :class="props.lintas ? btnSecondary : btnPrimary" @click="toggleLintas(false)">
                        Per jadwal
                    </button>
                    <button type="button" :class="props.lintas ? btnPrimary : btnSecondary" @click="toggleLintas(true)">
                        <i class="fas fa-layer-group h-3.5 w-3.5"></i> Lintas ujian
                    </button>
                    <div v-if="!props.lintas" class="w-full max-w-sm min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-ujian">Jadwal Ujian</label>
                        <select :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal" id="jadwal-ujian">
                            <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                                {{ item.label }} · {{ item.tgl_mulai }}
                            </option>
                        </select>
                    </div>
                    <ExportLink
                        v-if="props.selected && !props.lintas"
                        :href="`/cbt/analisis/export?jadwal=${props.selected}`"
                        label="Excel"
                        icon="file-excel"
                    />
                </div>
            </div>

            <div v-if="!props.lintas && !props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-file-excel mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else-if="!props.lintas && props.jadwal">
                <div :class="cardClass" class="p-4">
                    <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.jadwal.jenis }} · {{ props.jadwal.tgl_mulai }}</p>
                </div>

                <div v-if="props.ringkasan" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <div :class="cardClass" class="p-4">
                        <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Peserta</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.ringkasan.peserta }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Rata-rata skor</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.ringkasan.rata }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Daya beda rata</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.ringkasan.daya_beda_rata ?? '-' }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Reliabilitas KR-20</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ kr20Label(props.ringkasan.kr20) }}</p>
                        <p class="text-2xs text-muted-foreground">0–1, makin tinggi makin konsisten</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Butir bermasalah</p>
                        <p class="mt-1 text-xl font-semibold" :class="props.ringkasan.butir_bermasalah > 0 ? 'text-warning' : 'text-foreground'">
                            {{ props.ringkasan.butir_bermasalah }}<span class="text-xs font-normal text-muted-foreground">/{{ props.ringkasan.butir_total }}</span>
                        </p>
                        <p class="text-2xs text-muted-foreground">Daya beda rendah atau kunci perlu dicek</p>
                    </div>
                </div>

                <div v-if="!props.butir.length" :class="cardClass" class="p-10 text-center text-sm text-muted-foreground">
                    Belum ada jawaban peserta yang dapat dianalisis.
                </div>

                <div v-else class="grid gap-4">
                    <div v-for="item in props.butir" :key="item.id_soal" :class="cardClass" class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">Soal {{ item.nomor }}</span>
                                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">{{ jenisLabel[item.jenis] }}</span>
                                    <span class="text-xs text-muted-foreground">Poin {{ item.point }}</span>
                                </div>
                                <RichContent :html="item.soal" class="prose prose-sm mt-2 max-w-none text-foreground" />
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <span v-if="item.kesulitan" class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="kesulitanClass(item.kesulitan)">
                                    {{ item.kesulitan }}
                                </span>
                                <span
                                    v-if="item.flag"
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="flagClass(item.flag)"
                                    :title="item.catatan || ''"
                                >
                                    <i class="fas fa-triangle-exclamation mr-1 h-3 w-3" aria-hidden="true"></i>
                                    {{ flagLabel[item.flag] ?? item.flag }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Peserta</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.peserta }}</p>
                            </div>
                            <div class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Menjawab</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.menjawab }}</p>
                            </div>
                            <div v-if="item.benar !== null" class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Benar</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.benar }}</p>
                            </div>
                            <div v-else class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Terkoreksi</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.terkoreksi ?? 0 }}</p>
                            </div>
                            <div v-if="item.benar !== null" class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Ketuntasan</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.persentase }}%</p>
                            </div>
                            <div v-else class="rounded-lg bg-muted/60 px-3 py-2">
                                <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">Rata nilai</p>
                                <p class="text-sm font-semibold text-foreground">{{ item.rata_nilai ?? '-' }}</p>
                            </div>
                        </div>

                        <p v-if="item.daya_beda !== null && item.daya_beda !== undefined" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span>Daya beda <b class="text-foreground">{{ item.daya_beda }}</b></span>
                            <span>Korelasi butir-total <b class="text-foreground">{{ item.korelasi ?? '-' }}</b></span>
                            <span>Atas <b class="text-foreground">{{ item.kelompok_atas }}%</b></span>
                            <span>Bawah <b class="text-foreground">{{ item.kelompok_bawah }}%</b></span>
                        </p>
                        <p v-if="item.catatan" class="mt-1 text-xs text-warning">{{ item.catatan }}</p>

                        <div v-if="item.jenis === 1" class="mt-4 space-y-2 border-t border-border pt-4">
                            <p class="text-xs font-medium text-muted-foreground">Distribusi jawaban</p>
                            <div v-for="opsi in item.opsi" :key="opsi.label" class="flex items-center gap-3">
                                <span class="w-4 text-xs font-semibold text-muted-foreground">{{ opsi.label }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary/100" :style="{ width: `${item.menjawab ? (opsi.jumlah / item.menjawab) * 100 : 0}%` }" />
                                </div>
                                <span class="w-8 text-right text-xs text-muted-foreground">{{ opsi.jumlah }}</span>
                            </div>
                        </div>

                        <p v-else-if="item.jenis === 2" class="mt-3 border-t border-border pt-3 text-xs text-muted-foreground">
                            Rata-rata skor parsial: <b class="text-foreground">{{ item.partial }}%</b>
                        </p>
                    </div>
                </div>
            </template>

            <template v-if="props.lintas">
                <div :class="cardClass" class="p-4">
                    <p class="text-sm font-semibold text-foreground">Analisis butir lintas ujian</p>
                    <p class="text-xs text-muted-foreground">
                        Soal yang sama digabungkan dari seluruh ujian pada periode {{ props.periode }}.
                        Butir dengan flag ditampilkan lebih dahulu.
                    </p>
                </div>

                <div v-if="!props.lintasButir.length" :class="cardClass" class="p-10 text-center text-sm text-muted-foreground">
                    Belum ada jawaban peserta yang dapat dianalisis lintas ujian.
                </div>

                <div v-else :class="cardClass" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Soal</th>
                                <th class="px-4 py-3 font-semibold">Jenis</th>
                                <th class="px-4 py-3 text-right font-semibold">Ujian</th>
                                <th class="px-4 py-3 text-right font-semibold">Peserta</th>
                                <th class="px-4 py-3 text-right font-semibold">Ketuntasan</th>
                                <th class="px-4 py-3 text-right font-semibold">Daya beda</th>
                                <th class="px-4 py-3 font-semibold">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in props.lintasButir" :key="item.id_soal" class="hover:bg-muted/40">
                                <td class="max-w-md px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">Soal {{ item.nomor }}</span>
                                        <span v-if="item.kesulitan" class="rounded-full border px-2 py-0.5 text-xs" :class="kesulitanClass(item.kesulitan)">
                                            {{ item.kesulitan }}
                                        </span>
                                        <span
                                            v-if="item.flag"
                                            class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                            :class="flagClass(item.flag)"
                                        >
                                            {{ flagLabel[item.flag] ?? item.flag }}
                                        </span>
                                    </div>
                                    <RichContent :html="item.soal" class="prose prose-sm mt-1 max-w-none text-foreground line-clamp-2" />
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ jenisLabel[item.jenis] }}</td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ item.jumlah_ujian }}</td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ item.peserta }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-foreground">
                                    {{ item.persentase === null ? '-' : item.persentase + '%' }}
                                </td>
                                <td class="px-4 py-3 text-right text-muted-foreground">{{ item.daya_beda ?? '-' }}</td>
                                <td class="max-w-xs px-4 py-3 text-xs text-muted-foreground">{{ item.catatan || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
