import { test, expect } from '@playwright/test';

const BASE_URL = 'http://forums.test';
const DASHBOARD_PAGE = `${BASE_URL}/afspaces/`;
const MANAGER = 'afp_e2e_manager';
const MANAGER_PASS = 'Test@1234';

test('Dashboard should not display orphaned spaces', async ({ page }) => {
	// Login
	await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	await page.fill('#user_login', MANAGER);
	await page.fill('#user_pass', MANAGER_PASS);
	await page.click('#wp-submit', { noWaitAfter: true });
	await page.waitForTimeout(3000);
	
	// Navigate to dashboard
	await page.goto(DASHBOARD_PAGE, { waitUntil: 'load' }).catch(() => {});
	await page.waitForTimeout(1000);
	
	// Check: no "Unbekanntes Forum" visible
	const unknownForums = await page.locator('text=Unbekanntes Forum').count();
	console.log(`Found ${unknownForums} "Unbekanntes Forum" entries`);
	
	expect(unknownForums).toBe(0);
	
	// Screenshot
	await page.screenshot({ path: 'dashboard-cleanup.png' });
	console.log('Dashboard screenshot saved');
});
