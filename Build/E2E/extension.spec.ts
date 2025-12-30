import { test, expect } from '@playwright/test';

test.describe('LlmsTxt Extension Tests', () => {
  test('should fetch and validate index.md content', async ({ page, baseURL }) => {

    const response = await page.goto(`${baseURL}/index.md`, { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');

    expect(content).toBeTruthy();
    expect(content).toContain('Home');
    expect(content).toContain('What si this');
    expect(content).toContain('Testing');
    expect(content).toContain('Testin 123');
  });

  test('should fetch and validate llms.txt content', async ({ page, baseURL }) => {

    const response = await page.goto(`${baseURL}/llms.txt`, { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');

    expect(content).toBeTruthy();
    expect(content).toContain('HomePAGE');
    expect(content).toContain('What si this');
    expect(content).toContain('Main Page Structure');
    expect(content).toContain('https://llms-txt.ddev.site/this-is-an-awesome-page.md');
    expect(content).toContain('in Folder');
    expect(content).toContain('Second Level');
    expect(content).toContain('Hello');
    expect(content).toContain('# 123');
    expect(content).toContain('## This is testing');
  });
});
