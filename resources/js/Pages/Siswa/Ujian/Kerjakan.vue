<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { formatClock, postJson } from '@/cbt';
import RichContent from '@/Components/RichContent.vue';
import Modal from '@/Components/Modal.vue';
import { askAlert } from '@/confirm';
import { btnSecondary } from '@/ui';

const props = defineProps({
    jadwal: { type: Object, required: true },
    siswa: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    remaining: { type: Number, required: true },
    serverNow: { type: Number, required: true },
    hasilTampil: { type: Number, default: 0 },
});

const answers = reactive({});
const currentIndex = ref(0);
const deadline = ref(props.serverNow + props.remaining * 1000);
const nowTick = ref(Date.now());
const saveState = ref('idle');
const answeredServer = ref(null);
const finishing = ref(false);
const showFinish = ref(false);
const violations = ref(0);
const lastViolation = ref('');
const reportCooldown = new Map();

const draftKey = `draft-${props.jadwal.id_jadwal}-${props.siswa.id_siswa}`;
const pending = ref({});
const serverOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const fullscreen = ref(false);
const fullscreenDitolak = ref(false);
const fsSupported = ref(typeof document !== 'undefined' && !!document.documentElement.requestFullscreen);
let flushing = false;

try {
    const stored = JSON.parse(localStorage.getItem(draftKey) || '{}');
    pending.value = stored && typeof stored === 'object' ? stored : {};
} catch {
    pending.value = {};
}

const pendingCount = computed(() => Object.keys(pending.value).length);

const violationLabels = {
    pindah_tab: 'Berpindah tab atau jendela',
    salin: 'Mencoba menyalin soal',
    tempel: 'Mencoba menempel teks',
    klik_kanan: 'Membuka menu klik kanan',
    keluar_halaman: 'Mencoba meninggalkan halaman ujian',
    fokus_hilang: 'Jendela ujian kehilangan fokus',
    keluar_fullscreen: 'Keluar dari mode layar penuh',
};

function reportViolation(jenis, detail = '', force = false) {
    if (finishing.value) return;

    const now = Date.now();

    if (!force && now - (reportCooldown.get(jenis) ?? 0) < 3000) return;

    reportCooldown.set(jenis, now);
    violations.value++;
    lastViolation.value = violationLabels[jenis] ?? 'Aktivitas mencurigakan';

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    fetch(`/ujian/${props.jadwal.id_jadwal}/pelanggaran`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ jenis, detail }),
        keepalive: true,
    }).catch(() => {});
}

const raguKey = `ragu-${props.jadwal.id_jadwal}-${props.siswa.id_siswa}`;
const ragu = ref(new Set(JSON.parse(localStorage.getItem(raguKey) || '[]')));

props.questions.forEach((question) => {
    if (question.jenis === 2) {
        answers[question.id_soal_siswa] = Array.isArray(question.answer) && question.answer.length
            ? [...question.answer]
            : question.opsi.filter((option) => option.checked).map((option) => option.value);
    } else if (question.jenis === 3) {
        answers[question.id_soal_siswa] = Array.isArray(question.answer)
            ? [...question.answer]
            : question.pairs.map(() => null);
    } else if (question.jenis === 1) {
        answers[question.id_soal_siswa] = question.opsi.find((option) => option.checked)?.value ?? '';
    } else {
        answers[question.id_soal_siswa] = question.answer ?? '';
    }
});

const current = computed(() => props.questions[currentIndex.value]);
const total = computed(() => props.questions.length);
const secondsLeft = computed(() => Math.max(0, Math.ceil((deadline.value - nowTick.value) / 1000)));
const timerClass = computed(() => {
    if (secondsLeft.value <= 300) return 'bg-red-600 text-white';
    if (secondsLeft.value <= 900) return 'bg-amber-500 text-slate-950';
    return 'bg-white/10 text-white';
});

function isAnswered(question) {
    const value = answers[question.id_soal_siswa];

    if (question.jenis === 1) return !!value;
    if (question.jenis === 2) return Array.isArray(value) && value.length > 0;
    if (question.jenis === 3) return Array.isArray(value) && value.some((item) => item !== null && item !== undefined);
    return String(value ?? '').trim() !== '';
}

