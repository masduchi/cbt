<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass, inputClass } from '@/ui';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { askAlert, askConfirm } from '@/confirm';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    peserta: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
    realtime: { type: Boolean, default: false },
});

const checked = ref([]);
const rows = ref(props.peserta);
const syncedAt = ref(Date.now());
const nowTick = ref(Date.now());
const autoRefresh = ref(true);
const refreshing = ref(false);
// 'live' = SSE aktif, 'connecting' = membuka SSE, 'polling' = cadangan, 'manual' = dimatikan.
const channel = ref(props.realtime ? 'connecting' : 'polling');
const changed = ref(new Set());
let refreshTimer = null;
let tickTimer = null;
let highlightTimer = null;
let stream = null;
let streamFailures = 0;
let streamReceived = false;

// Jenis yang menunjukkan tindakan nyata (bukan sekadar efek samping teknis)
// ditampilkan dengan warna tegas agar pengawas bisa membedakannya.
const pelanggaranSerius = new Set(['sesi_ganda', 'salin', 'tempel', 'keluar_halaman']);

function violationClass(row) {
    const serius = (row.violations_rincian ?? []).some((item) => pelanggaranSerius.has(item.kode));

    return serius
        ? 'border-destructive/30 bg-destructive/10 text-destructive'
        : 'border-warning/30 bg-warning/10 text-warning';
}

function violationTitle(row) {
    const rincian = (row.violations_rincian ?? [])
        .map((item) => `${item.label} (${item.jumlah})`)
        .join('\n');

    return [rincian, row.last_violation ? `Terakhir: ${row.last_violation}` : '']
        .filter(Boolean)
        .join('\n');
}

const allChecked = computed(() => rows.value.length > 0 && checked.value.length === rows.value.length);

function toggleAll(event) {
    checked.value = event.target.checked ? rows.value.map((row) => row.id_siswa) : [];
}

function changeJadwal(event) {
    router.get('/cbt/status', { jadwal: event.target.value }, { preserveState: false });
    checked.value = [];
}

function statusLabel(row) {
    if (row.status === 1) return 'Mengerjakan';
    if (row.status === 2) return 'Selesai';
    if (row.reset) return 'Menunggu reset';
    return 'Belum mulai';
}

function statusClass(row) {
    if (row.status === 1) return 'bg-warning/10 text-warning border-warning/30';
    if (row.status === 2) return 'bg-success/10 text-success border-success/30';
    if (row.reset) return 'bg-primary/10 text-primary border-primary/30';
    return 'bg-muted text-muted-foreground border-border';
}

