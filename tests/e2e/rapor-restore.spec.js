import { expect, test } from '@playwright/test';

const ADMIN = { identity: 'admin', password: 'admin123' };

async function login(page) {
    await page.goto('/login');
    await page.locator('#login-identity').fill(ADMIN.identity);
    await page.locator('#login-password').fill(ADMIN.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL('**/dashboard');
}

test('restore nilai rapor menolak konfirmasi otomatis dan kosong', async ({ page }) => {
    await login(page);
    await page.goto('/rapor/pengaturan');

    const fileName = 'backup-rapor-uji.json';

    await page.locator('#berkas-backup-json').setInputFiles({
        name: fileName,
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify({ tables: { rapor_kkm: [] } })),
    });

    await page.getByRole('button', { name: 'Pulihkan', exact: true }).click();

    const modal = page.getByRole('dialog');
    await expect(modal.getByRole('heading', { name: 'Pulihkan Nilai Rapor' })).toBeVisible();

    const submit = modal.getByRole('button', { name: 'Pulihkan nilai rapor' });
    await expect(submit).toBeDisabled();

    await modal.locator('#rapor-restore-confirm').fill('nama-lain.json');
    await expect(submit).toBeDisabled();

    await modal.locator('#rapor-restore-confirm').fill(fileName);
    await expect(submit).toBeEnabled();

    // Tidak dikirim: tes ini hanya memastikan gerbang konfirmasi bekerja.
    await modal.getByRole('button', { name: 'Batal' }).click();
    await expect(modal).toBeHidden();
});
