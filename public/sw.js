/*
 * Service worker CBT.
 *
 * Yang disimpan hanya dua hal: aset statis ber-hash di /build/ dan halaman
 * /offline.html. Respons navigasi (termasuk halaman ujian beserta snapshot
 * soalnya) dan semua permintaan data tidak pernah disimpan, sehingga saat
 * koneksi putus tidak ada data ujian basi yang bisa disajikan. Navigasi selalu
 * diambil langsung dari jaringan; bila gagal, pengguna mendapat halaman
 * "koneksi terputus" yang menjelaskan jawaban mana yang masih menunggu.
 *
 * Versi cache diambil dari parameter ?v= saat registrasi. Build baru mendaftar
 * URL yang berbeda, sehingga service worker baru terpasang dan cache build lama
 * dibuang seluruhnya pada activate. Tanpa ini, perubahan /offline.html pada
 * build baru tidak pernah terlihat karena sw.js sendiri tidak berubah.
 */
const VERSION = (new URL(self.location.href).searchParams.get('v') || 'dev').replace(/[^a-zA-Z0-9._-]/g, '');
const CACHE_NAME = `cbt-shell-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const ASSET_PREFIXES = ['/build/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navigation(request));
        return;
    }

    if (ASSET_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(asset(request));
    }
});

/**
 * Navigasi selalu jaringan-dulu tanpa cache HTTP. Halaman ujian memuat snapshot
 * soal dan sisa waktu, jadi versi simpanan tidak boleh dipakai sama sekali.
 */
async function navigation(request) {
    try {
        return await fetch(request, { cache: 'no-store' });
    } catch {
        const cached = await caches.match(OFFLINE_URL);

        if (cached) {
            return cached;
        }

        return new Response('Koneksi terputus. Muat ulang halaman setelah tersambung kembali.', {
            status: 503,
            headers: {
                'Content-Type': 'text/plain; charset=utf-8',
                'Cache-Control': 'no-store',
            },
        });
    }
}

/**
 * Aset Vite memakai nama ber-hash, sehingga aman disajikan dari cache lebih
 * dulu; nama baru akan diambil dari jaringan saat ada pembaruan aplikasi.
 */
async function asset(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const copy = response.clone();

        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
    }

    return response;
}
