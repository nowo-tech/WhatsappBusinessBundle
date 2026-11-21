import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy path of the WhatsApp Business demo (mock transport): the page renders inside the demo platform
 * shell, sending a text shows the mock message id, and the form validates its fields.
 */
test.describe('WhatsApp Business demo', () => {
  test('the page renders inside the demo platform shell', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'WhatsappBusinessBundle', level: 1 })).toBeVisible();
    await expect(page.getByTestId('demo-profile')).toContainText('timeout=');
  });

  test('sending a text with the mock transport returns a message id', async ({ page }) => {
    await page.goto('/');
    await page.getByLabel('Message').fill('Hello from Playwright');
    await page.getByRole('button', { name: 'Send text (mock)' }).click();
    await expect(page.getByTestId('demo-result')).toContainText('Message id:');
  });

  test('an empty message is rejected', async ({ page }) => {
    await page.goto('/');
    await page.getByLabel('Message').fill('');
    await page.locator('form').evaluate((form: HTMLFormElement) => {
      form.noValidate = true;
      form.requestSubmit();
    });
    await expect(page.getByTestId('demo-result')).toHaveCount(0);
    await expect(page.getByTestId('demo-form')).toContainText(/should not be blank|no debe estar vacío/i);
  });

  test('the webhook endpoint answers', async ({ request }) => {
    const response = await request.get('/whatsapp/webhook');
    expect([200, 400, 403]).toContain(response.status());
  });
});
