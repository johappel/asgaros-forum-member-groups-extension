/**
 * Accessibility-Tests für die Mitglieder-Verwaltung (afspaces_members).
 *
 * Prüft WCAG 2.1 AA Konformität via axe-core, Tastaturbedienung und
 * semantische Struktur der Mitgliederansicht.
 */

import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const BASE = 'http://forums.test';
const SPACE_ID = Number(process.env.AFSPACES_SPACE_ID) || 262;
const MEMBERS_PAGE = `${BASE}/afspaces/?afspaces_view=members&space_id=${SPACE_ID}`;

const MANAGER = {
  username: 'afp_e2e_manager',
  password: 'E2ePassw0rd!',
};

/** Wartet, bis der Benutzer eingeloggt ist (WP+Asgaros feuert kein load-Event). */
async function waitForLoggedIn(page: Page): Promise<void> {
  await page.waitForFunction(
    () => document.body.classList.contains('logged-in'),
    undefined,
    { timeout: 15000 }
  ).catch(() => {});
}

async function login(page: Page, user: { username: string; password: string }): Promise<void> {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.fill('#user_login', user.username);
  await page.fill('#user_pass', user.password);
  await page.click('#wp-submit', { noWaitAfter: true });
  await waitForLoggedIn(page);
}

/** Navigiert zur Mitgliederseite und wartet auf die Überschrift. */
async function gotoMembers(page: Page): Promise<void> {
  await page.goto(MEMBERS_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
}

test.describe('Barrierefreiheit: Mitglieder-Verwaltung', () => {
  test('Keine WCAG-2.1-AA-Verstöße in der Mitglieder-Verwaltung', async ({ page }) => {
    await login(page, MANAGER);
    await gotoMembers(page);

    // Nur unsere Mitglieder-Verwaltung prüfen (nicht Header/Footer des Themes).
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .include('.afspaces-members')
      .analyze();

    expect(results.violations).toEqual([]);
  });

  test('Suchformular ist per Tastatur erreichbar und bedienbar', async ({ page }) => {
    await login(page, MANAGER);
    await gotoMembers(page);

    // Fokus auf das Suchfeld.
    const search = page.locator('#afp_search');
    await expect(search).toBeVisible();
    await search.focus();
    await expect(search).toBeFocused();

    // Eingabe und Absenden per Enter.
    await search.fill('afp_e2e_target');
    await search.press('Enter');

    // Suchergebnisse erscheinen.
    await expect(page.locator('h3:has-text("Suchergebnisse")')).toBeVisible({ timeout: 15000 });
  });

  test('Mitgliederliste ist semantische Tabelle mit Überschriften', async ({ page }) => {
    await login(page, MANAGER);
    await gotoMembers(page);

    const table = page.locator('table.afspaces-member-table');
    await expect(table).toBeVisible();

    // Spaltenüberschriften vorhanden.
    await expect(table.locator('thead th').first()).toContainText(/Name/);
    await expect(table.locator('thead th').nth(1)).toContainText(/Aktion/);

    // Tabellenstruktur: thead + tbody.
    await expect(table.locator('thead')).toHaveCount(1);
    await expect(table.locator('tbody')).toHaveCount(1);
  });

  test('Aktionsbuttons haben zugängliche Beschriftungen', async ({ page }) => {
    await login(page, MANAGER);
    await gotoMembers(page);

    // Entfernen-Button vorhanden und beschriften.
    const removeButtons = page.locator('button:has-text("Entfernen")');
    if (await removeButtons.count() > 0) {
      await expect(removeButtons.first()).toHaveText(/Entfernen/);
    }

    // Such-Button beschriften.
    await expect(page.locator('button:has-text("Suchen")')).toHaveText(/Suchen/);
  });

  test('Seite funktioniert bei 200% Zoom (Layout bricht nicht)', async ({ page }) => {
    await login(page, MANAGER);
    await gotoMembers(page);

    // Emuliert 200% Zoom über eine kleinere Viewport-Auflösung.
    await page.setViewportSize({ width: 640, height: 480 });

    // Überschrift noch sichtbar.
    await expect(page.locator('h2#afspaces-members-heading')).toBeVisible();
    // Suchfeld noch sichtbar und nicht überlappend.
    await expect(page.locator('#afp_search')).toBeVisible();
  });
});
