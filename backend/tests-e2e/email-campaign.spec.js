import { test, expect } from '@playwright/test';

test.describe('Admin Dashboard — Bulk Email & Cadet Import System', () => {

  test('Full UI Flow: Login, Configure SMTP, Test SMTP, Select Cadets, Compose & Dispatch Campaign, View Delivery Logs', async ({ page }) => {
    // 1. Login to Admin Console
    await page.goto('/#/login');
    await expect(page.locator('h1')).toContainText('NCCAA HRMS');

    await page.fill('#login-input', 'admin');
    await page.fill('#password-input', 'password123');
    await page.click('#login-btn');

    // Wait for Dashboard to render
    await page.waitForURL('**/#/dashboard');
    await expect(page.locator('#sidebar')).toBeVisible();
    await expect(page.locator('header')).toContainText('Super Administrator');

    // 2. Navigate to Email Campaigns
    const emailNavLink = page.locator('nav a[href="#/email"]');
    await expect(emailNavLink).toBeVisible();
    await emailNavLink.click();
    await page.waitForURL('**/#/email');

    await expect(page.locator('h2')).toContainText('Email & Bulk Campaigns');

    // 3. SMTP Configuration & Live Test UI
    const settingsTab = page.locator('#tab-settings');
    await expect(settingsTab).toBeVisible();
    await settingsTab.click();

    // Verify SMTP form fields
    const hostInput = page.locator('input[name="host"]');
    const fromEmailInput = page.locator('input[name="from_email"]');
    const saveBtn = page.locator('button:has-text("Save Configuration")');

    await expect(hostInput).toBeVisible();
    await hostInput.fill('smtp.mailtrap.io');
    await page.locator('input[name="port"]').fill('2525');
    await page.locator('input[name="username"]').fill('playwright-test');
    await page.locator('input[name="password"]').fill('secret-smtp-password');
    await fromEmailInput.fill('noreply@nccaa.org.np');
    await page.locator('input[name="from_name"]').fill('NCCAA Secretariat');

    await saveBtn.click();
    await expect(page.locator('#toasts')).toContainText('SMTP configuration saved securely.');

    // Test SMTP Connection
    const testRecipientInput = page.locator('#smtp-test-recipient');
    const testSendBtn = page.locator('#btn-test-smtp');
    await testRecipientInput.fill('test-cadet@example.com');
    await testSendBtn.click();

    const resultBox = page.locator('#test-result-box');
    await expect(resultBox).toBeVisible();
    await expect(resultBox).toContainText('Test email sent successfully');

    // 4. Compose Bulk Email Campaign & Recipient Selection UI
    const composeTab = page.locator('#tab-compose');
    await composeTab.click();

    await expect(page.locator('h3:has-text("1. Compose Message")')).toBeVisible();
    await expect(page.locator('h3:has-text("2. Select Recipients")')).toBeVisible();

    // Fill Campaign Details
    await page.fill('#cmp-title', 'Disaster Relief Regional Mobilization 2026');
    await page.fill('#cmp-subject', 'URGENT: NCCAA Flood Relief Deployment Notice for {{name}}');
    await page.fill('#cmp-sender-name', 'NCCAA Central Command');
    await page.fill('#cmp-reply-to', 'disaster-response@nccaa.org.np');
    await page.fill('#cmp-body', '<p>Dear <strong>{{name}}</strong>,</p><p>You are hereby mobilized for active relief operations. Please report to your district command center immediately.</p>');

    // Recipient selection
    const selectAllBtn = page.locator('#btn-select-all');
    await expect(selectAllBtn).toBeVisible();
    await selectAllBtn.click();

    const selectedBadge = page.locator('#selected-badge');
    await expect(selectedBadge).toContainText('cadets selected');

    // Add extra manual email
    await page.fill('#cmp-manual-emails', 'liaison.officer@example.com');

    // Send Campaign
    const sendNowBtn = page.locator('#btn-send-now');
    await sendNowBtn.click();

    await expect(page.locator('#toasts')).toContainText('Campaign created and queued for sending');

    // 5. Verify Campaign in History & Inspect Delivery Logs
    await expect(page.locator('#campaigns-table')).toBeVisible();
    await expect(page.locator('#campaigns-table')).toContainText('Disaster Relief Regional Mobilization 2026');
    await expect(page.locator('#campaigns-table')).toContainText('URGENT: NCCAA Flood Relief Deployment Notice');

    // Click Details button on the campaign
    const detailsBtn = page.locator('button:has-text("Details")').first();
    await detailsBtn.click();

    // Verify modal content
    const modal = page.locator('#campaign-modal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('Disaster Relief Regional Mobilization 2026');
    await expect(modal).toContainText('Recipient Delivery Logs');
    await expect(modal).toContainText('Total Recipients');

    // Close modal
    await page.click('#btn-close-modal');
    await expect(modal).not.toBeVisible();
  });

  test('Cadet Excel Import Flow: Inspect, Map Columns, Preview Validation, and Commit', async ({ page }) => {
    // Login
    await page.goto('/#/login');
    await page.fill('#login-input', 'admin');
    await page.fill('#password-input', 'password123');
    await page.click('#login-btn');
    await page.waitForURL('**/#/dashboard');

    // Navigate to Cadets
    await page.click('nav a[href="#/cadets"]');
    await page.waitForURL('**/#/cadets');
    await expect(page.locator('#cadet-table')).toBeVisible();

    // Click Import Excel
    const importBtn = page.locator('#import-cadets-btn');
    await expect(importBtn).toBeVisible();
    await importBtn.click();

    const importModal = page.locator('#cadet-import-modal');
    await expect(importModal).toBeVisible();

    // Upload a CSV file
    const csvContent = 'Cadet Number,Full Name,Email,Phone,Rank,Province,District\nNCC-PW-101,Bikram Rai,bikram.rai@example.com,9841999901,Cadet Sergeant,Bagmati,Kathmandu\nNCC-PW-102,Deepa Thapa,deepa.thapa@example.com,9841999902,Cadet Corporal,Bagmati,Kathmandu';
    const fileChooserPromise = page.waitForEvent('filechooser');
    await page.locator('#import-upload-step').click();
    const fileChooser = await fileChooserPromise;
    await fileChooser.setFiles({
      name: 'playwright_cadets.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from(csvContent),
    });

    // Verify Mapping Screen
    await expect(importModal).toContainText('Column Mapping');
    await expect(importModal).toContainText('Cadet Number *');

    // Click Preview & Validate
    const previewBtn = page.locator('#btn-preview-import');
    await expect(previewBtn).toBeVisible();
    await previewBtn.click();

    // Verify Preview Table & Stats
    await expect(importModal).toContainText('Valid Records');
    await expect(importModal).toContainText('NCC-PW-101');
    await expect(importModal).toContainText('Bikram Rai');

    // Commit Import
    const commitBtn = page.locator('#btn-commit-import');
    await expect(commitBtn).toBeVisible();
    await commitBtn.click();

    await expect(page.locator('#toasts')).toContainText('Import complete: 2 imported');
  });

});
