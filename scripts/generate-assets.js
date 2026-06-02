/**
 * Generate icon and banner images for WordPress.org plugin assets.
 * Usage: node scripts/generate-assets.js
 * Output: wp-assets/icon-256x256.png, wp-assets/banner-772x250.png, wp-assets/banner-1544x500.png
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT_DIR = path.join(__dirname, '..', 'wp-assets');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

// ---------------------------------------------------------------------------
// Mock card data for banner
// ---------------------------------------------------------------------------
const CARDS = [
  {
    img: 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&q=80',
    title: 'Global Climate Summit Reaches Historic Agreement',
    date: 'Jun 2, 2025',
    site: 'Reuters',
  },
  {
    img: 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=800&q=80',
    title: 'New JavaScript Framework Promises 10x Faster Builds',
    date: 'Jun 1, 2025',
    site: 'Dev.to',
  },
  {
    img: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80',
    title: '次世代AIチップが量産開始、性能は前世代比3倍',
    date: 'May 31, 2025',
    site: 'Zenn',
  },
  {
    img: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&q=80',
    title: 'Remote Work Policies Reshape Urban Landscape',
    date: 'May 30, 2025',
    site: 'The Verge',
  },
  {
    img: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80',
    title: 'Open Source Projects Receive Record Funding in 2025',
    date: 'May 29, 2025',
    site: 'GitHub Blog',
  },
  {
    img: 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&q=80',
    title: 'Space Tourism Milestone: 1000th Civilian Flight',
    date: 'May 28, 2025',
    site: 'NASA',
  },
];

function cardHTML(card) {
  return `
    <a class="rss-d-card" href="#" style="text-decoration:none;">
      <img class="rss-d-img" src="${card.img}" alt="" loading="eager" crossorigin="anonymous">
      <div class="rss-d-overlay"></div>
      <span class="rss-d-site">${card.site}</span>
      <span class="rss-d-date">${card.date}</span>
      <p class="rss-d-title">${card.title}</p>
    </a>`;
}

// ---------------------------------------------------------------------------
// Banner HTML (772×250 layout — 4 cards, plugin name badge)
// ---------------------------------------------------------------------------
function bannerHTML(scale = 1) {
  const w = 772 * scale;
  const h = 250 * scale;
  const css = fs.readFileSync(
    path.join(__dirname, '..', 'rss-display', 'assets', 'css', 'rss-display.css'),
    'utf8'
  );

  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  width: ${w}px; height: ${h}px;
  overflow: hidden;
  background: #f0f0f0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
${css}
.wrapper {
  width: ${w}px;
  height: ${h}px;
  display: flex;
  align-items: stretch;
  gap: 0;
  overflow: hidden;
  position: relative;
}
/* 4-card strip */
.cards-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: ${8 * scale}px;
  flex: 1 1 auto;
  padding: ${14 * scale}px;
  --rss-d-columns: 4;
  --rss-d-title-lines: 2;
  align-items: stretch;
}
/* Override card aspect for fixed banner height */
.cards-strip .rss-d-card {
  aspect-ratio: unset;
  height: ${(250 - 28) * scale}px;
  border-radius: ${6 * scale}px;
}
/* Right badge panel */
.badge-panel {
  flex: 0 0 ${180 * scale}px;
  background: linear-gradient(145deg, #c0392b, #922b21);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: ${8 * scale}px;
  padding: ${20 * scale}px ${16 * scale}px;
}
.badge-icon {
  width: ${52 * scale}px;
  height: ${52 * scale}px;
  background: rgba(255,255,255,0.15);
  border-radius: ${12 * scale}px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.badge-icon svg {
  width: ${32 * scale}px;
  height: ${32 * scale}px;
}
.badge-title {
  font-size: ${18 * scale}px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.02em;
  text-align: center;
  line-height: 1.2;
}
.badge-sub {
  font-size: ${10 * scale}px;
  color: rgba(255,255,255,0.75);
  text-align: center;
  line-height: 1.4;
}
</style>
</head>
<body>
<div class="wrapper">
  <div class="cards-strip">
    ${CARDS.slice(0, 4).map(cardHTML).join('')}
  </div>
  <div class="badge-panel">
    <div class="badge-icon">
      <!-- RSS wave icon -->
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="5" cy="19" r="2.5" fill="white"/>
        <path d="M4 11C8.418 11 12 14.582 12 19" stroke="white" stroke-width="2.2" stroke-linecap="round" fill="none"/>
        <path d="M4 5C11.732 5 18 11.268 18 19" stroke="white" stroke-width="2.2" stroke-linecap="round" fill="none"/>
      </svg>
    </div>
    <div class="badge-title">RSS Display</div>
    <div class="badge-sub">Multiple feeds.<br>Beautiful cards.</div>
  </div>
</div>
</body>
</html>`;
}

// ---------------------------------------------------------------------------
// Icon HTML (256×256)
// ---------------------------------------------------------------------------
function iconHTML() {
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  width: 256px; height: 256px;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.wrap {
  width: 256px; height: 256px;
  background: linear-gradient(145deg, #c0392b 0%, #7b241c 100%);
  border-radius: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.rss-wave {
  width: 80px;
  height: 80px;
}
.label {
  font-size: 22px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.04em;
  line-height: 1;
}
.label-sub {
  font-size: 11px;
  font-weight: 500;
  color: rgba(255,255,255,0.7);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
</style>
</head>
<body>
<div class="wrap">
  <svg class="rss-wave" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="16" cy="64" r="8" fill="white"/>
    <path d="M12 42C25.255 42 36 52.745 36 66" stroke="white" stroke-width="7" stroke-linecap="round" fill="none"/>
    <path d="M12 20C36.853 20 57 40.147 57 65" stroke="white" stroke-width="7" stroke-linecap="round" fill="none"/>
    <path d="M12 4C44.033 4 70 30.133 70 62" stroke="rgba(255,255,255,0.45)" stroke-width="7" stroke-linecap="round" fill="none"/>
  </svg>
  <div class="label">RSS Display</div>
  <div class="label-sub">WordPress Plugin</div>
</div>
</body>
</html>`;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // --- Icon 256×256 ---
  console.log('Generating icon-256x256.png...');
  await page.setViewportSize({ width: 256, height: 256 });
  await page.setContent(iconHTML(), { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(OUT_DIR, 'icon-256x256.png'), clip: { x: 0, y: 0, width: 256, height: 256 } });
  console.log('  -> wp-assets/icon-256x256.png');

  // --- Banner 772×250 ---
  console.log('Generating banner-772x250.png...');
  await page.setViewportSize({ width: 772, height: 250 });
  await page.setContent(bannerHTML(1), { waitUntil: 'networkidle' });
  // Wait for images to load
  await page.waitForTimeout(2000);
  await page.screenshot({ path: path.join(OUT_DIR, 'banner-772x250.png'), clip: { x: 0, y: 0, width: 772, height: 250 } });
  console.log('  -> wp-assets/banner-772x250.png');

  // --- Banner 1544×500 (Retina @2x) ---
  console.log('Generating banner-1544x500.png...');
  await page.setViewportSize({ width: 1544, height: 500 });
  await page.setContent(bannerHTML(2), { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: path.join(OUT_DIR, 'banner-1544x500.png'), clip: { x: 0, y: 0, width: 1544, height: 500 } });
  console.log('  -> wp-assets/banner-1544x500.png');

  await browser.close();
  console.log('Done!');
})();
