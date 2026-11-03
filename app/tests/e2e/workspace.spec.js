import { test, expect } from '@playwright/test'

// Runs against the seeded demo database (php artisan migrate:fresh --seed).
async function signIn(page, name) {
    await page.goto('/login')
    await page.getByRole('button', { name: new RegExp(name) }).click()
    await page.getByRole('button', { name: 'Sign in' }).click()
    await page.waitForURL('**/')
}

test('manager creates, works and closes a deal; admin is notified of the mention', async ({ page, browser }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(String(e)))
    await signIn(page, 'Bimo Setiawan')

    // New deal through the custom select and date picker.
    await page.goto('/pipeline')
    await page.getByRole('button', { name: 'New deal' }).first().click()
    await page.fill('#nd-title', 'E2E workspace deal')
    await page.getByRole('combobox', { name: 'Contact' }).click()
    await page.keyboard.type('dewi')
    await page.keyboard.press('Enter')
    await page.getByRole('button', { name: 'Expected close' }).click()
    await page.getByRole('gridcell').nth(20).click()
    await page.fill('#nd-val', '12500000')
    await page.getByRole('button', { name: 'Create deal' }).click()
    await expect(page.getByRole('heading', { name: 'E2E workspace deal' })).toBeVisible()
    await expect(page.locator('select')).toHaveCount(0)

    // @mention note, then a task.
    await page.locator('textarea[aria-label=Note]').fill('Hi @ayu please review the scope.')
    await page.keyboard.press('Meta+Enter')
    await expect(page.locator('.mention', { hasText: '@ayu' })).toBeVisible()
    await page.getByRole('tab', { name: 'Task' }).click()
    await page.fill('input[aria-label=Task]', 'Send quote')
    await page.getByRole('button', { name: 'Add task' }).click()
    await expect(page.getByText('Send quote').first()).toBeVisible()

    // The deal is closed as lost with a reason.
    await page.locator('.acts').getByRole('button', { name: 'Lost' }).click()
    await page.getByRole('combobox', { name: 'Lost reason' }).click()
    await page.getByRole('option', { name: 'No response' }).click()
    await page.getByRole('button', { name: 'Mark lost' }).click()
    await expect(page.getByText('Lost: No response')).toBeVisible()

    // Admin sees the mention in the bell and can follow it.
    const admin = await browser.newContext()
    const ap = await admin.newPage()
    await signIn(ap, 'Ayu Kartika')
    await ap.getByRole('button', { name: 'Notifications' }).click()
    await ap.getByText('You were mentioned on E2E workspace deal').click()
    await expect(ap.getByRole('heading', { name: 'E2E workspace deal' })).toBeVisible()

    // Pipeline stages and templates are editable in settings.
    await ap.goto('/settings')
    await ap.getByRole('link', { name: 'Pipeline' }).click()
    await ap.getByPlaceholder('New stage name').fill('Contract review')
    await ap.getByRole('button', { name: 'Add stage' }).click()
    await expect(ap.getByLabel('Name of stage Contract review')).toBeVisible()
    await ap.getByRole('link', { name: 'Message templates' }).click()
    await ap.getByRole('button', { name: 'New template' }).click()
    await ap.fill('#t-name', 'check_in_week')
    await ap.fill('#t-body', 'Halo, bagaimana kabarnya?')
    await ap.getByRole('button', { name: 'Save template' }).click()
    await expect(ap.getByText('check_in_week').first()).toBeVisible()
    await admin.close()

    expect(errors).toEqual([])
})

test('a failed message can be reopened from the thread with its error', async ({ page }) => {
    await signIn(page, 'Bimo Setiawan')
    await page.goto('/inbox?view=all')
    await page.getByRole('link', { name: /Bayu Firmansyah/ }).first().click()
    await expect(page.getByText(/More than 24 hours have passed/)).toBeVisible()
    await expect(page.getByText('The 24-hour window is closed')).toBeVisible()
})
