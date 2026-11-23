<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { btnPrimary, btnSecondary } from '@/ui';

const props = defineProps({ status: { type: Number, default: 404 } });

const page = usePage();
const appName = computed(() => page.props.school?.nama_aplikasi || page.props.appName || 'CBT Sekolah');
const schoolName = computed(() => page.props.school?.sekolah ?? '');

const messages = {
    403: {
        title: 'Akses ditolak',
        description: 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Bila menurut Anda ini keliru, hubungi administrator sekolah.',
        icon: 'shield-alt',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description: 'Alamat yang Anda buka tidak ada atau sudah dipindahkan. Periksa kembali tautannya atau kembali ke beranda.',
        icon: 'search-minus',
    },
    419: {
        title: 'Sesi Anda sudah berakhir',
        description: 'Halaman terlalu lama terbuka sehingga sesi berakhir. Muat ulang halaman, lalu ulangi langkah Anda.',
        icon: 'stopwatch',
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description: 'Anda mengirim permintaan terlalu cepat. Tunggu sebentar, lalu coba lagi.',
        icon: 'stopwatch',
    },
    500: {
        title: 'Terjadi kesalahan pada server',
        description: 'Aplikasi gagal memproses permintaan ini. Coba beberapa saat lagi; bila terus berulang, hubungi administrator.',
        icon: 'server',
    },
    503: {
        title: 'Layanan sedang tidak tersedia',
        description: 'Aplikasi sedang dalam pemeliharaan. Coba lagi beberapa saat lagi.',
        icon: 'wrench',
    },
};

const detail = computed(
    () =>
        messages[props.status] ?? {
            title: 'Terjadi kesalahan',
            description: 'Permintaan Anda tidak dapat diproses. Kembali ke beranda dan coba lagi.',
            icon: 'exclamation-triangle',
        },
);

// Rute beranda sudah dijaga: pengunjung yang belum masuk otomatis diarahkan ke halaman login.
const homeHref = '/';

const goBack = () => window.history.back();
const reload = () => window.location.reload();
</script>

<template>
    <Head :title="detail.title" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4 py-10">
        <main class="w-full max-w-md">
            <div class="mb-6 text-center">
                <p class="text-sm font-semibold tracking-tight text-foreground">{{ appName }}</p>
                <p v-if="schoolName" class="text-xs text-muted-foreground">{{ schoolName }}</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-6 text-center shadow-card sm:p-8">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <i :class="'fas fa-' + (detail.icon)" class="h-6 w-6" aria-hidden="true" ></i>
                </div>

                <p class="mt-5 font-mono text-2xs font-semibold uppercase tracking-widest text-muted-foreground">Kode {{ status }}</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-foreground">{{ detail.title }}</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">{{ detail.description }}</p>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <Link :href="homeHref" :class="btnPrimary">
                        <i class="fas fa-home h-4 w-4" aria-hidden="true" ></i> Ke beranda
                    </Link>
                    <button v-if="status === 419" type="button" :class="btnSecondary" @click="reload">
                        <i class="fas fa-sync-alt h-4 w-4" aria-hidden="true" ></i> Muat ulang
                    </button>
                    <button v-else type="button" :class="btnSecondary" @click="goBack">
                        <i class="fas fa-arrow-left h-4 w-4" aria-hidden="true" ></i> Kembali
                    </button>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                Bila masalah berlanjut, sampaikan kode {{ status }} kepada administrator sekolah.
            </p>
        </main>
    </div>
</template>
