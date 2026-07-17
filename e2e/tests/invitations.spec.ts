import { test, expect, Page } from '@playwright/test';

const runtime = globalThis as { process?: { env?: Record<string, string | undefined> } };
const BASE = runtime.process?.env?.AFSPACES_BASE_URL || 'http://forums.test';
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };
const INVITEE = { username: 'afp_e2e_target', password: 'E2ePassw0rd!' };
const SPACE_ID = 92;
const MEMBERS_PAGE = `${BASE}/afspaces-members/?space_id=${SPACE_ID}`;
const INV_PAGE = `${BASE}/afspaces-invitations/?space_id=${SPACE_ID}`;
const MY_INV_PAGE = `${BASE}/afspaces-my-invitations/`;
const TARGET_LOGIN = 'afp_e2e_target';

test.describe.configure({ timeout: 240000 });

async function waitForManagerAccess(page: Page) {
  await page.goto(MEMBERS_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });
}

async function login(page: Page, user: { username: string; password: string }) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  if (page.isClosed()) {
    throw new Error('Browserseite wurde vor dem Login geschlossen.');
  }
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

async function gotoInvitations(page: Page, search = TARGET_LOGIN) {
  await page.goto(`${INV_PAGE}&inv_search=${encodeURIComponent(search)}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-invitations-heading')).toBeVisible({ timeout: 15000 });
}

async function gotoMyInvitations(page: Page) {
  await page.goto(MY_INV_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-my-invitations-heading')).toBeVisible({ timeout: 15000 });
}

async function ensureTargetRemovedFromMembers(page: Page) {
  await page.goto(MEMBERS_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  const row = page.locator('.afspaces-member-table tbody tr', { hasText: TARGET_LOGIN });
  if ((await row.count()) > 0) {
    page.once('dialog', (dialog) => dialog.accept());
    await row.first().locator('button:has-text("Entfernen")').click({ noWaitAfter: true });
    await page.waitForTimeout(2000);
  }
}

async function revokeOpenInvitationsForTarget(page: Page) {
  await gotoInvitations(page);
  const rows = page.locator('.afspaces-invitations-table tbody tr', { hasText: TARGET_LOGIN });
  const count = await rows.count();
  for (let i = 0; i < count; i++) {
    const row = rows.nth(i);
    const revoke = row.locator('button:has-text("Widerrufen")');
    if ((await revoke.count()) > 0) {
      await revoke.first().click({ noWaitAfter: true });
      await page.waitForTimeout(1500);
      await gotoInvitations(page);
    }
  }
}

async function createInvitation(page: Page, message = 'Automatischer Test', days = '7') {
  await gotoInvitations(page);
  const resultItem = page.locator('.afspaces-search-results li', { hasText: TARGET_LOGIN }).first();
  await expect(resultItem).toBeVisible({ timeout: 15000 });
  await resultItem.locator('input[name="message"]').fill(message);
  await resultItem.locator('input[name="expires_in_days"]').fill(days);
  await resultItem.locator('button:has-text("Einladen")').click({ noWaitAfter: true });
  await page.waitForTimeout(1500);
}

async function answerLatestInvitation(page: Page, action: 'Annehmen' | 'Ablehnen') {
  await gotoMyInvitations(page);
  const item = page.locator('.afspaces-space-item', { hasText: 'AFSpaces Testforum' }).first();
  await expect(item).toBeVisible({ timeout: 15000 });
  await item.locator(`button:has-text("${action}")`).first().click({ noWaitAfter: true });
  await page.waitForTimeout(1500);
}

test.describe('Einladungsfluss', () => {
  test('Manager lädt Benutzer ein, Benutzer sieht Einladung', async ({ page }) => {
    await login(page, MANAGER);
    await waitForManagerAccess(page);
    await ensureTargetRemovedFromMembers(page);
    await revokeOpenInvitationsForTarget(page);
    await createInvitation(page, 'Bitte beitreten', '7');

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, INVITEE);
    await gotoMyInvitations(page);
    await expect(page.locator('.afspaces-space-item').first()).toBeVisible({ timeout: 15000 });
  });

  test('Benutzer nimmt Einladung an und erhält Zugriff', async ({ page }) => {
    await login(page, MANAGER);
    await waitForManagerAccess(page);
    await ensureTargetRemovedFromMembers(page);
    await revokeOpenInvitationsForTarget(page);
    await createInvitation(page, 'Annahme-Test', '7');

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, INVITEE);
    await answerLatestInvitation(page, 'Annehmen');

    await gotoMyInvitations(page);
    await expect(page.locator('.afspaces-my-invitations')).toContainText('accepted', { timeout: 15000 });
  });

  test('Benutzer lehnt eine zweite Einladung ab', async ({ page }) => {
    await login(page, MANAGER);
    await waitForManagerAccess(page);
    await ensureTargetRemovedFromMembers(page);
    await revokeOpenInvitationsForTarget(page);
    await createInvitation(page, 'Erste Einladung', '7');

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, INVITEE);
    await answerLatestInvitation(page, 'Ablehnen');

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, MANAGER);
    await waitForManagerAccess(page);
    await createInvitation(page, 'Zweite Einladung', '7');

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, INVITEE);
    await answerLatestInvitation(page, 'Ablehnen');
    await gotoMyInvitations(page);
    await expect(page.locator('.afspaces-space-item', { hasText: 'declined' }).first()).toBeVisible({ timeout: 15000 });
  });

  test('Manager widerruft eine Einladung', async ({ page }) => {
    await login(page, MANAGER);
    await waitForManagerAccess(page);
    await ensureTargetRemovedFromMembers(page);
    await revokeOpenInvitationsForTarget(page);
    await createInvitation(page, 'Widerruf-Test', '7');

    await gotoInvitations(page);
    const row = page.locator('.afspaces-invitations-table tbody tr', { hasText: TARGET_LOGIN }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.locator('button:has-text("Widerrufen")').click({ noWaitAfter: true });
    await page.waitForTimeout(1500);
    await gotoInvitations(page);
    await expect(page.locator('.afspaces-invitations-table')).toContainText('revoked', { timeout: 15000 });

    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.context().clearCookies();
    await login(page, INVITEE);
    await gotoMyInvitations(page);
    await expect(page.locator('.afspaces-my-invitations')).toContainText('revoked', { timeout: 15000 });
  });
});
