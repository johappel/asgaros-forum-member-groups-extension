import { test, expect, Page } from '@playwright/test';

const runtime = globalThis as { process?: { env?: Record<string, string | undefined> } };
const BASE = runtime.process?.env?.AFSPACES_BASE_URL || 'http://forums.test';
const MANAGER = { username: 'afp_e2e_manager', password: 'E2ePassw0rd!' };
const REQUESTER = { username: 'afp_e2e_target', password: 'E2ePassw0rd!' };
const TARGET_LOGIN = 'afp_e2e_target';
const DISCOVER_PAGE = `${BASE}/afspaces/?afspaces_view=discover`;
const MY_INVITATIONS_PAGE = `${BASE}/afspaces/?afspaces_view=my-invitations`;

test.describe.configure({ timeout: 240000 });

async function login(page: Page, user: { username: string; password: string }) {
  for (let i = 0; i < 3; i++) {
    await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    const submitVisible = await page.locator('#wp-submit').count();
    if (submitVisible > 0) {
      break;
    }
    await page.waitForTimeout(1000);
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

async function getManagedSpaceId(page: Page): Promise<number> {
  await page.goto(`${BASE}/afspaces/`, { waitUntil: 'domcontentloaded' }).catch(() => {});

  let membersLink = page.locator('a[href*="afspaces_view=members"][href*="space_id="]').first();

  if ((await membersLink.count()) === 0) {
    const registerButton = page.locator('button:has-text("Als Raum registrieren")').first();
    if ((await registerButton.count()) > 0) {
      await registerButton.click({ noWaitAfter: true });
      await page.waitForTimeout(1500);
      await page.goto(`${BASE}/afspaces/`, { waitUntil: 'domcontentloaded' }).catch(() => {});
      membersLink = page.locator('a[href*="afspaces_view=members"][href*="space_id="]').first();
    }
  }

  await expect(membersLink).toBeVisible({ timeout: 15000 });

  const href = (await membersLink.getAttribute('href')) || '';
  const parsed = new URL(href, BASE);
  const value = Number(parsed.searchParams.get('space_id') || '0');
  expect(value).toBeGreaterThan(0);

  return value;
}

async function ensureRequesterRemovedFromMembers(page: Page, spaceId: number) {
  const membersPage = `${BASE}/afspaces/?afspaces_view=members&space_id=${spaceId}`;
  await page.goto(membersPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-members-heading')).toBeVisible({ timeout: 15000 });

  const row = page.locator('.afspaces-member-table tbody tr', { hasText: TARGET_LOGIN }).first();
  if ((await row.count()) > 0) {
    page.once('dialog', (dialog) => dialog.accept());
    await row.locator('button:has-text("Entfernen")').click({ noWaitAfter: true });
    await page.waitForTimeout(1500);
  }
}

async function clearPendingJoinRequests(page: Page, spaceId: number) {
  const joinRequestsPage = `${BASE}/afspaces/?afspaces_view=join-requests&space_id=${spaceId}`;
  await page.goto(joinRequestsPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-join-requests-heading')).toBeVisible({ timeout: 15000 });

  for (let i = 0; i < 6; i++) {
    const pendingRow = page.locator('.afspaces-join-requests table tbody tr', { hasText: TARGET_LOGIN }).filter({
      has: page.locator('button:has-text("Ablehnen")'),
    }).first();

    if ((await pendingRow.count()) === 0) {
      break;
    }

    await pendingRow.locator('button:has-text("Ablehnen")').click({ noWaitAfter: true });
    await page.waitForTimeout(1200);
    await page.goto(joinRequestsPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
  }
}

async function submitJoinRequest(page: Page, message: string, spaceId: number) {
  await page.goto(DISCOVER_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await expect(page.locator('h2#afspaces-discover-heading')).toBeVisible({ timeout: 15000 });

  const form = page
    .locator('form')
    .filter({ has: page.locator('input[name="afspaces_action"][value="create_join_request"]') })
    .filter({ has: page.locator(`input[name="space_id"][value="${spaceId}"]`) })
    .first();

  await expect(form).toBeVisible({ timeout: 15000 });
  await form.locator('input[name="request_message"]').fill(message);
  await form.locator('button:has-text("Beitritt anfragen")').click({ noWaitAfter: true });
  const successNotice = page.locator('.afspaces-notice-success, .afspaces-message-success').first();
  await expect(successNotice).toBeVisible({ timeout: 15000 });
  await expect(successNotice).toContainText('Beitrittsanfrage', { timeout: 15000 });
}

test.describe('Join-Request-Flow', () => {
  test('Anfrage erscheint bei Raumverantwortlichen als pending', async ({ page, context }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    const joinRequestsPage = `${BASE}/afspaces/?afspaces_view=join-requests&space_id=${spaceId}`;

    await ensureRequesterRemovedFromMembers(page, spaceId);
    await clearPendingJoinRequests(page, spaceId);

    await context.clearCookies();
    await login(page, REQUESTER);
    await submitJoinRequest(page, 'Bitte um Aufnahme (E2E pending)', spaceId);

    await context.clearCookies();
    await login(page, MANAGER);
    await page.goto(joinRequestsPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
    const row = page.locator('.afspaces-join-requests table tbody tr', { hasText: TARGET_LOGIN }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await expect(row).toContainText('pending');
  });

  test('Manager kann Anfrage genehmigen und ablehnen', async ({ page, context }) => {
    await login(page, MANAGER);
    const spaceId = await getManagedSpaceId(page);
    const joinRequestsPage = `${BASE}/afspaces/?afspaces_view=join-requests&space_id=${spaceId}`;
    const membersPage = `${BASE}/afspaces/?afspaces_view=members&space_id=${spaceId}`;

    await ensureRequesterRemovedFromMembers(page, spaceId);
    await clearPendingJoinRequests(page, spaceId);

    await context.clearCookies();
    await login(page, REQUESTER);
    await submitJoinRequest(page, 'Bitte um Aufnahme (E2E approve)', spaceId);

    await context.clearCookies();
    await login(page, MANAGER);
    await page.goto(joinRequestsPage, { waitUntil: 'domcontentloaded' }).catch(() => {});

    const approveRow = page.locator('.afspaces-join-requests table tbody tr', { hasText: TARGET_LOGIN }).filter({
      has: page.locator('button:has-text("Genehmigen")'),
    }).first();
    await expect(approveRow).toBeVisible({ timeout: 15000 });
    await approveRow.locator('button:has-text("Genehmigen")').click({ noWaitAfter: true });
    const decisionSuccess = page.locator('.afspaces-message-success, .afspaces-notice-success').first();
    await expect(decisionSuccess).toBeVisible({ timeout: 15000 });
    await expect(decisionSuccess).toContainText('genehmigt', { timeout: 15000 });

    await page.goto(membersPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('.afspaces-member-table')).toContainText(TARGET_LOGIN, { timeout: 15000 });

    page.once('dialog', (dialog) => dialog.accept());
    const memberRow = page.locator('.afspaces-member-table tbody tr', { hasText: TARGET_LOGIN }).first();
    await memberRow.locator('button:has-text("Entfernen")').click({ noWaitAfter: true });
    await page.waitForTimeout(1200);

    await context.clearCookies();
    await login(page, REQUESTER);
    await submitJoinRequest(page, 'Bitte um Aufnahme (E2E reject)', spaceId);

    await context.clearCookies();
    await login(page, MANAGER);
    await page.goto(joinRequestsPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
    const rejectRow = page.locator('.afspaces-join-requests table tbody tr', { hasText: TARGET_LOGIN }).filter({
      has: page.locator('button:has-text("Ablehnen")'),
    }).first();
    await expect(rejectRow).toBeVisible({ timeout: 15000 });
    await rejectRow.locator('button:has-text("Ablehnen")').click({ noWaitAfter: true });
    await expect(decisionSuccess).toBeVisible({ timeout: 15000 });
    await expect(decisionSuccess).toContainText('abgelehnt', { timeout: 15000 });

    await page.goto(membersPage, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('.afspaces-member-table')).not.toContainText(TARGET_LOGIN, { timeout: 15000 });

    await context.clearCookies();
    await login(page, REQUESTER);
    await page.goto(MY_INVITATIONS_PAGE, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await expect(page.locator('.afspaces-my-invitations')).toContainText('Meine Beitrittsanfragen', { timeout: 15000 });
    await expect(page.locator('.afspaces-my-invitations')).toContainText('rejected', { timeout: 15000 });
  });
});
