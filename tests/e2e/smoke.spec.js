import { expect, test } from '@playwright/test';

const ADMIN = { identity: 'admin', password: 'admin123' };

async function login(page) {
    await page.goto('/login');
    await page.locator('#login-identity').fill(ADMIN.identity);
    await page.locator('#login-password').fill(ADMIN.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL('**/dashboard');
}

test('halaman login tampil dengan kolom yang benar', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('#login-identity')).toBeVisible();
    await expect(page.locator('#login-password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
});

test('password salah menampilkan pesan kesalahan', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#login-identity').fill('admin');
    await page.locator('#login-password').fill('password-salah');
    await page.getByRole('button', { name: 'Masuk' }).click();

    await expect(page.getByRole('alert')).toContainText('salah');
    await expect(page).toHaveURL(/\/login$/);
});

test('administrator bisa masuk dan melihat navigasi', async ({ page }) => {
    await login(page);

    await expect(page.getByRole('heading', { name: 'Beranda' })).toBeVisible();
    await expect(page.locator('#app-sidebar nav a[href="/master/siswa"]')).toHaveCount(1);
});

test('tabel data siswa memakai header standar', async ({ page }) => {
    await login(page);
    await page.goto('/master/siswa');

    const headers = page.locator('table.data-table thead th');
    await expect(headers.first()).toBeVisible();
    await expect(page.locator('table.data-table thead th', { hasText: 'AKSI' })).toHaveCount(1);
});

test('pilihan tema gelap bertahan setelah muat ulang', async ({ page }) => {
    await login(page);

    await page.locator('header button[aria-label*="tema"]').click();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.reload();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.locator('header button[aria-label*="tema"]').click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
});

test('keluar mengembalikan pengguna ke halaman login', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Buka menu pengguna' }).click();
    await page.getByRole('menuitem', { name: 'Keluar' }).click();

    await page.waitForURL('**/login');
    await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
});
