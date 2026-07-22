import { test, expect, Page } from '@playwright/test';

/**
 * E2E-Tests für die Forum-integrierte Navigation der Spaces-Verwaltung.
 * Prüft den Hub, die Unternavigation (Tabs) und die Tastaturbedienbarkeit.
 */

const BASE = process.env.AFSPACES_BASE_URL || 'http://forums.test';
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };

const HUB_DASHBOARD = `${BASE}/afspaces/`;

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

async function getManagedSpaceId(page: Page): Promise<number> {
  await page.goto(HUB_DASHBOARD, { waitUntil: 'domcontentloaded' }).catch(() => {});

  const membersLink = page.locator('a[href*="afspaces_view=members"][href*="space_id="]').first();
  await expect(membersLink).toBeVisible({ timeout: 15000 });

  const href = (await membersLink.getAttribute('href')) || '';
  const parsed = new URL(href, BASE);
  const value = Number(parsed.searchParams.get('space_id') || '0');
  expect(value).toBeGreaterThan(0);

  return value;
}

test.describe('Forum-integrierte Navigation', () => {
  test('Hub zeigt globale Unternavigation mit Dashboard und Einladungen', async ({ page }) => {
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
    const spaceId = await getManagedSpaceId(page);
    const membersUrl = `${BASE}/afspaces/?afspaces_view=members&space_id=${spaceId}`;
    await page.goto(membersUrl, { waitUntil: 'domcontentloaded' }).catch(() => {});

    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('nav.afspaces-breadcrumb')).toBeVisible();

    await expect(page.locator('h2.afspaces-space-context-title')).toContainText('Raum verwalten:', { timeout: 15000 });

    const spaceNav = page.locator('nav.afspaces-space-nav');
    await expect(spaceNav.getByRole('link', { name: 'Mitglieder', exact: true })).toBeVisible();
    await expect(spaceNav.getByRole('link', { name: 'Einladungen', exact: true })).toBeVisible();
    await expect(spaceNav.getByRole('link', { name: 'Beitrittsanfragen', exact: true })).toBeVisible();
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
    const spaceId = await getManagedSpaceId(page);
    await page.goto(`${BASE}/afspaces-members/?space_id=${spaceId}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page).toHaveURL(/\/afspaces\/\?afspaces_view=members/, { timeout: 15000 });
    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
  });
});
