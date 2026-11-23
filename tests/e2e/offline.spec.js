import { expect, test } from '@playwright/test';

const ADMIN = { identity: 'admin', password: 'admin123' };

async function login(page) {
    await page.goto('/login');
    await page.locator('#login-identity').fill(ADMIN.identity);
    await page.locator('#login-password').fill(ADMIN.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL('**/dashboard');
}

test('halaman ujian tidak pernah dibuka dari cache saat koneksi putus', async ({ page, context }) => {
    await login(page);

    // Service worker hanya aktif setelah terpasang dan mengambil alih halaman.
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.waitForFunction(() => navigator.serviceWorker.controller !== null);

    await page.evaluate(() => {
        localStorage.setItem('draft-1-1', JSON.stringify({ 'soal-1': {}, 'soal-2': {} }));
    });

    await page.goto('/master/siswa');

    await context.setOffline(true);
    const response = await page.goto('/ujian/1/kerjakan');

    await expect(page.getByRole('heading', { name: 'Koneksi terputus' })).toBeVisible();
    await expect(page.getByText('2 jawaban tersimpan di perangkat ini')).toBeVisible();

    // Halaman ujian (beserta snapshot soalnya) tidak boleh disajikan dari cache.
    await expect(page.locator('#app')).toHaveCount(0);
    await expect(page.getByText('Navigasi Soal')).toHaveCount(0);
    expect(response?.status()).toBe(200);

    // Koneksi kembali: halaman otomatis memuat ulang dari server, bukan dari cache.
    await context.setOffline(false);
    await expect(page.getByRole('heading', { name: 'Koneksi terputus' })).toHaveCount(0, { timeout: 20_000 });
});

test('build baru membuang cache service worker lama', async ({ page }) => {
    // offline.html tidak memuat app.js, sehingga registrasi aplikasi tidak ikut berjalan.
    await page.goto('/offline.html');

    await page.evaluate(() => navigator.serviceWorker.register('/sw.js?v=uji-a'));
    await expect.poll(() => page.evaluate(() => caches.keys())).toContain('cbt-shell-uji-a');

    await page.evaluate(() => navigator.serviceWorker.register('/sw.js?v=uji-b'));
    await expect.poll(() => page.evaluate(() => caches.keys())).toContain('cbt-shell-uji-b');
    await expect.poll(() => page.evaluate(() => caches.keys())).not.toContain('cbt-shell-uji-a');
});