function formatRemaining(seconds) {
    if (seconds === null || seconds === undefined) return '-';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${String(s).padStart(2, '0')}s`;
}

const sinceLabel = computed(() => {
    const seconds = Math.max(0, Math.floor((nowTick.value - syncedAt.value) / 1000));

    return seconds < 5 ? 'baru saja' : `${seconds} detik lalu`;
});

function snapshot(list) {
    // Sisa waktu berubah tiap detik dengan sendirinya, jadi tidak dipakai
    // sebagai penanda perubahan; fokus pada status, pelanggaran, dan nilai.
    return new Map(list.map((row) => [row.id_siswa, [row.status, row.violations, row.nilai, row.dikoreksi].join('|')]));
}

/**
 * Terapkan data terbaru sekaligus tandai baris yang berubah, sehingga pengawas
 * langsung melihat peserta mana yang bergerak.
 */
function applyRows(next) {
    const before = snapshot(rows.value);
    rows.value = Array.isArray(next) ? next : [];

    const after = snapshot(rows.value);
    const ids = new Set();

    after.forEach((signature, id) => {
        if (before.get(id) !== signature) {
            ids.add(id);
        }
    });

    syncedAt.value = Date.now();
    nowTick.value = Date.now();

    if (ids.size) {
        changed.value = ids;
        clearTimeout(highlightTimer);
        highlightTimer = setTimeout(() => (changed.value = new Set()), 6000);
    }
}

watch(
    () => props.peserta,
    (next) => applyRows(next),
);

/**
 * Sisa waktu dihitung lokal dari sinkronisasi terakhir agar hitung mundur
 * tetap berjalan tanpa membebani server.
 */
function liveRemaining(row) {
    if (row.remaining === null || row.remaining === undefined) return null;
    if (row.status !== 1) return row.remaining;

    const elapsed = Math.max(0, Math.floor((nowTick.value - syncedAt.value) / 1000));

    return Math.max(0, row.remaining - elapsed);
}

function refresh(force = false) {
    if (!props.selected || refreshing.value) return;
    if (!force && (!autoRefresh.value || document.hidden)) return;
    // Saat SSE aktif, data sudah mengalir sendiri.
    if (!force && channel.value === 'live') return;

    refreshing.value = true;

    router.reload({
        only: ['peserta'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            applyRows(props.peserta);
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

function stopStream() {
    if (stream) {
        stream.close();
        stream = null;
    }

    streamFailures = 0;
    streamReceived = false;
}

/**
 * SSE adalah jalur utama; bila server menolak (mis. server bawaan PHP yang
 * hanya melayani satu permintaan) atau koneksi gagal berulang, monitor kembali
 * ke polling tanpa mengganggu pengguna.
 */
function startStream() {
    stopStream();

    if (!props.selected || !autoRefresh.value || document.hidden) {
        channel.value = autoRefresh.value ? 'polling' : 'manual';

        return;
    }

    if (typeof EventSource === 'undefined' || !props.realtime) {
        channel.value = 'polling';

        return;
    }

    channel.value = 'connecting';

    const source = new EventSource(`/cbt/status/${props.selected}/stream`);
    stream = source;

    source.addEventListener('snapshot', (event) => {
        if (stream !== source) return;

        try {
            const payload = JSON.parse(event.data);
            applyRows(payload.rows ?? []);
            streamReceived = true;
            streamFailures = 0;
            channel.value = 'live';
        } catch {
            // Potongan data tidak utuh; kiriman berikutnya menyusul.
        }
    });

    source.onerror = () => {
        if (stream !== source) return;

        if (!streamReceived) {
            stopStream();
            channel.value = 'polling';

            return;
        }

        streamFailures += 1;

        if (streamFailures >= 3) {
            stopStream();
            channel.value = 'polling';
            refresh(true);
        }
    };
}

function toggleAutoRefresh() {
    autoRefresh.value = !autoRefresh.value;

    if (autoRefresh.value) {
        refresh(true);
        startStream();
    } else {
        stopStream();
        channel.value = 'manual';
    }
}

function onVisibilityChange() {
    if (document.hidden) {
        stopStream();

        return;
    }

    if (autoRefresh.value) {
        refresh(true);
        startStream();
    }
}

const channelLabel = computed(() => {
    if (!autoRefresh.value) return 'Manual';
    if (channel.value === 'live') return 'Terhubung';
    if (channel.value === 'connecting') return 'Menghubungkan…';

    return 'Polling';
});

const channelTitle = computed(() => {
    if (!autoRefresh.value) return 'Pembaruan otomatis dimatikan. Klik untuk mengaktifkan.';
    if (channel.value === 'live') return 'Terhubung langsung ke server (realtime).';
    if (channel.value === 'connecting') return 'Mencoba menyambung ke server…';

    return 'Realtime tidak tersedia; data diperbarui setiap 10 detik.';
});

onMounted(() => {
    tickTimer = setInterval(() => (nowTick.value = Date.now()), 1000);
    refreshTimer = setInterval(() => refresh(), 10000);
    document.addEventListener('visibilitychange', onVisibilityChange);
    startStream();
});

onUnmounted(() => {
    clearInterval(tickTimer);
    clearInterval(refreshTimer);
    clearTimeout(highlightTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    stopStream();
});

async function action(mode, confirmText) {
    if (!props.selected) return;
    if (!checked.value.length) {
        await askAlert('Pilih peserta terlebih dahulu.');
        return;
    }
    if (!await askConfirm(confirmText)) return;

    router.post(
        `/cbt/status/${props.selected}/${mode === 'repeat' ? 'repeat' : mode === 'force' ? 'force' : 'reset'}`,
        mode === 'reset' ? { siswa: checked.value, mode: 1 } : { siswa: checked.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                checked.value = [];
            },
        },
    );
}
</script>

<template>
    <Head title="Status Siswa" />
    <AdminLayout title="Status Siswa">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Status Pengerjaan Siswa">
                    <template #description>Pantau progres peserta, reset waktu, atau selesaikan ujian secara paksa. Periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="w-full max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-ujian">Jadwal Ujian</label>
                    <select :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal" id="jadwal-ujian">
                        <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                            {{ item.label }} · {{ item.tgl_mulai }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="!props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-users mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else>
                <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.jadwal.jenis }} · {{ props.jadwal.durasi_ujian }} menit · {{ props.jadwal.tgl_mulai }} – {{ props.jadwal.tgl_selesai }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-medium transition-colors"
                            :class="{
                                'border-success/40 bg-success/10 text-success': channel === 'live',
                                'border-primary/30 bg-primary/10 text-primary': channel === 'connecting',
                                'border-warning/30 bg-warning/10 text-warning': channel === 'polling' && autoRefresh,
                                'border-border bg-muted text-muted-foreground': !autoRefresh,
                            }"
                            :title="channelTitle"
                        >
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="{
                                    'bg-success': channel === 'live',
                                    'bg-primary animate-pulse': channel === 'connecting',
                                    'bg-warning': channel === 'polling' && autoRefresh,
                                    'bg-muted-foreground': !autoRefresh,
                                }"
                                aria-hidden="true"
                            ></span>
                            {{ channelLabel }}
                            <span class="text-muted-foreground">· {{ sinceLabel }}</span>
                        </div>
                        <span class="sr-only" role="status" aria-live="polite">{{ channelLabel }}</span>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :aria-pressed="autoRefresh"
                            :title="autoRefresh ? 'Matikan pembaruan otomatis' : 'Aktifkan pembaruan otomatis'"
                            @click="toggleAutoRefresh"
                        >
                            <i class="fas" :class="refreshing ? 'fa-circle-notch animate-spin' : 'fa-bolt'" aria-hidden="true"></i>
                            {{ autoRefresh ? 'Otomatis' : 'Manual' }}
                        </button>
                        <button type="button" :class="btnSecondary" :disabled="!checked.length" @click="action('reset', `Reset waktu ${checked.length} peserta ke 0?`)">
                            <i class="fas fa-stopwatch h-4 w-4" ></i> Reset Waktu
                        </button>
                        <button type="button" :class="btnSecondary" :disabled="!checked.length" @click="action('force', `Selesaikan paksa ujian ${checked.length} peserta?`)">
                            <i class="fas fa-exclamation-triangle h-4 w-4" ></i> Selesaikan
                        </button>
                        <button type="button" :class="btnSecondary" :disabled="!checked.length" @click="action('repeat', `Hapus jawaban & izinkan ${checked.length} peserta mengulang?`)">
                            <i class="fas fa-undo-alt h-4 w-4" ></i> Izinkan Ulang
                        </button>
                    </div>
                </div>

                <div class="space-y-3 lg:hidden">
                    <div v-for="row in rows" :key="'m-'+row.id_siswa" :class="cardClass" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <label class="flex min-w-0 items-start gap-3">
                                <input v-model="checked" type="checkbox" :value="row.id_siswa" class="mt-1 rounded border-border" :aria-label="`Pilih ${row.nama}`" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-foreground">{{ row.nama }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ row.nis }} · {{ row.kelas || '-' }} · {{ row.no_peserta || 'tanpa nomor' }}</span>
                                </span>
                            </label>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium" :class="statusClass(row)">
                                {{ statusLabel(row) }}
                            </span>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <dt class="text-muted-foreground">Sisa waktu</dt>
                                <dd class="font-medium text-foreground">{{ formatRemaining(liveRemaining(row)) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Nilai</dt>
                                <dd class="font-medium text-foreground">
                                    {{ row.nilai ?? '-' }}
                                    <span v-if="row.status === 2 && !row.dikoreksi" class="ml-1 text-warning">esai</span>
                                </dd>
                            </div>
                            <div v-if="row.violations" class="col-span-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="violationClass(row)"
                                    :title="violationTitle(row)"
                                >
                                    <i class="fas fa-exclamation-triangle h-3.5 w-3.5" aria-hidden="true"></i>
                                    {{ row.violations_rincian?.[0]?.label || 'Pelanggaran' }} ({{ row.violations }})
                                </span>
                            </div>
                        </dl>
                    </div>
                    <div v-if="!rows.length" :class="cardClass" class="p-8 text-center text-sm text-muted-foreground">
                        Belum ada peserta terdaftar pada jadwal ini.
                    </div>
                </div>

                <div :class="cardClass" class="hidden overflow-x-auto lg:block">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <span class="sr-only">Pilih</span>
                                    <input type="checkbox" :checked="allChecked" class="rounded border-border" @change="toggleAll" aria-label="Pilih semua peserta" />
                                </th>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th class="px-4 py-3">No. Peserta</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Pelanggaran</th>
                                <th class="px-4 py-3">Sisa Waktu</th>
                                <th class="px-4 py-3">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in rows" :key="row.id_siswa" class="hover:bg-muted/40 transition-colors" :class="changed.has(row.id_siswa) ? 'bg-primary/5' : ''">
                                <td class="px-4 py-3">
                                    <input v-model="checked" type="checkbox" :value="row.id_siswa" class="rounded border-border" :aria-label="`Pilih ${row.nama}`" />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.no_peserta || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-medium" :class="statusClass(row)">
                                        {{ statusLabel(row) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="row.violations" class="flex flex-col gap-1">
                                        <span
                                            class="inline-flex w-fit items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="violationClass(row)"
                                            :title="violationTitle(row)"
                                        >
                                            <i class="fas fa-exclamation-triangle h-3.5 w-3.5" aria-hidden="true"></i>
                                            {{ row.violations_rincian?.[0]?.label || 'Pelanggaran' }} ({{ row.violations }})
                                        </span>
                                        <p v-if="(row.violations_rincian?.length || 0) > 1" class="text-xs text-muted-foreground">
                                            +{{ row.violations_rincian.length - 1 }} jenis lain
                                        </p>
                                    </div>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatRemaining(liveRemaining(row)) }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="row.nilai !== null" class="font-semibold text-foreground">{{ row.nilai }}</span>
                                    <span v-else class="text-muted-foreground">-</span>
                                    <span v-if="row.status === 2 && !row.dikoreksi" class="ml-2 inline-flex items-center gap-1 text-xs text-warning">
                                        <i class="fas fa-question-circle h-3.5 w-3.5" ></i> esai
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Belum ada peserta terdaftar pada jadwal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
