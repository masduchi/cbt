import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8124';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL,
        headless: true,
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command:
                  'bash -lc "DB_DATABASE=cbt_laravel_test php artisan migrate:fresh --seed --force >/dev/null && DB_DATABASE=cbt_laravel_test php artisan serve --host=127.0.0.1 --port=8124"',
              url: 'http://127.0.0.1:8124/login',
              reuseExistingServer: true,
              timeout: 240_000,
              stdout: 'ignore',
              stderr: 'pipe',
          },
});