const answeredCount = computed(() => props.questions.filter((question) => isAnswered(question)).length);
const unanswered = computed(() => total.value - answeredCount.value);

function isRagu(question) {
    return ragu.value.has(question.id_soal_siswa);
}

function toggleRagu() {
    const id = current.value.id_soal_siswa;
    const next = new Set(ragu.value);
    next.has(id) ? next.delete(id) : next.add(id);
    ragu.value = next;
    localStorage.setItem(raguKey, JSON.stringify([...next]));
}

let saveTimers = {};

function onChange(question) {
    clearTimeout(saveTimers[question.id_soal_siswa]);
    remember(payloadFor(question));
    saveState.value = 'idle';
    saveTimers[question.id_soal_siswa] = setTimeout(() => persist(question), 500);
}

function payloadFor(question) {
    const payload = {
        id_soal_siswa: question.id_soal_siswa,
        jenis: question.jenis,
        jawaban: answers[question.id_soal_siswa],
    };

    if (question.jenis === 1) {
        payload.jawaban_alias = question.opsi.find((option) => option.value === answers[question.id_soal_siswa])?.alias ?? '';
    }

    return payload;
}

/**
 * Draft disimpan di perangkat sebelum dikirim, sehingga jawaban tidak hilang
 * saat koneksi lab terputus dan tetap terkirim setelah tersambung lagi.
 */
function remember(payload) {
    pending.value = { ...pending.value, [payload.id_soal_siswa]: payload };
    localStorage.setItem(draftKey, JSON.stringify(pending.value));
}

function forget(id, payload = null) {
    if (!pending.value[id]) return;

    // Jawaban yang lebih baru sudah menunggu: jangan hapus draft tersebut saat
    // respons untuk jawaban lama datang belakangan.
    if (payload && JSON.stringify(pending.value[id]) !== JSON.stringify(payload)) return;

    const next = { ...pending.value };
    delete next[id];
    pending.value = next;
    localStorage.setItem(draftKey, JSON.stringify(next));
}

function clearDrafts() {
    pending.value = {};

    try {
        localStorage.removeItem(draftKey);
    } catch {
        // localStorage bisa dilarang pada mode privat; abaikan.
    }
}

async function send(payload) {
    const response = await postJson(`/ujian/${props.jadwal.id_jadwal}/jawaban`, payload);
    forget(payload.id_soal_siswa, payload);
    answeredServer.value = response.answered;

    if (typeof response.remaining === 'number') {
        deadline.value = Date.now() + response.remaining * 1000;
    }

    return response;
}

async function flushPending(force = false) {
    if (flushing || (!force && finishing.value) || !pendingCount.value) return;

    flushing = true;

    for (const payload of Object.values(pending.value)) {
        try {
            const response = await send(payload);

            if (response.expired) {
                await submitExam();
                break;
            }
        } catch {
            break;
        }
    }

    flushing = false;

    if (!pendingCount.value && !finishing.value) {
        saveState.value = 'saved';
    }
}

async function persist(question) {
    // Autosave tidak perlu dijalankan lagi setelah ujian dikumpulkan.
    if (finishing.value) return;

    const payload = payloadFor(question);

    remember(payload);
    saveState.value = 'saving';

    try {
        const response = await send(payload);
        saveState.value = pendingCount.value ? 'pending' : 'saved';

        if (response.expired) {
            await submitExam();
        }
    } catch {
        // Abaikan bila ujian sudah dikumpulkan (autosave tertunda mendapat 409).
        if (finishing.value) return;
        saveState.value = 'error';
        startRetry();
    }
}

let retryTimer = null;

function startRetry() {
    if (retryTimer) return;

    retryTimer = setInterval(() => {
        flushPending();
    }, 8000);
}

