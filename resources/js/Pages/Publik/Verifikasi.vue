<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    kode: { type: String, required: true },
    valid: { type: Boolean, required: true },
    jenis: { type: String, default: null },
    ringkasan: { type: Object, default: () => ({}) },
    diterbitkan: { type: String, default: null },
    diperbarui: { type: String, default: null },
});

const labels = {
    'kartu-peserta': 'Kartu peserta ujian',
    'buku-induk': 'Buku induk siswa',
    rapor: 'Rapor siswa',
};
</script>

<template>
    <Head title="Verifikasi Dokumen" />
    <div class="flex min-h-screen items-center justify-center bg-muted px-4 py-10">
        <main class="w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-card">
            <h1 class="text-lg font-semibold text-foreground">Verifikasi Dokumen</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Kode: <span class="font-mono font-semibold text-foreground">{{ props.kode }}</span>
            </p>

            <p
                class="mt-4 rounded-lg px-3 py-2 text-sm font-medium"
                :class="props.valid ? 'bg-success/10 text-success' : 'bg-destructive/10 text-destructive'"
            >
                {{ props.valid ? 'Dokumen terdaftar dan masih berlaku.' : 'Kode tidak ditemukan atau dokumen sudah dicabut.' }}
            </p>

            <dl v-if="props.valid" class="mt-5 space-y-2 text-sm">
                <div class="flex justify-between gap-3 border-b border-border pb-2">
                    <dt class="text-muted-foreground">Jenis dokumen</dt>
                    <dd class="font-medium text-foreground">{{ labels[props.jenis] || props.jenis }}</dd>
                </div>
                <div v-for="(value, key) in props.ringkasan" :key="key" class="flex justify-between gap-3 border-b border-border pb-2">
                    <dt class="text-muted-foreground">{{ String(key).replaceAll('_', ' ') }}</dt>
                    <dd class="text-right font-medium text-foreground">{{ value }}</dd>
                </div>
                <div v-if="props.diterbitkan" class="flex justify-between gap-3 border-b border-border pb-2">
                    <dt class="text-muted-foreground">Diterbitkan</dt>
                    <dd class="font-medium text-foreground">{{ props.diterbitkan }}</dd>
                </div>
                <div v-if="props.diperbarui" class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Terakhir diperbarui</dt>
                    <dd class="font-medium text-foreground">{{ props.diperbarui }}</dd>
                </div>
            </dl>
        </main>
    </div>
</template>
