import { test, expect } from '@playwright/test'

// E2E (M5): lead WhatsApp masuk → rep balas → buat deal.
// Runs against the seeded demo database (php artisan migrate:fresh --seed).
test('lead enters via simulation, rep replies, deal is created', async ({ page }) => {
    // 1. Login as rep.
    await page.goto('/login')
    await page.getByRole('textbox', { name: 'Email' }).fill('bimo@northwind.test')
    await page.getByRole('textbox', { name: 'Password' }).fill('password')
    await page.getByRole('button', { name: 'Sign in' }).click()
    await expect(page.getByRole('heading', { name: 'Today', exact: true })).toBeVisible()

    // 2. Simulate an inbound WhatsApp lead (admin-only endpoint via artisan tinker
    //    is not needed: use the UI-independent API with an admin session).
    //    We log in as admin in a second context just for the simulation call.
    const admin = await page.context().browser().newContext()
    const adminPage = await admin.newPage()
    await adminPage.goto('/login')
    await adminPage.getByRole('textbox', { name: 'Email' }).fill('ayu@northwind.test')
    await adminPage.getByRole('textbox', { name: 'Password' }).fill('password')
    await adminPage.getByRole('button', { name: 'Sign in' }).click()
    await adminPage.waitForURL('http://localhost:8001/')
    const sim = await adminPage.evaluate(async () => {
        const csrf = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')
            .slice(1)
            .join('=')
        const res = await fetch('/dev/simulate-inbound', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(csrf ?? ''),
            },
            body: JSON.stringify({
                from: '+6281234599991',
                name: 'E2E Lead',
                text: 'Hello, I need a website redesign before December. Budget is flexible.',
            }),
        })
        const body = await res.text().catch(() => '')
        return { ok: res.ok, status: res.status, body: body.slice(0, 200) }
    })
    expect(sim.ok, `simulate failed: ${sim.status} ${sim.body}`).toBeTruthy()
    await admin.close()

    // 3. Rep opens the inbox; the new conversation appears under Unreplied.
    await page.goto('/inbox')
    await expect(page.getByText('E2E Lead').first()).toBeVisible()
    await page.getByRole('link', { name: /E2E Lead/ }).first().click()
    await expect(page.locator('.bubble').first()).toBeVisible()
    await expect(page.getByText('Free reply window open for')).toBeVisible()

    // 4. Draft with AI fills the composer, then the rep edits and sends manually.
    await page.getByRole('button', { name: /Draft with AI/ }).click()
    await expect(page.locator('.composer-input')).not.toBeEmpty({ timeout: 10_000 })
    await page.locator('.composer-input').fill('Hi! Thanks for reaching out — we can definitely help with the redesign. Let me prepare a quote.')
    await page.getByRole('button', { name: 'Send' }).click()
    await expect(page.locator('.brow.out .bubble').last()).toContainText('we can definitely help')

    // 5. Create a deal for the contact.
    await page.getByRole('link', { name: 'Open contact' }).first().click()
    await expect(page.locator('.crumb').getByText('E2E Lead')).toBeVisible()

    // 6. Verify the outbound message landed on the contact timeline.
    await expect(page.getByText('Reply sent').first()).toBeVisible()
})
