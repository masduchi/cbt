<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { computed } from 'vue';
import { formatTanggalWaktu } from '@/ui';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    tahunAktif: { type: String, default: null },
    analytics: { type: Object, default: null },
    reminders: { type: Array, default: () => [] },
    recentJadwal: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Siswa tidak punya akses ke halaman jadwal ujian admin/guru.
const jadwalHref = computed(() => (user.value?.role === 'siswa' ? '/ujian' : '/cbt/jadwal'));

const cards = computed(() => {
    const map = {
        siswa: { label: 'Siswa', icon: 'users' },
        guru: { label: 'Guru', icon: 'user-cog' },
        kelas: { label: 'Kelas', icon: 'school' },
        mapel: { label: 'Mata Pelajaran', icon: 'book-open' },
        bank_soal: { label: 'Bank Soal', icon: 'book' },
        jadwal: { label: 'Jadwal Ujian', icon: 'calendar-alt' },
    };

    return Object.entries(props.stats).map(([key, value]) => ({ ...map[key], value }));
});

const kpis = computed(() => {
    if (!props.analytics) return [];

    return [
        {
            label: 'Rata-rata nilai',
            value: formatNumber(props.analytics.nilai_rata),
            hint: `${formatNumber(props.analytics.dinilai)} nilai terkumpul`,
            icon: 'chart-bar',
        },
        {
            label: 'Tingkat tuntas',
            value: props.analytics.pass_rate === null ? '-' : `${formatNumber(props.analytics.pass_rate)}%`,
            hint: props.analytics.dinilai_kkm > 0
                ? `${formatNumber(props.analytics.tuntas)} dari ${formatNumber(props.analytics.dinilai_kkm)} nilai memenuhi KKM`
                : 'KKM bank soal belum diisi',
            icon: 'check-circle',
        },
        {
            label: 'Peserta selesai',
            value: formatNumber(props.analytics.selesai),
            hint: props.analytics.selesai > 0
                ? 'Peserta yang sudah menuntaskan ujian'
                : 'Belum ada peserta yang menuntaskan ujian',
            icon: 'graduation-cap',
        },
        {
            label: 'Sedang mengerjakan',
            value: formatNumber(props.analytics.berjalan),
            hint: 'Sesi ujian aktif saat ini',
            icon: 'hourglass-half',
        },
    ];
});

const toneClass = {
    amber: 'bg-warning/10 text-warning',
    rose: 'bg-destructive/10 text-destructive',
    slate: 'bg-muted text-muted-foreground',
};

const stateClass = {
    Berlangsung: 'bg-success/10 text-success',
    Mendatang: 'bg-info/10 text-info',
    Selesai: 'bg-muted text-muted-foreground',
    Nonaktif: 'bg-destructive/10 text-destructive',
};

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';
    return 'Selamat malam';
});

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(value) || 0);
}

function barWidth(value) {
    const score = Math.max(0, Math.min(100, Number(value) || 0));

    return `${score === 0 ? 0 : Math.max(2, score)}%`;
}
</script>