function goTo(index) {
    if (index < 0 || index >= total.value) return;
    currentIndex.value = index;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function next() {
    if (currentIndex.value >= total.value - 1) {
        showFinish.value = true;
        return;
    }
    goTo(currentIndex.value + 1);
}

async function submitExam() {
    if (finishing.value) return;
    finishing.value = true;
    showFinish.value = false;
    saveState.value = 'saving';

    try {
        await flushPending(true);
        const response = await postJson(`/ujian/${props.jadwal.id_jadwal}/selesai`, {});
        clearDrafts();
        const redirect = response.redirect ?? '/ujian/hasil';
        router.visit(redirect);

        // Fallback: bila navigasi Inertia tidak terjadi dalam beberapa detik,
        // paksa muat ulang halaman agar siswa tidak tertinggal di halaman ujian.
        window.setTimeout(() => {
            const target = new URL(redirect, window.location.href);
            if (window.location.href !== target.href) {
                window.location.assign(target.href);
            }
        }, 2500);
    } catch (error) {
        finishing.value = false;
        saveState.value = 'error';
        await askAlert(error.message);
    }
}

let clockTimer;
let checkpointTimer;

function onVisibility() {
    if (document.hidden) {
        reportViolation('pindah_tab');
    }
}

function onBlur() {
    reportViolation('fokus_hilang');
}

function onCopy(event) {
    event.preventDefault();
    reportViolation('salin');
}

function onPaste(event) {
    event.preventDefault();
    reportViolation('tempel');
}

function onContextMenu(event) {
    event.preventDefault();
    reportViolation('klik_kanan');
}

function onFullscreenChange() {
    fullscreen.value = !!document.fullscreenElement;

    if (!fullscreen.value && !finishing.value) {
        reportViolation('keluar_fullscreen');
    }
}

async function enterFullscreen() {
    fullscreenDitolak.value = false;

    try {
        await document.documentElement.requestFullscreen?.();

        // Sebagian browser tidak menyelesaikan promise ini; beri tahu pengguna
        // bila layar penuh tetap tidak aktif.
        window.setTimeout(() => {
            if (! document.fullscreenElement) {
                fullscreenDitolak.value = true;
            }
        }, 1200);
    } catch {
        fullscreenDitolak.value = true;
    }
}

function onOffline() {
    serverOnline.value = false;
}

function onOnline() {
    serverOnline.value = true;
    flushPending();
}

function onBeforeUnload(event) {
    if (finishing.value) return;

    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => {
    clockTimer = setInterval(() => {
        nowTick.value = Date.now();
        if (secondsLeft.value <= 0 && !finishing.value) {
            submitExam();
        }
    }, 1000);

    checkpointTimer = setInterval(async () => {
        if (finishing.value) return;

        try {
            const response = await postJson(`/ujian/${props.jadwal.id_jadwal}/checkpoint`, {});
            if (typeof response.remaining === 'number') {
                deadline.value = Date.now() + response.remaining * 1000;
            }
            if (response.expired) {
                await submitExam();
            }
        } catch {
            // biarkan timer lokal berjalan sampai permintaan berikutnya
        }
    }, 30000);

    if (pendingCount.value) {
        saveState.value = 'pending';
        startRetry();
    }

    document.addEventListener('fullscreenchange', onFullscreenChange);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);

    document.addEventListener('visibilitychange', onVisibility);
    document.addEventListener('copy', onCopy);
    document.addEventListener('cut', onCopy);
    document.addEventListener('paste', onPaste);
    document.addEventListener('contextmenu', onContextMenu);
    window.addEventListener('blur', onBlur);
    window.addEventListener('beforeunload', onBeforeUnload);
});

