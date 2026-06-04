// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');

const PAGE_URL = 'file:///' + path.resolve(__dirname, '../../docs/index.html').replace(/\\/g, '/');

const LAYOUTS = ['grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line'];

test.beforeEach(async ({ page }) => {
  await page.goto(PAGE_URL);
  // buildAll() はDOMContentLoaded後に実行されるため待機
  await page.waitForFunction(() => {
    const el = document.getElementById('grid-grid');
    return el && el.children.length > 0;
  });
});

test('ページタイトルが正しく表示される', async ({ page }) => {
  await expect(page).toHaveTitle(/Rssemble Cards for RSS Feeds/);
});

test('コントロールバーが表示される', async ({ page }) => {
  await expect(page.locator('.preview-bar')).toBeVisible();
  await expect(page.locator('#ctrl-columns')).toBeVisible();
  await expect(page.locator('#ctrl-count')).toBeVisible();
  await expect(page.locator('#ctrl-lines')).toBeVisible();
});

for (const layout of LAYOUTS) {
  test(`[${layout}] グリッドコンテナが存在する`, async ({ page }) => {
    const grid = page.locator(`#grid-${layout}`);
    await expect(grid).toBeVisible();
    await expect(grid).toHaveClass(/rss-d-type-/);
  });

  test(`[${layout}] デフォルト6件のカードが描画される`, async ({ page }) => {
    const cards = page.locator(`#grid-${layout} .rss-d-card`);
    await expect(cards).toHaveCount(6);
  });

  test(`[${layout}] カードにタイトルが含まれる（image_only除く）`, async ({ page }) => {
    if (layout === 'image_only') return;
    const titles = page.locator(`#grid-${layout} .rss-d-title`);
    await expect(titles).toHaveCount(6);
    const first = await titles.first().textContent();
    expect(first?.trim().length).toBeGreaterThan(0);
  });

  test(`[${layout}] カードがリンクを持ちhttps://で始まる`, async ({ page }) => {
    const links = page.locator(`#grid-${layout} .rss-d-card`);
    const first = await links.first().getAttribute('href');
    expect(first).toMatch(/^https?:\/\//);
  });

  test(`[${layout}] カードが target="_blank" を持つ`, async ({ page }) => {
    const card = page.locator(`#grid-${layout} .rss-d-card`).first();
    await expect(card).toHaveAttribute('target', '_blank');
  });
}

test('列数変更でCSS変数が更新される', async ({ page }) => {
  await page.selectOption('#ctrl-columns', '4');
  const style = await page.locator('#grid-grid').getAttribute('style');
  expect(style).toContain('--rss-d-columns:4');
});

test('件数変更でカード数が変わる', async ({ page }) => {
  await page.fill('#ctrl-count', '3');
  await page.dispatchEvent('#ctrl-count', 'change');
  const cards = page.locator('#grid-grid .rss-d-card');
  await expect(cards).toHaveCount(3);
});

test('タイトル行数変更でCSS変数が更新される', async ({ page }) => {
  await page.selectOption('#ctrl-lines', '3');
  const style = await page.locator('#grid-grid').getAttribute('style');
  expect(style).toContain('--rss-d-title-lines:3');
});

test('日付チェックOFF で rss-d-date が消える', async ({ page }) => {
  // デフォルトはON（checked）
  await expect(page.locator('#grid-grid .rss-d-date').first()).toBeVisible();
  await page.uncheck('#ctrl-date');
  await expect(page.locator('#grid-grid .rss-d-date')).toHaveCount(0);
});

test('説明文チェックON で rss-d-desc が表示される', async ({ page }) => {
  // デフォルトはOFF
  await expect(page.locator('#grid-grid .rss-d-desc')).toHaveCount(0);
  await page.check('#ctrl-desc');
  await expect(page.locator('#grid-grid .rss-d-desc').first()).toBeVisible();
});

test('サイト名チェックON で rss-d-site が表示される', async ({ page }) => {
  await expect(page.locator('#grid-grid .rss-d-site')).toHaveCount(0);
  await page.check('#ctrl-site');
  await expect(page.locator('#grid-grid .rss-d-site').first()).toBeVisible();
});

test('画像なしアイテムにプレースホルダーが設定される', async ({ page }) => {
  // 12件表示にして最後のno-imageアイテムを表示
  await page.fill('#ctrl-count', '12');
  await page.dispatchEvent('#ctrl-count', 'change');
  const imgs = page.locator('#grid-grid .rss-d-img');
  const count = await imgs.count();
  // 最後のカード（no-image）のsrcがplaceholderのdata:URIであること
  const lastSrc = await imgs.nth(count - 1).getAttribute('src');
  expect(lastSrc).toMatch(/^data:image\/svg\+xml/);
});

test('image_only レイアウトにタイトルテキストが存在しない', async ({ page }) => {
  const titles = page.locator('#grid-image_only .rss-d-title');
  await expect(titles).toHaveCount(0);
});

test('list / list_vertical カードが rss-d-card-body を持つ', async ({ page }) => {
  for (const layout of ['list', 'list_vertical']) {
    const bodies = page.locator(`#grid-${layout} .rss-d-card-body`);
    await expect(bodies).toHaveCount(6);
  }
});

test('text / text_line カードに画像要素が存在しない', async ({ page }) => {
  for (const layout of ['text', 'text_line']) {
    const imgs = page.locator(`#grid-${layout} .rss-d-img`);
    await expect(imgs).toHaveCount(0);
  }
});
