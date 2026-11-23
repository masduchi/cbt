<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Masuk ke akun Anda' },
    subtitle: { type: String, default: 'Gunakan username atau email yang terdaftar.' },
});

const page = usePage();
const appName = computed(() => page.props.appName || 'CBT Sekolah');
const school = computed(() => {
    const nama = (page.props.school?.sekolah || '').trim();

    return nama && nama !== appName.value ? nama : null;
});
const logo = computed(() => page.props.school?.logo || null);
const period = computed(() => {
    const tahun = page.props.period?.tahun;
    const smt = page.props.period?.smt;

    return tahun ? [tahun, smt].filter(Boolean).join(' · ') : null;
});
</script>

<template>
    <div class="flex min-h-dvh bg-muted/50">
        <aside
            aria-label="Informasi sekolah"
            class="hidden w-1/2 flex-col justify-between bg-primary-900 p-12 lg:flex"
        >
            <div class="flex items-center gap-3">
                <img
                    v-if="logo"
                    :src="logo"
                    alt="Logo sekolah"
                    class="h-10 w-10 shrink-0 rounded-lg bg-white object-contain p-1"
                />
                <div v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white">
                    <i class="fas fa-graduation-cap h-5 w-5" aria-hidden="true" ></i>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ appName }}</p>
                    <p v-if="school" class="truncate text-xs text-primary-200">{{ school }}</p>
                </div>
            </div>

            <dl class="space-y-4 border-l border-white/25 pl-5">
                <div v-if="school">
                    <dt class="text-xs font-medium text-primary-200">Sekolah</dt>
                    <dd class="text-sm text-white">{{ school }}</dd>
                </div>
                <div v-if="period">
                    <dt class="text-xs font-medium text-primary-200">Tahun pelajaran aktif</dt>
                    <dd class="text-sm text-white">{{ period }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-primary-200">Akses</dt>
                    <dd class="text-sm text-white">Administrator, guru, dan siswa</dd>
                </div>
            </dl>

            <p class="text-xs text-primary-200">&copy; {{ new Date().getFullYear() }} {{ school || appName }}</p>
        </aside>

        <div class="flex w-full items-center justify-center p-6 lg:w-1/2">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img
                        v-if="logo"
                        :src="logo"
                        alt="Logo sekolah"
                        class="h-10 w-10 shrink-0 rounded-lg border border-border bg-white object-contain p-1"
                    />
                    <div v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-white">
                        <i class="fas fa-graduation-cap h-5 w-5" aria-hidden="true" ></i>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-foreground">{{ appName }}</p>
                        <p v-if="school" class="truncate text-xs text-muted-foreground">{{ school }}</p>
                    </div>
                </div>

                <h1 class="text-xl font-semibold text-foreground">{{ props.title }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>

                <slot />
            </div>
        </div>
    </div>
</template>
