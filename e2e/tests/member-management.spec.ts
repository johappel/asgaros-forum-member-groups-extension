import { test, expect, Page } from '@playwright/test';

/**
 * E2E-Tests für die Mitglieder-Verwaltung (AFSpaces).
 * Läuft gegen die lokale WP-Instanz http://forums.test.
 */

const BASE = process.env.AFSPACES_BASE_URL || 'http://forums.test';
const ADMIN = { username: 'afp_e2e_admin', password: 'E2ePassw0rd!' };
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };
const TARGET = { username: 'afp_e2e_target', password: 'E2ePassw0rd!' };

function membersPage(spaceId: number) {
  return `${BASE}/afspaces/?afspaces_view=members&space_id=${spaceId}`;
}

async function getManagedSpaceId(page: Page): Promise<number> {
  await page.goto(`${BASE}/afspaces/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  const membersLink = page.locator('a[href*="afspaces_view=members"][href*="space_id="]').first();
  await expect(membersLink).toBeVisible({ timeout: 15000 });

  const href = (await membersLink.getAttribute('href')) || '';
  const parsed = new URL(href, BASE);
  const value = Number(parsed.searchParams.get('space_id') || '0');
  expect(value).toBeGreaterThan(0);

  return value;
}

async function waitForLoggedIn(page: Page) {
  // Prüfen, ob die Mitgliederseite erreichbar ist.
  try {
    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
  } catch {
    throw new Error('Login nicht erfolgreich (Mitgliederseite nicht erreichbar).');
  }
}

async function login(page: Page, user: { username: string; password: string }) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#wp-submit');
  await page.fill('#user_login', user.username);
  await page.fill('#user_pass', user.password);
  await page.click('#wp-submit', { noWaitAfter: true });
  // Warten, bis der Login verarbeitet ist (Cookies gesetzt).
  await page.waitForTimeout(10000);
}

const TARGET_ROW = '.afspaces-member-table tbody tr';

function targetRow(page: Page) {
  return page.locator(TARGET_ROW, { hasText: 'afp_e2e_target' });
}

/**
 * Navigiert zur Mitgliederseite und wartet auf den sichtbaren Heading.
 * WP+Asgaros feuert kein `load`-Event, daher kann nicht auf domcontentloaded
 * gewartet werden — wir pollen auf einen konkreten Selektor.
 */
async function gotoMembers(page: Page, spaceId: number) {
  await page.goto(membersPage(spaceId), { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
}

/** Stellt sicher, dass der Zielbenutzer KEIN Mitglied ist (entfernt ggf.). */
async function ensureTargetRemoved(page: Page, spaceId: number) {
  await gotoMembers(page, spaceId);
  const row = await targetRow(page);
  if (await row.count() > 0) {
    page.once('dialog', (dialog) => dialog.accept());
    await row.locator('button:has-text("Entfernen")').click({ noWaitAfter: true });
    // Nach dem Entfernen neu laden und sicherstellen, dass der Benutzer weg ist.
    await page.waitForTimeout(2000);
    await gotoMembers(page, spaceId);
    await expect(await targetRow(page).count()).toBe(0);
  }
}

/** Stellt sicher, dass der Zielbenutzer Mitglied ist (fügt ggf. hinzu). */
async function ensureTargetAdded(page: Page, spaceId: number) {
  await gotoMembers(page, spaceId);
  if ((await targetRow(page).count()) === 0) {
    await page.goto(`${membersPage(spaceId)}&afp_search=afp_e2e_target`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('.afspaces-section-title', { hasText: 'Suchergebnisse' })).toBeVisible({ timeout: 15000 });
    const addButton = page.locator('form.afspaces-inline-form button:has-text("Hinzufügen")').first();
    await expect(addButton).toBeVisible({ timeout: 15000 });
    await addButton.click({ noWaitAfter: true });
    await expect(targetRow(page)).toBeVisible({ timeout: 15000 }).catch(() => {});
  }
}

test.describe('Mitglieder-Verwaltung (mit JavaScript)', () => {
  test('Manager kann einen Benutzer suchen und hinzufügen', async ({ page }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    await gotoMembers(page, spaceId);
    await waitForLoggedIn(page);
    await ensureTargetRemoved(page, spaceId);
    await ensureTargetAdded(page, spaceId);

    // Mitglied erscheint in der Tabelle.
    await gotoMembers(page, spaceId);
    await expect(targetRow(page)).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.afspaces-member-table')).toContainText('afp_e2e_target');
  });

  test('Manager kann einen Benutzer entfernen', async ({ page }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    await gotoMembers(page, spaceId);
    await waitForLoggedIn(page);
    await ensureTargetAdded(page, spaceId);

    await gotoMembers(page, spaceId);
    const row = await targetRow(page);
    await expect(row).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await row.locator('button:has-text("Entfernen")').click({ noWaitAfter: true });
    await expect(page.locator('.afspaces-member-table')).not.toContainText('afp_e2e_target', { timeout: 15000 });
  });
});

test.describe('Barrierefreiheit: Tastaturbedienung ohne JavaScript', () => {
  // Playwright 1.61 bietet kein setJavaScriptEnabled() – JS wird nur über die
  // Context-Option gesteuert. Wir loggen uns im JS-Context ein, kopieren die
  // Cookies in einen JS-freien Context und führen den eigentlichen Test dort aus.
  test('Kernfunktion funktioniert ohne JavaScript', async ({ browser, context, page }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    await gotoMembers(page, spaceId);
    await waitForLoggedIn(page);
    const cookies = await context.cookies();

    const noJsContext = await browser.newContext({ javaScriptEnabled: false });
    await noJsContext.addCookies(cookies);
    const noJsPage = await noJsContext.newPage();

    await ensureTargetRemoved(noJsPage, spaceId);

    await gotoMembers(noJsPage, spaceId);

    // Suche per Tastatur ausfüllen und absenden.
    await noJsPage.focus('#afp_search');
    await noJsPage.keyboard.type('afp_e2e_target');
    await noJsPage.keyboard.press('Tab');
    await noJsPage.keyboard.press('Enter');
    const addButton = noJsPage.locator('form.afspaces-inline-form button:has-text("Hinzufügen")').first();
    await expect(addButton).toBeVisible({ timeout: 15000 });

    // Hinzufügen-Button per Tastatur aktivieren.
    await addButton.focus();
    await noJsPage.keyboard.press('Enter');
    await expect(noJsPage.locator('.afspaces-member-table')).toContainText('afp_e2e_target', { timeout: 15000 });

    await noJsContext.close();
  });

  test('Mitgliederliste ist semantische Tabelle mit Überschriften', async ({ browser, context, page }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    await gotoMembers(page, spaceId);
    await waitForLoggedIn(page);
    const cookies = await context.cookies();

    const noJsContext = await browser.newContext({ javaScriptEnabled: false });
    await noJsContext.addCookies(cookies);
    const noJsPage = await noJsContext.newPage();

    await gotoMembers(noJsPage, spaceId);

    const table = noJsPage.locator('table.afspaces-member-table');
    await expect(table).toBeVisible();
    await expect(table.locator('thead th').first()).toHaveText(/Name/);

    await noJsContext.close();
  });
});
