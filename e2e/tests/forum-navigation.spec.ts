import { test, expect, Page } from '@playwright/test';

/**
 * E2E-Tests für die Forum-integrierte Navigation der Spaces-Verwaltung.
 * Prüft den Hub, die Unternavigation (Tabs) und die Tastaturbedienbarkeit.
 */

const BASE = process.env.AFSPACES_BASE_URL || 'http://forums.test';
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };

const SPACE_ID = Number(process.env.AFSPACES_SPACE_ID) || 262;
const HUB_DASHBOARD = `${BASE}/afspaces/`;
const HUB_MEMBERS = `${BASE}/afspaces/?afspaces_view=members&space_id=${SPACE_ID}`;

test.describe.configure({ timeout: 240000 });

async function login(page: Page, user: { username: string; password: string }) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForSelector('#wp-submit', { timeout: 30000 });
  await page.fill('#user_login', user.username);
  await page.fill('#user_pass', user.password);
  await page.click('#wp-submit', { noWaitAfter: true });
  await page.waitForFunction(
    () => document.body.classList.contains('logged-in') || window.location.pathname.includes('wp-admin'),
    undefined,
    { timeout: 30000 }
  ).catch(() => {});
}

test.describe('Forum-integrierte Navigation', () => {
  test('Hub zeigt Unternavigation mit Dashboard und Einladungen', async ({ page }) => {
    await login(page, MANAGER);
    await page.goto(HUB_DASHBOARD, { waitUntil: 'domcontentloaded' }).catch(() => {});

    const nav = page.locator('nav.afspaces-hub-nav');
    await expect(nav).toBeVisible({ timeout: 15000 });
    await expect(nav.locator('a', { hasText: 'Meine Räume' })).toBeVisible();
    await expect(nav.locator('a', { hasText: 'Meine Einladungen' })).toBeVisible();

    // Aktiver Tab ist als solcher gekennzeichnet (nicht nur farblich).
    await expect(page.locator('a.afspaces-hub-tab.is-active[aria-current="page"]')).toHaveText('Meine Räume');
  });

  test('Breadcrumb und kontextbezogene Tabs auf der Mitgliederseite', async ({ page }) => {
    await login(page, MANAGER);
    await page.goto(HUB_MEMBERS, { waitUntil: 'domcontentloaded' }).catch(() => {});

    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('nav.afspaces-breadcrumb')).toBeVisible();

    const nav = page.locator('nav.afspaces-hub-nav');
    await expect(nav.getByRole('link', { name: 'Mitglieder', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Einladungen', exact: true })).toBeVisible();
  });

  test('Unternavigation ist per Tastatur bedienbar', async ({ page }) => {
    await login(page, MANAGER);
    await page.goto(HUB_DASHBOARD, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('nav.afspaces-hub-nav')).toBeVisible({ timeout: 15000 });

    const invitationsTab = page.locator('nav.afspaces-hub-nav a', { hasText: 'Meine Einladungen' });
    await invitationsTab.focus();
    await expect(invitationsTab).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('h2#afspaces-my-invitations-heading')).toBeVisible({ timeout: 15000 });
  });

  test('Alte Einzelseite leitet auf den Hub um', async ({ page }) => {
    await login(page, MANAGER);
    await page.goto(`${BASE}/afspaces-members/?space_id=${SPACE_ID}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page).toHaveURL(/\/afspaces\/\?afspaces_view=members/, { timeout: 15000 });
    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
  });
});