<template>
    <Head title="Beranda" />
    <AdminLayout title="Beranda">
        <div class="page-stack space-y-4 sm:space-y-5">
            <!-- Page header -->
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Beranda">
                    <template #description>{{ greeting }}, {{ user?.name }}.</template>
                </PageHeader>
                
            </div>

            <!-- Master data counters -->
            <div v-if="cards.length" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                <div
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-xl border border-border bg-card p-4 shadow-card"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                            <p class="mt-1 text-2xl font-semibold tracking-tight text-foreground">{{ card.value }}</p>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <i :class="'fas fa-' + (card.icon)" class="h-4 w-4" aria-hidden="true" ></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Exam analytics -->
            <div v-if="kpis.length" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div v-for="kpi in kpis" :key="kpi.label" class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <div class="flex items-center gap-2">
                        <i :class="'fas fa-' + (kpi.icon)" class="h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" ></i>
                        <p class="text-2xs font-medium text-muted-foreground sm:text-xs">{{ kpi.label }}</p>
                    </div>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-foreground sm:text-2xl">{{ kpi.value }}</p>
                    <p class="mt-1 line-clamp-2 text-2xs leading-snug text-muted-foreground">{{ kpi.hint }}</p>
                </div>
            </div>

            <div v-if="props.analytics" class="grid gap-3 sm:gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-border bg-card shadow-card lg:col-span-2">
                    <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-semibold leading-none text-foreground">Rata-rata Nilai per Jadwal</h2>
                            <p class="mt-1 text-2xs text-muted-foreground">Enam ujian terakhir pada periode aktif</p>
                        </div>
                        <i class="fas fa-chart-bar h-4 w-4 text-muted-foreground" aria-hidden="true" ></i>
                    </div>
                    <div v-if="props.analytics.chart.length" class="space-y-4 px-5 py-4">
                        <div v-for="item in props.analytics.chart" :key="item.id_jadwal">
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="truncate font-medium text-foreground">{{ item.nama }}</span>
                                <span class="shrink-0 text-muted-foreground">
                                    {{ formatNumber(item.rata) }} · {{ item.peserta }} peserta
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary transition-[width]"
                                    :style="{ width: barWidth(item.rata) }"
                                />
                            </div>
                            <p class="mt-1 text-2xs text-muted-foreground">
                                KKM {{ item.kkm > 0 ? item.kkm : 'belum diisi' }} · {{ formatTanggalWaktu(item.tgl_mulai) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="px-5 py-12 text-center">
                        <i class="fas fa-chart-bar mx-auto h-8 w-8 text-muted-foreground/50" aria-hidden="true" ></i>
                        <p class="mt-2 text-sm text-muted-foreground">Belum ada nilai yang masuk pada periode ini.</p>
                    </div>
                </div>

                <div class="self-start rounded-xl border border-border bg-card shadow-card">
                    <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                        <h2 class="text-sm font-semibold leading-none text-foreground">Perlu Perhatian</h2>
                        <i class="fas fa-clipboard-list h-4 w-4 text-muted-foreground" aria-hidden="true" ></i>
                    </div>
                    <div v-if="props.reminders.length" class="divide-y divide-border">
                        <Link
                            v-for="item in props.reminders"
                            :key="item.label"
                            :href="item.href"
                            class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted/40"
                        >
                            <span
                                class="flex h-7 min-w-7 items-center justify-center rounded-md px-2 text-xs font-semibold"
                                :class="toneClass[item.tone]"
                            >
                                {{ item.count }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm text-foreground">{{ item.label }}</span>
                            <i class="fas fa-arrow-right h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden="true" ></i>
                        </Link>
                    </div>
                    <div v-else class="flex items-center gap-3 px-5 py-6">
                        <i class="fas fa-check-circle h-5 w-5 shrink-0 text-success/70" aria-hidden="true" ></i>
                        <p class="text-sm text-muted-foreground">Tidak ada tindakan mendesak.</p>
                    </div>
                </div>
            </div>

            <div v-if="props.analytics && (props.analytics.mapel?.length || props.analytics.kelas?.length)" class="grid gap-3 sm:gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-border bg-card shadow-card lg:col-span-2">
                    <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-semibold leading-none text-foreground">Ketuntasan per Mata Pelajaran</h2>
                            <p class="mt-1 text-2xs text-muted-foreground">Mapel dengan rata-rata terendah lebih dulu</p>
                        </div>
                        <i class="fas fa-book-open h-4 w-4 text-muted-foreground" aria-hidden="true"></i>
                    </div>
                    <div v-if="props.analytics.mapel?.length" class="divide-y divide-border">
                        <div v-for="item in props.analytics.mapel" :key="item.nama" class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="truncate font-medium text-foreground">{{ item.nama }}</span>
                                <span class="shrink-0 text-muted-foreground">
                                    {{ formatNumber(item.rata) }} · {{ item.peserta }} nilai
                                    <span v-if="item.pass_rate !== null"> · tuntas {{ formatNumber(item.pass_rate) }}%</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary transition-[width]" :style="{ width: barWidth(item.rata) }" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                        Belum ada nilai per mata pelajaran.
                    </div>
                </div>

                <div class="space-y-3 sm:space-y-4">
                    <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                        <p class="text-xs font-medium text-muted-foreground">Partisipasi ujian</p>
                        <p class="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                            {{ props.analytics.partisipasi?.persen === null || props.analytics.partisipasi?.persen === undefined
                                ? '-'
                                : `${formatNumber(props.analytics.partisipasi.persen)}%` }}
                        </p>
                        <p class="mt-1 text-2xs text-muted-foreground">
                            {{ formatNumber(props.analytics.partisipasi?.dinilai ?? 0) }} dari
                            {{ formatNumber(props.analytics.partisipasi?.siswa ?? 0) }} siswa sudah punya nilai
                        </p>
                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary" :style="{ width: barWidth(props.analytics.partisipasi?.persen ?? 0) }" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-card shadow-card">
                        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                            <h2 class="text-sm font-semibold leading-none text-foreground">Kelas Perlu Perhatian</h2>
                            <i class="fas fa-school h-4 w-4 text-muted-foreground" aria-hidden="true"></i>
                        </div>
                        <div v-if="props.analytics.kelas?.length" class="divide-y divide-border">
                            <div v-for="item in props.analytics.kelas" :key="item.id_kelas" class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <span class="min-w-0 truncate text-sm text-foreground">{{ item.nama }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">
                                    {{ formatNumber(item.rata) }}
                                    <span v-if="item.pass_rate !== null" class="ml-1" :class="item.pass_rate < 60 ? 'font-semibold text-destructive' : ''">
                                        · {{ formatNumber(item.pass_rate) }}% tuntas
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div v-else class="px-5 py-6 text-sm text-muted-foreground">Belum ada data kelas.</div>
                    </div>
                </div>
            </div>

            <!-- Recent schedules -->
            <div class="rounded-xl border border-border bg-card shadow-card">
                <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    <h2 class="text-sm font-semibold leading-none text-foreground">Jadwal Ujian Terbaru</h2>
                    <Link :href="jadwalHref" class="text-xs font-medium text-primary transition-colors hover:text-primary-600">
                        Lihat semua
                    </Link>
                </div>
                <div v-if="recentJadwal.length" class="divide-y divide-border">
                    <div v-for="j in recentJadwal" :key="j.id_jadwal" class="flex items-center gap-3 px-5 py-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <i class="fas fa-calendar-alt h-4 w-4" aria-hidden="true" ></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ j.bank || 'Ujian' }}</p>
                            <p class="truncate text-2xs text-muted-foreground">
                                {{ j.jenis || 'Jenis ujian belum diatur' }} · {{ formatTanggalWaktu(j.tgl_mulai) }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="stateClass[j.state]">
                            {{ j.state }}
                        </span>
                    </div>
                </div>
                <div v-else class="px-5 py-10 text-center">
                    <i class="fas fa-clipboard-list mx-auto h-8 w-8 text-muted-foreground/50" aria-hidden="true" ></i>
                    <p class="mt-2 text-sm text-muted-foreground">Belum ada jadwal ujian pada periode ini.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
