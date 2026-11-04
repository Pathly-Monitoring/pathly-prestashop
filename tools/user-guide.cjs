/*
 * Builds docs/readme_en.pdf and docs/readme_fr.pdf, the user guides shipped in
 * the module (PrestaShop Addons asks for the documentation in docs/, as PDF),
 * from publication/guide/user-guide-{en,fr}.html and the screenshots.
 *
 * Usage: npm install --no-save playwright && npx playwright install chromium
 *        node tools/user-guide.cjs
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  fs.mkdirSync(path.join(root, 'docs'), { recursive: true });
  for (const lang of ['en', 'fr']) {
    const html = path.join(root, 'publication', 'guide', `user-guide-${lang}.html`);
    await page.goto('file://' + html.replace(/\\/g, '/'), { waitUntil: 'load' });
    await page.pdf({
      path: path.join(root, 'docs', `readme_${lang}.pdf`),
      format: 'A4',
      margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' },
      printBackground: true,
    });
    console.log(`docs/readme_${lang}.pdf`);
  }
  await browser.close();
})();
