/* global __BUILD_ID__ */
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import '../css/app.css';

// Service worker hanya menyimpan aset statis dan halaman "koneksi terputus",
// sehingga halaman ujian tetap bisa dibuka ulang saat offline tanpa data basi.
// ?v= membuat build baru memasang cache baru dan membuang cache build lama.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const buildId = typeof __BUILD_ID__ === 'undefined' ? 'dev' : __BUILD_ID__;

        navigator.serviceWorker
            .register(`/sw.js?v=${encodeURIComponent(buildId)}`, { scope: '/' })
            .then((registration) => {
                // Periksa pembaruan berkala agar tab yang lama terbuka tidak
                // tertinggal pada versi cache sebelumnya.
                setInterval(() => registration.update().catch(() => {}), 30 * 60 * 1000);
            })
            .catch(() => {
                // Pendaftaran bisa ditolak (mis. mode privat); ujian tetap berjalan.
            });
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} · CBT Sekolah` : 'CBT Sekolah'),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4f5edd',
    },
});
