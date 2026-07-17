import { test, expect, Page } from '@playwright/test';

const runtime = globalThis as { process?: { env?: Record<string, string | undefined> } };
const BASE = runtime.process?.env?.AFSPACES_BASE_URL || 'http://forums.test';
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };
const INVITEE = { username: 'afp_e2e_target', password: 'E2ePassw0rd!' };
const SPACE_ID = 92;
const INV_PAGE = `${BASE}/afspaces-invitations/?space_id=${SPACE_ID}`;

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

async function createInviteLink(page: Page, maxUses = '1', days = '7', approvalMode: 'auto_join' | 'approval_required' = 'auto_join') {
  await page.goto(INV_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-invitations-heading')).toBeVisible({ timeout: 15000 });
  await page.selectOption('select[name="approval_mode"]', approvalMode);
  await page.locator('input[name="max_uses"]').fill(maxUses);
  await page.locator('input[name="expires_in_days"]').fill(days);
  await page.locator('button:has-text("Einladungslink erstellen")').click({ noWaitAfter: true });
  await expect(page.locator('#afspaces-created-link')).toBeVisible({ timeout: 15000 });
  return await page.locator('#afspaces-created-link').inputValue();
}

test.describe('Invite-Links', () => {
  test('Manager erstellt Link mit Ablauf und Nutzungslimit, bestehender Benutzer tritt bei', async ({ page, context }) => {
    await login(page, MANAGER);
    const inviteLink = await createInviteLink(page, '1', '7', 'auto_join');

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await login(page, INVITEE);
    await expect(page.locator('h2#afspaces-invite-link-heading')).toBeVisible({ timeout: 15000 });
    await page.locator('button:has-text("Raum beitreten")').click({ noWaitAfter: true });
    await page.waitForURL(/\/forum\//, { timeout: 15000 }).catch(() => {});
  });

  test('Nicht angemeldeter Benutzer wird zur Anmeldung geführt und kehrt zurück', async ({ page, context }) => {
    await login(page, MANAGER);
    const inviteLink = await createInviteLink(page, '1', '7', 'auto_join');

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('a:has-text("Anmelden und fortfahren")')).toBeVisible({ timeout: 15000 });
    await page.locator('a:has-text("Anmelden und fortfahren")').click();
    await expect(page).toHaveURL(/wp-login\.php/, { timeout: 15000 });
  });

  test('Ausgeschöpfter Link zeigt verständliche Meldung', async ({ page, context }) => {
    await login(page, MANAGER);
    const inviteLink = await createInviteLink(page, '1', '7', 'auto_join');

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await login(page, INVITEE);
    await page.locator('button:has-text("Raum beitreten")').click({ noWaitAfter: true });

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await login(page, MANAGER);
    await expect(page.locator('.afspaces-notice, .afspaces-message-error')).toContainText('aufgebraucht', { timeout: 15000 });
  });

  test('Manager widerruft Link', async ({ page, context }) => {
    await login(page, MANAGER);
    const inviteLink = await createInviteLink(page, '2', '7', 'auto_join');
    const row = page.locator('.afspaces-invite-links-table tbody tr').first();
    await row.locator('button:has-text("Widerrufen")').click({ noWaitAfter: true });
    await page.waitForTimeout(1500);

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('.afspaces-notice, .afspaces-message-error')).toContainText('widerrufen', { timeout: 15000 });
  });

  test('Optionaler Freigabeflow erzeugt Anfrage statt Direktbeitritt', async ({ page, context }) => {
    await login(page, MANAGER);
    const inviteLink = await createInviteLink(page, '1', '7', 'approval_required');

    await context.clearCookies();
    await page.goto(inviteLink, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await login(page, INVITEE);
    await page.locator('button:has-text("Beitrittsanfrage senden")').click({ noWaitAfter: true });
    await expect(page.locator('.afspaces-message-success, .afspaces-my-invitations')).toContainText('Beitrittsanfrage', { timeout: 15000 });
  });
});