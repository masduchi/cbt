<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, formatTanggalWaktu, inputClass, labelClass } from '@/ui';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    token: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

const form = useForm({
    force: false,
    auto: props.token.auto === 1,
    jarak: props.token.jarak || 1,
});

const now = ref(Date.now());
let ticker = null;
let autoBusy = false;

function parseUpdated(value) {
    if (!value) return null;
    return new Date(value.replace(' ', 'T')).getTime();
}

const elapsedSeconds = computed(() => {
    const updated = props.token.updated_iso
        ? new Date(props.token.updated_iso).getTime()
        : parseUpdated(props.token.updated);
    if (!updated) return null;
    return Math.max(0, Math.floor((now.value - updated) / 1000));
});

const elapsedLabel = computed(() => {
    if (elapsedSeconds.value === null) return '-';
    const minutes = Math.floor(elapsedSeconds.value / 60);
    const seconds = elapsedSeconds.value % 60;
    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
});

const nextIn = computed(() => {
    if (!form.auto || elapsedSeconds.value === null) return null;
    const total = Math.max(0, form.jarak * 60 - elapsedSeconds.value);
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
});

function generate(force) {
    form.force = !!force;
    form.post('/cbt/token', {
        preserveScroll: true,
        onFinish: () => {
            form.force = false;
            autoBusy = false;
        },
    });
}

function maybeAutoGenerate() {
    if (!form.auto || autoBusy || elapsedSeconds.value === null) return;
    if (elapsedSeconds.value >= form.jarak * 60) {
        autoBusy = true;
        generate(false);
    }
}

onMounted(() => {
    ticker = setInterval(() => {
        now.value = Date.now();
        maybeAutoGenerate();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(ticker);
});
</script>

<template>
    <Head title="Token Ujian" />
    <AdminLayout title="Token Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Token Ujian" description="Token dipakai siswa untuk memulai ujian. Token dapat diperbarui otomatis setiap beberapa menit." /></div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-card p-6 shadow-card">
                    <div class="flex items-center gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <i class="fas fa-key h-5 w-5" aria-hidden="true" ></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-muted-foreground">Token aktif</p>
                            <p class="font-mono text-3xl font-semibold tracking-widest text-foreground">
                                {{ props.token.token || '------' }}
                            </p>
                            <p v-if="props.token.kedaluwarsa" class="mt-1 inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-2xs font-medium text-destructive">
                                <i class="fas fa-exclamation-triangle h-3 w-3"></i> Kedaluwarsa — siswa tidak bisa memulai ujian
                            </p>
                            <p v-else-if="props.token.expires" class="mt-1 text-2xs text-muted-foreground">
                                Berlaku hingga {{ formatTanggalWaktu(props.token.expires) }}
                            </p>
                        </div>
                    </div>
                    <dl class="mt-5 grid gap-3 border-t border-border pt-4 text-xs sm:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground">Diperbarui</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ formatTanggalWaktu(props.token.updated) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Sejak pembaruan</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ elapsedLabel }}</dd>
                        </div>
                        <div v-if="nextIn !== null">
                            <dt class="text-muted-foreground">Token baru dalam</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ nextIn }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <form class="space-y-4" @submit.prevent="generate(true)">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm text-foreground">
                                <input v-model="form.auto" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                                Perbarui otomatis
                            </label>
                            <div>
                                <label :class="labelClass" for="interval-menit">Interval (menit)</label>
                                <input v-model.number="form.jarak" type="number" min="1" max="1440" :class="inputClass" id="interval-menit" />
                            </div>
                        </div>
                        <p v-if="form.errors.jarak" class="text-xs text-destructive">{{ form.errors.jarak }}</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" :class="btnPrimary" :disabled="form.processing">
                                <i class="fas fa-sync-alt h-4 w-4" ></i> Generate Token Baru
                            </button>
                            <button type="button" :class="btnSecondary" :disabled="form.processing" @click="generate(false)">
                                Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