function plainText(value) {
    return String(value ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

onUnmounted(() => {
    clearInterval(clockTimer);
    clearInterval(checkpointTimer);
    clearInterval(retryTimer);

    document.removeEventListener('fullscreenchange', onFullscreenChange);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    document.removeEventListener('visibilitychange', onVisibility);
    document.removeEventListener('copy', onCopy);
    document.removeEventListener('cut', onCopy);
    document.removeEventListener('paste', onPaste);
    document.removeEventListener('contextmenu', onContextMenu);
    window.removeEventListener('blur', onBlur);
    window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
    <Head :title="`Ujian ${props.jadwal.bank_nama}`" />
    <div class="min-h-screen bg-muted">
        <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-900 text-white shadow">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4 sm:px-6">
                <div class="min-w-0 sm:flex-1">
                    <p class="truncate text-sm font-semibold">{{ props.jadwal.bank_nama }}</p>
                    <p class="truncate text-xs text-slate-300">
                        {{ props.jadwal.mapel }} · {{ props.siswa.nama }} · {{ props.siswa.kelas || '-' }}
                        <span v-if="props.siswa.nomor_peserta"> · No. {{ props.siswa.nomor_peserta }}</span>
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="fsSupported"
                        class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-2xs font-medium"
                        :class="fullscreen ? 'border-emerald-300/40 bg-emerald-400/10 text-emerald-200' : 'border-amber-300/40 bg-amber-400/10 text-amber-200'"
                    >
                        <i class="fas h-3 w-3" :class="fullscreen ? 'fa-compress' : 'fa-expand'" aria-hidden="true"></i>
                        Layar penuh: {{ fullscreen ? 'aktif' : 'nonaktif' }}
                    </span>

                    <span
                        v-if="!serverOnline"
                        class="inline-flex items-center gap-1 rounded-full border border-amber-300/40 bg-amber-400/10 px-2 py-1 text-2xs font-medium text-amber-100"
                        role="status"
                    >
                        <i class="fas fa-wifi h-3 w-3" aria-hidden="true"></i>
                        {{ pendingCount > 0 ? `Offline — ${pendingCount} jawaban menunggu terkirim` : 'Offline — jawaban dikirim otomatis saat tersambung' }}
                    </span>
                    </div>

                    <div class="flex items-center justify-between gap-2 sm:ml-auto sm:justify-end">
                        <div class="flex items-center gap-2 rounded-xl px-4 py-2 font-mono text-lg font-semibold tabular-nums" :class="timerClass">
                            <i class="fas fa-clock h-4 w-4" ></i>
                            {{ formatClock(secondsLeft) }}
                        </div>

                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700" :disabled="finishing" @click="showFinish = true">
                            <i class="fas fa-paper-plane h-3.5 w-3.5" ></i> Selesai
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="fsSupported && !fullscreen"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-300/40 bg-amber-400/15 px-4 py-2 text-xs font-medium text-amber-100"
                role="status"
            >
                <span><i class="fas fa-expand mr-1" aria-hidden="true"></i> Aktifkan layar penuh agar aplikasi lain tidak mudah dibuka saat ujian.</span>
                <span v-if="fullscreenDitolak" class="basis-full text-amber-200">
                    Browser menolak mode layar penuh. Gunakan tombol F11 atau minta pengawas mendampingi; ujian tetap berjalan.
                </span>
                <button type="button" class="inline-flex items-center gap-1 rounded-md bg-amber-400 px-2.5 py-1 font-semibold text-slate-900" @click="enterFullscreen">
                    Aktifkan layar penuh
                </button>
            </div>

            <div v-if="violations > 0" class="border-t border-red-400/40 bg-red-500/20 px-4 py-1.5 text-center text-xs font-medium text-red-100" role="status">
                <i class="fas fa-shield-alt mr-1 inline h-3.5 w-3.5" ></i>
                {{ lastViolation }}. Aktivitas ini tercatat pada pengawas ({{ violations }} kali).
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <section v-if="current" class="rounded-xl border border-border bg-card shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ current.nomor }}</span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Soal {{ current.nomor }} dari {{ total }}</p>
                            <p class="text-xs text-muted-foreground">Poin {{ current.point }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span v-if="saveState === 'saving'" class="inline-flex items-center gap-1 text-muted-foreground"><i class="fas fa-spinner h-3.5 w-3.5 animate-spin" ></i> Menyimpan…</span>
                        <span v-else-if="saveState === 'saved'" class="inline-flex items-center gap-1 font-medium text-success"><i class="fas fa-check-circle h-3.5 w-3.5" ></i> Tersimpan</span>
                        <span v-else-if="pendingCount" class="inline-flex items-center gap-1 font-medium text-warning"><i class="fas fa-cloud-arrow-up h-3.5 w-3.5" ></i> {{ pendingCount }} belum terkirim</span>
                        <span v-else-if="saveState === 'error'" class="inline-flex items-center gap-1 font-medium text-destructive"><i class="fas fa-exclamation-triangle h-3.5 w-3.5" ></i> Gagal menyimpan, dicoba ulang</span>
                        <span v-else-if="!serverOnline" class="inline-flex items-center gap-1 font-medium text-warning"><i class="fas fa-wifi h-3.5 w-3.5" ></i> Offline</span>
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 font-medium" :class="isRagu(current) ? 'border-warning/40 bg-warning/10 text-warning' : 'border-border bg-card text-muted-foreground hover:bg-muted/40'" @click="toggleRagu">
                            <i class="fas fa-flag h-3.5 w-3.5" ></i> {{ isRagu(current) ? 'Ragu-ragu' : 'Tandai ragu' }}
                        </button>
                    </div>
                </div>

                <div class="px-5 py-5 sm:px-6">
                    <RichContent :html="current.soal" class="question-text text-base leading-relaxed text-foreground" />
                    <img v-if="current.file" :src="current.file" alt="Lampiran soal" class="mt-4 max-h-80 rounded-lg border border-border" />
                    <RichContent v-if="current.deskripsi" :html="current.deskripsi" class="mt-3 rounded-lg bg-muted/60 px-3 py-2 text-sm text-muted-foreground" />

                    <div class="mt-6">
                        <template v-if="current.jenis === 1">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Pilih satu jawaban</p>
                            <div class="space-y-2">
                                <label v-for="option in current.opsi" :key="option.value" class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="answers[current.id_soal_siswa] === option.value ? 'border-primary bg-primary/10' : 'border-border bg-card hover:border-muted-foreground/40'">
                                    <input v-model="answers[current.id_soal_siswa]" type="radio" :name="current.id_soal_siswa" :value="option.value" class="mt-1 h-4 w-4 text-primary" @change="onChange(current)" />
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-bold text-foreground">{{ option.alias }}</span>
                                    <span class="min-w-0 text-sm text-foreground">
                                        <RichContent :html="option.text" />
                                        <img v-if="option.file" :src="option.file" alt="" class="mt-2 max-h-40 rounded border border-border" />
                                    </span>
                                </label>
                            </div>
                        </template>

                        <template v-else-if="current.jenis === 2">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Pilih satu atau lebih jawaban</p>
                            <div class="space-y-2">
                                <label v-for="option in current.opsi" :key="option.value" class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="answers[current.id_soal_siswa].includes(option.value) ? 'border-primary bg-primary/10' : 'border-border bg-card hover:border-muted-foreground/40'">
                                    <input v-model="answers[current.id_soal_siswa]" type="checkbox" :value="option.value" class="mt-1 h-4 w-4 rounded text-primary" @change="onChange(current)" />
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-bold text-foreground">{{ option.value }}</span>
                                    <span class="min-w-0 text-sm text-foreground">
                                        <RichContent :html="option.text" />
                                        <img v-if="option.file" :src="option.file" alt="" class="mt-2 max-h-40 rounded border border-border" />
                                    </span>
                                </label>
                            </div>
                        </template>

                        <template v-else-if="current.jenis === 3">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Pasangkan pernyataan kiri dengan pilihan kanan</p>
                            <div class="overflow-x-auto rounded-xl border border-border">
                                <table class="data-table w-full text-sm">
                                    <tbody>
                                        <tr v-for="pair in current.pairs" :key="pair.index" class="border-b border-border last:border-0">
                                            <td class="w-1/2 px-3 py-2 align-top text-foreground">
                                                <RichContent :html="pair.kiri" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <select v-model="answers[current.id_soal_siswa][pair.index]" class="w-full rounded-lg border border-border bg-card px-2 py-1.5 text-sm" @change="onChange(current)" :aria-label="`Pasangan ${pair.index + 1}`">
                                                    <option :value="null">Pilih pasangan</option>
                                                    <option v-for="option in current.rightOptions" :key="option.index" :value="option.index">{{ plainText(option.text) }}</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template v-else-if="current.jenis === 4">
                            <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground" for="jawaban-singkat">Jawaban singkat</label>
                            <input v-model="answers[current.id_soal_siswa]" type="text" class="mt-2 w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" placeholder="Tulis jawaban singkat" @input="onChange(current)" id="jawaban-singkat" />
                        </template>

                        <template v-else>
                            <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground" for="jawaban-uraian">Jawaban uraian</label>
                            <textarea v-model="answers[current.id_soal_siswa]" rows="7" class="mt-2 w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" placeholder="Tulis jawaban uraian Anda" @input="onChange(current)" id="jawaban-uraian"></textarea>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-border px-5 py-4">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3.5 py-2 text-sm font-medium text-foreground transition hover:bg-muted/40 disabled:opacity-40" :disabled="currentIndex === 0" @click="goTo(currentIndex - 1)">
                        <i class="fas fa-chevron-left h-4 w-4" ></i> Sebelumnya
                    </button>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-sm font-medium text-white transition hover:bg-primary-600" @click="next">
                        {{ currentIndex >= total - 1 ? 'Selesai & Kumpulkan' : 'Berikutnya' }}
                        <i v-if="currentIndex < total - 1" class="fas fa-chevron-right h-4 w-4" ></i>
                        <i v-else class="fas fa-paper-plane h-4 w-4" ></i>
                    </button>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-foreground">Navigasi Soal</p>
                        <span class="text-xs text-muted-foreground">{{ answeredCount }}/{{ total }} terjawab</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-[width]" :style="{ width: `${total ? (answeredCount / total) * 100 : 0}%` }"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-6 gap-2">
                        <button type="button" v-for="(question, index) in props.questions" :key="question.id_soal_siswa" class="flex h-9 items-center justify-center rounded-lg border text-xs font-semibold transition" :class="[
                            index === currentIndex
                                ? 'border-primary-700 bg-primary text-white'
                                : isRagu(question)
                                    ? 'border-warning/40 bg-warning/15 text-warning'
                                    : isAnswered(question)
                                        ? 'border-success/40 bg-success/15 text-success'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                        ]" @click="goTo(index)">
                            {{ question.nomor }}
                        </button>
                    </div>
                    <div class="mt-4 space-y-1.5 text-xs text-muted-foreground">
                        <p class="flex items-center gap-2"><span class="h-3 w-3 rounded border border-success/40 bg-success/15"></span> Sudah dijawab</p>
                        <p class="flex items-center gap-2"><span class="h-3 w-3 rounded border border-warning/40 bg-warning/15"></span> Ragu-ragu</p>
                        <p class="flex items-center gap-2"><span class="h-3 w-3 rounded border border-border bg-card"></span> Belum dijawab</p>
                    </div>
                </div>

                <div class="rounded-xl border border-warning/30 bg-warning/10 p-4 text-xs text-warning">
                    <p class="font-semibold">Perhatian</p>
                    <p class="mt-1">Jangan menutup atau memindahkan tab. Waktu ujian tetap berjalan dan jawaban tersimpan otomatis.</p>
                </div>
            </aside>
        </main>

        <Modal :show="showFinish" title="Kumpulkan jawaban?" max-width="max-w-md" @close="showFinish = false">
            <p class="text-sm text-muted-foreground">
                Anda telah menjawab <b>{{ answeredCount }}</b> dari <b>{{ total }}</b> soal.
                <span v-if="unanswered > 0" class="mt-1 block font-medium text-destructive">Masih ada {{ unanswered }} soal yang belum dijawab.</span>
            </p>
            <p class="mt-2 text-xs text-muted-foreground">Setelah dikumpulkan, Anda tidak dapat mengubah jawaban lagi.</p>

            <template #footer>
                <button type="button" :class="btnSecondary" @click="showFinish = false">Periksa lagi</button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-destructive px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-destructive/90 disabled:pointer-events-none disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/40 focus-visible:ring-offset-2"
                    :disabled="finishing"
                    @click="submitExam()"
                >
                    <i v-if="finishing" class="fas fa-spinner h-4 w-4 animate-spin" aria-hidden="true"></i>
                    <i v-else class="fas fa-paper-plane h-4 w-4" aria-hidden="true"></i>
                    Kumpulkan
                </button>
            </template>
        </Modal>
    </div>
</template>

<style scoped>
.question-text :deep(img) {
    max-width: 100%;
    height: auto;
}
</style>
