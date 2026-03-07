const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 1000 });
  await page.goto('https://app.netlify.com/sites/comforting-sawine-6d1e1c/settings/domain', { waitUntil: 'networkidle2' });
  await page.screenshot({ path: 'netlify_domain_step1.png' });
  console.log('Fatto: ho scattato una foto al pannello Netlify.');
  await browser.close();
})();