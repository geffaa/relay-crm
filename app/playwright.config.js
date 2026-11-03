import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    use: {
        baseURL: 'http://localhost:8001',
        headless: true,
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php artisan serve --port=8001',
        url: 'http://localhost:8001/login',
        reuseExistingServer: true,
    },
})
