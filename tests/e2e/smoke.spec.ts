import { test, expect } from '@playwright/test';

test('homepage loads and mounts Vue app', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle('⚡ LOG VIEWER');
  await expect(page.locator('#app')).toBeAttached();
});

test('setup status endpoint returns JSON', async ({ request }) => {
  const res = await request.get('/api/setup/status');
  expect(res.status()).toBe(200);
  const body = await res.json();
  expect(body).toHaveProperty('state');
});

test('directories endpoint returns 200', async ({ request }) => {
  const res = await request.get('/api/directories');
  expect(res.status()).toBe(200);
});
